<?php
/**
 * Logger class for WordPress Salesforce Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Logger {
    
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_DEBUG = 'debug';
    
    private $log_table = 'wsi_logs';
    private $max_log_entries = 1000;
    
    public function __construct() {
        add_action('init', array($this, 'create_log_table'));
    }
    
    /**
     * Create log table if it doesn't exist
     */
    public function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            post_id bigint(20),
            trigger_type varchar(50),
            PRIMARY KEY (id),
            KEY level (level),
            KEY post_id (post_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log a message
     */
    public function log($level, $message, $context = array(), $post_id = null, $trigger_type = null) {
        if (!$this->should_log($level)) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $data = array(
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'post_id' => $post_id,
            'trigger_type' => $trigger_type
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log('WSI Logger: Failed to insert log entry - ' . $wpdb->last_error);
        }
        
        // Clean up old logs
        $this->cleanup_old_logs();
        
        // Also log to WordPress error log for errors
        if ($level === self::LOG_LEVEL_ERROR) {
            error_log('WSI Error: ' . $message . ' Context: ' . json_encode($context));
        }
    }
    
    /**
     * Log error
     */
    public function error($message, $context = array(), $post_id = null, $trigger_type = null) {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context, $post_id, $trigger_type);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $context = array(), $post_id = null, $trigger_type = null) {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context, $post_id, $trigger_type);
    }
    
    /**
     * Log info
     */
    public function info($message, $context = array(), $post_id = null, $trigger_type = null) {
        $this->log(self::LOG_LEVEL_INFO, $message, $context, $post_id, $trigger_type);
    }
    
    /**
     * Log debug
     */
    public function debug($message, $context = array(), $post_id = null, $trigger_type = null) {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context, $post_id, $trigger_type);
    }
    
    /**
     * Get logs with filters
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $defaults = array(
            'level' => null,
            'post_id' => null,
            'trigger_type' => null,
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array();
        $where_values = array();
        
        if ($args['level']) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $args['level'];
        }
        
        if ($args['post_id']) {
            $where_conditions[] = 'post_id = %d';
            $where_values[] = $args['post_id'];
        }
        
        if ($args['trigger_type']) {
            $where_conditions[] = 'trigger_type = %s';
            $where_values[] = $args['trigger_type'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY timestamp {$args['order']} LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get log statistics
     */
    public function get_log_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        $stats = array();
        
        // Total logs by level
        $level_stats = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM $table_name GROUP BY level",
            ARRAY_A
        );
        
        foreach ($level_stats as $stat) {
            $stats['by_level'][$stat['level']] = (int) $stat['count'];
        }
        
        // Recent activity (last 24 hours)
        $stats['recent_24h'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE timestamp > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );
        
        // Recent errors (last 7 days)
        $stats['errors_7d'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE level = %s AND timestamp > %s",
                self::LOG_LEVEL_ERROR,
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );
        
        return $stats;
    }
    
    /**
     * Clear logs
     */
    public function clear_logs($level = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        if ($level) {
            $wpdb->delete($table_name, array('level' => $level));
        } else {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }
    
    /**
     * Export logs
     */
    public function export_logs($format = 'csv', $args = array()) {
        $logs = $this->get_logs($args);
        
        if ($format === 'csv') {
            return $this->export_logs_csv($logs);
        } elseif ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT);
        }
        
        return false;
    }
    
    /**
     * Export logs as CSV
     */
    private function export_logs_csv($logs) {
        $output = fopen('php://temp', 'r+');
        
        // CSV header
        fputcsv($output, array('ID', 'Timestamp', 'Level', 'Message', 'Post ID', 'Trigger Type', 'Context'));
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->id,
                $log->timestamp,
                $log->level,
                $log->message,
                $log->post_id,
                $log->trigger_type,
                $log->context
            ));
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Check if we should log based on level
     */
    private function should_log($level) {
        $log_levels = get_option('wsi_log_levels', array(
            self::LOG_LEVEL_ERROR => true,
            self::LOG_LEVEL_WARNING => true,
            self::LOG_LEVEL_INFO => true,
            self::LOG_LEVEL_DEBUG => false
        ));
        
        return isset($log_levels[$level]) && $log_levels[$level];
    }
    
    /**
     * Clean up old logs
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->log_table;
        
        // Keep only the most recent entries
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table_name ORDER BY timestamp DESC LIMIT %d
                    ) AS recent_logs
                )",
                $this->max_log_entries
            )
        );
    }
    
    /**
     * Log sync attempt
     */
    public function log_sync_attempt($post_id, $trigger_type, $success, $message = '', $context = array()) {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_ERROR;
        $log_message = $success ? 'Sync successful' : 'Sync failed: ' . $message;
        
        $this->log($level, $log_message, $context, $post_id, $trigger_type);
    }
    
    /**
     * Log API call
     */
    public function log_api_call($endpoint, $method, $response_code, $response_time, $context = array()) {
        $level = $response_code >= 400 ? self::LOG_LEVEL_ERROR : self::LOG_LEVEL_INFO;
        $message = sprintf('API call to %s %s - Response: %d - Time: %dms', 
                          $method, $endpoint, $response_code, $response_time);
        
        $context['endpoint'] = $endpoint;
        $context['method'] = $method;
        $context['response_code'] = $response_code;
        $context['response_time'] = $response_time;
        
        $this->log($level, $message, $context);
    }
    
    /**
     * Log authentication event
     */
    public function log_auth_event($success, $message = '', $context = array()) {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_ERROR;
        $log_message = $success ? 'Authentication successful' : 'Authentication failed: ' . $message;
        
        $this->log($level, $log_message, $context);
    }
}
