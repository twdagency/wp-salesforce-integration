<?php
/**
 * Migration Admin Interface
 * Provides admin interface for managing migration from miniOrange plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSI_Migration_Admin {
    
    private $migration_handler;
    private $miniorange_handler;
    
    public function __construct() {
        $this->migration_handler = new WSI_Migration_Handler();
        $this->miniorange_handler = new WSI_MiniOrange_Migration_Handler();
        
        add_action('admin_menu', array($this, 'add_migration_menu'));
        add_action('wp_ajax_wsi_start_migration', array($this, 'handle_migration_ajax'));
        add_action('wp_ajax_wsi_check_migration_status', array($this, 'handle_status_check_ajax'));
        add_action('wp_ajax_wsi_cleanup_miniorange', array($this, 'handle_cleanup_ajax'));
    }
    
    /**
     * Add migration menu to admin
     */
    public function add_migration_menu() {
        add_submenu_page(
            'wsi-settings',
            'Migration from miniOrange',
            'Migration',
            'manage_options',
            'wsi-migration',
            array($this, 'migration_page')
        );
    }
    
    /**
     * Migration admin page
     */
    public function migration_page() {
        $migration_status = $this->miniorange_handler->get_migration_status();
        ?>
        <div class="wrap">
            <h1>Migration from miniOrange Plugin</h1>
            
            <div class="notice notice-info">
                <p><strong>Important:</strong> This migration will safely transfer your existing Salesforce data from the miniOrange plugin to prevent duplicates.</p>
            </div>
            
            <div id="migration-status">
                <h2>Migration Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Migration Needed</th>
                        <td>
                            <?php if ($migration_status['migration_needed']): ?>
                                <span class="dashicons dashicons-warning" style="color: #ff6b35;"></span> Yes
                            <?php else: ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> No
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Migration Completed</th>
                        <td>
                            <?php if ($migration_status['migration_completed']): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> Yes
                                <?php if ($migration_status['migration_date']): ?>
                                    <br><small>Completed on: <?php echo esc_html($migration_status['migration_date']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #dc3232;"></span> No
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>MiniOrange Table Exists</th>
                        <td>
                            <?php if ($migration_status['miniorange_table_exists']): ?>
                                <span class="dashicons dashicons-warning" style="color: #ff6b35;"></span> Yes
                            <?php else: ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> No
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($migration_status['migration_needed']): ?>
                <div class="migration-actions">
                    <h2>Migration Actions</h2>
                    <p>Click the button below to start the migration process. This will:</p>
                    <ul>
                        <li>Identify existing Salesforce records created by miniOrange</li>
                        <li>Map WordPress users to their existing Salesforce records</li>
                        <li>Update user meta with Salesforce IDs</li>
                        <li>Prevent creation of duplicate records</li>
                    </ul>
                    
                    <button id="start-migration" class="button button-primary button-large">
                        <span class="dashicons dashicons-migrate"></span> Start Migration
                    </button>
                    
                    <div id="migration-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <p id="migration-status-text">Starting migration...</p>
                    </div>
                </div>
            <?php elseif ($migration_status['migration_completed']): ?>
                <div class="migration-completed">
                    <h2>Migration Completed Successfully</h2>
                    <p>Your data has been successfully migrated from the miniOrange plugin.</p>
                    
                    <?php if ($migration_status['miniorange_table_exists']): ?>
                        <div class="cleanup-section">
                            <h3>Cleanup MiniOrange Data</h3>
                            <p>You can now safely remove the miniOrange plugin data from your database.</p>
                            <button id="cleanup-miniorange" class="button button-secondary">
                                <span class="dashicons dashicons-trash"></span> Cleanup MiniOrange Data
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-migration-needed">
                    <h2>No Migration Needed</h2>
                    <p>No miniOrange plugin data was found, or migration has already been completed.</p>
                </div>
            <?php endif; ?>
            
            <div id="migration-results" style="display: none;">
                <h2>Migration Results</h2>
                <div id="migration-results-content"></div>
            </div>
        </div>
        
        <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .migration-actions {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .migration-completed {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .cleanup-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#start-migration').on('click', function() {
                startMigration();
            });
            
            $('#cleanup-miniorange').on('click', function() {
                cleanupMiniOrange();
            });
            
            function startMigration() {
                $('#start-migration').prop('disabled', true);
                $('#migration-progress').show();
                updateProgress(0, 'Starting migration...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_start_migration',
                        nonce: '<?php echo wp_create_nonce('wsi_migration_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateProgress(100, 'Migration completed successfully!');
                            showResults(response.data);
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            updateProgress(0, 'Migration failed: ' + response.data.message);
                            $('#start-migration').prop('disabled', false);
                        }
                    },
                    error: function() {
                        updateProgress(0, 'Migration failed due to an error');
                        $('#start-migration').prop('disabled', false);
                    }
                });
            }
            
            function cleanupMiniOrange() {
                if (!confirm('Are you sure you want to cleanup miniOrange data? This action cannot be undone.')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wsi_cleanup_miniorange',
                        nonce: '<?php echo wp_create_nonce('wsi_migration_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('MiniOrange data cleaned up successfully!');
                            location.reload();
                        } else {
                            alert('Cleanup failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Cleanup failed due to an error');
                    }
                });
            }
            
            function updateProgress(percent, text) {
                $('.progress-fill').css('width', percent + '%');
                $('#migration-status-text').text(text);
            }
            
            function showResults(data) {
                let html = '<h3>Migration Summary</h3>';
                html += '<ul>';
                html += '<li>Mapped Users: ' + (data.mapped_users || 0) + '</li>';
                html += '<li>MiniOrange Mappings: ' + (data.miniorange_mappings || 0) + '</li>';
                html += '</ul>';
                
                $('#migration-results-content').html(html);
                $('#migration-results').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Handle migration AJAX request
     */
    public function handle_migration_ajax() {
        check_ajax_referer('wsi_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->miniorange_handler->start_migration();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle status check AJAX request
     */
    public function handle_status_check_ajax() {
        check_ajax_referer('wsi_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $status = $this->miniorange_handler->get_migration_status();
        wp_send_json_success($status);
    }
    
    /**
     * Handle cleanup AJAX request
     */
    public function handle_cleanup_ajax() {
        check_ajax_referer('wsi_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->miniorange_handler->cleanup_miniorange_data();
        
        if ($result) {
            wp_send_json_success('MiniOrange data cleaned up successfully');
        } else {
            wp_send_json_error('Failed to cleanup MiniOrange data');
        }
    }
}
