<?php
/**
 * Example Usage of WordPress to Salesforce Integration Plugin
 * 
 * This file demonstrates how to use the plugin in various scenarios.
 * Copy the relevant code snippets to your theme's functions.php or a custom plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Custom field mapping for specific post type
 */
add_filter('wsi_field_mappings', 'custom_field_mappings');

function custom_field_mappings($mappings) {
    // Add custom field mapping for 'product' post type
    $mappings['product_price'] = array(
        'salesforce_field' => 'Price__c',
        'checkbox_strategy' => '' // Not a checkbox field
    );
    
    // Add checkbox field mapping with custom strategy
    $mappings['product_categories'] = array(
        'salesforce_field' => 'Categories__c',
        'checkbox_strategy' => 'comma_separated'
    );
    
    // Add boolean field mapping
    $mappings['product_featured'] = array(
        'salesforce_field' => 'Is_Featured__c',
        'checkbox_strategy' => 'boolean'
    );
    
    return $mappings;
}

/**
 * Example 2: Modify data before sending to Salesforce
 */
add_filter('wsi_salesforce_data', 'modify_salesforce_data', 10, 2);

function modify_salesforce_data($data, $post_id) {
    $post = get_post($post_id);
    
    // Add custom calculated field
    if ($post->post_type === 'product') {
        $price = get_field('product_price', $post_id);
        $discount = get_field('product_discount', $post_id);
        
        if ($price && $discount) {
            $final_price = $price * (1 - $discount / 100);
            $data['Final_Price__c'] = $final_price;
        }
    }
    
    // Add WordPress site URL
    $data['WordPress_Site__c'] = home_url();
    
    return $data;
}

/**
 * Example 3: Skip sync for certain conditions
 */
add_filter('wsi_should_sync_post', 'conditional_sync', 10, 2);

function conditional_sync($should_sync, $post_id) {
    $post = get_post($post_id);
    
    // Don't sync drafts
    if ($post->post_status === 'draft') {
        return false;
    }
    
    // Don't sync if custom field is set to skip
    $skip_sync = get_field('skip_salesforce_sync', $post_id);
    if ($skip_sync) {
        return false;
    }
    
    return $should_sync;
}

/**
 * Example 4: Custom sync trigger for frontend forms
 */
function trigger_custom_sync($post_id) {
    if (!function_exists('WSI_Post_Sync_Handler')) {
        return;
    }
    
    $sync_handler = new WSI_Post_Sync_Handler();
    
    try {
        $result = $sync_handler->manual_sync($post_id);
        return array('success' => true, 'result' => $result);
    } catch (Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Example 5: AJAX handler for frontend sync
 */
add_action('wp_ajax_sync_post_frontend', 'handle_frontend_sync');
add_action('wp_ajax_nopriv_sync_post_frontend', 'handle_frontend_sync');

function handle_frontend_sync() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_sync_nonce')) {
        wp_die('Security check failed');
    }
    
    $post_id = intval($_POST['post_id']);
    
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }
    
    $result = trigger_custom_sync($post_id);
    
    if ($result['success']) {
        wp_send_json_success($result['result']);
    } else {
        wp_send_json_error($result['error']);
    }
}

/**
 * Example 6: JavaScript for frontend sync
 */
function enqueue_frontend_sync_script() {
    if (is_singular()) {
        wp_enqueue_script('frontend-sync', get_template_directory_uri() . '/js/frontend-sync.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('frontend-sync', 'frontend_sync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('frontend_sync_nonce'),
            'post_id' => get_the_ID()
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_frontend_sync_script');

/**
 * Example 7: Custom checkbox transformation
 */
add_filter('wsi_checkbox_transformation', 'custom_checkbox_transformation', 10, 3);

function custom_checkbox_transformation($value, $field_name, $mapping_config) {
    // Custom transformation for specific field
    if ($field_name === 'special_categories') {
        // Convert array to numbered list
        if (is_array($value) && !empty($value)) {
            $numbered_list = array();
            foreach ($value as $index => $item) {
                $numbered_list[] = ($index + 1) . '. ' . $item;
            }
            return implode("\n", $numbered_list);
        }
    }
    
    return $value; // Return original value for other fields
}

/**
 * Example 8: Log custom sync events
 */
add_action('wsi_after_sync', 'log_custom_sync_event');

function log_custom_sync_event($post_id, $result) {
    $logger = new WSI_Logger();
    $logger->info('Custom sync completed', array(
        'post_id' => $post_id,
        'salesforce_id' => $result['id'] ?? null,
        'action' => 'custom_sync'
    ), $post_id, 'custom');
}

/**
 * Example 9: Handle sync errors with custom logic
 */
add_action('wsi_sync_failed', 'handle_sync_failure');

function handle_sync_failure($post_id, $error_message, $context) {
    // Send email notification for critical errors
    if (strpos($error_message, 'authentication') !== false) {
        wp_mail(
            get_option('admin_email'),
            'Salesforce Sync Authentication Error',
            'Authentication failed for post ID: ' . $post_id . "\nError: " . $error_message
        );
    }
    
    // Log to custom error tracking service
    error_log('WSI Sync Failed - Post ID: ' . $post_id . ' - Error: ' . $error_message);
}

/**
 * Example 10: Batch sync multiple posts
 */
function batch_sync_posts($post_ids) {
    $results = array();
    
    foreach ($post_ids as $post_id) {
        $result = trigger_custom_sync($post_id);
        $results[$post_id] = $result;
        
        // Add small delay to avoid overwhelming Salesforce API
        usleep(100000); // 0.1 second delay
    }
    
    return $results;
}

/**
 * Example 11: Sync specific ACF field groups
 */
add_action('acf/save_post', 'sync_on_acf_group_save', 20);

function sync_on_acf_group_save($post_id) {
    // Only sync if specific ACF group was saved
    $field_groups = get_field_objects($post_id);
    
    foreach ($field_groups as $field_name => $field_obj) {
        if ($field_obj['group'] === 'product_details') {
            // Trigger sync for this specific group
            trigger_custom_sync($post_id);
            break;
        }
    }
}

/**
 * Example 12: Custom validation before sync
 */
add_filter('wsi_validate_before_sync', 'custom_sync_validation', 10, 2);

function custom_sync_validation($is_valid, $post_id) {
    $post = get_post($post_id);
    
    // Ensure required fields are present
    $required_fields = array('product_price', 'product_description');
    
    foreach ($required_fields as $field) {
        $value = get_field($field, $post_id);
        if (empty($value)) {
            $logger = new WSI_Logger();
            $logger->warning('Required field missing', array(
                'field' => $field,
                'post_id' => $post_id
            ), $post_id, 'validation');
            
            return false;
        }
    }
    
    return $is_valid;
}
