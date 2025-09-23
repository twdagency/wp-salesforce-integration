<?php
/**
 * Audit Trail System
 * Tracks all sync operations and provides detailed logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Audit_Trail {
    
    private $wpdb;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wsi_audit_trail';
        
        add_action('admin_menu', array($this, 'add_audit_menu'));
        add_action('wp_ajax_wsi_clear_audit_logs', array($this, 'handle_clear_logs_ajax'));
        add_action('wp_ajax_wsi_export_audit_logs', array($this, 'handle_export_logs_ajax'));
        add_action('wp_ajax_wsi_get_audit_details', array($this, 'handle_get_details_ajax'));
        
        // Create table on activation
        register_activation_hook(WSI_PLUGIN_FILE, array($this, 'create_audit_table'));
    }
    
    /**
     * Create audit trail table
     */
    public function create_audit_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            operation_type varchar(50) NOT NULL,
            object_type varchar(100) NOT NULL,
            object_id varchar(100) NOT NULL,
            wp_object_id bigint(20) NOT NULL,
            wp_object_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            data_sent longtext,
            data_received longtext,
            error_details longtext,
            execution_time decimal(10,4) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            PRIMARY KEY (id),
            KEY operation_type (operation_type),
            KEY object_type (object_type),
            KEY status (status),
            KEY timestamp (timestamp),
            KEY wp_object_id (wp_object_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log sync operation
     */
    public function log_operation($operation_type, $object_type, $object_id, $wp_object_id, $wp_object_type, $status, $message = '', $data_sent = null, $data_received = null, $error_details = null, $execution_time = null) {
        $data = array(
            'operation_type' => $operation_type,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'wp_object_id' => $wp_object_id,
            'wp_object_type' => $wp_object_type,
            'status' => $status,
            'message' => $message,
            'data_sent' => $data_sent ? json_encode($data_sent) : null,
            'data_received' => $data_received ? json_encode($data_received) : null,
            'error_details' => $error_details ? json_encode($error_details) : null,
            'execution_time' => $execution_time,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $this->wpdb->insert($this->table_name, $data);
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Add audit menu to admin
     */
    public function add_audit_menu() {
        add_submenu_page(
            'wsi-settings',
            'Audit Trail',
            'Audit Trail',
            'manage_options',
            'wsi-audit-trail',
            array($this, 'audit_trail_page')
        );
    }
    
    /**
     * Audit trail admin page
     */
    public function audit_trail_page() {
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filters
        $operation_filter = isset($_GET['operation']) ? sanitize_text_field($_GET['operation']) : '';
        $object_filter = isset($_GET['object']) ? sanitize_text_field($_GET['object']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // Build query
        $where_conditions = array();
        $where_values = array();
        
        if ($operation_filter) {
            $where_conditions[] = "operation_type = %s";
            $where_values[] = $operation_filter;
        }
        
        if ($object_filter) {
            $where_conditions[] = "object_type = %s";
            $where_values[] = $object_filter;
        }
        
        if ($status_filter) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status_filter;
        }
        
        if ($date_from) {
            $where_conditions[] = "timestamp >= %s";
            $where_values[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $where_conditions[] = "timestamp <= %s";
            $where_values[] = $date_to . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} $where_clause";
        if (!empty($where_values)) {
            $count_query = $this->wpdb->prepare($count_query, $where_values);
        }
        $total_items = $this->wpdb->get_var($count_query);
        
        // Get logs
        $query = "SELECT * FROM {$this->table_name} $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $query = $this->wpdb->prepare($query, $query_values);
        $logs = $this->wpdb->get_results($query);
        
        // Get filter options
        $operations = $this->wpdb->get_col("SELECT DISTINCT operation_type FROM {$this->table_name} ORDER BY operation_type");
        $objects = $this->wpdb->get_col("SELECT DISTINCT object_type FROM {$this->table_name} ORDER BY object_type");
        $statuses = $this->wpdb->get_col("SELECT DISTINCT status FROM {$this->table_name} ORDER BY status");
        
        // Get statistics
        $stats = $this->get_audit_statistics();
        
        ?>
        <div class="wrap">
            <h1>Audit Trail</h1>
            
            <div class="audit-stats">
                <div class="stat-box">
                    <h3>Total Operations</h3>
                    <span class="stat-number"><?php echo number_format($total_items); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Successful</h3>
                    <span class="stat-number success"><?php echo number_format($stats['successful']); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Failed</h3>
                    <span class="stat-number error"><?php echo number_format($stats['failed']); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Success Rate</h3>
                    <span class="stat-number"><?php echo $stats['success_rate']; ?>%</span>
                </div>
            </div>
            
            <div class="audit-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wsi-audit-trail">
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="operation">Operation:</label>
                            <select name="operation" id="operation">
                                <option value="">All Operations</option>
                                <?php foreach ($operations as $operation): ?>
                                    <option value="<?php echo esc_attr($operation); ?>" <?php selected($operation_filter, $operation); ?>>
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $operation))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="object">Object:</label>
                            <select name="object" id="object">
                                <option value="">All Objects</option>
                                <?php foreach ($objects as $object): ?>
                                    <option value="<?php echo esc_attr($object); ?>" <?php selected($object_filter, $object); ?>>
                                        <?php echo esc_html($object); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>>
                                        <?php echo esc_html(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">From:</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">To:</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="button">Filter</button>
                            <a href="<?php echo admin_url('admin.php?page=wsi-audit-trail'); ?>" class="button">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="audit-actions">
                <button id="clear-logs" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span> Clear Logs
                </button>
                <button id="export-logs" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> Export Logs
                </button>
            </div>
            
            <div class="audit-logs">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Operation</th>
                            <th>Object</th>
                            <th>WP Object</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Execution Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="no-logs">No audit logs found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-row" data-log-id="<?php echo $log->id; ?>">
                                    <td>
                                        <?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->timestamp))); ?>
                                    </td>
                                    <td>
                                        <span class="operation-type <?php echo esc_attr($log->operation_type); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $log->operation_type))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($log->object_type); ?></strong>
                                        <?php if ($log->object_id): ?>
                                            <br><small><?php echo esc_html($log->object_id); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->wp_object_type); ?> #<?php echo $log->wp_object_id; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($log->status); ?>">
                                            <?php echo esc_html(ucfirst($log->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->message); ?>
                                    </td>
                                    <td>
                                        <?php if ($log->execution_time): ?>
                                            <?php echo number_format($log->execution_time, 4); ?>s
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="button button-small view-details" data-log-id="<?php echo $log->id; ?>">
                                            <span class="dashicons dashicons-visibility"></span> Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php
            // Pagination
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1):
                $pagination_args = array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                );
                echo '<div class="tablenav">';
                echo '<div class="tablenav-pages">';
                echo paginate_links($pagination_args);
                echo '</div>';
                echo '</div>';
            endif;
            ?>
        </div>
        
        <!-- Details Modal -->
        <div id="log-details-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Audit Log Details</h2>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="log-details-content"></div>
                </div>
            </div>
        </div>
        
        <style>
        .audit-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stat-number.success {
            color: #46b450;
        }
        
        .stat-number.error {
            color: #dc3232;
        }
        
        .audit-filters {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: bold;
        }
        
        .filter-group select,
        .filter-group input {
            min-width: 150px;
        }
        
        .audit-actions {
            margin: 20px 0;
        }
        
        .audit-actions button {
            margin-right: 10px;
        }
        
        .audit-logs {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .operation-type {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .operation-type.create {
            background: #d4edda;
            color: #155724;
        }
        
        .operation-type.update {
            background: #fff3cd;
            color: #856404;
        }
        
        .operation-type.delete {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .log-row:hover {
            background-color: #f9f9f9;
        }
        
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 4px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .log-details {
            font-family: monospace;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // View details
            $('.view-details').on('click', function() {
                var logId = $(this).data('log-id');
                viewLogDetails(logId);
            });
            
            // Clear logs
            $('#clear-logs').on('click', function() {
                if (confirm('Are you sure you want to clear all audit logs? This action cannot be undone.')) {
                    clearLogs();
                }
            });
            
            // Export logs
            $('#export-logs').on('click', function() {
                exportLogs();
            });
            
            // Close modal
            $('.close').on('click', function() {
                $('#log-details-modal').hide();
            });
            
            // Close modal on outside click
            $(window).on('click', function(event) {
                if (event.target.id === 'log-details-modal') {
                    $('#log-details-modal').hide();
                }
            });
            
            function viewLogDetails(logId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_audit_details',
                        log_id: logId,
                        nonce: '<?php echo wp_create_nonce('wsi_audit_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#log-details-content').html(response.data.html);
                            $('#log-details-modal').show();
                        } else {
                            alert('Failed to load log details: ' + response.data.message);
                        }
                    }
                });
            }
            
            function clearLogs() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_clear_audit_logs',
                        nonce: '<?php echo wp_create_nonce('wsi_audit_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Audit logs cleared successfully');
                            location.reload();
                        } else {
                            alert('Failed to clear logs: ' + response.data.message);
                        }
                    }
                });
            }
            
            function exportLogs() {
                window.location.href = ajaxurl + '?action=wsi_export_audit_logs&nonce=<?php echo wp_create_nonce('wsi_audit_nonce'); ?>';
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get audit statistics
     */
    private function get_audit_statistics() {
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $successful = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'success'");
        $failed = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'error'");
        
        $success_rate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
        
        return array(
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $success_rate
        );
    }
    
    /**
     * Handle clear logs AJAX request
     */
    public function handle_clear_logs_ajax() {
        check_ajax_referer('wsi_audit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        if ($result !== false) {
            wp_send_json_success('Audit logs cleared successfully');
        } else {
            wp_send_json_error('Failed to clear audit logs');
        }
    }
    
    /**
     * Handle export logs AJAX request
     */
    public function handle_export_logs_ajax() {
        check_ajax_referer('wsi_audit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $logs = $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY timestamp DESC", ARRAY_A);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="wsi_audit_logs_' . date('Y-m-d') . '.json"');
        echo json_encode($logs, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Handle get details AJAX request
     */
    public function handle_get_details_ajax() {
        check_ajax_referer('wsi_audit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $log_id = intval($_POST['log_id']);
        $log = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $log_id));
        
        if (!$log) {
            wp_send_json_error('Log not found');
        }
        
        $html = '<div class="log-details">';
        $html .= '<h3>Operation Details</h3>';
        $html .= '<p><strong>Operation:</strong> ' . esc_html(ucfirst(str_replace('_', ' ', $log->operation_type))) . '</p>';
        $html .= '<p><strong>Object Type:</strong> ' . esc_html($log->object_type) . '</p>';
        $html .= '<p><strong>Object ID:</strong> ' . esc_html($log->object_id) . '</p>';
        $html .= '<p><strong>WordPress Object:</strong> ' . esc_html($log->wp_object_type) . ' #' . $log->wp_object_id . '</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html(ucfirst($log->status)) . '</p>';
        $html .= '<p><strong>Message:</strong> ' . esc_html($log->message) . '</p>';
        $html .= '<p><strong>Execution Time:</strong> ' . ($log->execution_time ? number_format($log->execution_time, 4) . 's' : 'N/A') . '</p>';
        $html .= '<p><strong>User ID:</strong> ' . ($log->user_id ? $log->user_id : 'N/A') . '</p>';
        $html .= '<p><strong>IP Address:</strong> ' . esc_html($log->ip_address) . '</p>';
        $html .= '<p><strong>Timestamp:</strong> ' . esc_html($log->timestamp) . '</p>';
        
        if ($log->data_sent) {
            $html .= '<h3>Data Sent</h3>';
            $html .= '<div class="log-details">' . esc_html($log->data_sent) . '</div>';
        }
        
        if ($log->data_received) {
            $html .= '<h3>Data Received</h3>';
            $html .= '<div class="log-details">' . esc_html($log->data_received) . '</div>';
        }
        
        if ($log->error_details) {
            $html .= '<h3>Error Details</h3>';
            $html .= '<div class="log-details">' . esc_html($log->error_details) . '</div>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
}
