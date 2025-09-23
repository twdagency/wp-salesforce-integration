<?php
/**
 * Manual Sync Handler
 * Provides one-time sync buttons for individual posts and users
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Manual_Sync {
    
    private $salesforce_api;
    private $data_transformer;
    private $csv_mapper;
    private $audit_trail;
    
    public function __construct() {
        $this->salesforce_api = new WSI_Salesforce_API();
        $this->data_transformer = new WSI_Data_Transformer();
        $this->csv_mapper = new WSI_CSV_Based_Salesforce_Mapper();
        $this->audit_trail = new WSI_Audit_Trail();
        
        add_action('admin_init', array($this, 'init_manual_sync'));
        add_action('wp_ajax_wsi_manual_sync_user', array($this, 'handle_manual_sync_user_ajax'));
        add_action('wp_ajax_wsi_manual_sync_post', array($this, 'handle_manual_sync_post_ajax'));
        add_action('wp_ajax_wsi_get_sync_status', array($this, 'handle_get_sync_status_ajax'));
    }
    
    /**
     * Initialize manual sync functionality
     */
    public function init_manual_sync() {
        // Add meta boxes for posts
        add_action('add_meta_boxes', array($this, 'add_post_meta_boxes'));
        
        // Add user profile fields
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_sync_notices'));
    }
    
    /**
     * Add meta boxes for posts
     */
    public function add_post_meta_boxes() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'wsi_manual_sync',
                'Salesforce Sync',
                array($this, 'post_meta_box_callback'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Post meta box callback
     */
    public function post_meta_box_callback($post) {
        $sync_status = $this->get_post_sync_status($post->ID);
        $salesforce_id = get_post_meta($post->ID, 'salesforce_id', true);
        
        ?>
        <div id="wsi-post-sync" data-post-id="<?php echo $post->ID; ?>">
            <div class="sync-status">
                <p><strong>Sync Status:</strong> 
                    <span class="status-indicator status-<?php echo esc_attr($sync_status['status']); ?>">
                        <?php echo esc_html(ucfirst($sync_status['status'])); ?>
                    </span>
                </p>
                
                <?php if ($salesforce_id): ?>
                    <p><strong>Salesforce ID:</strong> <code><?php echo esc_html($salesforce_id); ?></code></p>
                <?php endif; ?>
                
                <?php if ($sync_status['last_sync']): ?>
                    <p><strong>Last Sync:</strong> <?php echo esc_html($sync_status['last_sync']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="sync-actions">
                <button type="button" id="sync-post-to-salesforce" class="button button-primary">
                    <span class="dashicons dashicons-cloud"></span> Sync to Salesforce
                </button>
                
                <button type="button" id="check-sync-status" class="button button-secondary">
                    <span class="dashicons dashicons-admin-tools"></span> Check Status
                </button>
            </div>
            
            <div class="sync-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">Syncing...</p>
            </div>
            
            <div class="sync-results" style="display: none;">
                <div class="sync-message"></div>
            </div>
        </div>
        
        <style>
        .sync-status {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .status-indicator {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-indicator.status-synced {
            background: #d4edda;
            color: #155724;
        }
        
        .status-indicator.status-not-synced {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-indicator.status-error {
            background: #fff3cd;
            color: #856404;
        }
        
        .sync-actions {
            margin-bottom: 15px;
        }
        
        .sync-actions button {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .sync-progress {
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            margin: 0;
            text-align: center;
            font-style: italic;
        }
        
        .sync-results {
            margin-top: 15px;
        }
        
        .sync-message {
            padding: 10px;
            border-radius: 4px;
        }
        
        .sync-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .sync-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Sync post to Salesforce
            $('#sync-post-to-salesforce').on('click', function() {
                var postId = $('#wsi-post-sync').data('post-id');
                syncPostToSalesforce(postId);
            });
            
            // Check sync status
            $('#check-sync-status').on('click', function() {
                var postId = $('#wsi-post-sync').data('post-id');
                checkSyncStatus(postId);
            });
            
            function syncPostToSalesforce(postId) {
                var button = $('#sync-post-to-salesforce');
                var progress = $('.sync-progress');
                var results = $('.sync-results');
                
                button.prop('disabled', true);
                progress.show();
                results.hide();
                
                updateProgress(0, 'Starting sync...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_manual_sync_post',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('wsi_manual_sync_nonce'); ?>'
                    },
                    success: function(response) {
                        updateProgress(100, 'Sync completed');
                        
                        if (response.success) {
                            showResult('Post synced successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showResult('Sync failed: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        updateProgress(0, 'Sync failed');
                        showResult('Sync failed due to an error', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        setTimeout(function() {
                            progress.hide();
                        }, 2000);
                    }
                });
            }
            
            function checkSyncStatus(postId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_sync_status',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('wsi_manual_sync_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showResult('Status: ' + response.data.status, 'success');
                        } else {
                            showResult('Failed to check status', 'error');
                        }
                    }
                });
            }
            
            function updateProgress(percent, text) {
                $('.progress-fill').css('width', percent + '%');
                $('.progress-text').text(text);
            }
            
            function showResult(message, type) {
                $('.sync-message').removeClass('success error').addClass(type).text(message);
                $('.sync-results').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add user profile fields
     */
    public function add_user_profile_fields($user) {
        $sync_status = $this->get_user_sync_status($user->ID);
        $salesforce_lead_id = get_field('salesforce_lead_id', 'user_' . $user->ID);
        $salesforce_contact_id = get_field('salesforce_contact_id', 'user_' . $user->ID);
        $salesforce_account_id = get_field('salesforce_account_id', 'user_' . $user->ID);
        
        ?>
        <h3>Salesforce Sync</h3>
        <table class="form-table">
            <tr>
                <th><label>Sync Status</label></th>
                <td>
                    <span class="status-indicator status-<?php echo esc_attr($sync_status['status']); ?>">
                        <?php echo esc_html(ucfirst($sync_status['status'])); ?>
                    </span>
                    <?php if ($sync_status['last_sync']): ?>
                        <br><small>Last sync: <?php echo esc_html($sync_status['last_sync']); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            
            <?php if ($salesforce_lead_id): ?>
            <tr>
                <th><label>Salesforce Lead ID</label></th>
                <td><code><?php echo esc_html($salesforce_lead_id); ?></code></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salesforce_contact_id): ?>
            <tr>
                <th><label>Salesforce Contact ID</label></th>
                <td><code><?php echo esc_html($salesforce_contact_id); ?></code></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salesforce_account_id): ?>
            <tr>
                <th><label>Salesforce Account ID</label></th>
                <td><code><?php echo esc_html($salesforce_account_id); ?></code></td>
            </tr>
            <?php endif; ?>
            
            <tr>
                <th><label>Sync Actions</label></th>
                <td>
                    <div id="wsi-user-sync" data-user-id="<?php echo $user->ID; ?>">
                        <button type="button" id="sync-user-to-salesforce" class="button button-primary">
                            <span class="dashicons dashicons-cloud"></span> Sync to Salesforce
                        </button>
                        
                        <button type="button" id="check-user-sync-status" class="button button-secondary">
                            <span class="dashicons dashicons-admin-tools"></span> Check Status
                        </button>
                        
                        <div class="sync-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p class="progress-text">Syncing...</p>
                        </div>
                        
                        <div class="sync-results" style="display: none;">
                            <div class="sync-message"></div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        <style>
        .status-indicator {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-indicator.status-synced {
            background: #d4edda;
            color: #155724;
        }
        
        .status-indicator.status-not-synced {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-indicator.status-error {
            background: #fff3cd;
            color: #856404;
        }
        
        .sync-progress {
            margin: 10px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            margin: 0;
            text-align: center;
            font-style: italic;
        }
        
        .sync-results {
            margin-top: 10px;
        }
        
        .sync-message {
            padding: 10px;
            border-radius: 4px;
        }
        
        .sync-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .sync-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Sync user to Salesforce
            $('#sync-user-to-salesforce').on('click', function() {
                var userId = $('#wsi-user-sync').data('user-id');
                syncUserToSalesforce(userId);
            });
            
            // Check sync status
            $('#check-user-sync-status').on('click', function() {
                var userId = $('#wsi-user-sync').data('user-id');
                checkUserSyncStatus(userId);
            });
            
            function syncUserToSalesforce(userId) {
                var button = $('#sync-user-to-salesforce');
                var progress = $('.sync-progress');
                var results = $('.sync-results');
                
                button.prop('disabled', true);
                progress.show();
                results.hide();
                
                updateProgress(0, 'Starting sync...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_manual_sync_user',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('wsi_manual_sync_nonce'); ?>'
                    },
                    success: function(response) {
                        updateProgress(100, 'Sync completed');
                        
                        if (response.success) {
                            showResult('User synced successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showResult('Sync failed: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        updateProgress(0, 'Sync failed');
                        showResult('Sync failed due to an error', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        setTimeout(function() {
                            progress.hide();
                        }, 2000);
                    }
                });
            }
            
            function checkUserSyncStatus(userId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_sync_status',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('wsi_manual_sync_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showResult('Status: ' + response.data.status, 'success');
                        } else {
                            showResult('Failed to check status', 'error');
                        }
                    }
                });
            }
            
            function updateProgress(percent, text) {
                $('.progress-fill').css('width', percent + '%');
                $('.progress-text').text(text);
            }
            
            function showResult(message, type) {
                $('.sync-message').removeClass('success error').addClass(type).text(message);
                $('.sync-results').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get post sync status
     */
    private function get_post_sync_status($post_id) {
        $salesforce_id = get_post_meta($post_id, 'salesforce_id', true);
        $last_sync = get_post_meta($post_id, 'salesforce_last_sync', true);
        $sync_error = get_post_meta($post_id, 'salesforce_sync_error', true);
        
        if ($sync_error) {
            return array(
                'status' => 'error',
                'last_sync' => $last_sync,
                'error' => $sync_error
            );
        } elseif ($salesforce_id) {
            return array(
                'status' => 'synced',
                'last_sync' => $last_sync,
                'salesforce_id' => $salesforce_id
            );
        } else {
            return array(
                'status' => 'not-synced',
                'last_sync' => null
            );
        }
    }
    
    /**
     * Get user sync status
     */
    private function get_user_sync_status($user_id) {
        $sync_status = get_field('salesforce_sync_status', 'user_' . $user_id);
        $last_sync = get_field('salesforce_last_sync', 'user_' . $user_id);
        $sync_errors = get_field('salesforce_sync_errors', 'user_' . $user_id);
        
        if ($sync_errors) {
            return array(
                'status' => 'error',
                'last_sync' => $last_sync,
                'error' => $sync_errors
            );
        } elseif ($sync_status && $sync_status !== 'not_synced') {
            return array(
                'status' => 'synced',
                'last_sync' => $last_sync,
                'sync_status' => $sync_status
            );
        } else {
            return array(
                'status' => 'not-synced',
                'last_sync' => null
            );
        }
    }
    
    /**
     * Show sync notices
     */
    public function show_sync_notices() {
        if (isset($_GET['wsi_sync_success'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>Item synced to Salesforce successfully!</p>
            </div>
            <?php
        }
        
        if (isset($_GET['wsi_sync_error'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>Sync to Salesforce failed: <?php echo esc_html($_GET['wsi_sync_error']); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Handle manual sync user AJAX request
     */
    public function handle_manual_sync_user_ajax() {
        check_ajax_referer('wsi_manual_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        try {
            $result = $this->sync_user_to_salesforce($user_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle manual sync post AJAX request
     */
    public function handle_manual_sync_post_ajax() {
        check_ajax_referer('wsi_manual_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        try {
            $result = $this->sync_post_to_salesforce($post_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle get sync status AJAX request
     */
    public function handle_get_sync_status_ajax() {
        check_ajax_referer('wsi_manual_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            $status = $this->get_user_sync_status($user_id);
        } elseif (isset($_POST['post_id'])) {
            $post_id = intval($_POST['post_id']);
            $status = $this->get_post_sync_status($post_id);
        } else {
            wp_send_json_error('Invalid request');
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * Sync user to Salesforce
     */
    public function sync_user_to_salesforce($user_id) {
        $user = get_user_by('id', $user_id);
        $start_time = microtime(true);
        
        try {
            // Check if user already has Salesforce IDs
            $lead_id = get_field('salesforce_lead_id', 'user_' . $user_id);
            $contact_id = get_field('salesforce_contact_id', 'user_' . $user_id);
            $account_id = get_field('salesforce_account_id', 'user_' . $user_id);
            
            if ($lead_id || $contact_id || $account_id) {
                // User already synced, update existing records
                $result = $this->update_existing_user_records($user_id);
            } else {
                // New user, create Lead
                $result = $this->create_user_lead($user_id);
            }
            
            $execution_time = microtime(true) - $start_time;
            
            // Log the operation
            $this->audit_trail->log_operation(
                'manual_sync',
                'User',
                $result['salesforce_id'] ?? '',
                $user_id,
                'user',
                'success',
                'User manually synced to Salesforce',
                array('user_id' => $user_id),
                $result,
                null,
                $execution_time
            );
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            // Log the error
            $this->audit_trail->log_operation(
                'manual_sync',
                'User',
                '',
                $user_id,
                'user',
                'error',
                'Manual sync failed: ' . $e->getMessage(),
                array('user_id' => $user_id),
                null,
                array('error' => $e->getMessage()),
                $execution_time
            );
            
            // Update user meta with error
            update_field('salesforce_sync_errors', $e->getMessage(), 'user_' . $user_id);
            update_field('salesforce_sync_status', 'sync_error', 'user_' . $user_id);
            
            throw $e;
        }
    }
    
    /**
     * Sync post to Salesforce
     */
    public function sync_post_to_salesforce($post_id) {
        $post = get_post($post_id);
        $start_time = microtime(true);
        
        try {
            // Determine Salesforce object type based on post type
            $object_type = $this->get_salesforce_object_for_post_type($post->post_type);
            
            if (!$object_type) {
                throw new Exception('No Salesforce object mapping for post type: ' . $post->post_type);
            }
            
            // Get field mappings
            $field_mappings = $this->csv_mapper->get_field_mappings($object_type);
            
            if (empty($field_mappings)) {
                throw new Exception('No field mappings found for object: ' . $object_type);
            }
            
            // Transform post data
            $salesforce_data = $this->data_transformer->transform_post_data($post, $field_mappings);
            
            // Add WordPress integration fields
            $salesforce_data['WP_Post_ID__c'] = $post_id;
            $salesforce_data['WP_Author_ID__c'] = $post->post_author;
            $salesforce_data['WP_Published_Datetime__c'] = $post->post_date;
            $salesforce_data['WP_Modified_Datetime__c'] = $post->post_modified;
            $salesforce_data['WP_Permalink__c'] = get_permalink($post_id);
            $salesforce_data['WP_Slug__c'] = $post->post_name;
            
            // Check if post already has Salesforce ID
            $existing_id = get_post_meta($post_id, 'salesforce_id', true);
            
            if ($existing_id) {
                // Update existing record
                $result = $this->salesforce_api->update_record($object_type, $existing_id, $salesforce_data);
                $operation = 'update';
            } else {
                // Create new record
                $result = $this->salesforce_api->create_record($object_type, $salesforce_data);
                $operation = 'create';
            }
            
            $salesforce_id = $result['id'] ?? '';
            
            // Update post meta
            update_post_meta($post_id, 'salesforce_id', $salesforce_id);
            update_post_meta($post_id, 'salesforce_last_sync', current_time('mysql'));
            delete_post_meta($post_id, 'salesforce_sync_error');
            
            $execution_time = microtime(true) - $start_time;
            
            // Log the operation
            $this->audit_trail->log_operation(
                'manual_sync',
                $object_type,
                $salesforce_id,
                $post_id,
                'post',
                'success',
                'Post manually synced to Salesforce',
                $salesforce_data,
                $result,
                null,
                $execution_time
            );
            
            return array(
                'salesforce_id' => $salesforce_id,
                'operation' => $operation,
                'object_type' => $object_type
            );
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            // Log the error
            $this->audit_trail->log_operation(
                'manual_sync',
                $object_type ?? 'Unknown',
                '',
                $post_id,
                'post',
                'error',
                'Manual sync failed: ' . $e->getMessage(),
                array('post_id' => $post_id),
                null,
                array('error' => $e->getMessage()),
                $execution_time
            );
            
            // Update post meta with error
            update_post_meta($post_id, 'salesforce_sync_error', $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Get Salesforce object type for post type
     */
    private function get_salesforce_object_for_post_type($post_type) {
        $mappings = array(
            'post' => 'Sales_Listing',
            'waste_listing' => 'Sales_Listing',
            'wanted_listing' => 'Wanted_Listings',
            'offer' => 'Offers',
            'haulage_offer' => 'Haulage_Offers',
            'haulage_load' => 'Haulage_Loads',
            'sample_request' => 'Sample_Requests',
            'mfi_test' => 'MFI_Tests'
        );
        
        return isset($mappings[$post_type]) ? $mappings[$post_type] : null;
    }
    
    /**
     * Create user Lead
     */
    private function create_user_lead($user_id) {
        $user = get_user_by('id', $user_id);
        $field_mappings = $this->csv_mapper->get_field_mappings('Lead');
        
        if (empty($field_mappings)) {
            throw new Exception('No field mappings found for Lead object');
        }
        
        // Transform user data
        $salesforce_data = $this->data_transformer->transform_user_data($user, $field_mappings);
        
        // Add WordPress integration fields
        $salesforce_data['WordPress_User_ID__c'] = $user_id;
        
        // Create Lead
        $result = $this->salesforce_api->create_record('Lead', $salesforce_data);
        $lead_id = $result['id'];
        
        // Update user meta
        update_field('salesforce_lead_id', $lead_id, 'user_' . $user_id);
        update_field('salesforce_sync_status', 'lead_created', 'user_' . $user_id);
        update_field('salesforce_last_sync', current_time('mysql'), 'user_' . $user_id);
        
        return array(
            'salesforce_id' => $lead_id,
            'object_type' => 'Lead',
            'operation' => 'create'
        );
    }
    
    /**
     * Update existing user records
     */
    private function update_existing_user_records($user_id) {
        $user = get_user_by('id', $user_id);
        $lead_id = get_field('salesforce_lead_id', 'user_' . $user_id);
        $contact_id = get_field('salesforce_contact_id', 'user_' . $user_id);
        $account_id = get_field('salesforce_account_id', 'user_' . $user_id);
        
        $results = array();
        
        // Update Lead if exists
        if ($lead_id) {
            $field_mappings = $this->csv_mapper->get_field_mappings('Lead');
            $salesforce_data = $this->data_transformer->transform_user_data($user, $field_mappings);
            $salesforce_data['WordPress_User_ID__c'] = $user_id;
            
            $result = $this->salesforce_api->update_record('Lead', $lead_id, $salesforce_data);
            $results['lead_updated'] = true;
        }
        
        // Update Contact if exists
        if ($contact_id) {
            $field_mappings = $this->csv_mapper->get_field_mappings('Contact');
            $salesforce_data = $this->data_transformer->transform_user_data($user, $field_mappings);
            $salesforce_data['WordPress_User_ID__c'] = $user_id;
            
            $result = $this->salesforce_api->update_record('Contact', $contact_id, $salesforce_data);
            $results['contact_updated'] = true;
        }
        
        // Update Account if exists
        if ($account_id) {
            $field_mappings = $this->csv_mapper->get_field_mappings('Account');
            $salesforce_data = $this->data_transformer->transform_user_data($user, $field_mappings);
            $salesforce_data['WordPress_User_ID__c'] = $user_id;
            
            $result = $this->salesforce_api->update_record('Account', $account_id, $salesforce_data);
            $results['account_updated'] = true;
        }
        
        // Update sync status
        update_field('salesforce_last_sync', current_time('mysql'), 'user_' . $user_id);
        
        return array(
            'operation' => 'update',
            'updated_records' => $results
        );
    }
}
