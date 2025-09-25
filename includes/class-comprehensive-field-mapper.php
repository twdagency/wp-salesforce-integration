<?php
/**
 * Comprehensive Field Mapper
 * Maps WordPress fields to all Salesforce objects from the Excel file
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Comprehensive_Field_Mapper {
    
    private $salesforce_objects;
    private $field_mappings;
    
    public function __construct() {
        $this->load_salesforce_objects();
        $this->build_field_mappings();
    }
    
    /**
     * Load Salesforce objects from the identified data
     */
    private function load_salesforce_objects() {
        // Load the identified Salesforce objects
        $objects_file = WSI_PLUGIN_PATH . 'salesforce_objects_correct.json';
        
        if (file_exists($objects_file)) {
            $objects_data = file_get_contents($objects_file);
            $this->salesforce_objects = json_decode($objects_data, true);
        } else {
            // Fallback to basic objects if file doesn't exist
            $this->salesforce_objects = array(
                'Account' => array('fields' => array(), 'description' => 'Salesforce Account'),
                'Lead' => array('fields' => array(), 'description' => 'Salesforce Lead'),
                'Contact' => array('fields' => array(), 'description' => 'Salesforce Contact'),
                'Sales Listing' => array('fields' => array(), 'description' => 'Sales Listing'),
                'Wanted Listings' => array('fields' => array(), 'description' => 'Wanted Listings'),
                'Offers' => array('fields' => array(), 'description' => 'Offers'),
                'Haulage Offers' => array('fields' => array(), 'description' => 'Haulage Offers'),
                'Haulage Load' => array('fields' => array(), 'description' => 'Haulage Load'),
                'Sample Requests' => array('fields' => array(), 'description' => 'Sample Requests'),
                'MFI Tests' => array('fields' => array(), 'description' => 'MFI Tests')
            );
        }
    }
    
    /**
     * Build comprehensive field mappings
     */
    private function build_field_mappings() {
        $this->field_mappings = array(
            // Lead object mappings
            'Lead' => array(
                'FirstName' => array(
                    'wp_field' => 'first_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'User first name'
                ),
                'LastName' => array(
                    'wp_field' => 'last_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'User last name'
                ),
                'Email' => array(
                    'wp_field' => 'user_email',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'email',
                    'description' => 'User email address'
                ),
                'Company' => array(
                    'wp_field' => 'company_name',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Company name'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone',
                    'description' => 'Phone number'
                ),
                'Status' => array(
                    'wp_field' => 'lead_status',
                    'wp_source' => 'user_meta',
                    'default' => 'New',
                    'transformation' => 'text',
                    'description' => 'Lead status'
                ),
                'LeadSource' => array(
                    'wp_field' => 'lead_source',
                    'wp_source' => 'user_meta',
                    'default' => 'Website',
                    'transformation' => 'text',
                    'description' => 'Lead source'
                ),
                'Title' => array(
                    'wp_field' => 'job_title',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Job title'
                ),
                'Industry' => array(
                    'wp_field' => 'industry',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Industry'
                ),
                'Website' => array(
                    'wp_field' => 'website',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'url',
                    'description' => 'Website URL'
                ),
                'Street' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Street address'
                ),
                'City' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'City'
                ),
                'State' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'State/Province'
                ),
                'PostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Postal/ZIP code'
                ),
                'Country' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Country'
                ),
                'Description' => array(
                    'wp_field' => 'description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea',
                    'description' => 'Description'
                ),
                // Custom Lead fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress User ID'
                ),
                'Registration_Date__c' => array(
                    'wp_field' => 'user_registered',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'datetime',
                    'description' => 'User registration date'
                ),
                'User_Role__c' => array(
                    'wp_field' => 'roles',
                    'wp_source' => 'user',
                    'required' => false,
                    'transformation' => 'array_to_text',
                    'description' => 'User roles'
                ),
                'Approval_Status__c' => array(
                    'wp_field' => 'approval_status',
                    'wp_source' => 'user_meta',
                    'default' => 'Pending',
                    'transformation' => 'text',
                    'description' => 'Approval status'
                )
            ),
            
            // Contact object mappings
            'Contact' => array(
                'FirstName' => array(
                    'wp_field' => 'first_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Contact first name'
                ),
                'LastName' => array(
                    'wp_field' => 'last_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Contact last name'
                ),
                'Email' => array(
                    'wp_field' => 'user_email',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'email',
                    'description' => 'Contact email'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone',
                    'description' => 'Contact phone'
                ),
                'Title' => array(
                    'wp_field' => 'job_title',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Job title'
                ),
                'AccountId' => array(
                    'wp_field' => 'salesforce_account_id',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Associated Account ID'
                ),
                'Street' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Street address'
                ),
                'City' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'City'
                ),
                'State' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'State/Province'
                ),
                'PostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Postal/ZIP code'
                ),
                'Country' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Country'
                ),
                'Description' => array(
                    'wp_field' => 'description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea',
                    'description' => 'Description'
                ),
                // Custom Contact fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress User ID'
                ),
                'Approval_Date__c' => array(
                    'wp_field' => 'approval_date',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'datetime',
                    'description' => 'Approval date'
                ),
                'Lead_Converted_From__c' => array(
                    'wp_field' => 'original_lead_id',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Original Lead ID'
                )
            ),
            
            // Account object mappings
            'Account' => array(
                'Name' => array(
                    'wp_field' => 'company_name',
                    'wp_source' => 'user_meta',
                    'required' => true,
                    'transformation' => 'text',
                    'fallback' => 'first_name + last_name',
                    'description' => 'Account name'
                ),
                'Type' => array(
                    'wp_field' => 'account_type',
                    'wp_source' => 'user_meta',
                    'default' => 'Customer',
                    'transformation' => 'text',
                    'description' => 'Account type'
                ),
                'Industry' => array(
                    'wp_field' => 'industry',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Industry'
                ),
                'Phone' => array(
                    'wp_field' => 'phone',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'phone',
                    'description' => 'Phone number'
                ),
                'Website' => array(
                    'wp_field' => 'website',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'url',
                    'description' => 'Website URL'
                ),
                'BillingStreet' => array(
                    'wp_field' => 'address_line_1',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Billing street'
                ),
                'BillingCity' => array(
                    'wp_field' => 'city',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Billing city'
                ),
                'BillingState' => array(
                    'wp_field' => 'state',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Billing state'
                ),
                'BillingPostalCode' => array(
                    'wp_field' => 'postal_code',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Billing postal code'
                ),
                'BillingCountry' => array(
                    'wp_field' => 'country',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Billing country'
                ),
                'Description' => array(
                    'wp_field' => 'company_description',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'textarea',
                    'description' => 'Account description'
                ),
                // Custom Account fields
                'WordPress_User_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress User ID'
                ),
                'Primary_Contact_ID__c' => array(
                    'wp_field' => 'salesforce_contact_id',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Primary Contact ID'
                ),
                'Account_Status__c' => array(
                    'wp_field' => 'account_status',
                    'wp_source' => 'user_meta',
                    'default' => 'Active',
                    'transformation' => 'text',
                    'description' => 'Account status'
                ),
                // WordPress-specific Account fields
                'WP_Author_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress Author ID'
                ),
                'WP_Meta_JSON__c' => array(
                    'wp_field' => 'wp_meta_json',
                    'wp_source' => 'computed',
                    'required' => false,
                    'transformation' => 'json',
                    'description' => 'WordPress meta data as JSON'
                ),
                'WP_Modified_Datetime__c' => array(
                    'wp_field' => 'user_modified',
                    'wp_source' => 'user_meta',
                    'required' => false,
                    'transformation' => 'datetime',
                    'description' => 'WordPress modification date'
                ),
                'WP_Permalink__c' => array(
                    'wp_field' => 'user_permalink',
                    'wp_source' => 'computed',
                    'required' => false,
                    'transformation' => 'url',
                    'description' => 'WordPress user permalink'
                ),
                'WP_Post_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'user',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress Post ID (User ID)'
                ),
                'WP_Published_Datetime__c' => array(
                    'wp_field' => 'user_registered',
                    'wp_source' => 'user',
                    'required' => false,
                    'transformation' => 'datetime',
                    'description' => 'WordPress registration date'
                ),
                'WP_Slug__c' => array(
                    'wp_field' => 'user_nicename',
                    'wp_source' => 'user',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'WordPress user slug'
                )
            ),
            
            // Waste Listing object mappings
            'Waste_Listing__c' => array(
                'Name' => array(
                    'wp_field' => 'post_title',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Listing title'
                ),
                'Description__c' => array(
                    'wp_field' => 'post_content',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'textarea',
                    'description' => 'Listing description'
                ),
                'Material_Type__c' => array(
                    'wp_field' => 'material_type',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Type of material'
                ),
                'Quantity__c' => array(
                    'wp_field' => 'quantity',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'Quantity available'
                ),
                'Quantity_Metric__c' => array(
                    'wp_field' => 'quantity_metric',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Quantity unit of measurement'
                ),
                'Guide_Price__c' => array(
                    'wp_field' => 'guide_price',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'currency',
                    'description' => 'Guide price'
                ),
                'Country__c' => array(
                    'wp_field' => 'country_of_waste',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Country where waste is located'
                ),
                'Location_of_Waste__c' => array(
                    'wp_field' => 'location_of_waste',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Specific location of waste'
                ),
                'Warehouse_Address__c' => array(
                    'wp_field' => 'seller_warehouse_address',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'textarea',
                    'description' => 'Warehouse address'
                ),
                'Available_From__c' => array(
                    'wp_field' => 'available_from',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'date',
                    'description' => 'Available from date'
                ),
                'End_Date__c' => array(
                    'wp_field' => 'end_date',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'date',
                    'description' => 'End date'
                ),
                'Listing_Status__c' => array(
                    'wp_field' => 'listing_status',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'text',
                    'description' => 'Listing status'
                ),
                'Is_Approved__c' => array(
                    'wp_field' => 'approved_listing',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'boolean',
                    'description' => 'Is listing approved'
                ),
                'Is_Sold__c' => array(
                    'wp_field' => 'listing_sold',
                    'wp_source' => 'acf',
                    'required' => false,
                    'transformation' => 'boolean',
                    'description' => 'Is listing sold'
                ),
                'Seller_ID__c' => array(
                    'wp_field' => 'seller_id',
                    'wp_source' => 'acf',
                    'required' => true,
                    'transformation' => 'text',
                    'description' => 'Seller ID'
                ),
                'WordPress_Post_ID__c' => array(
                    'wp_field' => 'ID',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress Post ID'
                ),
                'WordPress_Author_ID__c' => array(
                    'wp_field' => 'post_author',
                    'wp_source' => 'post',
                    'required' => true,
                    'transformation' => 'number',
                    'description' => 'WordPress Author ID'
                ),
                'WP_Published_Datetime__c' => array(
                    'wp_field' => 'post_date',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'datetime',
                    'description' => 'WordPress publication date'
                ),
                'WP_Modified_Datetime__c' => array(
                    'wp_field' => 'post_modified',
                    'wp_source' => 'post',
                    'required' => false,
                    'transformation' => 'datetime',
                    'description' => 'WordPress modification date'
                )
            )
        );
    }
    
    /**
     * Get field mappings for a specific Salesforce object
     */
    public function get_field_mappings($salesforce_object) {
        return isset($this->field_mappings[$salesforce_object]) ? $this->field_mappings[$salesforce_object] : array();
    }
    
    /**
     * Get all available Salesforce objects
     */
    public function get_available_objects() {
        // Ensure field_mappings is always an array
        if (!is_array($this->field_mappings)) {
            $this->field_mappings = array();
        }
        return array_keys($this->field_mappings);
    }
    
    /**
     * Get field mapping for a specific field
     */
    public function get_field_mapping($salesforce_object, $salesforce_field) {
        $mappings = $this->get_field_mappings($salesforce_object);
        return isset($mappings[$salesforce_field]) ? $mappings[$salesforce_field] : null;
    }
    
    /**
     * Check if a field mapping exists
     */
    public function has_field_mapping($salesforce_object, $salesforce_field) {
        $mappings = $this->get_field_mappings($salesforce_object);
        return isset($mappings[$salesforce_field]);
    }
    
    /**
     * Get all Salesforce objects from the Excel file
     */
    public function get_salesforce_objects() {
        return $this->salesforce_objects;
    }
    
    /**
     * Get fields for a specific Salesforce object from Excel data
     */
    public function get_salesforce_object_fields($object_name) {
        if (isset($this->salesforce_objects[$object_name])) {
            return $this->salesforce_objects[$object_name]['fields'];
        }
        return array();
    }
    
    /**
     * Generate field mapping documentation
     */
    public function generate_mapping_documentation() {
        $documentation = array();
        
        foreach ($this->field_mappings as $object_name => $fields) {
            $documentation[$object_name] = array(
                'object_name' => $object_name,
                'total_fields' => count($fields),
                'required_fields' => 0,
                'optional_fields' => 0,
                'fields' => array()
            );
            
            foreach ($fields as $sf_field => $mapping) {
                $is_required = isset($mapping['required']) && $mapping['required'];
                
                if ($is_required) {
                    $documentation[$object_name]['required_fields']++;
                } else {
                    $documentation[$object_name]['optional_fields']++;
                }
                
                $documentation[$object_name]['fields'][] = array(
                    'salesforce_field' => $sf_field,
                    'wordpress_field' => $mapping['wp_field'],
                    'wordpress_source' => $mapping['wp_source'],
                    'required' => $is_required,
                    'transformation' => $mapping['transformation'],
                    'description' => isset($mapping['description']) ? $mapping['description'] : ''
                );
            }
        }
        
        return $documentation;
    }
    
    /**
     * Export field mappings to JSON
     */
    public function export_mappings_to_json() {
        return json_encode($this->field_mappings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import field mappings from JSON
     */
    public function import_mappings_from_json($json_data) {
        $mappings = json_decode($json_data, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($mappings)) {
            $this->field_mappings = $mappings;
            return true;
        }
        
        return false;
    }
}
