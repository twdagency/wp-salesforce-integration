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
        add_action('wp_ajax_wsi_get_acf_fields', array($this, 'handle_get_acf_fields_ajax'));
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
        // Ensure objects is always an array
        if (!is_array($objects)) {
            $objects = array();
        }
        $current_object = isset($_GET['object']) ? sanitize_text_field($_GET['object']) : (count($objects) > 0 ? $objects[0] : '');
        $field_mappings = $this->csv_mapper->get_field_mappings($current_object);
        // Ensure field_mappings is always an array
        if (!is_array($field_mappings)) {
            $field_mappings = array();
        }
        $salesforce_fields = $this->csv_mapper->get_salesforce_object_fields($current_object);
        // Ensure salesforce_fields is always an array
        if (!is_array($salesforce_fields)) {
            $salesforce_fields = array();
        }
        
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
                                        <div class="wp-field-container">
                                            <input type="text" 
                                                   class="wp-field-input" 
                                                   value="<?php echo esc_attr($mapping['wp_field']); ?>"
                                                   data-field="wp_field"
                                                   placeholder="Enter field name or select from dropdown">
                                            <select class="wp-field-dropdown" data-field="wp_field_dropdown" style="display: none;">
                                                <option value="">Select a field...</option>
                                            </select>
                                            <button type="button" class="button button-small toggle-field-dropdown" title="Toggle field dropdown">
                                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="wp-source-select" data-field="wp_source">
                                            <option value="user" <?php selected($mapping['wp_source'], 'user'); ?>>User</option>
                                            <option value="user_meta" <?php selected($mapping['wp_source'], 'user_meta'); ?>>User Meta</option>
                                            <option value="post" <?php selected($mapping['wp_source'], 'post'); ?>>Post</option>
                                            <option value="acf" <?php selected($mapping['wp_source'], 'acf'); ?>>ACF</option>
                                            <option value="computed" <?php selected($mapping['wp_source'], 'computed'); ?>>Computed</option>
                                            <option value="custom" <?php selected($mapping['wp_source'], 'custom'); ?>>Custom Value</option>
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
                                        <button class="button button-small edit-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>" title="Edit mapping">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button class="button button-small save-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>" title="Save mapping">
                                            <span class="dashicons dashicons-yes"></span>
                                        </button>
                                        <button class="button button-small test-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>" title="Test mapping">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                        </button>
                                        <button class="button button-small delete-mapping" data-sf-field="<?php echo esc_attr($sf_field); ?>" title="Delete mapping">
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
                                <th><label for="modal-sf-field-select">Salesforce Field</label></th>
                                <td>
                                    <select id="modal-sf-field-select" name="sf_field_select" required>
                                        <option value="">Select a Salesforce field...</option>
                                        <?php 
                                        // Get unmapped fields for the current object
                                        $salesforce_fields = $this->csv_mapper->get_salesforce_object_fields($current_object);
                                        $field_mappings = $this->csv_mapper->get_field_mappings($current_object);
                                        $mapped_fields = array_keys($field_mappings);
                                        
                                        foreach ($salesforce_fields as $field):
                                            if (!in_array($field['name'], $mapped_fields)):
                                        ?>
                                            <option value="<?php echo esc_attr($field['name']); ?>">
                                                <?php echo esc_html($field['name']); ?> (<?php echo esc_html($field['data_type']); ?>)
                                            </option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                    <p class="description">Select the Salesforce field to map</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-wp-field">WordPress Field</label></th>
                                <td>
                                    <div class="wp-field-container">
                                        <input type="text" 
                                               id="modal-wp-field" 
                                               name="wp_field" 
                                               class="wp-field-input regular-text" 
                                               required
                                               placeholder="Enter field name or select from dropdown">
                                        <select class="wp-field-dropdown" id="modal-wp-field-dropdown" style="display: none;">
                                            <option value="">Select a field...</option>
                                        </select>
                                        <button type="button" class="button button-small toggle-field-dropdown" title="Toggle field dropdown">
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                        </button>
                                    </div>
                                    <p class="description">The WordPress field name (e.g., first_name, user_email)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="modal-wp-source">WordPress Source</label></th>
                                <td>
                                    <select id="modal-wp-source" name="wp_source" class="wp-source-select" required>
                                        <option value="user">User Object</option>
                                        <option value="user_meta">User Meta</option>
                                        <option value="post">Post Object</option>
                                        <option value="acf">ACF Field</option>
                                        <option value="computed">Computed Field</option>
                                        <option value="custom">Custom Value</option>
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
        
        .field-mappings-table table {
            table-layout: fixed; /* Fixed layout for consistent column widths */
            width: 100%;
        }
        
        .field-mappings-table th,
        .field-mappings-table td {
            padding: 10px;
            vertical-align: top;
        }
        
        /* Set specific column widths */
        .field-mappings-table th:nth-child(1),
        .field-mappings-table td:nth-child(1) { width: 18%; } /* Salesforce Field */
        .field-mappings-table th:nth-child(2),
        .field-mappings-table td:nth-child(2) { width: 22%; } /* WordPress Field */
        .field-mappings-table th:nth-child(3),
        .field-mappings-table td:nth-child(3) { width: 12%; } /* Source */
        .field-mappings-table th:nth-child(4),
        .field-mappings-table td:nth-child(4) { width: 12%; } /* Transformation */
        .field-mappings-table th:nth-child(5),
        .field-mappings-table td:nth-child(5) { width: 8%; } /* Required */
        .field-mappings-table th:nth-child(6),
        .field-mappings-table td:nth-child(6) { width: 18%; } /* Description */
        .field-mappings-table th:nth-child(7),
        .field-mappings-table td:nth-child(7) { width: 10%; } /* Actions */
        
        .wp-field-container {
            display: flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }
        
        .wp-field-container input,
        .wp-field-container select {
            flex: 1;
            min-width: 0; /* Allow shrinking */
        }
        
        .wp-field-container .toggle-field-dropdown {
            flex-shrink: 0;
            padding: 4px 8px;
        }
        
        /* Fix dropdown z-index and positioning */
        .wp-field-dropdown {
            z-index: 1000;
            position: relative;
            max-width: 200px; /* Limit width to prevent overlap */
        }
        
        /* Ensure table cells don't overflow */
        .mapping-table td {
            overflow: visible;
            position: relative;
        }
        
        /* Make sure dropdowns don't overlap other columns */
        .wp-source-select {
            z-index: 999;
            position: relative;
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
        
        /* Modal-specific styles */
        .modal .wp-field-container {
            width: 100%;
        }
        
        .modal .wp-field-container input,
        .modal .wp-field-container select {
            width: 100%;
        }
        
        .modal .wp-field-dropdown {
            max-width: none; /* Remove width restriction in modal */
        }
        </style>
        
        <script>
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        jQuery(document).ready(function($) {
            // Change object
            window.changeObject = function() {
                var object = $('#object-select').val();
                window.location.href = '<?php echo admin_url('admin.php?page=wsi-field-mappings'); ?>&object=' + object;
            };
            
            // Add mapping
            $('#add-mapping, #add-first-mapping').on('click', function() {
                openModal('', null);
            });
            
            // Add unmapped field
            $('.add-unmapped').on('click', function() {
                var sfField = $(this).data('sf-field');
                openModal(sfField, null);
            });
            
            // Edit mapping (using event delegation)
            $(document).on('click', '.edit-mapping', function() {
                var row = $(this).closest('tr');
                var sfField = $(this).data('sf-field');
                var mapping = {
                    wp_field: row.find('.wp-field-input').val(),
                    wp_source: row.find('.wp-source-select').val(),
                    transformation: row.find('.transformation-select').val(),
                    required: row.find('.required-checkbox').is(':checked'),
                    description: row.find('.description-input').val()
                };
                
                openModal(sfField, mapping);
            });
            
            // Save mapping (using event delegation)
            $(document).on('click', '.save-mapping', function() {
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
            
            // Test mapping (using event delegation)
            $(document).on('click', '.test-mapping', function() {
                var sfField = $(this).data('sf-field');
                testMapping(sfField);
            });
            
            // Delete mapping (using event delegation)
            $(document).on('click', '.delete-mapping', function() {
                var sfField = $(this).data('sf-field');
                deleteMapping(sfField);
            });
            
            // Source change handler (using event delegation)
            $(document).on('change', '.wp-source-select', function() {
                var row = $(this).closest('tr');
                var source = $(this).val();
                var wpFieldInput = row.find('.wp-field-input');
                var wpFieldDropdown = row.find('.wp-field-dropdown');
                
                // Update placeholder based on source
                switch(source) {
                    case 'user':
                        wpFieldInput.attr('placeholder', 'e.g., user_email, display_name, first_name');
                        populateFieldDropdown(wpFieldDropdown, getUserFields());
                        break;
                    case 'user_meta':
                        wpFieldInput.attr('placeholder', 'e.g., phone, company, address');
                        populateFieldDropdown(wpFieldDropdown, getUserMetaFields());
                        break;
                    case 'post':
                        wpFieldInput.attr('placeholder', 'e.g., post_title, post_content, post_date');
                        populateFieldDropdown(wpFieldDropdown, getPostFields());
                        break;
                    case 'acf':
                        wpFieldInput.attr('placeholder', 'e.g., field_name (ACF field name)');
                        wpFieldDropdown.empty().append('<option value="">Loading ACF fields...</option>');
                        getACFFields(function(fields) {
                            populateFieldDropdown(wpFieldDropdown, fields);
                        });
                        break;
                    case 'computed':
                        wpFieldInput.attr('placeholder', 'e.g., full_name, formatted_address');
                        populateFieldDropdown(wpFieldDropdown, getComputedFields());
                        break;
                    case 'custom':
                        wpFieldInput.attr('placeholder', 'e.g., "Yes", "No", "Custom Text", true, false');
                        populateFieldDropdown(wpFieldDropdown, getCustomValueFields());
                        break;
                    default:
                        wpFieldInput.attr('placeholder', 'Enter field name');
                        wpFieldDropdown.empty().append('<option value="">Select a field...</option>');
                }
            });
            
            // Toggle field dropdown (using event delegation)
            $(document).on('click', '.toggle-field-dropdown', function() {
                var container = $(this).closest('.wp-field-container');
                var input = container.find('.wp-field-input');
                var dropdown = container.find('.wp-field-dropdown');
                var icon = $(this).find('.dashicons');
                
                if (dropdown.is(':visible')) {
                    dropdown.hide();
                    input.show();
                    icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    input.hide();
                    dropdown.show();
                    icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            });
            
            // Field dropdown change handler (using event delegation)
            $(document).on('change', '.wp-field-dropdown', function() {
                var container = $(this).closest('.wp-field-container');
                var input = container.find('.wp-field-input');
                var selectedValue = $(this).val();
                
                if (selectedValue) {
                    input.val(selectedValue);
                    // Trigger change event on input to save the mapping
                    input.trigger('change');
                }
            });
            
            // Helper functions for field dropdowns
            function populateFieldDropdown(dropdown, fields) {
                dropdown.empty().append('<option value="">Select a field...</option>');
                fields.forEach(function(field) {
                    dropdown.append('<option value="' + field.value + '">' + field.label + '</option>');
                });
            }
            
            function getUserFields() {
                return [
                    { value: 'ID', label: 'User ID' },
                    { value: 'user_login', label: 'Username' },
                    { value: 'user_email', label: 'Email' },
                    { value: 'display_name', label: 'Display Name' },
                    { value: 'first_name', label: 'First Name' },
                    { value: 'last_name', label: 'Last Name' },
                    { value: 'user_url', label: 'Website' },
                    { value: 'user_registered', label: 'Registration Date' },
                    { value: 'user_status', label: 'User Status' }
                ];
            }
            
            function getUserMetaFields() {
                return [
                    { value: 'phone', label: 'Phone' },
                    { value: 'company', label: 'Company' },
                    { value: 'address', label: 'Address' },
                    { value: 'city', label: 'City' },
                    { value: 'state', label: 'State' },
                    { value: 'zip', label: 'ZIP Code' },
                    { value: 'country', label: 'Country' },
                    { value: 'salesforce_lead_id', label: 'Salesforce Lead ID' },
                    { value: 'salesforce_contact_id', label: 'Salesforce Contact ID' },
                    { value: 'salesforce_account_id', label: 'Salesforce Account ID' }
                ];
            }
            
            function getPostFields() {
                return [
                    { value: 'ID', label: 'Post ID' },
                    { value: 'post_title', label: 'Post Title' },
                    { value: 'post_content', label: 'Post Content' },
                    { value: 'post_excerpt', label: 'Post Excerpt' },
                    { value: 'post_date', label: 'Post Date' },
                    { value: 'post_modified', label: 'Post Modified' },
                    { value: 'post_status', label: 'Post Status' },
                    { value: 'post_author', label: 'Post Author' },
                    { value: 'post_name', label: 'Post Slug' },
                    { value: 'post_type', label: 'Post Type' }
                ];
            }
            
            function getACFFields(callback) {
                // Get current object type from URL or page context
                var objectType = getCurrentObjectType();
                
                // Make AJAX call to get ACF fields for this object type
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_acf_fields',
                        object_type: objectType,
                        nonce: '<?php echo wp_create_nonce('wsi_get_acf_fields'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            callback(response.data);
                        } else {
                            console.error('Failed to get ACF fields:', response);
                            callback([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error getting ACF fields:', error);
                        callback([]);
                    }
                });
            }
            
            function getCurrentObjectType() {
                // Try to get object type from URL parameter
                var urlParams = new URLSearchParams(window.location.search);
                var objectType = urlParams.get('object');
                
                if (objectType) {
                    return objectType;
                }
                
                // Fallback: try to get from page context
                var pageTitle = document.title;
                if (pageTitle.includes('Lead')) return 'Lead';
                if (pageTitle.includes('Contact')) return 'Contact';
                if (pageTitle.includes('Account')) return 'Account';
                
                return 'Lead'; // Default fallback
            }
            
            function getComputedFields() {
                return [
                    { value: 'full_name', label: 'Full Name (First + Last)' },
                    { value: 'formatted_address', label: 'Formatted Address' },
                    { value: 'user_display_name', label: 'User Display Name' },
                    { value: 'post_permalink', label: 'Post Permalink' }
                ];
            }
            
            function getCustomValueFields() {
                return [
                    { value: 'true', label: 'Boolean: true' },
                    { value: 'false', label: 'Boolean: false' },
                    { value: 'Yes', label: 'Text: Yes' },
                    { value: 'No', label: 'Text: No' },
                    { value: 'Active', label: 'Text: Active' },
                    { value: 'Inactive', label: 'Text: Inactive' },
                    { value: 'Enabled', label: 'Text: Enabled' },
                    { value: 'Disabled', label: 'Text: Disabled' },
                    { value: '1', label: 'Number: 1' },
                    { value: '0', label: 'Number: 0' },
                    { value: 'Default', label: 'Text: Default' },
                    { value: 'Custom', label: 'Text: Custom' }
                ];
            }
            
            // Modal functions
            window.openModal = function(sfField, existingMapping) {
                // Reset form
                $('#mapping-form')[0].reset();
                $('#modal-wp-field-dropdown').hide();
                $('#modal-wp-field').show();
                $('#modal-wp-field-dropdown').empty().append('<option value="">Select a field...</option>');
                
                // Set Salesforce field
                if (sfField) {
                    $('#modal-sf-field').val(sfField);
                    $('#modal-sf-field-select').val(sfField);
                } else {
                    $('#modal-sf-field').val('');
                    $('#modal-sf-field-select').val('');
                }
                
                // If editing existing mapping, populate fields
                if (existingMapping) {
                    $('#modal-wp-field').val(existingMapping.wp_field || '');
                    $('#modal-wp-source').val(existingMapping.wp_source || '');
                    $('#modal-transformation').val(existingMapping.transformation || '');
                    $('#modal-required').prop('checked', existingMapping.required || false);
                    $('#modal-description').val(existingMapping.description || '');
                    
                    // Trigger source change to populate dropdown
                    $('#modal-wp-source').trigger('change');
                }
                
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
            
            // Modal Salesforce field selection
            $('#modal-sf-field-select').on('change', function() {
                var selectedField = $(this).val();
                $('#modal-sf-field').val(selectedField);
            });
            
            // Modal source change handler
            $('#modal-wp-source').on('change', function() {
                var source = $(this).val();
                var wpFieldInput = $('#modal-wp-field');
                var wpFieldDropdown = $('#modal-wp-field-dropdown');
                
                // Update placeholder based on source
                switch(source) {
                    case 'user':
                        wpFieldInput.attr('placeholder', 'e.g., user_email, display_name, first_name');
                        populateFieldDropdown(wpFieldDropdown, getUserFields());
                        break;
                    case 'user_meta':
                        wpFieldInput.attr('placeholder', 'e.g., phone, company, address');
                        populateFieldDropdown(wpFieldDropdown, getUserMetaFields());
                        break;
                    case 'post':
                        wpFieldInput.attr('placeholder', 'e.g., post_title, post_content, post_date');
                        populateFieldDropdown(wpFieldDropdown, getPostFields());
                        break;
                    case 'acf':
                        wpFieldInput.attr('placeholder', 'e.g., field_name (ACF field name)');
                        wpFieldDropdown.empty().append('<option value="">Loading ACF fields...</option>');
                        getACFFields(function(fields) {
                            populateFieldDropdown(wpFieldDropdown, fields);
                        });
                        break;
                    case 'computed':
                        wpFieldInput.attr('placeholder', 'e.g., full_name, formatted_address');
                        populateFieldDropdown(wpFieldDropdown, getComputedFields());
                        break;
                    case 'custom':
                        wpFieldInput.attr('placeholder', 'e.g., "Yes", "No", "Custom Text", true, false');
                        populateFieldDropdown(wpFieldDropdown, getCustomValueFields());
                        break;
                    default:
                        wpFieldInput.attr('placeholder', 'Enter field name');
                        wpFieldDropdown.empty().append('<option value="">Select a field...</option>');
                }
            });
            
            // Modal field dropdown change handler
            $('#modal-wp-field-dropdown').on('change', function() {
                var selectedValue = $(this).val();
                if (selectedValue) {
                    $('#modal-wp-field').val(selectedValue);
                }
            });
            
            // Modal toggle dropdown
            $('#modal-wp-field-container .toggle-field-dropdown').on('click', function() {
                var container = $(this).closest('.wp-field-container');
                var input = container.find('.wp-field-input');
                var dropdown = container.find('.wp-field-dropdown');
                var icon = $(this).find('.dashicons');
                
                if (dropdown.is(':visible')) {
                    dropdown.hide();
                    input.show();
                    icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                } else {
                    input.hide();
                    dropdown.show();
                    icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            });
            
            // Form submission
            $('#mapping-form').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                saveMappingFromModal(formData);
            });
            
            function saveMapping(sfField, mapping) {
                console.log('Saving mapping:', sfField, mapping);
                
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
                        console.log('Save response:', response);
                        if (response.success) {
                            showNotice('Mapping saved successfully', 'success');
                        } else {
                            showNotice('Failed to save mapping: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save mapping error:', xhr, status, error);
                        showNotice('Failed to save mapping due to an error: ' + error, 'error');
                    }
                });
            }
            
            function saveMappingFromModal(formData) {
                console.log('Saving mapping from modal:', formData);
                
                // Parse form data to object for better debugging
                var formObj = {};
                var pairs = formData.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    if (pair.length === 2) {
                        formObj[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
                    }
                }
                console.log('Parsed form data:', formObj);
                
                // Get the selected Salesforce field
                var sfField = $('#modal-sf-field-select').val();
                if (!sfField) {
                    showNotice('Please select a Salesforce field', 'error');
                    return;
                }
                
                // Update the hidden field with the selected value
                $('#modal-sf-field').val(sfField);
                
                // Re-serialize the form data to include the updated sf_field
                var updatedFormData = $('#mapping-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: updatedFormData + '&action=wsi_save_field_mapping&nonce=<?php echo wp_create_nonce('wsi_field_mapping_nonce'); ?>',
                    success: function(response) {
                        console.log('Modal save response:', response);
                        if (response.success) {
                            showNotice('Mapping saved successfully', 'success');
                            closeModal();
                            // Reload the page to show the new mapping
                            location.reload();
                        } else {
                            showNotice('Failed to save mapping: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Modal save mapping error:', xhr, status, error);
                        console.error('Response text:', xhr.responseText);
                        showNotice('Failed to save mapping due to an error: ' + error, 'error');
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
                if (!confirm('Are you sure you want to delete this mapping?')) {
                    return;
                }
                
                console.log('Deleting mapping:', sfField);
                
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
                        console.log('Delete response:', response);
                        if (response.success) {
                            // Remove the row from the table instead of reloading
                            var row = $('tr[data-sf-field="' + sfField + '"]');
                            console.log('Found row to delete:', row.length);
                            row.fadeOut(300, function() {
                                $(this).remove();
                            });
                            showNotice('Mapping deleted successfully', 'success');
                        } else {
                            showNotice('Failed to delete mapping: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Delete mapping error:', xhr, status, error);
                        showNotice('Failed to delete mapping due to an error: ' + error, 'error');
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
        
        // Debug logging (can be removed in production)
        // error_log('WSI Save Mapping Debug - POST data: ' . print_r($_POST, true));
        
        $sf_field = sanitize_text_field($_POST['sf_field']);
        $object = sanitize_text_field($_POST['object']);
        
        // Handle both data formats: modal form fields and main table mapping object
        if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
            // Main table format
            $mapping = array(
                'wp_field' => sanitize_text_field($_POST['mapping']['wp_field']),
                'wp_source' => sanitize_text_field($_POST['mapping']['wp_source']),
                'transformation' => sanitize_text_field($_POST['mapping']['transformation']),
                'required' => isset($_POST['mapping']['required']) ? (bool)$_POST['mapping']['required'] : false,
                'description' => sanitize_text_field($_POST['mapping']['description'])
            );
        } else {
            // Modal form format
            $mapping = array(
                'wp_field' => sanitize_text_field($_POST['wp_field']),
                'wp_source' => sanitize_text_field($_POST['wp_source']),
                'transformation' => sanitize_text_field($_POST['transformation']),
                'required' => isset($_POST['required']) ? (bool)$_POST['required'] : false,
                'description' => sanitize_text_field($_POST['description'])
            );
        }
        
        // Validate required fields
        if (empty($sf_field)) {
            wp_send_json_error('Salesforce field is required');
        }
        
        if (empty($object)) {
            wp_send_json_error('Object type is required');
        }
        
        if (empty($mapping['wp_field'])) {
            wp_send_json_error('WordPress field is required');
        }
        
        if (empty($mapping['wp_source'])) {
            wp_send_json_error('WordPress source is required');
        }
        
        // Save to JSON file (same as CSV mapper uses)
        try {
            $mappings_file = WSI_PLUGIN_PATH . 'salesforce_field_mappings_from_csv.json';
            
            // Load existing mappings
            if (file_exists($mappings_file)) {
                $mappings_data = file_get_contents($mappings_file);
                $mappings = json_decode($mappings_data, true);
            } else {
                $mappings = array();
            }
            
            // Ensure $mappings is always an array
            if (!is_array($mappings)) {
                $mappings = array();
            }
            
            if (!isset($mappings[$object])) {
                $mappings[$object] = array();
            }
            $mappings[$object][$sf_field] = $mapping;
            
            // Debug logging
            error_log('WSI Save Debug - Object: ' . $object . ', Field: ' . $sf_field);
            error_log('WSI Save Debug - Mapping data: ' . print_r($mapping, true));
            error_log('WSI Save Debug - Mappings before save: ' . print_r($mappings, true));
            
            // Save to JSON file
            $result = file_put_contents($mappings_file, json_encode($mappings, JSON_PRETTY_PRINT));
            error_log('WSI Save Debug - File write result: ' . ($result ? 'true' : 'false'));
            
            if ($result === false) {
                error_log('WSI Save Debug - File write failed');
                wp_send_json_error('Failed to save mapping to file');
            }
            
            $this->logger->info('Field mapping saved', array(
                'object' => $object,
                'sf_field' => $sf_field,
                'mapping' => $mapping
            ));
            
            wp_send_json_success('Mapping saved successfully');
            
        } catch (Exception $e) {
            error_log('WSI Save Mapping Error: ' . $e->getMessage());
            wp_send_json_error('Database error: ' . $e->getMessage());
        }
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
        
        $mappings_file = WSI_PLUGIN_PATH . 'salesforce_field_mappings_from_csv.json';
        
        // Load existing mappings
        if (file_exists($mappings_file)) {
            $mappings_data = file_get_contents($mappings_file);
            $mappings = json_decode($mappings_data, true);
        } else {
            $mappings = array();
        }
        
        // Ensure $mappings is always an array
        if (!is_array($mappings)) {
            $mappings = array();
        }
        
        // Debug logging
        error_log('WSI Delete Debug - Object: ' . $object . ', Field: ' . $sf_field);
        error_log('WSI Delete Debug - Mappings: ' . print_r($mappings, true));
        
        if (isset($mappings[$object][$sf_field])) {
            unset($mappings[$object][$sf_field]);
            $result = file_put_contents($mappings_file, json_encode($mappings, JSON_PRETTY_PRINT));
            error_log('WSI Delete Debug - File write result: ' . ($result ? 'true' : 'false'));
            
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
     * Handle get ACF fields AJAX request
     */
    public function handle_get_acf_fields_ajax() {
        check_ajax_referer('wsi_get_acf_fields', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $object_type = sanitize_text_field($_POST['object_type']);
        
        // Get ACF fields for the specified object type
        $acf_fields = $this->get_acf_fields_for_object_type($object_type);
        
        wp_send_json_success($acf_fields);
    }
    
    /**
     * Get ACF fields for a specific object type
     */
    private function get_acf_fields_for_object_type($object_type) {
        $fields = array();
        
        // Check if ACF is active
        if (!function_exists('acf_get_field_groups')) {
            return $fields;
        }
        
        // Get all field groups
        $field_groups = acf_get_field_groups();
        
        foreach ($field_groups as $group) {
            // Get fields for this group
            $group_fields = acf_get_fields($group);
            
            if ($group_fields) {
                foreach ($group_fields as $field) {
                    // Check if this field group applies to the current object type
                    if ($this->field_group_applies_to_object_type($group, $object_type)) {
                        $fields[] = array(
                            'value' => $field['name'],
                            'label' => $field['label'] . ' (' . $field['name'] . ')',
                            'type' => $field['type']
                        );
                    }
                }
            }
        }
        
        // Also include any custom fields that might be relevant
        $custom_fields = $this->get_custom_fields_for_object_type($object_type);
        $fields = array_merge($fields, $custom_fields);
        
        return $fields;
    }
    
    /**
     * Check if a field group applies to a specific object type
     */
    private function field_group_applies_to_object_type($group, $object_type) {
        // Check location rules
        if (isset($group['location']) && is_array($group['location'])) {
            foreach ($group['location'] as $rule_group) {
                foreach ($rule_group as $rule) {
                    // Check for post type rules
                    if ($rule['param'] === 'post_type' && $rule['operator'] === '==') {
                        $post_type = $rule['value'];
                        
                        // Map Salesforce object types to WordPress post types
                        $post_type_mapping = array(
                            'Lead' => array('post', 'page'),
                            'Contact' => array('post', 'page'),
                            'Account' => array('post', 'page'),
                            'Opportunity' => array('post', 'page')
                        );
                        
                        if (isset($post_type_mapping[$object_type])) {
                            return in_array($post_type, $post_type_mapping[$object_type]);
                        }
                    }
                    
                    // Check for user rules
                    if ($rule['param'] === 'user_form' && $object_type === 'Contact') {
                        return true;
                    }
                }
            }
        }
        
        // Default: include all field groups if no specific rules
        return true;
    }
    
    /**
     * Get custom fields that might be relevant for the object type
     */
    private function get_custom_fields_for_object_type($object_type) {
        $custom_fields = array();
        
        // Add some common custom fields based on object type
        switch ($object_type) {
            case 'Lead':
            case 'Contact':
                $custom_fields = array(
                    array('value' => 'phone', 'label' => 'Phone (custom)'),
                    array('value' => 'company', 'label' => 'Company (custom)'),
                    array('value' => 'address', 'label' => 'Address (custom)'),
                    array('value' => 'city', 'label' => 'City (custom)'),
                    array('value' => 'state', 'label' => 'State (custom)'),
                    array('value' => 'zip', 'label' => 'ZIP Code (custom)'),
                    array('value' => 'country', 'label' => 'Country (custom)')
                );
                break;
                
            case 'Account':
                $custom_fields = array(
                    array('value' => 'company_name', 'label' => 'Company Name (custom)'),
                    array('value' => 'industry', 'label' => 'Industry (custom)'),
                    array('value' => 'website', 'label' => 'Website (custom)'),
                    array('value' => 'phone', 'label' => 'Phone (custom)'),
                    array('value' => 'address', 'label' => 'Address (custom)')
                );
                break;
        }
        
        return $custom_fields;
    }
    
    /**
     * Handle export mappings AJAX request
     */
    public function handle_export_mappings_ajax() {
        check_ajax_referer('wsi_field_mapping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $mappings_file = WSI_PLUGIN_PATH . 'salesforce_field_mappings_from_csv.json';
        
        // Load existing mappings
        if (file_exists($mappings_file)) {
            $mappings_data = file_get_contents($mappings_file);
            $mappings = json_decode($mappings_data, true);
        } else {
            $mappings = array();
        }
        
        // Ensure $mappings is always an array
        if (!is_array($mappings)) {
            $mappings = array();
        }
        
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
        
        $mappings_file = WSI_PLUGIN_PATH . 'salesforce_field_mappings_from_csv.json';
        file_put_contents($mappings_file, json_encode($mappings, JSON_PRETTY_PRINT));
        
        $this->logger->info('Field mappings imported', array(
            'objects' => array_keys($mappings)
        ));
        
        wp_send_json_success('Mappings imported successfully');
    }
}
