<?php
/**
 * Post Sync Handler - Manages WordPress hooks for post updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Post_Sync_Handler {
    
    private $salesforce_api;
    private $data_transformer;
    private $sync_queue = array();
    
    public function __construct() {
        $this->salesforce_api = new WSI_Salesforce_API();
        $this->data_transformer = new WSI_Data_Transformer();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Standard post hooks
        add_action('save_post', array($this, 'handle_post_save'), 10, 3);
        add_action('post_updated', array($this, 'handle_post_update'), 10, 3);
        add_action('trash_post', array($this, 'handle_post_trash'), 10, 1);
        add_action('untrash_post', array($this, 'handle_post_untrash'), 10, 1);
        
        // ACF specific hooks
        add_action('acf/save_post', array($this, 'handle_acf_save'), 20); // Priority 20 to run after ACF saves
        
        // AJAX hooks
        add_action('wp_ajax_wsi_sync_post', array($this, 'handle_ajax_sync'));
        add_action('wp_ajax_nopriv_wsi_sync_post', array($this, 'handle_ajax_sync'));
        
        // Background processing
        add_action('wsi_process_sync_queue', array($this, 'process_sync_queue'));
        add_action('init', array($this, 'maybe_process_sync_queue'));
        
        // Custom AJAX hooks for common AJAX save patterns
        add_action('wp_ajax_save_post', array($this, 'handle_custom_ajax_save'), 5);
        add_action('wp_ajax_autosave', array($this, 'handle_autosave'), 5);
    }
    
    /**
     * Handle standard post save
     */
    public function handle_post_save($post_id, $post, $update) {
        // Skip autosaves, revisions, and auto-drafts
        if (wp_is_post_autosave($post_id) || 
            wp_is_post_revision($post_id) || 
            $post->post_status === 'auto-draft') {
            return;
        }
        
        // Check if this post type should be synced
        if (!$this->should_sync_post_type($post->post_type)) {
            return;
        }
        
        // Add to sync queue to avoid blocking the save process
        $this->queue_post_sync($post_id, 'save_post');
    }
    
    /**
     * Handle post update
     */
    public function handle_post_update($post_id, $post_after, $post_before) {
        // Skip if it's not an actual update
        if ($post_after->post_status === $post_before->post_status && 
            $post_after->post_title === $post_before->post_title &&
            $post_after->post_content === $post_before->post_content) {
            return;
        }
        
        if (!$this->should_sync_post_type($post_after->post_type)) {
            return;
        }
        
        $this->queue_post_sync($post_id, 'post_updated');
    }
    
    /**
     * Handle ACF save
     */
    public function handle_acf_save($post_id) {
        if (!$this->should_sync_post_type(get_post_type($post_id))) {
            return;
        }
        
        $this->queue_post_sync($post_id, 'acf_save');
    }
    
    /**
     * Handle post trash
     */
    public function handle_post_trash($post_id) {
        $post = get_post($post_id);
        if (!$post || !$this->should_sync_post_type($post->post_type)) {
            return;
        }
        
        $this->sync_post_to_salesforce($post_id, 'trash');
    }
    
    /**
     * Handle post untrash
     */
    public function handle_post_untrash($post_id) {
        $post = get_post($post_id);
        if (!$post || !$this->should_sync_post_type($post->post_type)) {
            return;
        }
        
        $this->queue_post_sync($post_id, 'untrash');
    }
    
    /**
     * Handle AJAX sync request
     */
    public function handle_ajax_sync() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wsi_sync_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        try {
            $result = $this->sync_post_to_salesforce($post_id, 'ajax_sync');
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle custom AJAX save (common in frontend forms)
     */
    public function handle_custom_ajax_save() {
        // This hook runs before the standard save_post hook
        // We'll let the standard hooks handle the actual sync
    }
    
    /**
     * Handle autosave
     */
    public function handle_autosave() {
        // Skip autosaves as they don't represent real content changes
    }
    
    /**
     * Queue post for sync to avoid blocking operations
     */
    private function queue_post_sync($post_id, $trigger = 'unknown') {
        $this->sync_queue[] = array(
            'post_id' => $post_id,
            'trigger' => $trigger,
            'timestamp' => time()
        );
        
        // Schedule background processing
        if (!wp_next_scheduled('wsi_process_sync_queue')) {
            wp_schedule_single_event(time() + 5, 'wsi_process_sync_queue');
        }
    }
    
    /**
     * Process sync queue
     */
    public function process_sync_queue() {
        if (empty($this->sync_queue)) {
            // Get queued items from database if not in memory
            $this->sync_queue = get_option('wsi_sync_queue', array());
        }
        
        if (empty($this->sync_queue)) {
            return;
        }
        
        $processed = 0;
        $max_per_run = 10; // Process max 10 items per run
        
        foreach ($this->sync_queue as $key => $sync_item) {
            if ($processed >= $max_per_run) {
                break;
            }
            
            try {
                $this->sync_post_to_salesforce($sync_item['post_id'], $sync_item['trigger']);
                unset($this->sync_queue[$key]);
                $processed++;
            } catch (Exception $e) {
                error_log('WSI Sync Error: ' . $e->getMessage());
                // Keep failed items in queue for retry
            }
        }
        
        // Update queue in database
        update_option('wsi_sync_queue', $this->sync_queue);
        
        // Schedule another run if there are remaining items
        if (!empty($this->sync_queue)) {
            wp_schedule_single_event(time() + 30, 'wsi_process_sync_queue');
        }
    }
    
    /**
     * Maybe process sync queue on init
     */
    public function maybe_process_sync_queue() {
        if (wp_next_scheduled('wsi_process_sync_queue') && 
            wp_next_scheduled('wsi_process_sync_queue') < time()) {
            $this->process_sync_queue();
        }
    }
    
    /**
     * Sync post to Salesforce
     */
    public function sync_post_to_salesforce($post_id, $trigger = 'manual') {
        $post = get_post($post_id);
        if (!$post) {
            throw new Exception('Post not found');
        }
        
        if (!$this->should_sync_post_type($post->post_type)) {
            throw new Exception('Post type not configured for sync');
        }
        
        // Get sync configuration
        $sync_config = $this->get_sync_config($post->post_type);
        if (!$sync_config) {
            throw new Exception('No sync configuration found for post type');
        }
        
        // Transform data
        $salesforce_data = $this->data_transformer->transform_post_to_salesforce($post_id);
        
        // Add metadata
        $salesforce_data['WordPress_Post_ID__c'] = $post_id;
        $salesforce_data['WordPress_Post_Type__c'] = $post->post_type;
        $salesforce_data['WordPress_Last_Updated__c'] = current_time('mysql');
        $salesforce_data['Sync_Trigger__c'] = $trigger;
        
        // Handle post status
        if ($post->post_status === 'trash') {
            $salesforce_data['WordPress_Status__c'] = 'Deleted';
        } else {
            $salesforce_data['WordPress_Status__c'] = ucfirst($post->post_status);
        }
        
        // Perform upsert
        $external_id_field = $sync_config['external_id_field'];
        $external_id_value = $post_id; // Use WordPress post ID as external ID
        
        $result = $this->salesforce_api->upsert_record(
            $sync_config['salesforce_object'],
            $external_id_field,
            $external_id_value,
            $salesforce_data
        );
        
        // Log successful sync
        $this->log_sync_result($post_id, 'success', $trigger, $result);
        
        return $result;
    }
    
    /**
     * Check if post type should be synced
     */
    private function should_sync_post_type($post_type) {
        $enabled_post_types = get_option('wsi_enabled_post_types', array());
        return in_array($post_type, $enabled_post_types);
    }
    
    /**
     * Get sync configuration for post type
     */
    private function get_sync_config($post_type) {
        $configs = get_option('wsi_sync_configs', array());
        return isset($configs[$post_type]) ? $configs[$post_type] : null;
    }
    
    /**
     * Log sync result
     */
    private function log_sync_result($post_id, $status, $trigger, $result = null) {
        $log_entry = array(
            'post_id' => $post_id,
            'status' => $status,
            'trigger' => $trigger,
            'timestamp' => current_time('mysql'),
            'result' => $result
        );
        
        $logs = get_option('wsi_sync_logs', array());
        $logs[] = $log_entry;
        
        // Keep only last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('wsi_sync_logs', $logs);
    }
    
    /**
     * Manual sync method for external use
     */
    public function manual_sync($post_id) {
        return $this->sync_post_to_salesforce($post_id, 'manual');
    }
    
    /**
     * Get sync status for a post
     */
    public function get_sync_status($post_id) {
        $logs = get_option('wsi_sync_logs', array());
        
        // Find most recent log entry for this post
        $post_logs = array_filter($logs, function($log) use ($post_id) {
            return $log['post_id'] == $post_id;
        });
        
        if (empty($post_logs)) {
            return null;
        }
        
        // Sort by timestamp descending
        usort($post_logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return $post_logs[0];
    }
}
