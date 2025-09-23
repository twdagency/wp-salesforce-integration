<?php
/**
 * Salesforce API Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Salesforce_API {
    
    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    private $security_token;
    private $instance_url;
    private $access_token;
    private $api_version = 'v58.0';
    
    public function __construct() {
        $this->client_id = get_option('wsi_client_id');
        $this->client_secret = get_option('wsi_client_secret');
        $this->username = get_option('wsi_username');
        $this->password = get_option('wsi_password');
        $this->security_token = get_option('wsi_security_token');
        $this->instance_url = get_option('wsi_instance_url');
        $this->access_token = get_option('wsi_access_token');
    }
    
    /**
     * Authenticate with Salesforce
     */
    public function authenticate() {
        if (empty($this->client_id) || empty($this->client_secret) || 
            empty($this->username) || empty($this->password) || 
            empty($this->security_token)) {
            throw new Exception('Salesforce credentials not configured');
        }
        
        $auth_url = 'https://login.salesforce.com/services/oauth2/token';
        
        $data = array(
            'grant_type' => 'password',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'username' => $this->username,
            'password' => $this->password . $this->security_token
        );
        
        $response = wp_remote_post($auth_url, array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Authentication failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new Exception('Authentication error: ' . $data['error_description']);
        }
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->instance_url = $data['instance_url'];
            
            // Store the token for future use
            update_option('wsi_access_token', $this->access_token);
            update_option('wsi_instance_url', $this->instance_url);
            update_option('wsi_token_expires', time() + $data['expires_in']);
            
            return true;
        }
        
        throw new Exception('Authentication failed: No access token received');
    }
    
    /**
     * Check if current token is valid
     */
    private function is_token_valid() {
        $expires = get_option('wsi_token_expires', 0);
        return $expires > time() && !empty($this->access_token);
    }
    
    /**
     * Get valid access token
     */
    private function get_valid_token() {
        // Try OAuth first if available
        $oauth = new WSI_Salesforce_OAuth();
        if ($oauth->is_token_valid()) {
            return $oauth->get_valid_token();
        }
        
        // Fall back to username/password authentication
        if (!$this->is_token_valid()) {
            $this->authenticate();
        }
        return $this->access_token;
    }
    
    /**
     * Create or update a record in Salesforce
     */
    public function upsert_record($object_type, $external_id_field, $external_id_value, $data) {
        $token = $this->get_valid_token();
        
        $url = $this->instance_url . '/services/data/' . $this->api_version . 
               '/sobjects/' . $object_type . '/' . $external_id_field . '/' . $external_id_value;
        
        $response = wp_remote_request($url, array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Upsert failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code === 404) {
            // Record doesn't exist, create it
            return $this->create_record($object_type, $data, $external_id_field, $external_id_value);
        }
        
        if ($response_code >= 400) {
            $error_data = json_decode($body, true);
            $error_msg = isset($error_data[0]['message']) ? $error_data[0]['message'] : 'Unknown error';
            throw new Exception('Upsert failed: ' . $error_msg);
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Create a new record in Salesforce
     */
    public function create_record($object_type, $data, $external_id_field = null, $external_id_value = null) {
        $start_time = microtime(true);
        $audit_trail = new WSI_Audit_Trail();
        
        try {
            $token = $this->get_valid_token();
            
            $url = $this->instance_url . '/services/data/' . $this->api_version . 
                   '/sobjects/' . $object_type;
            
            // Add external ID if provided
            if ($external_id_field && $external_id_value) {
                $data[$external_id_field] = $external_id_value;
            }
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 30
            ));
            
            $execution_time = microtime(true) - $start_time;
            
            if (is_wp_error($response)) {
                $audit_trail->log_operation(
                    'create',
                    $object_type,
                    '',
                    0,
                    'unknown',
                    'error',
                    'Create failed: ' . $response->get_error_message(),
                    $data,
                    null,
                    array('error' => $response->get_error_message()),
                    $execution_time
                );
                throw new Exception('Create failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $response_data = json_decode($body, true);
            
            if ($response_code >= 400) {
                $error_msg = isset($response_data[0]['message']) ? $response_data[0]['message'] : 'Unknown error';
                
                $audit_trail->log_operation(
                    'create',
                    $object_type,
                    '',
                    0,
                    'unknown',
                    'error',
                    'Create failed: ' . $error_msg,
                    $data,
                    $response_data,
                    array('error' => $error_msg, 'response_code' => $response_code),
                    $execution_time
                );
                
                throw new Exception('Create failed: ' . $error_msg);
            }
            
            $record_id = isset($response_data['id']) ? $response_data['id'] : '';
            
            $audit_trail->log_operation(
                'create',
                $object_type,
                $record_id,
                0,
                'unknown',
                'success',
                'Record created successfully',
                $data,
                $response_data,
                null,
                $execution_time
            );
            
            return $response_data;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            $audit_trail->log_operation(
                'create',
                $object_type,
                '',
                0,
                'unknown',
                'error',
                'Create failed: ' . $e->getMessage(),
                $data,
                null,
                array('error' => $e->getMessage()),
                $execution_time
            );
            
            throw $e;
        }
    }
    
    /**
     * Test the connection to Salesforce
     */
    public function test_connection() {
        try {
            // Try OAuth first if available
            $oauth = new WSI_Salesforce_OAuth();
            if ($oauth->is_token_valid()) {
                $result = $oauth->test_connection();
                return $result['success'];
            }
            
            // Fall back to username/password authentication
            $this->authenticate();
            
            // Try to get organization info
            $token = $this->get_valid_token();
            $url = $this->instance_url . '/services/data/' . $this->api_version . '/sobjects/Organization';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            return wp_remote_retrieve_response_code($response) === 200;
            
        } catch (Exception $e) {
            error_log('Salesforce connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}
