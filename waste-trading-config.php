<?php
/**
 * Custom Configuration for Waste Trading Platform
 * WordPress to Salesforce Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom field mappings for waste trading platform
 */
function waste_trading_field_mappings($mappings) {
    
    // Basic post fields
    $mappings['post_title'] = array(
        'salesforce_field' => 'Title__c'
    );
    
    $mappings['post_content'] = array(
        'salesforce_field' => 'Description__c'
    );
    
    // Location fields
    $mappings['location_of_waste'] = array(
        'salesforce_field' => 'Location_of_Waste__c'
    );
    
    $mappings['country_of_waste'] = array(
        'salesforce_field' => 'Country__c'
    );
    
    $mappings['seller_warehouse_address'] = array(
        'salesforce_field' => 'Warehouse_Address__c'
    );
    
    // Material information
    $mappings['material_type'] = array(
        'salesforce_field' => 'Material_Type__c'
    );
    
    $mappings['polymer_group'] = array(
        'salesforce_field' => 'Polymer_Group__c'
    );
    
    $mappings['material_copy'] = array(
        'salesforce_field' => 'Material_Description__c'
    );
    
    $mappings['material'] = array(
        'salesforce_field' => 'Material__c'
    );
    
    $mappings['material_specifications'] = array(
        'salesforce_field' => 'Material_Specifications__c'
    );
    
    // Quantity and metrics
    $mappings['quantity'] = array(
        'salesforce_field' => 'Quantity__c'
    );
    
    $mappings['quantity_metric'] = array(
        'salesforce_field' => 'Quantity_Metric__c'
    );
    
    $mappings['guide_price'] = array(
        'salesforce_field' => 'Guide_Price__c'
    );
    
    // Packaging and storage
    $mappings['how_its_packaged'] = array(
        'salesforce_field' => 'Packaging_Type__c'
    );
    
    $mappings['how_its_stored'] = array(
        'salesforce_field' => 'Storage_Type__c'
    );
    
    // Logistics
    $mappings['number_of_loads'] = array(
        'salesforce_field' => 'Number_of_Loads__c'
    );
    
    $mappings['average_weight_per_load'] = array(
        'salesforce_field' => 'Average_Weight_Per_Load__c'
    );
    
    $mappings['seller_loads_remaining'] = array(
        'salesforce_field' => 'Loads_Remaining__c'
    );
    
    $mappings['regular_load'] = array(
        'salesforce_field' => 'Regular_Load__c'
    );
    
    $mappings['frequency'] = array(
        'salesforce_field' => 'Frequency__c'
    );
    
    // Dates - transform to Salesforce date format
    $mappings['end_date'] = array(
        'salesforce_field' => 'End_Date__c'
    );
    
    $mappings['available_from'] = array(
        'salesforce_field' => 'Available_From__c'
    );
    
    // Seller information
    $mappings['seller_id'] = array(
        'salesforce_field' => 'Seller_ID__c'
    );
    
    $mappings['seller__id'] = array(
        'salesforce_field' => 'Seller_Internal_ID__c'
    );
    
    $mappings['seller_warehouse_id'] = array(
        'salesforce_field' => 'Warehouse_ID__c'
    );
    
    // Status fields
    $mappings['listing_status'] = array(
        'salesforce_field' => 'Listing_Status__c'
    );
    
    $mappings['status'] = array(
        'salesforce_field' => 'Post_Status__c'
    );
    
    // Boolean fields
    $mappings['listing_sold'] = array(
        'salesforce_field' => 'Is_Sold__c'
    );
    
    $mappings['listing_pern'] = array(
        'salesforce_field' => 'Is_PERN__c'
    );
    
    $mappings['manage_approved_export'] = array(
        'salesforce_field' => 'Approved_Export__c'
    );
    
    $mappings['approved_listing'] = array(
        'salesforce_field' => 'Is_Approved__c'
    );
    
    // Notes and admin
    $mappings['post_notes'] = array(
        'salesforce_field' => 'Post_Notes__c'
    );
    
    $mappings['notes'] = array(
        'salesforce_field' => 'Admin_Notes__c'
    );
    
    $mappings['wt_admin'] = array(
        'salesforce_field' => 'Admin_User__c'
    );
    
    $mappings['listing_rejection_reason'] = array(
        'salesforce_field' => 'Rejection_Reason__c'
    );
    
    return $mappings;
}
add_filter('wsi_field_mappings', 'waste_trading_field_mappings');

/**
 * Custom data transformation for waste trading platform
 */
function waste_trading_data_transformation($data, $post_id) {
    
    // Transform date fields from YYYYMMDD to YYYY-MM-DD
    if (isset($data['End_Date__c']) && !empty($data['End_Date__c'])) {
        $end_date = $data['End_Date__c'];
        if (strlen($end_date) === 8 && is_numeric($end_date)) {
            $data['End_Date__c'] = substr($end_date, 0, 4) . '-' . substr($end_date, 4, 2) . '-' . substr($end_date, 6, 2);
        }
    }
    
    if (isset($data['Available_From__c']) && !empty($data['Available_From__c'])) {
        $available_from = $data['Available_From__c'];
        if (strlen($available_from) === 8 && is_numeric($available_from)) {
            $data['Available_From__c'] = substr($available_from, 0, 4) . '-' . substr($available_from, 4, 2) . '-' . substr($available_from, 6, 2);
        }
    }
    
    // Transform boolean fields (1/0 to true/false)
    $boolean_fields = array(
        'Is_Sold__c',
        'Is_PERN__c', 
        'Approved_Export__c',
        'Is_Approved__c',
        'Regular_Load__c'
    );
    
    foreach ($boolean_fields as $field) {
        if (isset($data[$field])) {
            $data[$field] = ($data[$field] === '1' || $data[$field] === 1 || $data[$field] === true) ? true : false;
        }
    }
    
    // Transform numeric fields
    $numeric_fields = array(
        'Quantity__c',
        'Guide_Price__c',
        'Number_of_Loads__c',
        'Average_Weight_Per_Load__c',
        'Loads_Remaining__c'
    );
    
    foreach ($numeric_fields as $field) {
        if (isset($data[$field]) && is_numeric($data[$field])) {
            $data[$field] = (float) $data[$field];
        }
    }
    
    // Handle media field - transform serialized array to comma-separated IDs
    $media_field = get_field('media', $post_id);
    if (!empty($media_field)) {
        if (is_string($media_field)) {
            // Unserialize the array
            $media_ids = maybe_unserialize($media_field);
            if (is_array($media_ids)) {
                $data['Media_Attachment_IDs__c'] = implode(',', array_values($media_ids));
            }
        } elseif (is_array($media_field)) {
            $data['Media_Attachment_IDs__c'] = implode(',', array_values($media_field));
        }
    }
    
    // Add calculated fields
    if (isset($data['Quantity__c']) && isset($data['Average_Weight_Per_Load__c'])) {
        $data['Total_Weight__c'] = $data['Quantity__c'] * $data['Average_Weight_Per_Load__c'];
    }
    
    // Add platform-specific metadata
    $data['Platform__c'] = 'Waste Trading Platform';
    $data['Data_Source__c'] = 'WordPress';
    
    return $data;
}
add_filter('wsi_salesforce_data', 'waste_trading_data_transformation', 10, 2);

/**
 * Custom sync configuration for waste trading posts
 */
function waste_trading_sync_config($configs) {
    $configs['your_post_type'] = array( // Replace 'your_post_type' with your actual post type
        'salesforce_object' => 'Waste_Listing__c', // Replace with your Salesforce object name
        'external_id_field' => 'WordPress_Post_ID__c'
    );
    
    return $configs;
}
add_filter('wsi_sync_configs', 'waste_trading_sync_config');

/**
 * Custom validation for waste trading posts
 */
function waste_trading_validation($is_valid, $post_id) {
    $post = get_post($post_id);
    
    // Only sync if post is published and approved
    if ($post->post_status !== 'publish') {
        return false;
    }
    
    $approved_listing = get_field('approved_listing', $post_id);
    if (!$approved_listing) {
        return false;
    }
    
    // Ensure required fields are present
    $required_fields = array(
        'material_type',
        'quantity',
        'country_of_waste',
        'seller_id'
    );
    
    foreach ($required_fields as $field) {
        $value = get_field($field, $post_id);
        if (empty($value)) {
            $logger = new WSI_Logger();
            $logger->warning('Required field missing for waste listing', array(
                'field' => $field,
                'post_id' => $post_id
            ), $post_id, 'validation');
            
            return false;
        }
    }
    
    return $is_valid;
}
add_filter('wsi_validate_before_sync', 'waste_trading_validation', 10, 2);

/**
 * Custom sync triggers for waste trading platform
 * Fixed to prevent data overwrites during post updates
 */
function waste_trading_custom_sync_triggers() {
    // Only sync on specific status changes, not on every field update
    add_action('acf/update_value/name=listing_status', function($value, $post_id, $field) {
        // Only trigger sync if status is changing TO 'Approved'
        $old_value = get_field('listing_status', $post_id, false); // Get raw value without formatting
        if ($value === 'Approved' && $old_value !== 'Approved') {
            // Schedule sync after all ACF saves are complete
            wp_schedule_single_event(time() + 5, 'waste_trading_delayed_sync', array($post_id, 'status_approved'));
        }
    }, 10, 3);
    
    add_action('acf/update_value/name=listing_sold', function($value, $post_id, $field) {
        // Only trigger sync if listing is being marked as sold
        $old_value = get_field('listing_sold', $post_id, false);
        if ($value === '1' && $old_value !== '1') {
            wp_schedule_single_event(time() + 5, 'waste_trading_delayed_sync', array($post_id, 'listing_sold'));
        }
    }, 10, 3);
    
    // Don't auto-sync on quantity changes to prevent overwrites
    // Quantity changes should be manual or handled differently
    
    // Handle the delayed sync
    add_action('waste_trading_delayed_sync', function($post_id, $reason) {
        // Double-check the post still exists and is in the right state
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Only sync if the listing is still approved
        $approved_listing = get_field('approved_listing', $post_id);
        if (!$approved_listing) {
            return;
        }
        
        // Perform the sync
        $sync_handler = new WSI_Post_Sync_Handler();
        $sync_handler->handle_acf_save($post_id);
        
        // Log the sync reason
        $logger = new WSI_Logger();
        $logger->info('Delayed sync triggered', array(
            'post_id' => $post_id,
            'reason' => $reason
        ), $post_id, 'sync');
    }, 10, 2);
}
add_action('init', 'waste_trading_custom_sync_triggers');

/**
 * Custom admin settings for waste trading platform
 */
function waste_trading_admin_settings() {
    ?>
    <div class="waste-trading-admin-section">
        <h3>Waste Trading Platform Settings</h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">Auto-sync on Status Change</th>
                <td>
                    <input type="checkbox" name="wsi_auto_sync_status_change" value="1" 
                           <?php checked(get_option('wsi_auto_sync_status_change'), 1); ?> />
                    <p class="description">Automatically sync when listing status changes to "Approved" (prevents overwrites)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Auto-sync on Sale</th>
                <td>
                    <input type="checkbox" name="wsi_auto_sync_sale" value="1" 
                           <?php checked(get_option('wsi_auto_sync_sale'), 1); ?> />
                    <p class="description">Automatically sync when listing is marked as sold</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Manual Quantity Sync</th>
                <td>
                    <p class="description">Quantity changes require manual sync to prevent data overwrites. Use the "Sync to Salesforce" button in the post editor.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Required Fields</th>
                <td>
                    <p class="description">The following fields are required for sync:</p>
                    <ul>
                        <li>Material Type</li>
                        <li>Quantity</li>
                        <li>Country of Waste</li>
                        <li>Seller ID</li>
                        <li>Approved Listing (must be 1)</li>
                    </ul>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Sync Behavior</th>
                <td>
                    <p class="description"><strong>Fixed:</strong> Quantity and approval status will no longer be overwritten during post updates. Only specific status changes trigger automatic sync.</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
add_action('wsi_admin_settings_after_connection', 'waste_trading_admin_settings');
