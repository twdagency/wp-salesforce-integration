<?php
/**
 * User Registration Handler
 * Handles user registration and creates Leads in Salesforce
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_User_Registration_Handler {
    
    private $salesforce_api;
    private $object_mapper;
    private $data_transformer;
    private $logger;
    
    public function __construct() {
        $this->salesforce_api = new WSI_Salesforce_API();
        $this->object_mapper = new WSI_Salesforce_Object_Mapper();
        $this->data_transformer = new WSI_Data_Transformer();
        $this->logger = new WSI_Logger();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook into user registration
        add_action('user_register', array($this, 'handle_user_registration'), 10, 1);
        
        // Hook into user profile updates
        add_action('profile_update', array($this, 'handle_user_update'), 10, 2);
        
        // Hook into user meta updates
        add_action('update_user_meta', array($this, 'handle_user_meta_update'), 10, 4);
        
        // Hook into user approval process
        add_action('wsi_user_approved', array($this, 'handle_user_approval'), 10, 1);
        
        // Hook into user rejection
        add_action('wsi_user_rejected', array($this, 'handle_user_rejection'), 10, 1);
    }
    
    /**
     * Handle new user registration
     */
    public function handle_user_registration($user_id) {
        try {
            $this->logger->info('Processing new user registration', array('user_id' => $user_id), $user_id, 'user_registration');
            
            // Get user data
            $user_data = $this->get_user_data($user_id);
            
            if (!$user_data) {
                $this->logger->error('Failed to get user data for registration', array('user_id' => $user_id), $user_id, 'user_registration');
                return;
            }
            
            // Transform user data to Salesforce Lead format
            $salesforce_data = $this->transform_user_to_lead($user_data);
            
            if (empty($salesforce_data)) {
                $this->logger->error('Failed to transform user data to Lead format', array('user_id' => $user_id), $user_id, 'user_registration');
                return;
            }
            
            // Create Lead in Salesforce
            $result = $this->create_lead_in_salesforce($salesforce_data, $user_id);
            
            if ($result) {
                // Store Salesforce Lead ID in user meta
                update_user_meta($user_id, 'salesforce_lead_id', $result['id']);
                update_user_meta($user_id, 'salesforce_sync_status', 'synced');
                update_user_meta($user_id, 'salesforce_last_sync', current_time('mysql'));
                
                $this->logger->info('Successfully created Lead in Salesforce', array(
                    'user_id' => $user_id,
                    'salesforce_id' => $result['id']
                ), $user_id, 'user_registration');
                
                // Trigger action for other plugins
                do_action('wsi_lead_created', $user_id, $result['id']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error during user registration sync', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'user_registration');
        }
    }
    
    /**
     * Handle user profile updates
     */
    public function handle_user_update($user_id, $old_user_data) {
        try {
            // Check if user has Salesforce Lead ID
            $lead_id = get_user_meta($user_id, 'salesforce_lead_id', true);
            
            if (!$lead_id) {
                // User doesn't have a Lead yet, create one
                $this->handle_user_registration($user_id);
                return;
            }
            
            $this->logger->info('Processing user profile update', array('user_id' => $user_id), $user_id, 'user_update');
            
            // Get updated user data
            $user_data = $this->get_user_data($user_id);
            
            if (!$user_data) {
                return;
            }
            
            // Determine target object based on approval status
            $target_object = $this->object_mapper->get_target_object_for_user($user_id);
            
            if ($target_object === 'Lead') {
                // Update Lead
                $salesforce_data = $this->transform_user_to_lead($user_data);
                $this->update_lead_in_salesforce($lead_id, $salesforce_data, $user_id);
            } else {
                // User is approved, update Contact instead
                $contact_id = get_user_meta($user_id, 'salesforce_contact_id', true);
                if ($contact_id) {
                    $salesforce_data = $this->transform_user_to_contact($user_data);
                    $this->update_contact_in_salesforce($contact_id, $salesforce_data, $user_id);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error during user update sync', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'user_update');
        }
    }
    
    /**
     * Handle user meta updates
     */
    public function handle_user_meta_update($meta_id, $user_id, $meta_key, $meta_value) {
        // Only sync for specific meta keys
        $sync_keys = array(
            'first_name',
            'last_name',
            'phone',
            'company_name',
            'job_title',
            'industry',
            'website',
            'address_line_1',
            'city',
            'state',
            'postal_code',
            'country',
            'description',
            'approval_status'
        );
        
        if (in_array($meta_key, $sync_keys)) {
            // Delay the sync to avoid multiple API calls
            wp_schedule_single_event(time() + 30, 'wsi_delayed_user_sync', array($user_id));
        }
    }
    
    /**
     * Handle user approval - convert Lead to Contact and Account
     */
    public function handle_user_approval($user_id) {
        try {
            $this->logger->info('Processing user approval - converting Lead to Contact/Account', array('user_id' => $user_id), $user_id, 'user_approval');
            
            // Get the Lead ID
            $lead_id = get_user_meta($user_id, 'salesforce_lead_id', true);
            
            if (!$lead_id) {
                $this->logger->error('No Lead ID found for approved user', array('user_id' => $user_id), $user_id, 'user_approval');
                return;
            }
            
            // Get user data
            $user_data = $this->get_user_data($user_id);
            
            if (!$user_data) {
                $this->logger->error('Failed to get user data for approval', array('user_id' => $user_id), $user_id, 'user_approval');
                return;
            }
            
            // Create Account first
            $account_result = $this->create_account_for_user($user_data, $user_id);
            
            if (!$account_result) {
                $this->logger->error('Failed to create Account for approved user', array('user_id' => $user_id), $user_id, 'user_approval');
                return;
            }
            
            // Create Contact
            $contact_result = $this->create_contact_for_user($user_data, $account_result['id'], $user_id);
            
            if (!$contact_result) {
                $this->logger->error('Failed to create Contact for approved user', array('user_id' => $user_id), $user_id, 'user_approval');
                return;
            }
            
            // Update Contact with Account ID
            $this->update_contact_account_id($contact_result['id'], $account_result['id'], $user_id);
            
            // Update Account with Contact ID
            $this->update_account_contact_id($account_result['id'], $contact_result['id'], $user_id);
            
            // Store IDs in user meta
            update_user_meta($user_id, 'salesforce_account_id', $account_result['id']);
            update_user_meta($user_id, 'salesforce_contact_id', $contact_result['id']);
            update_user_meta($user_id, 'approval_date', current_time('mysql'));
            update_user_meta($user_id, 'salesforce_sync_status', 'approved');
            
            // Mark Lead as converted (if possible)
            $this->mark_lead_as_converted($lead_id, $contact_result['id'], $account_result['id'], $user_id);
            
            $this->logger->info('Successfully converted Lead to Contact/Account', array(
                'user_id' => $user_id,
                'lead_id' => $lead_id,
                'contact_id' => $contact_result['id'],
                'account_id' => $account_result['id']
            ), $user_id, 'user_approval');
            
            // Trigger action for other plugins
            do_action('wsi_user_approved_synced', $user_id, $contact_result['id'], $account_result['id']);
            
        } catch (Exception $e) {
            $this->logger->error('Error during user approval sync', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'user_approval');
        }
    }
    
    /**
     * Handle user rejection
     */
    public function handle_user_rejection($user_id) {
        try {
            $this->logger->info('Processing user rejection', array('user_id' => $user_id), $user_id, 'user_rejection');
            
            // Update Lead status to indicate rejection
            $lead_id = get_user_meta($user_id, 'salesforce_lead_id', true);
            
            if ($lead_id) {
                $this->update_lead_status($lead_id, 'Rejected', $user_id);
            }
            
            // Update user meta
            update_user_meta($user_id, 'salesforce_sync_status', 'rejected');
            
            $this->logger->info('Successfully updated Lead status to Rejected', array(
                'user_id' => $user_id,
                'lead_id' => $lead_id
            ), $user_id, 'user_rejection');
            
        } catch (Exception $e) {
            $this->logger->error('Error during user rejection sync', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'user_rejection');
        }
    }
    
    /**
     * Get comprehensive user data
     */
    private function get_user_data($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return null;
        }
        
        $user_data = array(
            'ID' => $user->ID,
            'user_email' => $user->user_email,
            'user_registered' => $user->user_registered,
            'roles' => $user->roles,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'phone' => get_user_meta($user_id, 'phone', true),
            'company_name' => get_user_meta($user_id, 'company_name', true),
            'job_title' => get_user_meta($user_id, 'job_title', true),
            'industry' => get_user_meta($user_id, 'industry', true),
            'website' => get_user_meta($user_id, 'website', true),
            'address_line_1' => get_user_meta($user_id, 'address_line_1', true),
            'city' => get_user_meta($user_id, 'city', true),
            'state' => get_user_meta($user_id, 'state', true),
            'postal_code' => get_user_meta($user_id, 'postal_code', true),
            'country' => get_user_meta($user_id, 'country', true),
            'description' => get_user_meta($user_id, 'description', true),
            'approval_status' => get_user_meta($user_id, 'approval_status', true),
            'lead_source' => get_user_meta($user_id, 'lead_source', true)
        );
        
        return $user_data;
    }
    
    /**
     * Transform user data to Lead format
     */
    private function transform_user_to_lead($user_data) {
        $field_mappings = $this->object_mapper->get_field_mappings('Lead');
        $salesforce_data = array();
        
        foreach ($field_mappings as $sf_field => $mapping) {
            $value = $this->get_field_value($user_data, $mapping);
            
            if ($value !== null) {
                $salesforce_data[$sf_field] = $value;
            }
        }
        
        return $salesforce_data;
    }
    
    /**
     * Transform user data to Contact format
     */
    private function transform_user_to_contact($user_data) {
        $field_mappings = $this->object_mapper->get_field_mappings('Contact');
        $salesforce_data = array();
        
        foreach ($field_mappings as $sf_field => $mapping) {
            $value = $this->get_field_value($user_data, $mapping);
            
            if ($value !== null) {
                $salesforce_data[$sf_field] = $value;
            }
        }
        
        return $salesforce_data;
    }
    
    /**
     * Get field value based on mapping configuration
     */
    private function get_field_value($user_data, $mapping) {
        $wp_field = $mapping['wp_field'];
        $wp_source = $mapping['wp_source'];
        $transformation = $mapping['transformation'];
        $default = isset($mapping['default']) ? $mapping['default'] : null;
        
        // Get the raw value
        $value = null;
        
        if ($wp_source === 'user') {
            $value = isset($user_data[$wp_field]) ? $user_data[$wp_field] : null;
        } elseif ($wp_source === 'user_meta') {
            $value = isset($user_data[$wp_field]) ? $user_data[$wp_field] : null;
        }
        
        // Use default if value is empty
        if (empty($value) && $default !== null) {
            $value = $default;
        }
        
        // Apply transformation
        if ($value !== null) {
            $value = $this->apply_transformation($value, $transformation);
        }
        
        return $value;
    }
    
    /**
     * Apply field transformation
     */
    private function apply_transformation($value, $transformation) {
        switch ($transformation) {
            case 'text':
                return sanitize_text_field($value);
            case 'email':
                return is_email($value) ? $value : '';
            case 'phone':
                return sanitize_text_field($value);
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'number':
                return is_numeric($value) ? (float) $value : null;
            case 'boolean':
                return (bool) $value;
            case 'datetime':
                return date('c', strtotime($value));
            case 'date':
                return date('Y-m-d', strtotime($value));
            case 'array_to_text':
                return is_array($value) ? implode(';', $value) : $value;
            case 'currency':
                return is_numeric($value) ? (float) $value : null;
            default:
                return $value;
        }
    }
    
    /**
     * Create Lead in Salesforce
     */
    private function create_lead_in_salesforce($data, $user_id) {
        try {
            $result = $this->salesforce_api->create_record('Lead', $data, 'WordPress_User_ID__c', $user_id);
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to create Lead in Salesforce', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
            return false;
        }
    }
    
    /**
     * Update Lead in Salesforce
     */
    private function update_lead_in_salesforce($lead_id, $data, $user_id) {
        try {
            $result = $this->salesforce_api->upsert_record('Lead', 'Id', $lead_id, $data);
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to update Lead in Salesforce', array(
                'user_id' => $user_id,
                'lead_id' => $lead_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
            return false;
        }
    }
    
    /**
     * Create Account for user
     */
    private function create_account_for_user($user_data, $user_id) {
        $field_mappings = $this->object_mapper->get_field_mappings('Account');
        $salesforce_data = array();
        
        foreach ($field_mappings as $sf_field => $mapping) {
            $value = $this->get_field_value($user_data, $mapping);
            
            if ($value !== null) {
                $salesforce_data[$sf_field] = $value;
            }
        }
        
        // Handle fallback for Name field
        if (empty($salesforce_data['Name']) && !empty($user_data['first_name']) && !empty($user_data['last_name'])) {
            $salesforce_data['Name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
        }
        
        try {
            $result = $this->salesforce_api->create_record('Account', $salesforce_data, 'WordPress_User_ID__c', $user_id);
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to create Account in Salesforce', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
            return false;
        }
    }
    
    /**
     * Create Contact for user
     */
    private function create_contact_for_user($user_data, $account_id, $user_id) {
        $field_mappings = $this->object_mapper->get_field_mappings('Contact');
        $salesforce_data = array();
        
        foreach ($field_mappings as $sf_field => $mapping) {
            $value = $this->get_field_value($user_data, $mapping);
            
            if ($value !== null) {
                $salesforce_data[$sf_field] = $value;
            }
        }
        
        // Set Account ID
        $salesforce_data['AccountId'] = $account_id;
        
        try {
            $result = $this->salesforce_api->create_record('Contact', $salesforce_data, 'WordPress_User_ID__c', $user_id);
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to create Contact in Salesforce', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
            return false;
        }
    }
    
    /**
     * Update Contact with Account ID
     */
    private function update_contact_account_id($contact_id, $account_id, $user_id) {
        try {
            $this->salesforce_api->upsert_record('Contact', 'Id', $contact_id, array('AccountId' => $account_id));
        } catch (Exception $e) {
            $this->logger->error('Failed to update Contact Account ID', array(
                'user_id' => $user_id,
                'contact_id' => $contact_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
        }
    }
    
    /**
     * Update Account with Contact ID
     */
    private function update_account_contact_id($account_id, $contact_id, $user_id) {
        try {
            $this->salesforce_api->upsert_record('Account', 'Id', $account_id, array('Primary_Contact_ID__c' => $contact_id));
        } catch (Exception $e) {
            $this->logger->error('Failed to update Account Contact ID', array(
                'user_id' => $user_id,
                'account_id' => $account_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
        }
    }
    
    /**
     * Mark Lead as converted
     */
    private function mark_lead_as_converted($lead_id, $contact_id, $account_id, $user_id) {
        try {
            $this->salesforce_api->upsert_record('Lead', 'Id', $lead_id, array(
                'IsConverted' => true,
                'ConvertedContactId' => $contact_id,
                'ConvertedAccountId' => $account_id,
                'ConvertedOpportunityId' => null, // We're not creating opportunities automatically
                'Status' => 'Converted'
            ));
        } catch (Exception $e) {
            $this->logger->error('Failed to mark Lead as converted', array(
                'user_id' => $user_id,
                'lead_id' => $lead_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
        }
    }
    
    /**
     * Update Lead status
     */
    private function update_lead_status($lead_id, $status, $user_id) {
        try {
            $this->salesforce_api->upsert_record('Lead', 'Id', $lead_id, array('Status' => $status));
        } catch (Exception $e) {
            $this->logger->error('Failed to update Lead status', array(
                'user_id' => $user_id,
                'lead_id' => $lead_id,
                'error' => $e->getMessage()
            ), $user_id, 'salesforce_api');
        }
    }
}

// Initialize the handler
new WSI_User_Registration_Handler();
