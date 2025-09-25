<?php
/**
 * Field Mapping Admin Interface
 * Provides admin interface for managing Salesforce field mappings
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Field_Mapping_Admin {
    
    private $comprehensive_mapper;
    private $object_mapper;
    
    public function __construct() {
        $this->comprehensive_mapper = new WSI_Comprehensive_Field_Mapper();
        $this->object_mapper = new WSI_Salesforce_Object_Mapper();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wsi_test_field_mapping', array($this, 'ajax_test_field_mapping'));
        add_action('wp_ajax_wsi_export_mappings', array($this, 'ajax_export_mappings'));
        add_action('wp_ajax_wsi_import_mappings', array($this, 'ajax_import_mappings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-salesforce-integration',
            'Field Mappings',
            'Field Mappings',
            'manage_options',
            'wsi-field-mappings',
            array($this, 'field_mappings_page')
        );
        
        add_submenu_page(
            'wp-salesforce-integration',
            'Salesforce Objects',
            'Salesforce Objects',
            'manage_options',
            'wsi-salesforce-objects',
            array($this, 'salesforce_objects_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wsi-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-util');
        wp_enqueue_style('wsi-admin', WSI_PLUGIN_URL . 'assets/admin.css');
        wp_enqueue_script('wsi-admin', WSI_PLUGIN_URL . 'assets/admin.js', array('jquery'), WSI_VERSION, true);
        
        wp_localize_script('wsi-admin', 'wsi_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsi_admin_nonce'),
            'strings' => array(
                'testing_mapping' => __('Testing field mapping...', 'wp-salesforce-integration'),
                'mapping_success' => __('Field mapping test successful!', 'wp-salesforce-integration'),
                'mapping_error' => __('Field mapping test failed!', 'wp-salesforce-integration'),
                'export_success' => __('Mappings exported successfully!', 'wp-salesforce-integration'),
                'import_success' => __('Mappings imported successfully!', 'wp-salesforce-integration'),
                'import_error' => __('Failed to import mappings!', 'wp-salesforce-integration')
            )
        ));
    }
    
    /**
     * Field mappings admin page
     */
    public function field_mappings_page() {
        $available_objects = $this->comprehensive_mapper->get_available_objects();
        // Ensure it's always an array
        if (!is_array($available_objects)) {
            $available_objects = array();
        }
        $selected_object = isset($_GET['object']) ? sanitize_text_field($_GET['object']) : 'Lead';
        
        if (!in_array($selected_object, $available_objects)) {
            $selected_object = 'Lead';
        }
        
        $field_mappings = $this->comprehensive_mapper->get_field_mappings($selected_object);
        $salesforce_objects = $this->comprehensive_mapper->get_salesforce_objects();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Salesforce Field Mappings', 'wp-salesforce-integration'); ?></h1>
            
            <div class="wsi-admin-header">
                <div class="wsi-tabs">
                    <?php foreach ($available_objects as $object): ?>
                        <a href="<?php echo admin_url('admin.php?page=wsi-field-mappings&object=' . $object); ?>" 
                           class="wsi-tab <?php echo $selected_object === $object ? 'active' : ''; ?>">
                            <?php echo esc_html($object); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="wsi-field-mappings-content">
                <div class="wsi-object-info">
                    <h2><?php echo esc_html($selected_object); ?> Field Mappings</h2>
                    <p class="description">
                        <?php 
                        if (isset($salesforce_objects[$selected_object])) {
                            echo esc_html($salesforce_objects[$selected_object]['description']);
                        }
                        ?>
                    </p>
                </div>
                
                <div class="wsi-mapping-actions">
                    <button type="button" class="button button-secondary" id="test-mapping">
                        <?php _e('Test Mapping', 'wp-salesforce-integration'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="export-mappings">
                        <?php _e('Export Mappings', 'wp-salesforce-integration'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="import-mappings">
                        <?php _e('Import Mappings', 'wp-salesforce-integration'); ?>
                    </button>
                </div>
                
                <div class="wsi-field-mappings-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Salesforce Field', 'wp-salesforce-integration'); ?></th>
                                <th><?php _e('WordPress Field', 'wp-salesforce-integration'); ?></th>
                                <th><?php _e('Source', 'wp-salesforce-integration'); ?></th>
                                <th><?php _e('Required', 'wp-salesforce-integration'); ?></th>
                                <th><?php _e('Transformation', 'wp-salesforce-integration'); ?></th>
                                <th><?php _e('Description', 'wp-salesforce-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($field_mappings as $sf_field => $mapping): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($sf_field); ?></strong></td>
                                    <td><?php echo esc_html($mapping['wp_field']); ?></td>
                                    <td>
                                        <span class="wsi-source-badge wsi-source-<?php echo esc_attr($mapping['wp_source']); ?>">
                                            <?php echo esc_html(ucfirst($mapping['wp_source'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($mapping['required']) && $mapping['required']): ?>
                                            <span class="wsi-required"><?php _e('Yes', 'wp-salesforce-integration'); ?></span>
                                        <?php else: ?>
                                            <span class="wsi-optional"><?php _e('No', 'wp-salesforce-integration'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="wsi-transformation-badge">
                                            <?php echo esc_html($mapping['transformation']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(isset($mapping['description']) ? $mapping['description'] : ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="wsi-mapping-stats">
                    <h3><?php _e('Mapping Statistics', 'wp-salesforce-integration'); ?></h3>
                    <div class="wsi-stats-grid">
                        <div class="wsi-stat">
                            <span class="wsi-stat-number"><?php echo count($field_mappings); ?></span>
                            <span class="wsi-stat-label"><?php _e('Total Fields', 'wp-salesforce-integration'); ?></span>
                        </div>
                        <div class="wsi-stat">
                            <span class="wsi-stat-number">
                                <?php echo count(array_filter($field_mappings, function($mapping) { 
                                    return isset($mapping['required']) && $mapping['required']; 
                                })); ?>
                            </span>
                            <span class="wsi-stat-label"><?php _e('Required Fields', 'wp-salesforce-integration'); ?></span>
                        </div>
                        <div class="wsi-stat">
                            <span class="wsi-stat-number">
                                <?php echo count(array_filter($field_mappings, function($mapping) { 
                                    return !isset($mapping['required']) || !$mapping['required']; 
                                })); ?>
                            </span>
                            <span class="wsi-stat-label"><?php _e('Optional Fields', 'wp-salesforce-integration'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Import Mappings Modal -->
        <div id="wsi-import-modal" class="wsi-modal" style="display: none;">
            <div class="wsi-modal-content">
                <div class="wsi-modal-header">
                    <h3><?php _e('Import Field Mappings', 'wp-salesforce-integration'); ?></h3>
                    <span class="wsi-modal-close">&times;</span>
                </div>
                <div class="wsi-modal-body">
                    <p><?php _e('Paste your JSON field mappings below:', 'wp-salesforce-integration'); ?></p>
                    <textarea id="wsi-import-json" rows="10" cols="50" placeholder="<?php _e('Paste JSON here...', 'wp-salesforce-integration'); ?>"></textarea>
                </div>
                <div class="wsi-modal-footer">
                    <button type="button" class="button button-primary" id="wsi-import-confirm">
                        <?php _e('Import', 'wp-salesforce-integration'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="wsi-import-cancel">
                        <?php _e('Cancel', 'wp-salesforce-integration'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Salesforce objects admin page
     */
    public function salesforce_objects_page() {
        $salesforce_objects = $this->comprehensive_mapper->get_salesforce_objects();
        $documentation = $this->comprehensive_mapper->generate_mapping_documentation();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Salesforce Objects', 'wp-salesforce-integration'); ?></h1>
            
            <div class="wsi-salesforce-objects-content">
                <div class="wsi-objects-overview">
                    <h2><?php _e('Available Salesforce Objects', 'wp-salesforce-integration'); ?></h2>
                    <p class="description">
                        <?php _e('These are the Salesforce objects identified from your Excel file and configured for WordPress integration.', 'wp-salesforce-integration'); ?>
                    </p>
                </div>
                
                <div class="wsi-objects-grid">
                    <?php foreach ($salesforce_objects as $object_name => $object_data): ?>
                        <div class="wsi-object-card">
                            <div class="wsi-object-header">
                                <h3><?php echo esc_html($object_name); ?></h3>
                                <span class="wsi-object-field-count">
                                    <?php echo count($object_data['fields']); ?> <?php _e('fields', 'wp-salesforce-integration'); ?>
                                </span>
                            </div>
                            <div class="wsi-object-body">
                                <p><?php echo esc_html($object_data['description']); ?></p>
                                
                                <?php if (isset($documentation[$object_name])): ?>
                                    <div class="wsi-object-stats">
                                        <div class="wsi-stat">
                                            <span class="wsi-stat-number"><?php echo $documentation[$object_name]['total_fields']; ?></span>
                                            <span class="wsi-stat-label"><?php _e('Total Fields', 'wp-salesforce-integration'); ?></span>
                                        </div>
                                        <div class="wsi-stat">
                                            <span class="wsi-stat-number"><?php echo $documentation[$object_name]['required_fields']; ?></span>
                                            <span class="wsi-stat-label"><?php _e('Required', 'wp-salesforce-integration'); ?></span>
                                        </div>
                                        <div class="wsi-stat">
                                            <span class="wsi-stat-number"><?php echo $documentation[$object_name]['optional_fields']; ?></span>
                                            <span class="wsi-stat-label"><?php _e('Optional', 'wp-salesforce-integration'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="wsi-object-actions">
                                    <a href="<?php echo admin_url('admin.php?page=wsi-field-mappings&object=' . $object_name); ?>" 
                                       class="button button-secondary">
                                        <?php _e('View Mappings', 'wp-salesforce-integration'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="wsi-conversion-flow">
                    <h2><?php _e('Lead → Contact/Account Conversion Flow', 'wp-salesforce-integration'); ?></h2>
                    <div class="wsi-flow-diagram">
                        <div class="wsi-flow-step">
                            <div class="wsi-flow-icon">👤</div>
                            <div class="wsi-flow-content">
                                <h3><?php _e('User Registration', 'wp-salesforce-integration'); ?></h3>
                                <p><?php _e('When a user registers on WordPress, a Lead is created in Salesforce with their information.', 'wp-salesforce-integration'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wsi-flow-arrow">→</div>
                        
                        <div class="wsi-flow-step">
                            <div class="wsi-flow-icon">⏳</div>
                            <div class="wsi-flow-content">
                                <h3><?php _e('Pending Approval', 'wp-salesforce-integration'); ?></h3>
                                <p><?php _e('The Lead remains in "New" status while waiting for admin approval.', 'wp-salesforce-integration'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wsi-flow-arrow">→</div>
                        
                        <div class="wsi-flow-step">
                            <div class="wsi-flow-icon">✅</div>
                            <div class="wsi-flow-content">
                                <h3><?php _e('User Approved', 'wp-salesforce-integration'); ?></h3>
                                <p><?php _e('When approved, the Lead is converted to a Contact and an Account is created.', 'wp-salesforce-integration'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wsi-flow-arrow">→</div>
                        
                        <div class="wsi-flow-step">
                            <div class="wsi-flow-icon">🏢</div>
                            <div class="wsi-flow-content">
                                <h3><?php _e('Account & Contact', 'wp-salesforce-integration'); ?></h3>
                                <p><?php _e('The Contact is linked to the Account, and both are linked back to the WordPress user.', 'wp-salesforce-integration'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for testing field mapping
     */
    public function ajax_test_field_mapping() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-salesforce-integration'));
        }
        
        $object = sanitize_text_field($_POST['object']);
        $user_id = intval($_POST['user_id']);
        
        try {
            // Test with a real user
            $user = get_user_by('id', $user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $field_mappings = $this->comprehensive_mapper->get_field_mappings($object);
            $test_data = array();
            
            foreach ($field_mappings as $sf_field => $mapping) {
                $value = $this->get_test_field_value($user, $mapping);
                if ($value !== null) {
                    $test_data[$sf_field] = $value;
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('Field mapping test successful! Generated %d fields for %s object.', 'wp-salesforce-integration'), 
                    count($test_data), $object),
                'test_data' => $test_data
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Field mapping test failed: %s', 'wp-salesforce-integration'), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX handler for exporting mappings
     */
    public function ajax_export_mappings() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-salesforce-integration'));
        }
        
        $json_data = $this->comprehensive_mapper->export_mappings_to_json();
        
        wp_send_json_success(array(
            'json_data' => $json_data,
            'filename' => 'wsi-field-mappings-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
    
    /**
     * AJAX handler for importing mappings
     */
    public function ajax_import_mappings() {
        check_ajax_referer('wsi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-salesforce-integration'));
        }
        
        $json_data = sanitize_textarea_field($_POST['json_data']);
        
        if ($this->comprehensive_mapper->import_mappings_from_json($json_data)) {
            wp_send_json_success(array(
                'message' => __('Field mappings imported successfully!', 'wp-salesforce-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to import field mappings. Please check your JSON format.', 'wp-salesforce-integration')
            ));
        }
    }
    
    /**
     * Get test field value for mapping test
     */
    private function get_test_field_value($user, $mapping) {
        $wp_field = $mapping['wp_field'];
        $wp_source = $mapping['wp_source'];
        $transformation = $mapping['transformation'];
        $default = isset($mapping['default']) ? $mapping['default'] : null;
        
        $value = null;
        
        if ($wp_source === 'user') {
            $value = isset($user->$wp_field) ? $user->$wp_field : null;
        } elseif ($wp_source === 'user_meta') {
            $value = get_user_meta($user->ID, $wp_field, true);
        }
        
        if (empty($value) && $default !== null) {
            $value = $default;
        }
        
        if ($value !== null) {
            $value = $this->apply_test_transformation($value, $transformation);
        }
        
        return $value;
    }
    
    /**
     * Apply transformation for test
     */
    private function apply_test_transformation($value, $transformation) {
        switch ($transformation) {
            case 'text':
                return sanitize_text_field($value);
            case 'email':
                return is_email($value) ? $value : '';
            case 'phone':
                return sanitize_text_field($value);
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'number':
                return is_numeric($value) ? (float) $value : null;
            case 'boolean':
                return (bool) $value;
            case 'datetime':
                return date('c', strtotime($value));
            case 'date':
                return date('Y-m-d', strtotime($value));
            case 'array_to_text':
                return is_array($value) ? implode(';', $value) : $value;
            case 'currency':
                return is_numeric($value) ? (float) $value : null;
            default:
                return $value;
        }
    }
}

// Note: This class is instantiated in the main plugin file
