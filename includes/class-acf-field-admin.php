<?php
/**
 * ACF Field Admin Interface
 * Manages ACF field creation and status
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_ACF_Field_Admin {
    
    private $acf_setup;
    
    public function __construct() {
        $this->acf_setup = new WSI_ACF_Field_Setup();
        
        add_action('admin_menu', array($this, 'add_acf_menu'));
        add_action('wp_ajax_wsi_create_acf_fields', array($this, 'handle_create_fields_ajax'));
        add_action('wp_ajax_wsi_check_acf_status', array($this, 'handle_check_status_ajax'));
    }
    
    /**
     * Add ACF field menu to admin
     */
    public function add_acf_menu() {
        add_submenu_page(
            'wsi-dashboard',
            'ACF Fields',
            'ACF Fields',
            'manage_options',
            'wsi-acf-fields',
            array($this, 'acf_fields_page')
        );
    }
    
    /**
     * ACF fields admin page
     */
    public function acf_fields_page() {
        $field_status = $this->acf_setup->get_field_status();
        
        ?>
        <div class="wrap">
            <h1>ACF Field Management</h1>
            
            <!-- Field Status Overview -->
            <div class="acf-status-overview">
                <div class="status-card <?php echo $field_status['acf_available'] ? 'available' : 'unavailable'; ?>">
                    <div class="status-icon">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                    <div class="status-content">
                        <h3>ACF Plugin Status</h3>
                        <p class="status-text">
                            <?php echo $field_status['acf_available'] ? 'Available' : 'Not Available'; ?>
                        </p>
                        <?php if (!$field_status['acf_available']): ?>
                            <small>
                                <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>">
                                    Install ACF Plugin
                                </a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="status-card <?php echo $field_status['fields_exist'] ? 'created' : 'missing'; ?>">
                    <div class="status-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="status-content">
                        <h3>Required Fields</h3>
                        <p class="status-text">
                            <?php echo $field_status['fields_exist'] ? 'Created' : 'Missing'; ?>
                        </p>
                        <small>
                            <?php echo count($field_status['required_fields']); ?> fields required
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Field Management -->
            <?php if ($field_status['acf_available']): ?>
                <div class="field-management">
                    <h2>Field Management</h2>
                    
                    <?php if (!$field_status['fields_exist']): ?>
                        <div class="field-setup">
                            <h3>Setup Required Fields</h3>
                            <p>The following ACF fields are required for the WordPress Salesforce Integration to function properly:</p>
                            
                            <div class="required-fields-list">
                                <?php foreach ($field_status['required_fields'] as $field_name): ?>
                                    <div class="field-item">
                                        <span class="field-name"><?php echo esc_html($field_name); ?></span>
                                        <span class="field-status missing">Missing</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="field-actions">
                                <button id="create-acf-fields" class="button button-primary button-large">
                                    <span class="dashicons dashicons-plus"></span> Create Required Fields
                                </button>
                                <p class="description">
                                    This will create all required ACF fields for user profiles.
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="field-status-success">
                            <h3>✅ All Required Fields Created</h3>
                            <p>All required ACF fields have been successfully created and are available for use.</p>
                            
                            <div class="created-fields-list">
                                <?php foreach ($field_status['required_fields'] as $field_name): ?>
                                    <div class="field-item">
                                        <span class="field-name"><?php echo esc_html($field_name); ?></span>
                                        <span class="field-status created">Created</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Field Details -->
                <div class="field-details">
                    <h2>Field Details</h2>
                    <div class="field-specifications">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Field Name</th>
                                    <th>Field Type</th>
                                    <th>Description</th>
                                    <th>Required</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>salesforce_lead_id</code></td>
                                    <td>Text</td>
                                    <td>The Salesforce Lead ID for this user</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_contact_id</code></td>
                                    <td>Text</td>
                                    <td>The Salesforce Contact ID for this user</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_account_id</code></td>
                                    <td>Text</td>
                                    <td>The Salesforce Account ID for this user</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>original_lead_id</code></td>
                                    <td>Text</td>
                                    <td>The original Salesforce Lead ID before conversion to Contact</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_sync_status</code></td>
                                    <td>Select</td>
                                    <td>Current sync status with Salesforce</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_migration_date</code></td>
                                    <td>Date Time Picker</td>
                                    <td>Date when user was migrated from miniOrange plugin</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_last_sync</code></td>
                                    <td>Date Time Picker</td>
                                    <td>Last time this user was synced with Salesforce</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td><code>salesforce_sync_errors</code></td>
                                    <td>Textarea</td>
                                    <td>Any errors encountered during Salesforce sync</td>
                                    <td>No</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Field Usage -->
                <div class="field-usage">
                    <h2>Field Usage</h2>
                    <div class="usage-info">
                        <h3>How These Fields Are Used</h3>
                        <ul>
                            <li><strong>salesforce_lead_id</strong> - Stores the Salesforce Lead ID when a user registers</li>
                            <li><strong>salesforce_contact_id</strong> - Stores the Salesforce Contact ID when user is approved</li>
                            <li><strong>salesforce_account_id</strong> - Stores the Salesforce Account ID when user is approved</li>
                            <li><strong>original_lead_id</strong> - Preserves the original Lead ID after conversion to Contact</li>
                            <li><strong>salesforce_sync_status</strong> - Tracks the current sync status</li>
                            <li><strong>salesforce_migration_date</strong> - Records when user was migrated from miniOrange</li>
                            <li><strong>salesforce_last_sync</strong> - Tracks when user was last synced</li>
                            <li><strong>salesforce_sync_errors</strong> - Stores any sync errors for debugging</li>
                        </ul>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="acf-required">
                    <h2>ACF Plugin Required</h2>
                    <div class="acf-installation">
                        <p>The Advanced Custom Fields (ACF) plugin is required for this integration to work properly.</p>
                        <p>ACF provides the custom fields needed to store Salesforce data for each user.</p>
                        
                        <div class="install-actions">
                            <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>" class="button button-primary button-large">
                                <span class="dashicons dashicons-download"></span> Install ACF Plugin
                            </a>
                        </div>
                        
                        <div class="acf-info">
                            <h3>Why ACF is Required</h3>
                            <ul>
                                <li>Stores Salesforce Lead, Contact, and Account IDs</li>
                                <li>Tracks sync status and migration information</li>
                                <li>Provides user-friendly admin interface for field management</li>
                                <li>Enables data validation and formatting</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .acf-status-overview {
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
        
        .status-card.available {
            border-color: #46b450;
            background: #f7fff7;
        }
        
        .status-card.unavailable {
            border-color: #dc3232;
            background: #fff7f7;
        }
        
        .status-card.created {
            border-color: #46b450;
            background: #f7fff7;
        }
        
        .status-card.missing {
            border-color: #ff6b35;
            background: #fff7f0;
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
        
        .status-card.available .status-icon,
        .status-card.created .status-icon {
            background: #d4edda;
            color: #155724;
        }
        
        .status-card.unavailable .status-icon,
        .status-card.missing .status-icon {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-content h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .status-text {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .field-management {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .field-setup {
            text-align: center;
        }
        
        .required-fields-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .field-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .field-name {
            font-family: monospace;
            font-weight: bold;
        }
        
        .field-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .field-status.created {
            background: #d4edda;
            color: #155724;
        }
        
        .field-status.missing {
            background: #f8d7da;
            color: #721c24;
        }
        
        .field-actions {
            margin: 30px 0;
        }
        
        .field-status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .created-fields-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .field-details {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .field-specifications table {
            margin-top: 15px;
        }
        
        .field-specifications code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .field-usage {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .usage-info ul {
            margin-left: 20px;
        }
        
        .usage-info li {
            margin-bottom: 10px;
        }
        
        .acf-required {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .acf-installation {
            text-align: center;
        }
        
        .install-actions {
            margin: 30px 0;
        }
        
        .acf-info {
            text-align: left;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .acf-info ul {
            margin-left: 20px;
        }
        
        .acf-info li {
            margin-bottom: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Create ACF fields
            $('#create-acf-fields').on('click', function() {
                var button = $(this);
                var originalText = button.html();
                
                button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Creating Fields...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_create_acf_fields',
                        nonce: '<?php echo wp_create_nonce('wsi_acf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('ACF fields created successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice('Failed to create ACF fields: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Failed to create ACF fields due to an error', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false).html(originalText);
                    }
                });
            });
            
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
     * Handle create fields AJAX request
     */
    public function handle_create_fields_ajax() {
        check_ajax_referer('wsi_acf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->acf_setup->create_fields_programmatically();
        
        if ($result) {
            wp_send_json_success('ACF fields created successfully');
        } else {
            wp_send_json_error('Failed to create ACF fields');
        }
    }
    
    /**
     * Handle check status AJAX request
     */
    public function handle_check_status_ajax() {
        check_ajax_referer('wsi_acf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $status = $this->acf_setup->get_field_status();
        wp_send_json_success($status);
    }
}
