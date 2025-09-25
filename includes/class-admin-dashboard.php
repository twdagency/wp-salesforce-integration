<?php
/**
 * Admin Dashboard
 * Main dashboard for WordPress Salesforce Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Admin_Dashboard {
    
    private $audit_trail;
    private $miniorange_handler;
    private $acf_setup;
    
    public function __construct() {
        $this->audit_trail = new WSI_Audit_Trail();
        // Field mapping manager is instantiated in main plugin file
        $this->miniorange_handler = new WSI_MiniOrange_Migration_Handler();
        $this->acf_setup = new WSI_ACF_Field_Setup();
        
        add_action('admin_menu', array($this, 'add_dashboard_menu'));
        add_action('wp_ajax_wsi_get_dashboard_stats', array($this, 'handle_dashboard_stats_ajax'));
        add_action('wp_ajax_wsi_sync_now', array($this, 'handle_sync_now_ajax'));
        add_action('wp_ajax_wsi_bulk_sync_users', array($this, 'handle_bulk_sync_users_ajax'));
        add_action('wp_ajax_wsi_bulk_sync_posts', array($this, 'handle_bulk_sync_posts_ajax'));
    }
    
    /**
     * Add dashboard menu to admin
     */
    public function add_dashboard_menu() {
        add_menu_page(
            'Salesforce Integration',
            'Salesforce',
            'manage_options',
            'wsi-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-cloud',
            30
        );
        
        // Rename the first submenu item
        add_submenu_page(
            'wsi-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'wsi-dashboard',
            array($this, 'dashboard_page')
        );
    }
    
    /**
     * Dashboard admin page
     */
    public function dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        $recent_logs = $this->get_recent_logs(10);
        $migration_status = $this->miniorange_handler->get_migration_status();
        
        ?>
        <div class="wrap">
            <h1>WordPress Salesforce Integration Dashboard</h1>
            
            <!-- Status Overview -->
            <div class="dashboard-status">
                <div class="status-card">
                    <div class="status-icon <?php echo $stats['connection_status'] ? 'success' : 'error'; ?>">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <div class="status-content">
                        <h3>Connection Status</h3>
                        <p><?php echo $stats['connection_status'] ? 'Connected' : 'Disconnected'; ?></p>
                        <small>Last checked: <?php echo $stats['last_connection_check']; ?></small>
                    </div>
                </div>
                
                <div class="status-card">
                    <div class="status-icon <?php echo $migration_status['migration_completed'] ? 'success' : ($migration_status['migration_needed'] ? 'warning' : 'info'); ?>">
                        <span class="dashicons dashicons-migrate"></span>
                    </div>
                    <div class="status-content">
                        <h3>Migration Status</h3>
                        <p><?php echo $migration_status['migration_completed'] ? 'Completed' : ($migration_status['migration_needed'] ? 'Needed' : 'Not Required'); ?></p>
                        <?php if ($migration_status['migration_needed']): ?>
                            <small><a href="<?php echo admin_url('admin.php?page=wsi-migration'); ?>">Run Migration</a></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="status-card">
                    <div class="status-icon info">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="status-content">
                        <h3>Field Mappings</h3>
                        <p><?php echo $stats['total_mappings']; ?> Mappings</p>
                        <small><a href="<?php echo admin_url('admin.php?page=wsi-field-mappings'); ?>">Manage Mappings</a></small>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Grid -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Sync Operations</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_operations']); ?></div>
                    <div class="stat-details">
                        <span class="success"><?php echo number_format($stats['successful_operations']); ?> Success</span>
                        <span class="error"><?php echo number_format($stats['failed_operations']); ?> Failed</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Success Rate</h3>
                    <div class="stat-number"><?php echo $stats['success_rate']; ?>%</div>
                    <div class="stat-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['success_rate']; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Today's Operations</h3>
                    <div class="stat-number"><?php echo number_format($stats['today_operations']); ?></div>
                    <div class="stat-details">
                        <small>Last 24 hours</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Average Response Time</h3>
                    <div class="stat-number"><?php echo $stats['avg_response_time']; ?>s</div>
                    <div class="stat-details">
                        <small>Last 100 operations</small>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="dashboard-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <button id="sync-now" class="button button-primary button-large">
                        <span class="dashicons dashicons-update"></span> Sync Now
                    </button>
                    <button id="bulk-sync-users" class="button button-secondary button-large">
                        <span class="dashicons dashicons-groups"></span> Bulk Sync Users
                    </button>
                    <button id="bulk-sync-posts" class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-post"></span> Bulk Sync Posts
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=wsi-field-mappings'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-settings"></span> Manage Field Mappings
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wsi-audit-trail'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-list-view"></span> View Audit Trail
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wsi-settings'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-generic"></span> Settings
                    </a>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="dashboard-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <?php if (empty($recent_logs)): ?>
                        <div class="no-activity">
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="activity-item">
                                <div class="activity-icon status-<?php echo esc_attr($log->status); ?>">
                                    <span class="dashicons dashicons-<?php echo $this->get_operation_icon($log->operation_type); ?>"></span>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $log->operation_type))); ?> 
                                        <?php echo esc_html($log->object_type); ?>
                                        <?php if ($log->object_id): ?>
                                            (<?php echo esc_html($log->object_id); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-message">
                                        <?php echo esc_html($log->message); ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo esc_html(human_time_diff(strtotime($log->timestamp), current_time('timestamp'))); ?> ago
                                        <?php if ($log->execution_time): ?>
                                            • <?php echo number_format($log->execution_time, 3); ?>s
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="activity-footer">
                    <a href="<?php echo admin_url('admin.php?page=wsi-audit-trail'); ?>" class="button">View All Activity</a>
                </div>
            </div>
            
            <!-- System Health -->
            <div class="dashboard-health">
                <h2>System Health</h2>
                <div class="health-grid">
                    <div class="health-item">
                        <h4>WordPress Integration</h4>
                        <div class="health-status <?php echo $stats['wp_integration_health'] ? 'healthy' : 'unhealthy'; ?>">
                            <?php echo $stats['wp_integration_health'] ? 'Healthy' : 'Issues Detected'; ?>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <h4>Salesforce API</h4>
                        <div class="health-status <?php echo $stats['api_health'] ? 'healthy' : 'unhealthy'; ?>">
                            <?php echo $stats['api_health'] ? 'Healthy' : 'Issues Detected'; ?>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <h4>Field Mappings</h4>
                        <div class="health-status <?php echo $stats['mappings_health'] ? 'healthy' : 'unhealthy'; ?>">
                            <?php echo $stats['mappings_health'] ? 'Healthy' : 'Issues Detected'; ?>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <h4>Database</h4>
                        <div class="health-status <?php echo $stats['database_health'] ? 'healthy' : 'unhealthy'; ?>">
                            <?php echo $stats['database_health'] ? 'Healthy' : 'Issues Detected'; ?>
                        </div>
                    </div>
                    
                    <div class="health-item">
                        <h4>ACF Fields</h4>
                        <div class="health-status <?php echo $stats['acf_health'] ? 'healthy' : 'unhealthy'; ?>">
                            <?php echo $stats['acf_health'] ? 'Healthy' : 'Issues Detected'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .dashboard-status {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .status-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .status-icon.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-icon.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-icon.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-content h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .status-content p {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .status-content small {
            color: #666;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 10px;
        }
        
        .stat-details {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        
        .stat-details .success {
            color: #46b450;
        }
        
        .stat-details .error {
            color: #dc3232;
        }
        
        .stat-progress {
            margin-top: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1f1f1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        
        .dashboard-actions {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .action-buttons .button {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .dashboard-activity {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .activity-icon.status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-icon.status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .activity-icon.status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .activity-message {
            color: #666;
            margin-bottom: 5px;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #999;
        }
        
        .no-activity {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .activity-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f1f1;
        }
        
        .dashboard-health {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .health-item {
            text-align: center;
        }
        
        .health-item h4 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .health-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .health-status.healthy {
            background: #d4edda;
            color: #155724;
        }
        
        .health-status.unhealthy {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Sync now button
            $('#sync-now').on('click', function() {
                var button = $(this);
                var originalText = button.html();
                
                button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_sync_now',
                        nonce: '<?php echo wp_create_nonce('wsi_dashboard_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Sync completed successfully!');
                            location.reload();
                        } else {
                            alert('Sync failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Sync failed due to an error');
                    },
                    complete: function() {
                        button.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Bulk sync users button
            $('#bulk-sync-users').on('click', function() {
                if (confirm('This will sync all users to Salesforce. This may take a while. Continue?')) {
                    bulkSyncUsers();
                }
            });
            
            // Bulk sync posts button
            $('#bulk-sync-posts').on('click', function() {
                if (confirm('This will sync all posts to Salesforce. This may take a while. Continue?')) {
                    bulkSyncPosts();
                }
            });
            
            function bulkSyncUsers() {
                var button = $('#bulk-sync-users');
                var originalText = button.html();
                
                button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Syncing Users...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_bulk_sync_users',
                        nonce: '<?php echo wp_create_nonce('wsi_dashboard_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Bulk user sync completed! ' + response.data.message);
                            location.reload();
                        } else {
                            alert('Bulk user sync failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Bulk user sync failed due to an error');
                    },
                    complete: function() {
                        button.prop('disabled', false).html(originalText);
                    }
                });
            }
            
            function bulkSyncPosts() {
                var button = $('#bulk-sync-posts');
                var originalText = button.html();
                
                button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Syncing Posts...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_bulk_sync_posts',
                        nonce: '<?php echo wp_create_nonce('wsi_dashboard_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Bulk post sync completed! ' + response.data.message);
                            location.reload();
                        } else {
                            alert('Bulk post sync failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Bulk post sync failed due to an error');
                    },
                    complete: function() {
                        button.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        global $wpdb;
        
        // Get audit trail stats
        $audit_table = $wpdb->prefix . 'wsi_audit_trail';
        $total_operations = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table}");
        $successful_operations = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table} WHERE status = 'success'");
        $failed_operations = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table} WHERE status = 'error'");
        $today_operations = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table} WHERE DATE(timestamp) = CURDATE()");
        
        $success_rate = $total_operations > 0 ? round(($successful_operations / $total_operations) * 100, 1) : 0;
        
        // Get average response time
        $avg_response_time = $wpdb->get_var("SELECT AVG(execution_time) FROM {$audit_table} WHERE execution_time IS NOT NULL ORDER BY timestamp DESC LIMIT 100");
        $avg_response_time = $avg_response_time ? round($avg_response_time, 3) : 0;
        
        // Get field mappings count
        $mappings = get_option('wsi_field_mappings', array());
        $total_mappings = 0;
        foreach ($mappings as $object_mappings) {
            $total_mappings += count($object_mappings);
        }
        
        // Check connection status
        $last_connection_check = get_option('wsi_last_connection_check', 'Never');
        $connection_status = get_option('wsi_connection_status', false);
        
        // Health checks
        $wp_integration_health = $this->check_wp_integration_health();
        $api_health = $this->check_api_health();
        $mappings_health = $this->check_mappings_health();
        $database_health = $this->check_database_health();
        $acf_health = $this->check_acf_health();
        
        return array(
            'total_operations' => $total_operations,
            'successful_operations' => $successful_operations,
            'failed_operations' => $failed_operations,
            'success_rate' => $success_rate,
            'today_operations' => $today_operations,
            'avg_response_time' => $avg_response_time,
            'total_mappings' => $total_mappings,
            'connection_status' => $connection_status,
            'last_connection_check' => $last_connection_check,
            'wp_integration_health' => $wp_integration_health,
            'api_health' => $api_health,
            'mappings_health' => $mappings_health,
            'database_health' => $database_health,
            'acf_health' => $acf_health
        );
    }
    
    /**
     * Get recent logs
     */
    private function get_recent_logs($limit = 10) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'wsi_audit_trail';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$audit_table} ORDER BY timestamp DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get operation icon
     */
    private function get_operation_icon($operation_type) {
        $icons = array(
            'create' => 'plus',
            'update' => 'edit',
            'delete' => 'trash',
            'upsert' => 'update',
            'sync' => 'update'
        );
        
        return isset($icons[$operation_type]) ? $icons[$operation_type] : 'admin-tools';
    }
    
    /**
     * Check WordPress integration health
     */
    private function check_wp_integration_health() {
        // Check if required classes exist
        $required_classes = array(
            'WSI_Data_Transformer',
            'WSI_User_Registration_Handler',
            'WSI_CSV_Based_Salesforce_Mapper'
        );
        
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check API health
     */
    private function check_api_health() {
        try {
            $api = new WSI_Salesforce_API();
            $api->test_connection();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check mappings health
     */
    private function check_mappings_health() {
        $mappings = get_option('wsi_field_mappings', array());
        return !empty($mappings);
    }
    
    /**
     * Check database health
     */
    private function check_database_health() {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'wsi_audit_trail';
        
        // Check if audit table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$audit_table}'") == $audit_table;
        
        if (!$table_exists) {
            return false;
        }
        
        // Check if we can query the table
        $result = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table}");
        return $result !== false;
    }
    
    /**
     * Check ACF health
     */
    private function check_acf_health() {
        $acf_status = $this->acf_setup->get_field_status();
        return $acf_status['acf_available'] && $acf_status['fields_exist'];
    }
    
    /**
     * Handle dashboard stats AJAX request
     */
    public function handle_dashboard_stats_ajax() {
        check_ajax_referer('wsi_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $stats = $this->get_dashboard_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * Handle sync now AJAX request
     */
    public function handle_sync_now_ajax() {
        check_ajax_referer('wsi_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Trigger a manual sync
            $user_handler = new WSI_User_Registration_Handler();
            $result = $user_handler->sync_all_users();
            
            wp_send_json_success(array('message' => 'Sync completed successfully'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle bulk sync users AJAX request
     */
    public function handle_bulk_sync_users_ajax() {
        check_ajax_referer('wsi_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $result = $this->bulk_sync_users();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle bulk sync posts AJAX request
     */
    public function handle_bulk_sync_posts_ajax() {
        check_ajax_referer('wsi_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $result = $this->bulk_sync_posts();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Bulk sync users
     */
    private function bulk_sync_users() {
        $users = get_users(array(
            'number' => -1,
            'fields' => 'ID'
        ));
        
        $synced = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($users as $user_id) {
            try {
                $manual_sync = new WSI_Manual_Sync();
                $manual_sync->sync_user_to_salesforce($user_id);
                $synced++;
            } catch (Exception $e) {
                $errors++;
                error_log('Bulk sync user error for user ' . $user_id . ': ' . $e->getMessage());
            }
        }
        
        return array(
            'message' => "Synced: {$synced}, Errors: {$errors}, Skipped: {$skipped}",
            'synced' => $synced,
            'errors' => $errors,
            'skipped' => $skipped
        );
    }
    
    /**
     * Bulk sync posts
     */
    private function bulk_sync_posts() {
        $post_types = get_post_types(array('public' => true), 'names');
        $posts = get_posts(array(
            'post_type' => $post_types,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        
        $synced = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($posts as $post_id) {
            try {
                $manual_sync = new WSI_Manual_Sync();
                $manual_sync->sync_post_to_salesforce($post_id);
                $synced++;
            } catch (Exception $e) {
                $errors++;
                error_log('Bulk sync post error for post ' . $post_id . ': ' . $e->getMessage());
            }
        }
        
        return array(
            'message' => "Synced: {$synced}, Errors: {$errors}, Skipped: {$skipped}",
            'synced' => $synced,
            'errors' => $errors,
            'skipped' => $skipped
        );
    }
}
