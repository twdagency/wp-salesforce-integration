<?php
/**
 * ACF Field Setup
 * Automatically creates required ACF fields on plugin activation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_ACF_Field_Setup {
    
    private $required_fields = array(
        'salesforce_lead_id' => array(
            'label' => 'Salesforce Lead ID',
            'name' => 'salesforce_lead_id',
            'type' => 'text',
            'instructions' => 'The Salesforce Lead ID for this user',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'maxlength' => '',
        ),
        'salesforce_contact_id' => array(
            'label' => 'Salesforce Contact ID',
            'name' => 'salesforce_contact_id',
            'type' => 'text',
            'instructions' => 'The Salesforce Contact ID for this user',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'maxlength' => '',
        ),
        'salesforce_account_id' => array(
            'label' => 'Salesforce Account ID',
            'name' => 'salesforce_account_id',
            'type' => 'text',
            'instructions' => 'The Salesforce Account ID for this user',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'maxlength' => '',
        ),
        'original_lead_id' => array(
            'label' => 'Original Lead ID',
            'name' => 'original_lead_id',
            'type' => 'text',
            'instructions' => 'The original Salesforce Lead ID before conversion to Contact',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'maxlength' => '',
        ),
        'salesforce_sync_status' => array(
            'label' => 'Salesforce Sync Status',
            'name' => 'salesforce_sync_status',
            'type' => 'select',
            'instructions' => 'Current sync status with Salesforce',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'choices' => array(
                'not_synced' => 'Not Synced',
                'lead_created' => 'Lead Created',
                'contact_created' => 'Contact Created',
                'account_created' => 'Account Created',
                'migrated_from_miniorange' => 'Migrated from miniOrange',
                'sync_error' => 'Sync Error',
            ),
            'default_value' => 'not_synced',
            'allow_null' => 0,
            'multiple' => 0,
            'ui' => 0,
            'return_format' => 'value',
            'ajax' => 0,
        ),
        'salesforce_migration_date' => array(
            'label' => 'Salesforce Migration Date',
            'name' => 'salesforce_migration_date',
            'type' => 'date_time_picker',
            'instructions' => 'Date when user was migrated from miniOrange plugin',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'display_format' => 'Y-m-d H:i:s',
            'return_format' => 'Y-m-d H:i:s',
            'first_day' => 1,
        ),
        'salesforce_last_sync' => array(
            'label' => 'Last Salesforce Sync',
            'name' => 'salesforce_last_sync',
            'type' => 'date_time_picker',
            'instructions' => 'Last time this user was synced with Salesforce',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'display_format' => 'Y-m-d H:i:s',
            'return_format' => 'Y-m-d H:i:s',
            'first_day' => 1,
        ),
        'salesforce_sync_errors' => array(
            'label' => 'Salesforce Sync Errors',
            'name' => 'salesforce_sync_errors',
            'type' => 'textarea',
            'instructions' => 'Any errors encountered during Salesforce sync',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'maxlength' => '',
            'rows' => 4,
            'new_lines' => '',
        ),
    );
    
    public function __construct() {
        add_action('init', array($this, 'check_acf_availability'));
        add_action('acf/init', array($this, 'create_acf_fields'));
    }
    
    /**
     * Check if ACF is available
     */
    public function check_acf_availability() {
        if (!function_exists('acf_add_local_field_group')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            return false;
        }
        return true;
    }
    
    /**
     * Show notice if ACF is missing
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>WordPress Salesforce Integration:</strong> 
                Advanced Custom Fields (ACF) plugin is required for full functionality. 
                <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>">
                    Install ACF Plugin
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Create ACF fields for users
     */
    public function create_acf_fields() {
        if (!$this->check_acf_availability()) {
            return;
        }
        
        // Check if fields already exist
        if ($this->fields_exist()) {
            return;
        }
        
        // Create field group
        acf_add_local_field_group(array(
            'key' => 'group_wsi_user_fields',
            'title' => 'Salesforce Integration Fields',
            'fields' => $this->get_field_array(),
            'location' => array(
                array(
                    array(
                        'param' => 'user_form',
                        'operator' => '==',
                        'value' => 'all',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => array(),
            'active' => true,
            'description' => 'Fields for WordPress Salesforce Integration',
        ));
    }
    
    /**
     * Get field array for ACF
     */
    private function get_field_array() {
        $fields = array();
        $field_order = 0;
        
        foreach ($this->required_fields as $field_name => $field_config) {
            $fields[] = array_merge($field_config, array(
                'key' => 'field_wsi_' . $field_name,
                'parent' => 'group_wsi_user_fields',
                'menu_order' => $field_order++,
            ));
        }
        
        return $fields;
    }
    
    /**
     * Check if fields already exist
     */
    public function fields_exist() {
        try {
            error_log('WSI ACF: Checking if fields exist...');
            
            $existing_fields = get_posts(array(
                'post_type' => 'acf-field',
                'meta_query' => array(
                    array(
                        'key' => 'parent',
                        'value' => 'group_wsi_user_fields',
                        'compare' => '='
                    )
                ),
                'posts_per_page' => -1
            ));
            
            error_log('WSI ACF: get_posts result: ' . print_r($existing_fields, true));
            
            // Ensure we have an array
            if (!is_array($existing_fields)) {
                error_log('WSI ACF: get_posts returned non-array: ' . print_r($existing_fields, true));
                return false;
            }
            
            $count = count($existing_fields);
            $required_count = count($this->required_fields);
            error_log('WSI ACF: Found ' . $count . ' existing fields, need ' . $required_count);
            
            return $count >= $required_count;
            
        } catch (Exception $e) {
            error_log('WSI ACF: Exception in fields_exist: ' . $e->getMessage());
            error_log('WSI ACF: Stack trace: ' . $e->getTraceAsString());
            return false;
        } catch (Error $e) {
            error_log('WSI ACF: Fatal error in fields_exist: ' . $e->getMessage());
            error_log('WSI ACF: Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Create fields programmatically (fallback method)
     */
    public function create_fields_programmatically() {
        if (!$this->check_acf_availability()) {
            error_log('WSI ACF: ACF not available');
            return false;
        }
        
        // Create field group first
        $field_group_id = $this->create_field_group();
        if (!$field_group_id) {
            error_log('WSI ACF: Failed to create field group');
            return false;
        }
        
        error_log('WSI ACF: Created field group with ID: ' . $field_group_id);
        
        // Create individual fields
        $field_order = 0;
        $created_fields = 0;
        foreach ($this->required_fields as $field_name => $field_config) {
            $field_id = $this->create_single_field($field_group_id, $field_name, $field_config, $field_order++);
            if ($field_id) {
                $created_fields++;
                error_log('WSI ACF: Created field ' . $field_name . ' with ID: ' . $field_id);
            } else {
                error_log('WSI ACF: Failed to create field: ' . $field_name);
            }
        }
        
        error_log('WSI ACF: Created ' . $created_fields . ' out of ' . count($this->required_fields) . ' fields');
        
        return $created_fields > 0;
    }
    
    /**
     * Create field group
     */
    private function create_field_group() {
        $field_group = array(
            'post_title' => 'Salesforce Integration Fields',
            'post_name' => 'group_wsi_user_fields',
            'post_type' => 'acf-field-group',
            'post_status' => 'publish',
            'post_content' => '',
            'menu_order' => 0,
        );
        
        $field_group_id = wp_insert_post($field_group);
        
        if (is_wp_error($field_group_id)) {
            error_log('WSI ACF: Error creating field group: ' . $field_group_id->get_error_message());
            return false;
        }
        
        if ($field_group_id) {
            // Add field group meta using update_post_meta
            update_post_meta($field_group_id, 'key', 'group_wsi_user_fields');
            update_post_meta($field_group_id, 'title', 'Salesforce Integration Fields');
            update_post_meta($field_group_id, 'fields', '');
            update_post_meta($field_group_id, 'location', array(
                array(
                    array(
                        'param' => 'user_form',
                        'operator' => '==',
                        'value' => 'all',
                    ),
                ),
            ));
            update_post_meta($field_group_id, 'menu_order', 0);
            update_post_meta($field_group_id, 'position', 'normal');
            update_post_meta($field_group_id, 'style', 'default');
            update_post_meta($field_group_id, 'label_placement', 'top');
            update_post_meta($field_group_id, 'instruction_placement', 'label');
            update_post_meta($field_group_id, 'hide_on_screen', array());
            update_post_meta($field_group_id, 'active', 1);
            update_post_meta($field_group_id, 'description', 'Fields for WordPress Salesforce Integration');
        }
        
        return $field_group_id;
    }
    
    /**
     * Create single field
     */
    private function create_single_field($field_group_id, $field_name, $field_config, $field_order) {
        $field = array(
            'post_title' => $field_config['label'],
            'post_name' => 'field_wsi_' . $field_name,
            'post_type' => 'acf-field',
            'post_status' => 'publish',
            'post_content' => '',
            'post_parent' => $field_group_id,
            'menu_order' => $field_order,
        );
        
        $field_id = wp_insert_post($field);
        
        if (is_wp_error($field_id)) {
            error_log('WSI ACF: Error creating field ' . $field_name . ': ' . $field_id->get_error_message());
            return false;
        }
        
        if ($field_id) {
            // Add field meta using update_post_meta
            update_post_meta($field_id, 'key', 'field_wsi_' . $field_name);
            update_post_meta($field_id, 'label', $field_config['label']);
            update_post_meta($field_id, 'name', $field_config['name']);
            update_post_meta($field_id, 'type', $field_config['type']);
            update_post_meta($field_id, 'instructions', $field_config['instructions']);
            update_post_meta($field_id, 'required', $field_config['required']);
            update_post_meta($field_id, 'conditional_logic', $field_config['conditional_logic']);
            update_post_meta($field_id, 'wrapper', $field_config['wrapper']);
            update_post_meta($field_id, 'default_value', $field_config['default_value']);
            update_post_meta($field_id, 'placeholder', $field_config['placeholder'] ?? '');
            update_post_meta($field_id, 'prepend', $field_config['prepend'] ?? '');
            update_post_meta($field_id, 'append', $field_config['append'] ?? '');
            update_post_meta($field_id, 'maxlength', $field_config['maxlength'] ?? '');
            
            // Add type-specific fields
            if (isset($field_config['choices'])) {
                update_post_meta($field_id, 'choices', $field_config['choices']);
            }
            if (isset($field_config['allow_null'])) {
                update_post_meta($field_id, 'allow_null', $field_config['allow_null']);
            }
            if (isset($field_config['multiple'])) {
                update_post_meta($field_id, 'multiple', $field_config['multiple']);
            }
            if (isset($field_config['ui'])) {
                update_post_meta($field_id, 'ui', $field_config['ui']);
            }
            if (isset($field_config['return_format'])) {
                update_post_meta($field_id, 'return_format', $field_config['return_format']);
            }
            if (isset($field_config['ajax'])) {
                update_post_meta($field_id, 'ajax', $field_config['ajax']);
            }
            if (isset($field_config['display_format'])) {
                update_post_meta($field_id, 'display_format', $field_config['display_format']);
            }
            if (isset($field_config['first_day'])) {
                update_post_meta($field_id, 'first_day', $field_config['first_day']);
            }
            if (isset($field_config['rows'])) {
                update_post_meta($field_id, 'rows', $field_config['rows']);
            }
            if (isset($field_config['new_lines'])) {
                update_post_meta($field_id, 'new_lines', $field_config['new_lines']);
            }
        }
        
        return $field_id;
    }
    
    
    /**
     * Get field status
     */
    public function get_field_status() {
        try {
            error_log('WSI ACF: Getting field status...');
            
            $acf_available = $this->check_acf_availability();
            error_log('WSI ACF: ACF available: ' . ($acf_available ? 'yes' : 'no'));
            
            $fields_exist = $this->fields_exist();
            error_log('WSI ACF: Fields exist: ' . ($fields_exist ? 'yes' : 'no'));
            
            $required_fields = array_keys($this->required_fields);
            error_log('WSI ACF: Required fields: ' . print_r($required_fields, true));
            
            $status = array(
                'acf_available' => $acf_available,
                'fields_exist' => $fields_exist,
                'required_fields' => $required_fields,
                'missing_fields' => array()
            );
            
            if ($status['acf_available'] && !$status['fields_exist']) {
                $status['missing_fields'] = array_keys($this->required_fields);
            }
            
            error_log('WSI ACF: Field status result: ' . print_r($status, true));
            return $status;
            
        } catch (Exception $e) {
            error_log('WSI ACF: Error in get_field_status: ' . $e->getMessage());
            error_log('WSI ACF: Stack trace: ' . $e->getTraceAsString());
            return array(
                'acf_available' => false,
                'fields_exist' => false,
                'required_fields' => array(),
                'missing_fields' => array(),
                'error' => $e->getMessage()
            );
        } catch (Error $e) {
            error_log('WSI ACF: Fatal error in get_field_status: ' . $e->getMessage());
            error_log('WSI ACF: Stack trace: ' . $e->getTraceAsString());
            return array(
                'acf_available' => false,
                'fields_exist' => false,
                'required_fields' => array(),
                'missing_fields' => array(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create fields on activation
     */
    public function create_fields_on_activation() {
        if (!$this->check_acf_availability()) {
            return array(
                'success' => false,
                'message' => 'ACF plugin is not available'
            );
        }
        
        if ($this->fields_exist()) {
            return array(
                'success' => true,
                'message' => 'ACF fields already exist'
            );
        }
        
        $result = $this->create_fields_programmatically();
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'ACF fields created successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create ACF fields'
            );
        }
    }
}
