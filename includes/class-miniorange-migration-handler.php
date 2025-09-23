<?php
/**
 * MiniOrange Migration Handler
 * Safely migrates from miniOrange plugin to prevent duplicates
 * Based on analysis of miniOrange plugin structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_MiniOrange_Migration_Handler {
    
    private $salesforce_api;
    private $logger;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->salesforce_api = new WSI_Salesforce_API();
        $this->logger = new WSI_Logger();
    }
    
    /**
     * Check if miniOrange migration is needed
     */
    public function is_migration_needed() {
        // Check for miniOrange database table
        $table_name = 'mo_sf_sync_object_field_mapping';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        $migration_completed = get_option('wsi_miniorange_migration_completed', false);
        
        return $table_exists && !$migration_completed;
    }
    
    /**
     * Start migration from miniOrange plugin
     */
    public function start_migration() {
        if (!$this->is_migration_needed()) {
            return array('success' => false, 'message' => 'Migration not needed');
        }
        
        $this->logger->info('Starting migration from miniOrange plugin');
        
        try {
            // Step 1: Get miniOrange mappings
            $miniorange_mappings = $this->get_miniorange_mappings();
            
            // Step 2: Identify existing Salesforce records
            $existing_records = $this->identify_existing_salesforce_records();
            
            // Step 3: Map WordPress users to existing Salesforce records
            $mapping_results = $this->map_users_to_salesforce_records($existing_records);
            
            // Step 4: Update WordPress user meta with Salesforce IDs
            $this->update_user_meta_with_salesforce_ids($mapping_results);
            
            // Step 5: Mark migration as completed
            update_option('wsi_miniorange_migration_completed', true);
            update_option('wsi_miniorange_migration_date', current_time('mysql'));
            
            $this->logger->info('MiniOrange migration completed successfully', array(
                'mapped_users' => count($mapping_results),
                'migration_date' => current_time('mysql')
            ));
            
            return array(
                'success' => true,
                'message' => 'Migration completed successfully',
                'mapped_users' => count($mapping_results),
                'miniorange_mappings' => count($miniorange_mappings)
            );
            
        } catch (Exception $e) {
            $this->logger->error('Migration failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get miniOrange plugin mappings
     */
    private function get_miniorange_mappings() {
        $table_name = 'mo_sf_sync_object_field_mapping';
        
        $query = "SELECT * FROM {$table_name} WHERE wordpress_object = 'user'";
        $mappings = $this->wpdb->get_results($query);
        
        $this->logger->info('Found miniOrange mappings', array(
            'count' => count($mappings)
        ));
        
        return $mappings;
    }
    
    /**
     * Identify existing Salesforce records created by miniOrange
     */
    private function identify_existing_salesforce_records() {
        $existing_records = array(
            'leads' => array(),
            'contacts' => array(),
            'accounts' => array()
        );
        
        try {
            // Query for Leads with WordPress User ID (miniOrange uses this pattern)
            $leads = $this->query_salesforce_records('Lead', array(
                'WordPress_User_ID__c != null'
            ));
            
            if ($leads) {
                foreach ($leads as $lead) {
                    if (isset($lead['WordPress_User_ID__c'])) {
                        $existing_records['leads'][$lead['WordPress_User_ID__c']] = $lead;
                    }
                }
            }
            
            // Query for Contacts with WordPress User ID
            $contacts = $this->query_salesforce_records('Contact', array(
                'WordPress_User_ID__c != null'
            ));
            
            if ($contacts) {
                foreach ($contacts as $contact) {
                    if (isset($contact['WordPress_User_ID__c'])) {
                        $existing_records['contacts'][$contact['WordPress_User_ID__c']] = $contact;
                    }
                }
            }
            
            // Query for Accounts with WordPress User ID
            $accounts = $this->query_salesforce_records('Account', array(
                'WordPress_User_ID__c != null'
            ));
            
            if ($accounts) {
                foreach ($accounts as $account) {
                    if (isset($account['WordPress_User_ID__c'])) {
                        $existing_records['accounts'][$account['WordPress_User_ID__c']] = $account;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to identify existing records', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $existing_records;
    }
    
    /**
     * Map WordPress users to existing Salesforce records
     */
    private function map_users_to_salesforce_records($existing_records) {
        $mapping_results = array();
        
        // Get all WordPress users
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'salesforce_lead_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($users as $user) {
            $user_id = $user->ID;
            $mapping = array(
                'user_id' => $user_id,
                'lead_id' => null,
                'contact_id' => null,
                'account_id' => null,
                'status' => 'not_found'
            );
            
            // Check if user has existing Lead
            if (isset($existing_records['leads'][$user_id])) {
                $mapping['lead_id'] = $existing_records['leads'][$user_id]['Id'];
                $mapping['status'] = 'lead_found';
            }
            
            // Check if user has existing Contact
            if (isset($existing_records['contacts'][$user_id])) {
                $mapping['contact_id'] = $existing_records['contacts'][$user_id]['Id'];
                $mapping['status'] = 'contact_found';
                
                // Get associated Account
                if (isset($existing_records['contacts'][$user_id]['AccountId'])) {
                    $mapping['account_id'] = $existing_records['contacts'][$user_id]['AccountId'];
                }
            }
            
            // Check if user has existing Account
            if (isset($existing_records['accounts'][$user_id])) {
                $mapping['account_id'] = $existing_records['accounts'][$user_id]['Id'];
                if ($mapping['status'] === 'not_found') {
                    $mapping['status'] = 'account_found';
                }
            }
            
            $mapping_results[] = $mapping;
        }
        
        return $mapping_results;
    }
    
    /**
     * Update WordPress user meta with Salesforce IDs
     */
    private function update_user_meta_with_salesforce_ids($mapping_results) {
        foreach ($mapping_results as $mapping) {
            $user_id = $mapping['user_id'];
            
            if ($mapping['lead_id']) {
                update_user_meta($user_id, 'salesforce_lead_id', $mapping['lead_id']);
                update_user_meta($user_id, 'salesforce_sync_status', 'migrated_from_miniorange');
                update_user_meta($user_id, 'salesforce_migration_date', current_time('mysql'));
            }
            
            if ($mapping['contact_id']) {
                update_user_meta($user_id, 'salesforce_contact_id', $mapping['contact_id']);
            }
            
            if ($mapping['account_id']) {
                update_user_meta($user_id, 'salesforce_account_id', $mapping['account_id']);
            }
            
            // Log the mapping
            $this->logger->info('Mapped user to existing Salesforce records', array(
                'user_id' => $user_id,
                'lead_id' => $mapping['lead_id'],
                'contact_id' => $mapping['contact_id'],
                'account_id' => $mapping['account_id'],
                'status' => $mapping['status']
            ));
        }
    }
    
    /**
     * Query Salesforce records
     */
    private function query_salesforce_records($object_type, $conditions = array()) {
        try {
            $token = $this->salesforce_api->get_valid_token();
            $instance_url = get_option('wsi_instance_url');
            
            // Build SOQL query
            $fields = array('Id', 'WordPress_User_ID__c');
            if ($object_type === 'Contact') {
                $fields[] = 'AccountId';
            }
            
            $soql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $object_type;
            
            if (!empty($conditions)) {
                $soql .= ' WHERE ' . implode(' AND ', $conditions);
            }
            
            $url = $instance_url . '/services/data/v58.0/query/?q=' . urlencode($soql);
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Query failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 400) {
                $error_data = json_decode($body, true);
                $error_msg = isset($error_data[0]['message']) ? $error_data[0]['message'] : 'Unknown error';
                throw new Exception('Query failed: ' . $error_msg);
            }
            
            $data = json_decode($body, true);
            return isset($data['records']) ? $data['records'] : array();
            
        } catch (Exception $e) {
            $this->logger->error('Failed to query Salesforce records', array(
                'object_type' => $object_type,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    
    /**
     * Safe create/update record that checks for duplicates first
     */
    public function safe_upsert_record($object_type, $external_id_field, $external_id_value, $data, $user_id) {
        // Check for existing records first
        $existing_record = $this->check_for_duplicates($user_id, $object_type);
        
        if ($existing_record) {
            // Update existing record instead of creating new one
            $this->logger->info('Updating existing record instead of creating duplicate', array(
                'user_id' => $user_id,
                'object_type' => $object_type,
                'existing_id' => $existing_record['Id']
            ));
            
            // Update the existing record
            return $this->salesforce_api->update_record($object_type, $existing_record['Id'], $data);
        }
        
        // No existing record found, proceed with normal upsert
        return $this->salesforce_api->upsert_record($object_type, $external_id_field, $external_id_value, $data);
    }
    
    /**
     * Check for potential duplicates before creating new records
     */
    public function check_for_duplicates($user_id, $object_type) {
        try {
            // Query for existing records with this WordPress User ID
            $existing_records = $this->query_salesforce_records($object_type, array(
                'WordPress_User_ID__c = \'' . $user_id . '\''
            ));
            
            if (!empty($existing_records)) {
                $this->logger->warning('Potential duplicate found', array(
                    'user_id' => $user_id,
                    'object_type' => $object_type,
                    'existing_records' => count($existing_records)
                ));
                
                return $existing_records[0]; // Return the first match
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to check for duplicates', array(
                'user_id' => $user_id,
                'object_type' => $object_type,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Get migration status
     */
    public function get_migration_status() {
        return array(
            'migration_needed' => $this->is_migration_needed(),
            'migration_completed' => get_option('wsi_miniorange_migration_completed', false),
            'migration_date' => get_option('wsi_miniorange_migration_date', null),
            'miniorange_table_exists' => $this->wpdb->get_var("SHOW TABLES LIKE 'mo_sf_sync_object_field_mapping'") == 'mo_sf_sync_object_field_mapping'
        );
    }
    
    /**
     * Clean up miniOrange data after successful migration
     */
    public function cleanup_miniorange_data() {
        if (!get_option('wsi_miniorange_migration_completed', false)) {
            return false;
        }
        
        try {
            // Drop miniOrange table
            $this->wpdb->query("DROP TABLE IF EXISTS mo_sf_sync_object_field_mapping");
            
            // Remove miniOrange options
            delete_option('mo_salesforce_connector_plugin_activated');
            delete_site_option('mo_sf_sync_provision');
            
            $this->logger->info('MiniOrange data cleaned up successfully');
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup miniOrange data', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}
