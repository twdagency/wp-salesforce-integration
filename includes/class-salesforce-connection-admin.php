<?php
/**
 * Salesforce Connection Admin Interface
 * Manages OAuth2 connection settings and testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Salesforce_Connection_Admin {
    
    private $oauth;
    
    public function __construct() {
        $this->oauth = new WSI_Salesforce_OAuth();
        
        add_action('admin_menu', array($this, 'add_connection_menu'));
        add_action('wp_ajax_wsi_save_connection_settings', array($this, 'handle_save_settings_ajax'));
        add_action('wp_ajax_wsi_test_connection', array($this, 'handle_test_connection_ajax'));
        add_action('wp_ajax_wsi_disconnect', array($this, 'handle_disconnect_ajax'));
        add_action('wp_ajax_wsi_get_objects', array($this, 'handle_get_objects_ajax'));
        add_action('wp_ajax_wsi_get_object_fields', array($this, 'handle_get_object_fields_ajax'));
        
        // Handle OAuth callback
        add_action('init', array($this, 'handle_oauth_callback'));
    }
    
    /**
     * Add connection menu to admin
     */
    public function add_connection_menu() {
        add_submenu_page(
            'wsi-dashboard',
            'Salesforce Connection',
            'Connection',
            'manage_options',
            'wsi-connection',
            array($this, 'connection_page')
        );
    }
    
    /**
     * Connection admin page
     */
    public function connection_page() {
        $connection_status = $this->oauth->get_connection_status();
        $org_info = $this->oauth->get_organization_info();
        
        ?>
        <div class="wrap">
            <h1>Salesforce Connection Settings</h1>
            
            <!-- Connection Status -->
            <div class="connection-status">
                <div class="status-card <?php echo $connection_status['connected'] ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <div class="status-content">
                        <h3>Connection Status</h3>
                        <p class="status-text">
                            <?php echo $connection_status['connected'] ? 'Connected' : 'Disconnected'; ?>
                        </p>
                        <small>
                            Last checked: <?php echo esc_html($connection_status['last_check']); ?>
                            <?php if ($connection_status['token_expires']): ?>
                                <br>Token expires: <?php echo esc_html(date('Y-m-d H:i:s', $connection_status['token_expires'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                
                <?php if ($connection_status['connected'] && !isset($org_info['error'])): ?>
                    <div class="org-info">
                        <h4>Organization Information</h4>
                        <div class="org-details">
                            <p><strong>Name:</strong> <?php echo esc_html($org_info['Name'] ?? 'N/A'); ?></p>
                            <p><strong>Type:</strong> <?php echo esc_html($org_info['OrganizationType'] ?? 'N/A'); ?></p>
                            <p><strong>Instance URL:</strong> <?php echo esc_html($connection_status['instance_url']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Connection Settings -->
            <div class="connection-settings">
                <h2>OAuth2 Configuration</h2>
                <form id="connection-form" method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="authorization_uri">Authorization URI</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="authorization_uri" 
                                       name="authorization_uri" 
                                       value="<?php echo esc_attr(get_option('wsi_oauth_authorization_uri', '')); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">
                                    Your Salesforce instance URL (e.g., https://letsrecycleit.my.salesforce.com)
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="client_id">Application ID (Client ID)</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="client_id" 
                                       name="client_id" 
                                       value="<?php echo esc_attr(get_option('wsi_oauth_client_id', '')); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">
                                    The Application ID from your Salesforce Connected App
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="client_secret">Client Secret</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="client_secret" 
                                       name="client_secret" 
                                       value="<?php echo esc_attr(get_option('wsi_oauth_client_secret', '')); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">
                                    The Client Secret from your Salesforce Connected App
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="redirect_uri">Redirect URI</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="redirect_uri" 
                                       name="redirect_uri" 
                                       value="<?php echo esc_attr(get_option('wsi_oauth_redirect_uri', '')); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">
                                    Must match the Redirect URI in your Salesforce Connected App
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="scopes">Scopes</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="scopes" 
                                       name="scopes" 
                                       value="<?php echo esc_attr(get_option('wsi_oauth_scopes', 'api refresh_token')); ?>" 
                                       class="regular-text">
                                <p class="description">
                                    OAuth scopes (space-separated, e.g., "api refresh_token")
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="connection-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-admin-generic"></span> Save Settings
                        </button>
                        
                        <?php if ($connection_status['has_credentials']): ?>
                            <?php if ($connection_status['connected']): ?>
                                <button type="button" id="test-connection" class="button button-secondary">
                                    <span class="dashicons dashicons-admin-tools"></span> Test Connection
                                </button>
                                <button type="button" id="disconnect" class="button button-secondary">
                                    <span class="dashicons dashicons-no"></span> Disconnect
                                </button>
                            <?php else: ?>
                                <a href="<?php echo esc_url($this->oauth->get_authorization_url()); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-cloud"></span> Connect to Salesforce
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- API Testing -->
            <?php if ($connection_status['connected']): ?>
                <div class="api-testing">
                    <h2>API Testing</h2>
                    
                    <div class="test-section">
                        <h3>Available Objects</h3>
                        <div class="test-controls">
                            <button id="load-objects" class="button">
                                <span class="dashicons dashicons-list-view"></span> Load Objects
                            </button>
                        </div>
                        <div id="objects-list" class="test-results"></div>
                    </div>
                    
                    <div class="test-section">
                        <h3>Object Fields</h3>
                        <div class="test-controls">
                            <select id="object-select" class="regular-text">
                                <option value="">Select an object...</option>
                            </select>
                            <button id="load-fields" class="button" disabled>
                                <span class="dashicons dashicons-admin-settings"></span> Load Fields
                            </button>
                        </div>
                        <div id="fields-list" class="test-results"></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Help Section -->
            <div class="connection-help">
                <h2>Setup Instructions</h2>
                <div class="help-content">
                    <h3>1. Create a Salesforce Connected App</h3>
                    <ol>
                        <li>Go to <strong>Setup</strong> → <strong>App Manager</strong> in Salesforce</li>
                        <li>Click <strong>New Connected App</strong></li>
                        <li>Fill in the required fields:
                            <ul>
                                <li><strong>Connected App Name:</strong> WordPress Integration</li>
                                <li><strong>API Name:</strong> WordPress_Integration</li>
                                <li><strong>Contact Email:</strong> Your email</li>
                            </ul>
                        </li>
                        <li>Enable <strong>OAuth Settings</strong></li>
                        <li>Set <strong>Callback URL:</strong> <code><?php echo admin_url('admin.php?page=wsi-connection'); ?></code></li>
                        <li>Select <strong>OAuth Scopes:</strong>
                            <ul>
                                <li>Access and manage your data (api)</li>
                                <li>Perform requests on your behalf at any time (refresh_token)</li>
                            </ul>
                        </li>
                        <li>Save the app and note the <strong>Consumer Key</strong> and <strong>Consumer Secret</strong></li>
                    </ol>
                    
                    <h3>2. Configure the Plugin</h3>
                    <ol>
                        <li>Enter your Salesforce instance URL in <strong>Authorization URI</strong></li>
                        <li>Enter the <strong>Consumer Key</strong> as <strong>Application ID</strong></li>
                        <li>Enter the <strong>Consumer Secret</strong> as <strong>Client Secret</strong></li>
                        <li>Enter the callback URL as <strong>Redirect URI</strong></li>
                        <li>Save the settings</li>
                        <li>Click <strong>Connect to Salesforce</strong> to authorize</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <style>
        .connection-status {
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
        
        .status-card.connected {
            border-color: #46b450;
            background: #f7fff7;
        }
        
        .status-card.disconnected {
            border-color: #dc3232;
            background: #fff7f7;
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
        
        .status-card.connected .status-icon {
            background: #d4edda;
            color: #155724;
        }
        
        .status-card.disconnected .status-icon {
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
        
        .org-info {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            flex: 1;
        }
        
        .org-details p {
            margin: 5px 0;
        }
        
        .connection-settings {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .connection-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .api-testing {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .test-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .test-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .test-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .test-results {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .connection-help {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .help-content ol {
            margin-left: 20px;
        }
        
        .help-content ul {
            margin-left: 20px;
        }
        
        .help-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Save settings
            $('#connection-form').on('submit', function(e) {
                e.preventDefault();
                saveSettings();
            });
            
            // Test connection
            $('#test-connection').on('click', function() {
                testConnection();
            });
            
            // Disconnect
            $('#disconnect').on('click', function() {
                if (confirm('Are you sure you want to disconnect from Salesforce?')) {
                    disconnect();
                }
            });
            
            // Load objects
            $('#load-objects').on('click', function() {
                loadObjects();
            });
            
            // Load fields
            $('#load-fields').on('click', function() {
                var objectName = $('#object-select').val();
                if (objectName) {
                    loadFields(objectName);
                }
            });
            
            // Enable/disable load fields button
            $('#object-select').on('change', function() {
                $('#load-fields').prop('disabled', !$(this).val());
            });
            
            function saveSettings() {
                var formData = $('#connection-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=wsi_save_connection_settings&nonce=<?php echo wp_create_nonce('wsi_connection_nonce'); ?>',
                    success: function(response) {
                        if (response.success) {
                            showNotice('Settings saved successfully', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice('Failed to save settings: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Failed to save settings due to an error', 'error');
                    }
                });
            }
            
            function testConnection() {
                $('#test-connection').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_test_connection',
                        nonce: '<?php echo wp_create_nonce('wsi_connection_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Connection test successful!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice('Connection test failed: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Connection test failed due to an error', 'error');
                    },
                    complete: function() {
                        $('#test-connection').prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Test Connection');
                    }
                });
            }
            
            function disconnect() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_disconnect',
                        nonce: '<?php echo wp_create_nonce('wsi_connection_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('Disconnected successfully', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice('Failed to disconnect: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Failed to disconnect due to an error', 'error');
                    }
                });
            }
            
            function loadObjects() {
                $('#load-objects').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_objects',
                        nonce: '<?php echo wp_create_nonce('wsi_connection_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayObjects(response.data);
                        } else {
                            showNotice('Failed to load objects: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Failed to load objects due to an error', 'error');
                    },
                    complete: function() {
                        $('#load-objects').prop('disabled', false).html('<span class="dashicons dashicons-list-view"></span> Load Objects');
                    }
                });
            }
            
            function loadFields(objectName) {
                $('#load-fields').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_get_object_fields',
                        object_name: objectName,
                        nonce: '<?php echo wp_create_nonce('wsi_connection_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayFields(response.data);
                        } else {
                            showNotice('Failed to load fields: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showNotice('Failed to load fields due to an error', 'error');
                    },
                    complete: function() {
                        $('#load-fields').prop('disabled', false).html('<span class="dashicons dashicons-admin-settings"></span> Load Fields');
                    }
                });
            }
            
            function displayObjects(objects) {
                var html = '<div class="objects-grid">';
                objects.forEach(function(obj) {
                    html += '<div class="object-item">';
                    html += '<strong>' + obj.name + '</strong>';
                    html += '<br><small>' + obj.label + '</small>';
                    html += '</div>';
                });
                html += '</div>';
                
                $('#objects-list').html(html);
                
                // Populate object select
                var select = $('#object-select');
                select.empty().append('<option value="">Select an object...</option>');
                objects.forEach(function(obj) {
                    select.append('<option value="' + obj.name + '">' + obj.label + ' (' + obj.name + ')</option>');
                });
            }
            
            function displayFields(fields) {
                var html = '<div class="fields-grid">';
                fields.forEach(function(field) {
                    html += '<div class="field-item">';
                    html += '<strong>' + field.name + '</strong>';
                    html += '<br><small>Type: ' + field.type + '</small>';
                    if (field.label) {
                        html += '<br><small>Label: ' + field.label + '</small>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                
                $('#fields-list').html(html);
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
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (isset($_GET['code']) && isset($_GET['state']) && $_GET['state'] === 'wsi_oauth') {
            try {
                $this->oauth->exchange_code_for_token($_GET['code']);
                wp_redirect(admin_url('admin.php?page=wsi-connection&connected=1'));
                exit;
            } catch (Exception $e) {
                wp_redirect(admin_url('admin.php?page=wsi-connection&error=' . urlencode($e->getMessage())));
                exit;
            }
        }
    }
    
    /**
     * Handle save settings AJAX request
     */
    public function handle_save_settings_ajax() {
        check_ajax_referer('wsi_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $authorization_uri = sanitize_url($_POST['authorization_uri']);
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = sanitize_text_field($_POST['client_secret']);
        $redirect_uri = sanitize_url($_POST['redirect_uri']);
        $scopes = sanitize_text_field($_POST['scopes']);
        
        update_option('wsi_oauth_authorization_uri', $authorization_uri);
        update_option('wsi_oauth_client_id', $client_id);
        update_option('wsi_oauth_client_secret', $client_secret);
        update_option('wsi_oauth_redirect_uri', $redirect_uri);
        update_option('wsi_oauth_scopes', $scopes);
        
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection_ajax() {
        check_ajax_referer('wsi_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->oauth->test_connection();
        
        if ($result['success']) {
            wp_send_json_success('Connection test successful');
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Handle disconnect AJAX request
     */
    public function handle_disconnect_ajax() {
        check_ajax_referer('wsi_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->oauth->clear_tokens();
        wp_send_json_success('Disconnected successfully');
    }
    
    /**
     * Handle get objects AJAX request
     */
    public function handle_get_objects_ajax() {
        check_ajax_referer('wsi_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $objects = $this->oauth->get_objects();
        
        if (isset($objects['error'])) {
            wp_send_json_error($objects['error']);
        } else {
            wp_send_json_success($objects);
        }
    }
    
    /**
     * Handle get object fields AJAX request
     */
    public function handle_get_object_fields_ajax() {
        check_ajax_referer('wsi_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $object_name = sanitize_text_field($_POST['object_name']);
        $fields = $this->oauth->get_object_fields($object_name);
        
        if (isset($fields['error'])) {
            wp_send_json_error($fields['error']);
        } else {
            wp_send_json_success($fields);
        }
    }
}
