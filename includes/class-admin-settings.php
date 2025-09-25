<?php
/**
 * Admin Settings Page for WordPress Salesforce Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wsi_test_connection', array($this, 'test_salesforce_connection'));
        add_action('wp_ajax_wsi_get_salesforce_fields', array($this, 'get_salesforce_fields'));
        add_action('wp_ajax_wsi_sync_single_post', array($this, 'sync_single_post'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Salesforce Integration',
            'Salesforce Integration',
            'manage_options',
            'wp-salesforce-integration',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Salesforce Connection Settings
        register_setting('wsi_settings', 'wsi_client_id');
        register_setting('wsi_settings', 'wsi_client_secret');
        register_setting('wsi_settings', 'wsi_username');
        register_setting('wsi_settings', 'wsi_password');
        register_setting('wsi_settings', 'wsi_security_token');
        register_setting('wsi_settings', 'wsi_sandbox_mode');
        
        // Post Type Settings
        register_setting('wsi_settings', 'wsi_enabled_post_types');
        register_setting('wsi_settings', 'wsi_sync_configs');
        
        // Field Mapping Settings
        register_setting('wsi_settings', 'wsi_field_mappings');
        
        // General Settings
        register_setting('wsi_settings', 'wsi_enable_logging');
        register_setting('wsi_settings', 'wsi_auto_sync');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_wp-salesforce-integration') {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'wsi-admin',
            WSI_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WSI_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wsi-admin',
            WSI_PLUGIN_URL . 'assets/admin.css',
            array(),
            WSI_VERSION
        );
        
        wp_localize_script('wsi-admin', 'wsi_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsi_admin_nonce'),
            'strings' => array(
                'test_connection' => __('Test Connection', 'wsi'),
                'testing' => __('Testing...', 'wsi'),
                'connection_success' => __('Connection successful!', 'wsi'),
                'connection_failed' => __('Connection failed!', 'wsi'),
                'loading_fields' => __('Loading fields...', 'wsi'),
                'sync_success' => __('Sync successful!', 'wsi'),
                'sync_failed' => __('Sync failed!', 'wsi')
            )
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WordPress to Salesforce Integration', 'wsi'); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <div class="wsi-admin-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#connection" class="nav-tab nav-tab-active"><?php _e('Connection', 'wsi'); ?></a>
                    <a href="#post-types" class="nav-tab"><?php _e('Post Types', 'wsi'); ?></a>
                    <a href="#field-mapping" class="nav-tab"><?php _e('Field Mapping', 'wsi'); ?></a>
                    <a href="#sync-logs" class="nav-tab"><?php _e('Sync Logs', 'wsi'); ?></a>
                </nav>
                
                <div class="tab-content">
                    <div id="connection" class="tab-pane active">
                        <?php $this->connection_settings(); ?>
                    </div>
                    
                    <div id="post-types" class="tab-pane">
                        <?php $this->post_type_settings(); ?>
                    </div>
                    
                    <div id="field-mapping" class="tab-pane">
                        <?php $this->field_mapping_settings(); ?>
                    </div>
                    
                    <div id="sync-logs" class="tab-pane">
                        <?php $this->sync_logs(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Connection settings tab
     */
    private function connection_settings() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wsi_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Client ID', 'wsi'); ?></th>
                    <td>
                        <input type="text" name="wsi_client_id" value="<?php echo esc_attr(get_option('wsi_client_id')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Salesforce Connected App Client ID', 'wsi'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Client Secret', 'wsi'); ?></th>
                    <td>
                        <input type="password" name="wsi_client_secret" value="<?php echo esc_attr(get_option('wsi_client_secret')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Salesforce Connected App Client Secret', 'wsi'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Username', 'wsi'); ?></th>
                    <td>
                        <input type="text" name="wsi_username" value="<?php echo esc_attr(get_option('wsi_username')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Salesforce username', 'wsi'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Password', 'wsi'); ?></th>
                    <td>
                        <input type="password" name="wsi_password" value="<?php echo esc_attr(get_option('wsi_password')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Salesforce password', 'wsi'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Security Token', 'wsi'); ?></th>
                    <td>
                        <input type="text" name="wsi_security_token" value="<?php echo esc_attr(get_option('wsi_security_token')); ?>" class="regular-text" />
                        <p class="description"><?php _e('Salesforce security token', 'wsi'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Sandbox Mode', 'wsi'); ?></th>
                    <td>
                        <input type="checkbox" name="wsi_sandbox_mode" value="1" <?php checked(get_option('wsi_sandbox_mode'), 1); ?> />
                        <p class="description"><?php _e('Use Salesforce sandbox environment', 'wsi'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <?php submit_button(__('Save Settings', 'wsi'), 'primary', 'submit', false); ?>
                <button type="button" id="test-connection" class="button"><?php _e('Test Connection', 'wsi'); ?></button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Post type settings tab
     */
    private function post_type_settings() {
        $enabled_post_types = get_option('wsi_enabled_post_types', array());
        // Ensure it's always an array
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = array();
        }
        $sync_configs = get_option('wsi_sync_configs', array());
        // Ensure sync_configs is also an array
        if (!is_array($sync_configs)) {
            $sync_configs = array();
        }
        $post_types = get_post_types(array('public' => true), 'objects');
        
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wsi_settings'); ?>
            
            <h3><?php _e('Enable Sync for Post Types', 'wsi'); ?></h3>
            <table class="form-table">
                <?php foreach ($post_types as $post_type): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($post_type->labels->name); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wsi_enabled_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" 
                                       <?php checked(in_array($post_type->name, $enabled_post_types)); ?> />
                                <?php _e('Enable sync', 'wsi'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <h3><?php _e('Sync Configuration', 'wsi'); ?></h3>
            <table class="form-table">
                <?php foreach ($enabled_post_types as $post_type): ?>
                    <?php $config = isset($sync_configs[$post_type]) ? $sync_configs[$post_type] : array(); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html(get_post_type_object($post_type)->labels->name); ?></th>
                        <td>
                            <table class="wsi-config-table">
                                <tr>
                                    <td><?php _e('Salesforce Object', 'wsi'); ?></td>
                                    <td>
                                        <input type="text" name="wsi_sync_configs[<?php echo esc_attr($post_type); ?>][salesforce_object]" 
                                               value="<?php echo esc_attr($config['salesforce_object'] ?? ''); ?>" class="regular-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e('External ID Field', 'wsi'); ?></td>
                                    <td>
                                        <input type="text" name="wsi_sync_configs[<?php echo esc_attr($post_type); ?>][external_id_field]" 
                                               value="<?php echo esc_attr($config['external_id_field'] ?? 'WordPress_Post_ID__c'); ?>" class="regular-text" />
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <p class="submit">
                <?php submit_button(__('Save Settings', 'wsi')); ?>
            </p>
        </form>
        <?php
    }
    
    /**
     * Field mapping settings tab
     */
    private function field_mapping_settings() {
        $field_mappings = get_option('wsi_field_mappings', array());
        $enabled_post_types = get_option('wsi_enabled_post_types', array());
        
        ?>
        <div class="field-mapping-container">
            <?php foreach ($enabled_post_types as $post_type): ?>
                <div class="post-type-mapping" data-post-type="<?php echo esc_attr($post_type); ?>">
                    <h3><?php echo esc_html(get_post_type_object($post_type)->labels->name); ?></h3>
                    
                    <div class="mapping-section">
                        <h4><?php _e('WordPress/ACF Fields', 'wsi'); ?></h4>
                        
                        <div class="field-mapping-list" id="mapping-list-<?php echo esc_attr($post_type); ?>">
                            <?php
                            // Get sample post to show available fields
                            $sample_posts = get_posts(array(
                                'post_type' => $post_type,
                                'numberposts' => 1,
                                'meta_query' => array(
                                    'relation' => 'OR',
                                    array(
                                        'key' => '',
                                        'compare' => 'EXISTS'
                                    )
                                )
                            ));
                            
                            if ($sample_posts) {
                                $sample_post = $sample_posts[0];
                                $acf_fields = get_fields($sample_post->ID);
                                
                                // Add basic post fields
                                $basic_fields = array(
                                    'post_title' => 'Post Title',
                                    'post_content' => 'Post Content',
                                    'post_excerpt' => 'Post Excerpt',
                                    'post_date' => 'Post Date',
                                    'post_status' => 'Post Status'
                                );
                                
                                foreach ($basic_fields as $field_key => $field_label) {
                                    $this->render_field_mapping_row($post_type, $field_key, $field_label, $field_mappings);
                                }
                                
                                // Add ACF fields
                                if ($acf_fields) {
                                    foreach ($acf_fields as $field_key => $field_value) {
                                        $field_obj = get_field_object($field_key);
                                        $field_label = $field_obj ? $field_obj['label'] : $field_key;
                                        $this->render_field_mapping_row($post_type, $field_key, $field_label, $field_mappings, $field_obj);
                                    }
                                }
                            }
                            ?>
                        </div>
                        
                        <button type="button" class="button add-mapping"><?php _e('Add Field Mapping', 'wsi'); ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <p class="submit">
                <button type="button" id="save-field-mappings" class="button button-primary"><?php _e('Save Field Mappings', 'wsi'); ?></button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render field mapping row
     */
    private function render_field_mapping_row($post_type, $field_key, $field_label, $field_mappings, $acf_field_obj = null) {
        $mapping = isset($field_mappings[$field_key]) ? $field_mappings[$field_key] : array();
        $field_type = $acf_field_obj ? $acf_field_obj['type'] : 'text';
        
        ?>
        <div class="field-mapping-row" data-field="<?php echo esc_attr($field_key); ?>">
            <div class="mapping-field">
                <strong><?php echo esc_html($field_label); ?></strong>
                <small>(<?php echo esc_html($field_key); ?>) - <?php echo esc_html($field_type); ?></small>
            </div>
            
            <div class="mapping-controls">
                <label>
                    <?php _e('Salesforce Field:', 'wsi'); ?>
                    <input type="text" name="wsi_field_mappings[<?php echo esc_attr($field_key); ?>][salesforce_field]" 
                           value="<?php echo esc_attr($mapping['salesforce_field'] ?? ''); ?>" class="salesforce-field" />
                </label>
                
                <?php if ($field_type === 'checkbox'): ?>
                    <label>
                        <?php _e('Checkbox Strategy:', 'wsi'); ?>
                        <select name="wsi_field_mappings[<?php echo esc_attr($field_key); ?>][checkbox_strategy]">
                            <option value="semicolon_separated" <?php selected($mapping['checkbox_strategy'] ?? 'semicolon_separated', 'semicolon_separated'); ?>>
                                <?php _e('Semicolon Separated', 'wsi'); ?>
                            </option>
                            <option value="comma_separated" <?php selected($mapping['checkbox_strategy'] ?? '', 'comma_separated'); ?>>
                                <?php _e('Comma Separated', 'wsi'); ?>
                            </option>
                            <option value="pipe_separated" <?php selected($mapping['checkbox_strategy'] ?? '', 'pipe_separated'); ?>>
                                <?php _e('Pipe Separated', 'wsi'); ?>
                            </option>
                            <option value="json" <?php selected($mapping['checkbox_strategy'] ?? '', 'json'); ?>>
                                <?php _e('JSON Array', 'wsi'); ?>
                            </option>
                            <option value="first_value" <?php selected($mapping['checkbox_strategy'] ?? '', 'first_value'); ?>>
                                <?php _e('First Value Only', 'wsi'); ?>
                            </option>
                            <option value="count" <?php selected($mapping['checkbox_strategy'] ?? '', 'count'); ?>>
                                <?php _e('Count (Number)', 'wsi'); ?>
                            </option>
                            <option value="boolean" <?php selected($mapping['checkbox_strategy'] ?? '', 'boolean'); ?>>
                                <?php _e('Boolean (True if any selected)', 'wsi'); ?>
                            </option>
                        </select>
                    </label>
                    
                    <label class="custom-delimiter" style="display: none;">
                        <?php _e('Custom Delimiter:', 'wsi'); ?>
                        <input type="text" name="wsi_field_mappings[<?php echo esc_attr($field_key); ?>][custom_delimiter]" 
                               value="<?php echo esc_attr($mapping['custom_delimiter'] ?? ''); ?>" />
                    </label>
                <?php endif; ?>
                
                <button type="button" class="button remove-mapping"><?php _e('Remove', 'wsi'); ?></button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Sync logs tab
     */
    private function sync_logs() {
        $logs = get_option('wsi_sync_logs', array());
        
        ?>
        <div class="sync-logs">
            <h3><?php _e('Recent Sync Activity', 'wsi'); ?></h3>
            
            <?php if (empty($logs)): ?>
                <p><?php _e('No sync activity yet.', 'wsi'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Post ID', 'wsi'); ?></th>
                            <th><?php _e('Post Title', 'wsi'); ?></th>
                            <th><?php _e('Status', 'wsi'); ?></th>
                            <th><?php _e('Trigger', 'wsi'); ?></th>
                            <th><?php _e('Date/Time', 'wsi'); ?></th>
                            <th><?php _e('Actions', 'wsi'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_logs = array_slice(array_reverse($logs), 0, 50);
                        foreach ($recent_logs as $log):
                            $post = get_post($log['post_id']);
                            $post_title = $post ? $post->post_title : 'Post not found';
                        ?>
                            <tr>
                                <td><?php echo esc_html($log['post_id']); ?></td>
                                <td><?php echo esc_html($post_title); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(ucfirst($log['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['trigger']); ?></td>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td>
                                    <button type="button" class="button button-small sync-post" data-post-id="<?php echo esc_attr($log['post_id']); ?>">
                                        <?php _e('Re-sync', 'wsi'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wsi') . '</p></div>';
        }
    }
    
    /**
     * Test Salesforce connection
     */
    public function test_salesforce_connection() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $salesforce_api = new WSI_Salesforce_API();
            $result = $salesforce_api->test_connection();
            
            if ($result) {
                wp_send_json_success('Connection successful!');
            } else {
                wp_send_json_error('Connection failed. Please check your credentials.');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get Salesforce fields
     */
    public function get_salesforce_fields() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // This would typically make an API call to get Salesforce object fields
        // For now, return some common fields
        $common_fields = array(
            'Name' => 'Name',
            'Description__c' => 'Description',
            'WordPress_Post_ID__c' => 'WordPress Post ID',
            'WordPress_Post_Type__c' => 'WordPress Post Type',
            'WordPress_Status__c' => 'WordPress Status'
        );
        
        wp_send_json_success($common_fields);
    }
    
    /**
     * Sync single post
     */
    public function sync_single_post() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        try {
            $sync_handler = new WSI_Post_Sync_Handler();
            $result = $sync_handler->manual_sync($post_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
