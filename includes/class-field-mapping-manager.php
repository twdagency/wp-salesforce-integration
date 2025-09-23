<?php
/**
 * Field Mapping Manager
 * Provides admin interface for managing field mappings between WordPress and Salesforce
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Field_Mapping_Manager {
    
    private $csv_mapper;
    private $logger;
    
    public function __construct() {
        $this->csv_mapper = new WSI_CSV_Based_Salesforce_Mapper();
        $this->logger = new WSI_Logger();
        
        add_action('admin_menu', array($this, 'add_field_mapping_menu'));
        add_action('wp_ajax_wsi_save_field_mapping', array($this, 'handle_save_mapping_ajax'));
        add_action('wp_ajax_wsi_delete_field_mapping', array($this, 'handle_delete_mapping_ajax'));
        add_action('wp_ajax_wsi_test_field_mapping', array($this, 'handle_test_mapping_ajax'));
        add_action('wp_ajax_wsi_export_field_mappings', array($this, 'handle_export_mappings_ajax'));
        add_action('wp_ajax_wsi_import_field_mappings', array($this, 'handle_import_mappings_ajax'));
    }
    
    /**
     * Add field mapping menu to admin
     */
    public function add_field_mapping_menu() {
        add_submenu_page(
            'wsi-settings',
            'Field Mappings',
            'Field Mappings',
            'manage_options',
            'wsi-field-mappings',
            array($this, 'field_mapping_page')
        );
    }
    
    /**
     * Field mapping admin page
     */
    public function field_mapping_page() {
        $objects = $this->csv_mapper->get_available_objects();
        $current_object = isset($_GET['object']) ? sanitize_text_field($_GET['object']) : (count($objects) > 0 ? $objects[0] : '');
        $field_mappings = $this->csv_mapper->get_field_mappings($current_object);
        $salesforce_fields = $this->csv_mapper->get_salesforce_object_fields($current_object);
        
        ?>
        <div class="wrap">
            <h1>Field Mappings Management</h1>
            
            <div class="field-mapping-header">
                <div class="object-selector">
                    <label for="object-select">Select Salesforce Object:</label>
                    <select id="object-select" onchange="changeObject()">
                        <?php foreach ($objects as $object): ?>
                            <option value="<?php echo esc_attr($object); ?>" <?php selected($current_object, $object); ?>>
                                <?php echo esc_html($object); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mapping-actions">
                    <button id="add-mapping" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> Add New Mapping
                    </button>
                    <button id="test-mappings" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools"></span> Test Mappings
                    </button>
                    <button id="export-mappings" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span> Export
                    </button>
                    <button id="import-mappings" class="button button-secondary">
                        <span class="dashicons dashicons-upload"></span> Import
                    </button>
                </div>
            </div>
            
            <div class="mapping-stats">
                <div class="stat-box">
                    <h3>Total Mappings</h3>
                    <span class="stat-number"><?php echo count($field_mappings); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Salesforce Fields</h3>
                    <span class="stat-number"><?php echo count($salesforce_fields); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Mapped Fields</h3>
                    <span class="stat-number"><?php echo count($field_mappings); ?></span>
                </div>
                <div class="stat-box">
                    <h3>Unmapped Fields</h3>
                    <span class="stat-number"><?php echo count($salesforce_fields) - count($field_mappings); ?></span>
                </div>
            </div>
            
            <div class="field-mappings-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Salesforce Field</th>
                            <th>WordPress Field</th>
                            <th>Source</th>
                            <th>Transformation</th>
                            <th>Required</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($field_mappings)): ?>
                            <tr>
                                <td colspan="7" class="no-mappings">
                                    No field mappings found for this object. 
                                    <a href="#" id="add-first-mapping">Add your first mapping</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($field_mappings as $sf_field => $mapping): ?>
                                <tr data-sf-field="<?php echo esc_attr($sf_field); ?>">
                                    <td>
                                        <strong><?php echo esc_html($sf_field); ?></strong>
                                        <br><small class="field-type"><?php echo $this->get_field_type($sf_field, $salesforce_fields); ?></small>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="wp-field-input" 
                                               value="<?php echo esc_attr($mapping['wp_field']); ?>"
                                               data-field="wp_field">
                                    </td>
                                    <td>
                                        <select class="wp-source-select" data-field="wp_source">
                                            <option value="user" <?php selected($mapping['wp_source'], 'user'); ?>>User</option>
                                            <option value="user_meta" <?php selected($mapping['wp_source'], 'user_meta'); ?>>User Meta</option>
                                            <option value="post" <?php selected($mapping['wp_source'], 'post'); ?>>Post</option>
                                            <option value="acf" <?php selected($mapping['wp_source'], 'acf'); ?>>ACF</option>
                                            <option value="computed" <?php selected($mapping['wp_source'], 'computed'); ?>>Computed</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="transformation-select" data-field="transformation">
                                            <option value="text" <?php selected($mapping['transformation'], 'text'); ?>>Text</option>
                                            <option value="email" <?php selected($mapping['transformation'], 'email'); ?>>Email</option>
                                            <option value="phone" <?php selected($mapping['transformation'], 'phone'); ?>>Phone</option>
                                            <option value="url" <?php selected($mapping['transformation'], 'url'); ?>>URL</option>
                                            <option value="textarea" <?php selected($mapping['transformation'], 'textarea'); ?>>Textarea</option>
                                            <option value="number" <?php selected($mapping['transformation'], 'number'); ?>>Number</option>
                                            <option value="boolean" <?php selected($mapping['transformation'], 'boolean'); ?>>Boolean</option>
                                            <option value="datetime" <?php selected($mapping['transformation'], 'datetime'); ?>>DateTime</option>
                                            <option value="date" <?php selected($mapping['transformation'], 'date'); ?>>Date</option>
                                            <option value="array_to_text" <?php selected($mapping['transformation'], 'array_to_text'); ?>>Array to Text</option>
                                            <option value="currency" <?php selected($mapping['transformation'], 'currency'); ?>>Currency</option>
                                            <option value="json" <?php selected($mapping['transformation'], 'json'); ?>>JSON</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="checkbox" 
                                               class="required-checkbox" 
                                               data-field="required"
                                               <?php checked(isset($mapping['required']) && $mapping['required']); ?>>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="description-input" 
                                               value="<?php echo esc_attr($mapping['description']); ?>"
                                               data-field="description"
                                               placeholder="Field description">
                                    </td>
                                    <td>
                                        <button class="button button-small save-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>">
                                            <span class="dashicons dashicons-yes"></span>
                                        </button>
                                        <button class="button button-small test-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                        </button>
                                        <button class="button button-small delete-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="unmapped-fields">
                <h3>Unmapped Salesforce Fields</h3>
                <div class="unmapped-list">
                    <?php 
                    $mapped_fields = array_keys($field_mappings);
                    foreach ($salesforce_fields as $field): 
                        if (!in_array($field['name'], $mapped_fields)):
                    ?>
                        <div class="unmapped-field" data-sf-field="<?php echo esc_attr($field['name']); ?>">
                            <span class="field-name"><?php echo esc_html($field['name']); ?></span>
                            <span class="field-type"><?php echo esc_html($field['data_type']); ?></span>
                            <button class="button button-small add-unmapped" data-sf-field="<?php echo esc_attr($field['name']); ?>">
                                <span class="dashicons dashicons-plus"></span> Map
                            </button>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Add/Edit Mapping Modal -->
        <div id="mapping-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add/Edit Field Mapping</h2>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="mapping-form">
                        <input type="hidden" id="modal-sf-field" name="sf_field">
                        <input type="hidden" id="modal-object" name="object" value="<?php echo esc_attr($current_object); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="modal-wp-field">WordPress Field</label></th>
                                <td>
                                    <input type="text" id="modal-wp-field" name="wp_field" class="regular-text" required>
                                    <p class="description">The WordPress field name (e.g., first_name, user_email)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-wp-source">WordPress Source</label></th>
                                <td>
                                    <select id="modal-wp-source" name="wp_source" required>
                                        <option value="user">User Object</option>
                                        <option value="user_meta">User Meta</option>
                                        <option value="post">Post Object</option>
                                        <option value="acf">ACF Field</option>
                                        <option value="computed">Computed Field</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-transformation">Data Transformation</label></th>
                                <td>
                                    <select id="modal-transformation" name="transformation" required>
                                        <option value="text">Text</option>
                                        <option value="email">Email</option>
                                        <option value="phone">Phone</option>
                                        <option value="url">URL</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="boolean">Boolean</option>
                                        <option value="datetime">DateTime</option>
                                        <option value="date">Date</option>
                                        <option value="array_to_text">Array to Text</option>
                                        <option value="currency">Currency</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-required">Required Field</label></th>
                                <td>
                                    <input type="checkbox" id="modal-required" name="required">
                                    <p class="description">Mark this field as required for sync</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-description">Description</label></th>
                                <td>
                                    <textarea id="modal-description" name="description" rows="3" class="large-text"></textarea>
                                    <p class="description">Optional description for this field mapping</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="modal-actions">
                            <button type="submit" class="button button-primary">Save Mapping</button>
                            <button type="button" class="button" onclick="closeModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .field-mapping-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .object-selector select {
            min-width: 200px;
        }
        
        .mapping-actions button {
            margin-left: 10px;
        }
        
        .mapping-stats {
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
        
        .field-mappings-table {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .field-mappings-table th,
        .field-mappings-table td {
            padding: 10px;
        }
        
        .field-type {
            color: #666;
            font-style: italic;
        }
        
        .wp-field-input,
        .description-input {
            width: 100%;
        }
        
        .wp-source-select,
        .transformation-select {
            width: 100%;
        }
        
        .unmapped-fields {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .unmapped-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .unmapped-field {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .field-name {
            font-weight: bold;
        }
        
        .field-type {
            color: #666;
            font-size: 0.9em;
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
            width: 80%;
            max-width: 600px;
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
        
        .modal-actions {
            text-align: right;
            margin-top: 20px;
        }
        
        .modal-actions button {
            margin-left: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Change object
            window.changeObject = function() {
                var object = $('#object-select').val();
                window.location.href = '<?php echo admin_url('admin.php?page=wsi-field-mappings'); ?>&object=' + object;
            };
            
            // Add mapping
            $('#add-mapping, #add-first-mapping').on('click', function() {
                openModal();
            });
            
            // Add unmapped field
            $('.add-unmapped').on('click', function() {
                var sfField = $(this).data('sf-field');
                $('#modal-sf-field').val(sfField);
                openModal();
            });
            
            // Save mapping
            $('.save-mapping').on('click', function() {
                var row = $(this).closest('tr');
                var sfField = $(this).data('sf-field');
                var mapping = {
                    wp_field: row.find('.wp-field-input').val(),
                    wp_source: row.find('.wp-source-select').val(),
                    transformation: row.find('.transformation-select').val(),
                    required: row.find('.required-checkbox').is(':checked'),
                    description: row.find('.description-input').val()
                };
                
                saveMapping(sfField, mapping);
            });
            
            // Test mapping
            $('.test-mapping').on('click', function() {
                var sfField = $(this).data('sf-field');
                testMapping(sfField);
            });
            
            // Delete mapping
            $('.delete-mapping').on('click', function() {
                if (confirm('Are you sure you want to delete this mapping?')) {
                    var sfField = $(this).data('sf-field');
                    deleteMapping(sfField);
                }
            });
            
            // Modal functions
            window.openModal = function() {
                $('#mapping-modal').show();
            };
            
            window.closeModal = function() {
                $('#mapping-modal').hide();
            };
            
            // Close modal on outside click
            $(window).on('click', function(event) {
                if (event.target.id === 'mapping-modal') {
                    closeModal();
                }
            });
            
            // Form submission
            $('#mapping-form').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                saveMappingFromModal(formData);
            });
            
            function saveMapping(sfField, mapping) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_save_field_mapping',
                        sf_field: sfField,
                        object: '<?php echo esc_js($current_object); ?>',
                        mapping: mapping,
                        nonce: '<?php echo wp_create_nonce('wsi_field_mapping_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Mapping saved successfully', 'success');
                        } else {
                            showNotice('Failed to save mapping: ' + response.data.message, 'error');
                        }
                    }
                });
            }
            
            function saveMappingFromModal(formData) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=wsi_save_field_mapping&nonce=<?php echo wp_create_nonce('wsi_field_mapping_nonce'); ?>',
                    success: function(response) {
                        if (response.success) {
                            showNotice('Mapping saved successfully', 'success');
                            closeModal();
                            location.reload();
                        } else {
                            showNotice('Failed to save mapping: ' + response.data.message, 'error');
                        }
                    }
                });
            }
            
            function testMapping(sfField) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_test_field_mapping',
                        sf_field: sfField,
                        object: '<?php echo esc_js($current_object); ?>',
                        nonce: '<?php echo wp_create_nonce('wsi_field_mapping_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Mapping test successful', 'success');
                        } else {
                            showNotice('Mapping test failed: ' + response.data.message, 'error');
                        }
                    }
                });
            }
            
            function deleteMapping(sfField) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_delete_field_mapping',
                        sf_field: sfField,
                        object: '<?php echo esc_js($current_object); ?>',
                        nonce: '<?php echo wp_create_nonce('wsi_field_mapping_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Mapping deleted successfully', 'success');
                            location.reload();
                        } else {
                            showNotice('Failed to delete mapping: ' + response.data.message, 'error');
                        }
                    }
                });
            }
            
            function showNotice(message, type) {
                var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
                var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after(notice);
                setTimeout(function() {
                    notice.fadeOut();
                }, 3000);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get field type for display
     */
    private function get_field_type($field_name, $salesforce_fields) {
        foreach ($salesforce_fields as $field) {
            if ($field['name'] === $field_name) {
                return $field['data_type'];
            }
        }
        return 'Unknown';
    }
    
    /**
     * Handle save mapping AJAX request
     */
    public function handle_save_mapping_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $sf_field = sanitize_text_field($_POST['sf_field']);
        $object = sanitize_text_field($_POST['object']);
        $mapping = array(
            'wp_field' => sanitize_text_field($_POST['mapping']['wp_field']),
            'wp_source' => sanitize_text_field($_POST['mapping']['wp_source']),
            'transformation' => sanitize_text_field($_POST['mapping']['transformation']),
            'required' => isset($_POST['mapping']['required']) ? (bool)$_POST['mapping']['required'] : false,
            'description' => sanitize_text_field($_POST['mapping']['description'])
        );
        
        // Save to database
        $mappings = get_option('wsi_field_mappings', array());
        if (!isset($mappings[$object])) {
            $mappings[$object] = array();
        }
        $mappings[$object][$sf_field] = $mapping;
        update_option('wsi_field_mappings', $mappings);
        
        $this->logger->info('Field mapping saved', array(
            'object' => $object,
            'sf_field' => $sf_field,
            'mapping' => $mapping
        ));
        
        wp_send_json_success('Mapping saved successfully');
    }
    
    /**
     * Handle delete mapping AJAX request
     */
    public function handle_delete_mapping_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $sf_field = sanitize_text_field($_POST['sf_field']);
        $object = sanitize_text_field($_POST['object']);
        
        $mappings = get_option('wsi_field_mappings', array());
        if (isset($mappings[$object][$sf_field])) {
            unset($mappings[$object][$sf_field]);
            update_option('wsi_field_mappings', $mappings);
            
            $this->logger->info('Field mapping deleted', array(
                'object' => $object,
                'sf_field' => $sf_field
            ));
            
            wp_send_json_success('Mapping deleted successfully');
        } else {
            wp_send_json_error('Mapping not found');
        }
    }
    
    /**
     * Handle test mapping AJAX request
     */
    public function handle_test_mapping_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $sf_field = sanitize_text_field($_POST['sf_field']);
        $object = sanitize_text_field($_POST['object']);
        
        // Get a test user
        $users = get_users(array('number' => 1));
        if (empty($users)) {
            wp_send_json_error('No users found for testing');
        }
        
        $user = $users[0];
        $mapping = $this->csv_mapper->get_field_mapping($object, $sf_field);
        
        if (!$mapping) {
            wp_send_json_error('Mapping not found');
        }
        
        // Test data transformation
        $transformer = new WSI_Data_Transformer();
        $test_data = $transformer->get_field_value($user, $mapping['wp_field'], $mapping['wp_source']);
        $transformed_data = $transformer->transform_field_value($test_data, $mapping['transformation']);
        
        $this->logger->info('Field mapping test', array(
            'object' => $object,
            'sf_field' => $sf_field,
            'test_data' => $test_data,
            'transformed_data' => $transformed_data
        ));
        
        wp_send_json_success('Mapping test successful');
    }
    
    /**
     * Handle export mappings AJAX request
     */
    public function handle_export_mappings_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $mappings = get_option('wsi_field_mappings', array());
        $export_data = json_encode($mappings, JSON_PRETTY_PRINT);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="wsi_field_mappings_' . date('Y-m-d') . '.json"');
        echo $export_data;
        exit;
    }
    
    /**
     * Handle import mappings AJAX request
     */
    public function handle_import_mappings_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_FILES['mappings_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['mappings_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }
        
        $content = file_get_contents($file['tmp_name']);
        $mappings = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON file');
        }
        
        update_option('wsi_field_mappings', $mappings);
        
        $this->logger->info('Field mappings imported', array(
            'objects' => array_keys($mappings)
        ));
        
        wp_send_json_success('Mappings imported successfully');
    }
}
