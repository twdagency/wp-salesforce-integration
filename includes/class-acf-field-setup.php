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
        add_action('wp_ajax_wsi_create_acf_fields', array($this, 'handle_create_fields_ajax'));
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
            'hide_on_screen' => '',
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
    private function fields_exist() {
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
        
        return count($existing_fields) >= count($this->required_fields);
    }
    
    /**
     * Create fields programmatically (fallback method)
     */
    public function create_fields_programmatically() {
        if (!$this->check_acf_availability()) {
            return false;
        }
        
        // Create field group first
        $field_group_id = $this->create_field_group();
        if (!$field_group_id) {
            return false;
        }
        
        // Create individual fields
        $field_order = 0;
        foreach ($this->required_fields as $field_name => $field_config) {
            $this->create_single_field($field_group_id, $field_name, $field_config, $field_order++);
        }
        
        return true;
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
        
        if ($field_group_id) {
            // Add field group meta
            update_field('key', 'group_wsi_user_fields', $field_group_id);
            update_field('title', 'Salesforce Integration Fields', $field_group_id);
            update_field('fields', '', $field_group_id);
            update_field('location', array(
                array(
                    array(
                        'param' => 'user_form',
                        'operator' => '==',
                        'value' => 'all',
                    ),
                ),
            ), $field_group_id);
            update_field('menu_order', 0, $field_group_id);
            update_field('position', 'normal', $field_group_id);
            update_field('style', 'default', $field_group_id);
            update_field('label_placement', 'top', $field_group_id);
            update_field('instruction_placement', 'label', $field_group_id);
            update_field('hide_on_screen', '', $field_group_id);
            update_field('active', 1, $field_group_id);
            update_field('description', 'Fields for WordPress Salesforce Integration', $field_group_id);
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
        
        if ($field_id) {
            // Add field meta
            update_field('key', 'field_wsi_' . $field_name, $field_id);
            update_field('label', $field_config['label'], $field_id);
            update_field('name', $field_config['name'], $field_id);
            update_field('type', $field_config['type'], $field_id);
            update_field('instructions', $field_config['instructions'], $field_id);
            update_field('required', $field_config['required'], $field_id);
            update_field('conditional_logic', $field_config['conditional_logic'], $field_id);
            update_field('wrapper', $field_config['wrapper'], $field_id);
            update_field('default_value', $field_config['default_value'], $field_id);
            update_field('placeholder', $field_config['placeholder'] ?? '', $field_id);
            update_field('prepend', $field_config['prepend'] ?? '', $field_id);
            update_field('append', $field_config['append'] ?? '', $field_id);
            update_field('maxlength', $field_config['maxlength'] ?? '', $field_id);
            
            // Add type-specific fields
            if (isset($field_config['choices'])) {
                update_field('choices', $field_config['choices'], $field_id);
            }
            if (isset($field_config['allow_null'])) {
                update_field('allow_null', $field_config['allow_null'], $field_id);
            }
            if (isset($field_config['multiple'])) {
                update_field('multiple', $field_config['multiple'], $field_id);
            }
            if (isset($field_config['ui'])) {
                update_field('ui', $field_config['ui'], $field_id);
            }
            if (isset($field_config['return_format'])) {
                update_field('return_format', $field_config['return_format'], $field_id);
            }
            if (isset($field_config['ajax'])) {
                update_field('ajax', $field_config['ajax'], $field_id);
            }
            if (isset($field_config['display_format'])) {
                update_field('display_format', $field_config['display_format'], $field_id);
            }
            if (isset($field_config['first_day'])) {
                update_field('first_day', $field_config['first_day'], $field_id);
            }
            if (isset($field_config['rows'])) {
                update_field('rows', $field_config['rows'], $field_id);
            }
            if (isset($field_config['new_lines'])) {
                update_field('new_lines', $field_config['new_lines'], $field_id);
            }
        }
        
        return $field_id;
    }
    
    /**
     * Handle create fields AJAX request
     */
    public function handle_create_fields_ajax() {
        check_ajax_referer('wsi_acf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->create_fields_programmatically();
        
        if ($result) {
            wp_send_json_success('ACF fields created successfully');
        } else {
            wp_send_json_error('Failed to create ACF fields');
        }
    }
    
    /**
     * Get field status
     */
    public function get_field_status() {
        $status = array(
            'acf_available' => $this->check_acf_availability(),
            'fields_exist' => $this->fields_exist(),
            'required_fields' => array_keys($this->required_fields),
            'missing_fields' => array()
        );
        
        if ($status['acf_available'] && !$status['fields_exist']) {
            $status['missing_fields'] = array_keys($this->required_fields);
        }
        
        return $status;
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
