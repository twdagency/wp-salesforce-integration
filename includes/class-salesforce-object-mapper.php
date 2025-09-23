<?php
/**
 * Salesforce Object Mapper
 * Handles mapping between WordPress data and Salesforce objects
 * Supports Lead → Contact/Account conversion flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Salesforce_Object_Mapper {
    
    private $object_mappings;
    private $field_mappings;
    private $conversion_rules;
    
    public function __construct() {
        $this->load_object_mappings();
        $this->load_field_mappings();
        $this->load_conversion_rules();
    }
    
    /**
     * Load Salesforce object mappings
     */
    private function load_object_mappings() {
        $this->object_mappings = array(
            'user_registration' => array(
                'salesforce_object' => 'Lead',
                'external_id_field' => 'WordPress_User_ID__c',
                'description' => 'New user registrations create Leads in Salesforce'
            ),
            'user_approval' => array(
                'salesforce_object' => 'Contact',
                'external_id_field' => 'WordPress_User_ID__c',
                'description' => 'Approved users become Contacts in Salesforce'
            ),
            'account_creation' => array(
                'salesforce_object' => 'Account',
                'external_id_field' => 'WordPress_User_ID__c',
                'description' => 'Approved users get associated Accounts'
            ),
            'waste_listing' => array(
                'salesforce_object' => 'Waste_Listing__c',
                'external_id_field' => 'WordPress_Post_ID__c',
                'description' => 'Waste trading listings'
            ),
            'opportunity' => array(
                'salesforce_object' => 'Opportunity',
                'external_id_field' => 'WordPress_Post_ID__c',
                'description' => 'Sales opportunities from listings'
            )
        );
    }
    
    /**
     * Load field mappings for different object types
     */
    private function load_field_mappings() {
        $this->field_mappings = array(
            'Lead' => array(
                // Standard Lead fields
                'FirstName' => array(
                    'wp_field' => 'first_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'LastName' => array(
                    'wp_field' => 'last_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Email' => array(
                    'wp_field' => 'user_email',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'email'
                ),
                'Company' => array(
                    'wp_field' => 'company_name',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone'
                ),
                'Status' => array(
                    'wp_field' => 'lead_status',
                    'wp_source' => 'user_meta',
                    'default' => 'New',
                    'transformation' => 'text'
                ),
                'LeadSource' => array(
                    'wp_field' => 'lead_source',
                    'wp_source' => 'user_meta',
                    'default' => 'Website',
                    'transformation' => 'text'
                ),
                'Title' => array(
                    'wp_field' => 'job_title',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Industry' => array(
                    'wp_field' => 'industry',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Website' => array(
                    'wp_field' => 'website',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'url'
                ),
                'Street' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'City' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'State' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'PostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Country' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Description' => array(
                    'wp_field' => 'description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea'
                ),
                // Custom Lead fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'Registration_Date__c' => array(
                    'wp_field' => 'user_registered',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'datetime'
                ),
                'User_Role__c' => array(
                    'wp_field' => 'roles',
                    'wp_source' => 'user',
                    'required' => false,
                    'transformation' => 'array_to_text'
                ),
                'Approval_Status__c' => array(
                    'wp_field' => 'approval_status',
                    'wp_source' => 'user_meta',
                    'default' => 'Pending',
                    'transformation' => 'text'
                )
            ),
            
            'Contact' => array(
                // Standard Contact fields (inherited from Lead)
                'FirstName' => array(
                    'wp_field' => 'first_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'LastName' => array(
                    'wp_field' => 'last_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Email' => array(
                    'wp_field' => 'user_email',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'email'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone'
                ),
                'Title' => array(
                    'wp_field' => 'job_title',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'AccountId' => array(
                    'wp_field' => 'salesforce_account_id',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Street' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'City' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'State' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'PostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Country' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Description' => array(
                    'wp_field' => 'description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea'
                ),
                // Custom Contact fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'Approval_Date__c' => array(
                    'wp_field' => 'approval_date',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'datetime'
                ),
                'Lead_Converted_From__c' => array(
                    'wp_field' => 'original_lead_id',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                )
            ),
            
            'Account' => array(
                // Standard Account fields
                'Name' => array(
                    'wp_field' => 'company_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'fallback' => 'first_name + last_name'
                ),
                'Type' => array(
                    'wp_field' => 'account_type',
                    'wp_source' => 'user_meta',
                    'default' => 'Customer',
                    'transformation' => 'text'
                ),
                'Industry' => array(
                    'wp_field' => 'industry',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone'
                ),
                'Website' => array(
                    'wp_field' => 'website',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'url'
                ),
                'BillingStreet' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'BillingCity' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'BillingState' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'BillingPostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'BillingCountry' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Description' => array(
                    'wp_field' => 'company_description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea'
                ),
                // Custom Account fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'Primary_Contact_ID__c' => array(
                    'wp_field' => 'salesforce_contact_id',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Account_Status__c' => array(
                    'wp_field' => 'account_status',
                    'wp_source' => 'user_meta',
                    'default' => 'Active',
                    'transformation' => 'text'
                )
            ),
            
            'Waste_Listing__c' => array(
                // Basic listing information
                'Name' => array(
                    'wp_field' => 'post_title',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Description__c' => array(
                    'wp_field' => 'post_content',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'textarea'
                ),
                'Material_Type__c' => array(
                    'wp_field' => 'material_type',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Quantity__c' => array(
                    'wp_field' => 'quantity',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'Quantity_Metric__c' => array(
                    'wp_field' => 'quantity_metric',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Guide_Price__c' => array(
                    'wp_field' => 'guide_price',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'currency'
                ),
                'Country__c' => array(
                    'wp_field' => 'country_of_waste',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'Location_of_Waste__c' => array(
                    'wp_field' => 'location_of_waste',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Warehouse_Address__c' => array(
                    'wp_field' => 'seller_warehouse_address',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'textarea'
                ),
                'Available_From__c' => array(
                    'wp_field' => 'available_from',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'date'
                ),
                'End_Date__c' => array(
                    'wp_field' => 'end_date',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'date'
                ),
                'Listing_Status__c' => array(
                    'wp_field' => 'listing_status',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text'
                ),
                'Is_Approved__c' => array(
                    'wp_field' => 'approved_listing',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'boolean'
                ),
                'Is_Sold__c' => array(
                    'wp_field' => 'listing_sold',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'boolean'
                ),
                'Seller_ID__c' => array(
                    'wp_field' => 'seller_id',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text'
                ),
                'WordPress_Post_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'WordPress_Author_ID__c' => array(
                    'wp_field' => 'post_author',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'number'
                ),
                'WP_Published_Datetime__c' => array(
                    'wp_field' => 'post_date',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'datetime'
                ),
                'WP_Modified_Datetime__c' => array(
                    'wp_field' => 'post_modified',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'datetime'
                )
            )
        );
    }
    
    /**
     * Load conversion rules for Lead → Contact/Account
     */
    private function load_conversion_rules() {
        $this->conversion_rules = array(
            'lead_to_contact' => array(
                'trigger_field' => 'approval_status',
                'trigger_value' => 'approved',
                'source_object' => 'Lead',
                'target_object' => 'Contact',
                'field_mappings' => array(
                    'FirstName' => 'FirstName',
                    'LastName' => 'LastName',
                    'Email' => 'Email',
                    'Phone' => 'Phone',
                    'Title' => 'Title',
                    'Street' => 'Street',
                    'City' => 'City',
                    'State' => 'State',
                    'PostalCode' => 'PostalCode',
                    'Country' => 'Country',
                    'Description' => 'Description'
                ),
                'additional_fields' => array(
                    'Approval_Date__c' => 'current_datetime',
                    'Lead_Converted_From__c' => 'source_lead_id'
                )
            ),
            'lead_to_account' => array(
                'trigger_field' => 'approval_status',
                'trigger_value' => 'approved',
                'source_object' => 'Lead',
                'target_object' => 'Account',
                'field_mappings' => array(
                    'Company' => 'Name',
                    'Industry' => 'Industry',
                    'Phone' => 'Phone',
                    'Website' => 'Website',
                    'Street' => 'BillingStreet',
                    'City' => 'BillingCity',
                    'State' => 'BillingState',
                    'PostalCode' => 'BillingPostalCode',
                    'Country' => 'BillingCountry',
                    'Description' => 'Description'
                ),
                'additional_fields' => array(
                    'Account_Status__c' => 'Active',
                    'Primary_Contact_ID__c' => 'created_contact_id'
                )
            )
        );
    }
    
    /**
     * Get object mapping configuration
     */
    public function get_object_mapping($object_type) {
        return isset($this->object_mappings[$object_type]) ? $this->object_mappings[$object_type] : null;
    }
    
    /**
     * Get field mappings for a specific Salesforce object
     */
    public function get_field_mappings($salesforce_object) {
        return isset($this->field_mappings[$salesforce_object]) ? $this->field_mappings[$salesforce_object] : array();
    }
    
    /**
     * Get conversion rules
     */
    public function get_conversion_rules($conversion_type) {
        return isset($this->conversion_rules[$conversion_type]) ? $this->conversion_rules[$conversion_type] : null;
    }
    
    /**
     * Determine which Salesforce object to use based on user status
     */
    public function get_target_object_for_user($user_id) {
        $approval_status = get_user_meta($user_id, 'approval_status', true);
        
        if ($approval_status === 'approved') {
            return 'Contact'; // User is approved, create/update Contact
        } else {
            return 'Lead'; // User is not approved, create/update Lead
        }
    }
    
    /**
     * Get all available Salesforce objects
     */
    public function get_available_objects() {
        return array_keys($this->field_mappings);
    }
    
    /**
     * Check if a field mapping exists
     */
    public function has_field_mapping($salesforce_object, $salesforce_field) {
        $mappings = $this->get_field_mappings($salesforce_object);
        return isset($mappings[$salesforce_field]);
    }
    
    /**
     * Get field mapping for a specific field
     */
    public function get_field_mapping($salesforce_object, $salesforce_field) {
        $mappings = $this->get_field_mappings($salesforce_object);
        return isset($mappings[$salesforce_field]) ? $mappings[$salesforce_field] : null;
    }
}
