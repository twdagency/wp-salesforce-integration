<?php
/**
 * Salesforce OAuth2 Integration Class
 * Handles OAuth2 authentication with custom URLs
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Salesforce_OAuth {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $authorization_uri;
    private $instance_url;
    private $access_token;
    private $refresh_token;
    private $api_version = 'v58.0';
    
    public function __construct() {
        $this->client_id = get_option('wsi_oauth_client_id');
        $this->client_secret = get_option('wsi_oauth_client_secret');
        $this->redirect_uri = get_option('wsi_oauth_redirect_uri');
        $this->authorization_uri = $this->get_authorization_uri();
        $this->instance_url = get_option('wsi_oauth_instance_url');
        $this->access_token = get_option('wsi_oauth_access_token');
        $this->refresh_token = get_option('wsi_oauth_refresh_token');
    }
    
    /**
     * Get authorization URI based on environment
     */
    private function get_authorization_uri() {
        $environment = get_option('wsi_oauth_environment', 'production');
        $custom_uri = get_option('wsi_oauth_authorization_uri');
        
        switch ($environment) {
            case 'sandbox':
                return 'https://test.salesforce.com';
            case 'custom':
                return $custom_uri ?: 'https://login.salesforce.com';
            case 'production':
            default:
                return 'https://login.salesforce.com';
        }
    }
    
    /**
     * Get authorization URL for OAuth2 flow
     */
    public function get_authorization_url() {
        $params = array(
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'api refresh_token',
            'state' => 'wsi_oauth'
        );
        
        return $this->authorization_uri . '/services/oauth2/authorize?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchange_code_for_token($code) {
        $token_url = $this->authorization_uri . '/services/oauth2/token';
        
        $data = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'code' => $code
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Token exchange failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        
        if (isset($data['error'])) {
            $error_msg = isset($data['error_description']) ? $data['error_description'] : $data['error'];
            throw new Exception('Token exchange error: ' . $error_msg);
        }
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->refresh_token = $data['refresh_token'];
            $this->instance_url = $data['instance_url'];
            
            // Store tokens
            update_option('wsi_oauth_access_token', $this->access_token);
            update_option('wsi_oauth_refresh_token', $this->refresh_token);
            update_option('wsi_oauth_instance_url', $this->instance_url);
            update_option('wsi_oauth_token_expires', time() + $data['expires_in']);
            
            return true;
        }
        
        throw new Exception('Token exchange failed: No access token received');
    }
    
    /**
     * Refresh access token using refresh token
     */
    public function refresh_access_token() {
        if (empty($this->refresh_token)) {
            throw new Exception('No refresh token available');
        }
        
        $token_url = $this->authorization_uri . '/services/oauth2/token';
        
        $data = array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $this->refresh_token
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Token refresh failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $error_msg = isset($data['error_description']) ? $data['error_description'] : $data['error'];
            throw new Exception('Token refresh error: ' . $error_msg);
        }
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $expires_in = isset($data['expires_in']) ? $data['expires_in'] : 7200; // Default 2 hours
            
            // Update stored token
            update_option('wsi_oauth_access_token', $this->access_token);
            update_option('wsi_oauth_token_expires', time() + $expires_in);
            
            return true;
        }
        
        throw new Exception('Token refresh failed: No access token received');
    }
    
    /**
     * Check if current token is valid
     */
    public function is_token_valid() {
        $expires = get_option('wsi_oauth_token_expires', 0);
        return $expires > time() && !empty($this->access_token);
    }
    
    /**
     * Get valid access token
     */
    public function get_valid_token() {
        if (!$this->is_token_valid()) {
            $this->refresh_access_token();
        }
        return $this->access_token;
    }
    
    /**
     * Test connection to Salesforce
     */
    public function test_connection() {
        try {
            $token = $this->get_valid_token();
            
            // Try to get organization info
            $url = $this->instance_url . '/services/data/' . $this->api_version . '/sobjects/Organization';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Connection test failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 400) {
                $error_data = json_decode($body, true);
                $error_msg = isset($error_data[0]['message']) ? $error_data[0]['message'] : 'Unknown error';
                throw new Exception('Connection test failed: ' . $error_msg);
            }
            
            $org_data = json_decode($body, true);
            
            // Update connection status
            update_option('wsi_connection_status', true);
            update_option('wsi_last_connection_check', current_time('mysql'));
            
            return array(
                'success' => true,
                'organization' => $org_data,
                'instance_url' => $this->instance_url
            );
            
        } catch (Exception $e) {
            update_option('wsi_connection_status', false);
            update_option('wsi_last_connection_check', current_time('mysql'));
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get organization information
     */
    public function get_organization_info() {
        try {
            $token = $this->get_valid_token();
            $url = $this->instance_url . '/services/data/' . $this->api_version . '/sobjects/Organization';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Failed to get organization info: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 400) {
                throw new Exception('Failed to get organization info: HTTP ' . $response_code);
            }
            
            return json_decode($body, true);
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Get available objects
     */
    public function get_objects() {
        try {
            $token = $this->get_valid_token();
            $url = $this->instance_url . '/services/data/' . $this->api_version . '/sobjects/';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Failed to get objects: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 400) {
                throw new Exception('Failed to get objects: HTTP ' . $response_code);
            }
            
            $data = json_decode($body, true);
            return isset($data['sobjects']) ? $data['sobjects'] : array();
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Get object fields
     */
    public function get_object_fields($object_name) {
        try {
            $token = $this->get_valid_token();
            $url = $this->instance_url . '/services/data/' . $this->api_version . '/sobjects/' . $object_name . '/describe/';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Failed to get object fields: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 400) {
                throw new Exception('Failed to get object fields: HTTP ' . $response_code);
            }
            
            $data = json_decode($body, true);
            return isset($data['fields']) ? $data['fields'] : array();
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Clear stored tokens
     */
    public function clear_tokens() {
        delete_option('wsi_oauth_access_token');
        delete_option('wsi_oauth_refresh_token');
        delete_option('wsi_oauth_token_expires');
        delete_option('wsi_connection_status');
        
        $this->access_token = null;
        $this->refresh_token = null;
    }
    
    /**
     * Get connection status
     */
    public function get_connection_status() {
        return array(
            'connected' => $this->is_token_valid(),
            'has_credentials' => !empty($this->client_id) && !empty($this->client_secret) && !empty($this->authorization_uri),
            'instance_url' => $this->instance_url,
            'last_check' => get_option('wsi_last_connection_check', 'Never'),
            'token_expires' => get_option('wsi_oauth_token_expires', 0)
        );
    }
}
