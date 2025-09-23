<?php
/**
 * Data Transformation Handler for ACF to Salesforce
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Data_Transformer {
    
    /**
     * Transform WordPress post data with ACF fields to Salesforce format
     */
    public function transform_post_to_salesforce($post_id, $field_mappings = array()) {
        $post = get_post($post_id);
        if (!$post) {
            throw new Exception('Post not found');
        }
        
        $salesforce_data = array();
        $acf_fields = get_fields($post_id);
        
        // Get field mappings from options if not provided
        if (empty($field_mappings)) {
            $field_mappings = get_option('wsi_field_mappings', array());
        }
        
        // Transform basic post fields
        $salesforce_data = $this->transform_basic_post_fields($post, $field_mappings);
        
        // Transform ACF fields
        if ($acf_fields && is_array($acf_fields)) {
            $acf_data = $this->transform_acf_fields($acf_fields, $field_mappings);
            $salesforce_data = array_merge($salesforce_data, $acf_data);
        }
        
        return $salesforce_data;
    }
    
    /**
     * Transform basic WordPress post fields
     */
    private function transform_basic_post_fields($post, $field_mappings) {
        $data = array();
        
        $basic_fields = array(
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post->post_status,
            'post_date' => $post->post_date,
            'post_modified' => $post->post_modified,
            'post_type' => $post->post_type,
            'post_author' => get_the_author_meta('display_name', $post->post_author)
        );
        
        foreach ($basic_fields as $wp_field => $value) {
            $sf_field = $this->get_salesforce_field_mapping($wp_field, $field_mappings);
            if ($sf_field) {
                $data[$sf_field] = $this->transform_field_value($value, $wp_field, $field_mappings);
            }
        }
        
        return $data;
    }
    
    /**
     * Transform ACF fields to Salesforce format
     */
    private function transform_acf_fields($acf_fields, $field_mappings) {
        $data = array();
        
        foreach ($acf_fields as $acf_field_name => $acf_value) {
            $sf_field = $this->get_salesforce_field_mapping($acf_field_name, $field_mappings);
            if ($sf_field) {
                $data[$sf_field] = $this->transform_acf_field_value($acf_value, $acf_field_name, $field_mappings);
            }
        }
        
        return $data;
    }
    
    /**
     * Transform individual ACF field value based on field type
     */
    private function transform_acf_field_value($value, $field_name, $field_mappings) {
        // Get ACF field object to determine field type
        $acf_field = get_field_object($field_name);
        $field_type = $acf_field ? $acf_field['type'] : 'text';
        
        switch ($field_type) {
            case 'checkbox':
                return $this->transform_checkbox_field($value, $field_name, $field_mappings);
                
            case 'true_false':
                return $this->transform_boolean_field($value);
                
            case 'select':
            case 'radio':
                return $this->transform_select_field($value, $acf_field);
                
            case 'date_picker':
                return $this->transform_date_field($value);
                
            case 'date_time_picker':
                return $this->transform_datetime_field($value);
                
            case 'number':
                return $this->transform_number_field($value);
                
            case 'email':
                return $this->transform_email_field($value);
                
            case 'url':
                return $this->transform_url_field($value);
                
            case 'textarea':
            case 'wysiwyg':
                return $this->transform_textarea_field($value);
                
            case 'relationship':
            case 'post_object':
                return $this->transform_relationship_field($value);
                
            case 'image':
                return $this->transform_image_field($value);
                
            case 'file':
                return $this->transform_file_field($value);
                
            case 'gallery':
                return $this->transform_gallery_field($value);
                
            case 'repeater':
                return $this->transform_repeater_field($value, $field_name, $field_mappings);
                
            case 'group':
                return $this->transform_group_field($value, $field_name, $field_mappings);
                
            default:
                return $this->transform_text_field($value);
        }
    }
    
    /**
     * Transform checkbox field (array) to Salesforce-compatible format
     */
    private function transform_checkbox_field($value, $field_name, $field_mappings) {
        if (empty($value) || !is_array($value)) {
            return '';
        }
        
        // Get field mapping configuration
        $mapping_config = $this->get_field_mapping_config($field_name, $field_mappings);
        
        if (isset($mapping_config['checkbox_strategy'])) {
            switch ($mapping_config['checkbox_strategy']) {
                case 'semicolon_separated':
                    return implode(';', $value);
                    
                case 'comma_separated':
                    return implode(',', $value);
                    
                case 'pipe_separated':
                    return implode('|', $value);
                    
                case 'json':
                    return json_encode($value);
                    
                case 'first_value':
                    return isset($value[0]) ? $value[0] : '';
                    
                case 'count':
                    return count($value);
                    
                case 'boolean':
                    return !empty($value) ? true : false;
                    
                case 'custom_delimiter':
                    $delimiter = isset($mapping_config['custom_delimiter']) ? $mapping_config['custom_delimiter'] : ';';
                    return implode($delimiter, $value);
                    
                default:
                    return implode(';', $value);
            }
        }
        
        // Default to semicolon-separated
        return implode(';', $value);
    }
    
    /**
     * Transform boolean field
     */
    private function transform_boolean_field($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        // Handle string representations
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, array('1', 'true', 'yes', 'on'));
        }
        
        // Handle numeric values
        return (bool) $value;
    }
    
    /**
     * Transform select/radio field
     */
    private function transform_select_field($value, $acf_field) {
        if (empty($value)) {
            return '';
        }
        
        // Handle array values (multiple select)
        if (is_array($value)) {
            return implode(';', $value);
        }
        
        return $value;
    }
    
    /**
     * Transform date field
     */
    private function transform_date_field($value) {
        if (empty($value)) {
            return null;
        }
        
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            error_log('Date transformation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Transform datetime field
     */
    private function transform_datetime_field($value) {
        if (empty($value)) {
            return null;
        }
        
        try {
            $date = new DateTime($value);
            return $date->format('c'); // ISO 8601 format
        } catch (Exception $e) {
            error_log('DateTime transformation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Transform number field
     */
    private function transform_number_field($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        return null;
    }
    
    /**
     * Transform email field
     */
    private function transform_email_field($value) {
        return is_email($value) ? $value : '';
    }
    
    /**
     * Transform URL field
     */
    private function transform_url_field($value) {
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }
    
    /**
     * Transform textarea field
     */
    private function transform_textarea_field($value) {
        return wp_strip_all_tags($value);
    }
    
    /**
     * Transform relationship/post object field
     */
    private function transform_relationship_field($value) {
        if (empty($value)) {
            return '';
        }
        
        if (is_array($value)) {
            $titles = array();
            foreach ($value as $post) {
                if (is_object($post) && isset($post->post_title)) {
                    $titles[] = $post->post_title;
                } elseif (is_numeric($post)) {
                    $post_obj = get_post($post);
                    if ($post_obj) {
                        $titles[] = $post_obj->post_title;
                    }
                }
            }
            return implode(';', $titles);
        }
        
        return is_object($value) && isset($value->post_title) ? $value->post_title : '';
    }
    
    /**
     * Transform image field
     */
    private function transform_image_field($value) {
        if (empty($value)) {
            return '';
        }
        
        if (is_array($value) && isset($value['url'])) {
            return $value['url'];
        }
        
        if (is_numeric($value)) {
            $image_url = wp_get_attachment_url($value);
            return $image_url ? $image_url : '';
        }
        
        return '';
    }
    
    /**
     * Transform file field
     */
    private function transform_file_field($value) {
        return $this->transform_image_field($value);
    }
    
    /**
     * Transform gallery field
     */
    private function transform_gallery_field($value) {
        if (empty($value) || !is_array($value)) {
            return '';
        }
        
        $urls = array();
        foreach ($value as $image) {
            if (is_array($image) && isset($image['url'])) {
                $urls[] = $image['url'];
            } elseif (is_numeric($image)) {
                $image_url = wp_get_attachment_url($image);
                if ($image_url) {
                    $urls[] = $image_url;
                }
            }
        }
        
        return implode(';', $urls);
    }
    
    /**
     * Transform repeater field
     */
    private function transform_repeater_field($value, $field_name, $field_mappings) {
        if (empty($value) || !is_array($value)) {
            return '';
        }
        
        $repeater_data = array();
        foreach ($value as $row) {
            if (is_array($row)) {
                $row_data = array();
                foreach ($row as $sub_field => $sub_value) {
                    $row_data[$sub_field] = $this->transform_acf_field_value($sub_value, $sub_field, $field_mappings);
                }
                $repeater_data[] = $row_data;
            }
        }
        
        return json_encode($repeater_data);
    }
    
    /**
     * Transform group field
     */
    private function transform_group_field($value, $field_name, $field_mappings) {
        if (empty($value) || !is_array($value)) {
            return '';
        }
        
        $group_data = array();
        foreach ($value as $sub_field => $sub_value) {
            $group_data[$sub_field] = $this->transform_acf_field_value($sub_value, $sub_field, $field_mappings);
        }
        
        return json_encode($group_data);
    }
    
    /**
     * Transform text field
     */
    private function transform_text_field($value) {
        return sanitize_text_field($value);
    }
    
    /**
     * Get Salesforce field mapping for WordPress/ACF field
     */
    private function get_salesforce_field_mapping($wp_field, $field_mappings) {
        if (isset($field_mappings[$wp_field]) && !empty($field_mappings[$wp_field]['salesforce_field'])) {
            return $field_mappings[$wp_field]['salesforce_field'];
        }
        
        return null;
    }
    
    /**
     * Get field mapping configuration
     */
    private function get_field_mapping_config($wp_field, $field_mappings) {
        return isset($field_mappings[$wp_field]) ? $field_mappings[$wp_field] : array();
    }
    
    /**
     * Transform field value based on mapping configuration
     */
    private function transform_field_value($value, $wp_field, $field_mappings) {
        $mapping_config = $this->get_field_mapping_config($wp_field, $field_mappings);
        
        // Apply custom transformation if specified
        if (isset($mapping_config['custom_transformation']) && is_callable($mapping_config['custom_transformation'])) {
            return call_user_func($mapping_config['custom_transformation'], $value);
        }
        
        return $value;
    }
}
