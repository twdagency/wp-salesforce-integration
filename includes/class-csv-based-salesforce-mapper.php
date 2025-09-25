<?php
/**
 * CSV-Based Salesforce Object Mapper
 * Maps WordPress fields to actual Salesforce objects and fields from CSV files
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_CSV_Based_Salesforce_Mapper {
    
    private $salesforce_objects;
    private $field_mappings;
    
    public function __construct() {
        $this->load_salesforce_objects();
        $this->load_field_mappings();
    }
    
    /**
     * Load Salesforce objects from CSV data
     */
    private function load_salesforce_objects() {
        $objects_file = WSI_PLUGIN_PATH . 'salesforce_objects_from_csv.json';
        
        if (file_exists($objects_file)) {
            $objects_data = file_get_contents($objects_file);
            $this->salesforce_objects = json_decode($objects_data, true);
        } else {
            $this->salesforce_objects = array();
        }
    }
    
    /**
     * Load field mappings from CSV data
     */
    private function load_field_mappings() {
        $mappings_file = WSI_PLUGIN_PATH . 'salesforce_field_mappings_from_csv.json';
        
        if (file_exists($mappings_file)) {
            $mappings_data = file_get_contents($mappings_file);
            $this->field_mappings = json_decode($mappings_data, true);
        } else {
            $this->field_mappings = array();
        }
    }
    
    /**
     * Get all available Salesforce objects
     */
    public function get_available_objects() {
        // Ensure salesforce_objects is always an array
        if (!is_array($this->salesforce_objects)) {
            $this->salesforce_objects = array();
        }
        return array_keys($this->salesforce_objects);
    }
    
    /**
     * Get fields for a specific Salesforce object
     */
    public function get_salesforce_object_fields($object_name) {
        if (isset($this->salesforce_objects[$object_name])) {
            return $this->salesforce_objects[$object_name]['fields'];
        }
        return array();
    }
    
    /**
* Get field mappings for a specific Salesforce object
     */
    public function get_field_mappings($salesforce_object) {
        $mappings = isset($this->field_mappings[$salesforce_object]) ? $this->field_mappings[$salesforce_object] : array();
        // Ensure it's always an array
        return is_array($mappings) ? $mappings : array();
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
     * Get all Salesforce objects with field counts
     */
    public function get_objects_summary() {
        $summary = array();
        
        foreach ($this->salesforce_objects as $object_name => $object_data) {
            $summary[$object_name] = array(
                'name' => $object_name,
                'total_fields' => count($object_data['fields']),
                'mapped_fields' => count($this->get_field_mappings($object_name)),
                'description' => $object_data['description']
            );
        }
        
        return $summary;
    }
    
    /**
     * Get field type distribution for an object
     */
    public function get_field_type_distribution($object_name) {
        $fields = $this->get_salesforce_object_fields($object_name);
        $distribution = array();
        
        foreach ($fields as $field) {
            $data_type = $field['data_type'];
            if (!isset($distribution[$data_type])) {
                $distribution[$data_type] = 0;
            }
            $distribution[$data_type]++;
        }
        
        return $distribution;
    }
    
    /**
     * Get all fields of a specific type for an object
     */
    public function get_fields_by_type($object_name, $data_type) {
        $fields = $this->get_salesforce_object_fields($object_name);
        $filtered_fields = array();
        
        foreach ($fields as $field) {
            if ($field['data_type'] === $data_type) {
                $filtered_fields[] = $field;
            }
        }
        
        return $filtered_fields;
    }
    
    /**
     * Get external ID fields for an object
     */
    public function get_external_id_fields($object_name) {
        $fields = $this->get_salesforce_object_fields($object_name);
        $external_id_fields = array();
        
        foreach ($fields as $field) {
            if ($field['is_external_id']) {
                $external_id_fields[] = $field;
            }
        }
        
        return $external_id_fields;
    }
    
    /**
     * Get lookup fields for an object
     */
    public function get_lookup_fields($object_name) {
        $fields = $this->get_salesforce_object_fields($object_name);
        $lookup_fields = array();
        
        foreach ($fields as $field) {
            if ($field['is_lookup']) {
                $lookup_fields[] = $field;
            }
        }
        
        return $lookup_fields;
    }
    
    /**
     * Generate comprehensive field mapping documentation
     */
    public function generate_mapping_documentation() {
        $documentation = array();
        
        foreach ($this->salesforce_objects as $object_name => $object_data) {
            $documentation[$object_name] = array(
                'object_name' => $object_name,
                'total_fields' => count($object_data['fields']),
                'mapped_fields' => count($this->get_field_mappings($object_name)),
                'field_types' => $this->get_field_type_distribution($object_name),
                'external_id_fields' => count($this->get_external_id_fields($object_name)),
                'lookup_fields' => count($this->get_lookup_fields($object_name)),
                'fields' => array()
            );
            
            // Add field details
            foreach ($object_data['fields'] as $field) {
                $mapping = $this->get_field_mapping($object_name, $field['name']);
                
                $documentation[$object_name]['fields'][] = array(
                    'salesforce_field' => $field['name'],
                    'developer_name' => $field['developer_name'],
                    'data_type' => $field['data_type'],
                    'is_external_id' => $field['is_external_id'],
                    'is_lookup' => $field['is_lookup'],
                    'mapped' => $mapping !== null,
                    'wordpress_field' => $mapping ? $mapping['wp_field'] : null,
                    'wordpress_source' => $mapping ? $mapping['wp_source'] : null,
                    'transformation' => $mapping ? $mapping['transformation'] : null,
                    'description' => $mapping ? $mapping['description'] : null
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
     * Export objects to JSON
     */
    public function export_objects_to_json() {
        return json_encode($this->salesforce_objects, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get WordPress field requirements for all objects
     */
    public function get_wordpress_field_requirements() {
        $requirements = array(
            'user_meta' => array(),
            'user' => array(),
            'post' => array(),
            'acf' => array(),
            'computed' => array()
        );
        
        foreach ($this->field_mappings as $object_name => $mappings) {
            foreach ($mappings as $sf_field => $mapping) {
                $source = $mapping['wp_source'];
                $field = $mapping['wp_field'];
                
                if (!in_array($field, $requirements[$source])) {
                    $requirements[$source][] = $field;
                }
            }
        }
        
        return $requirements;
    }
    
    /**
     * Get transformation requirements
     */
    public function get_transformation_requirements() {
        $transformations = array();
        
        foreach ($this->field_mappings as $object_name => $mappings) {
            foreach ($mappings as $sf_field => $mapping) {
                $transformation = $mapping['transformation'];
                
                if (!isset($transformations[$transformation])) {
                    $transformations[$transformation] = 0;
                }
                $transformations[$transformation]++;
            }
        }
        
        return $transformations;
    }
    
    /**
     * Validate field mappings
     */
    public function validate_field_mappings() {
        $validation_results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        foreach ($this->field_mappings as $object_name => $mappings) {
            foreach ($mappings as $sf_field => $mapping) {
                // Check required fields
                if (isset($mapping['required']) && $mapping['required'] && empty($mapping['wp_field'])) {
                    $validation_results['errors'][] = "Required field {$sf_field} in {$object_name} has no WordPress field mapping";
                    $validation_results['valid'] = false;
                }
                
                // Check transformation types
                $valid_transformations = array(
                    'text', 'email', 'phone', 'url', 'textarea', 'number', 'boolean',
                    'datetime', 'date', 'array_to_text', 'currency', 'json'
                );
                
                if (!in_array($mapping['transformation'], $valid_transformations)) {
                    $validation_results['warnings'][] = "Unknown transformation type '{$mapping['transformation']}' for field {$sf_field} in {$object_name}";
                }
            }
        }
        
        return $validation_results;
    }
}
