<?php
/**
 * Plugin Name: WordPress to Salesforce Integration
 * Description: Syncs custom post types with ACF fields to Salesforce objects
 * Version: 1.0.0
 * Author: TWDA
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WSI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WSI_VERSION', '1.0.0');

// Include required files
require_once WSI_PLUGIN_PATH . 'includes/class-salesforce-api.php';
require_once WSI_PLUGIN_PATH . 'includes/class-salesforce-oauth.php';
require_once WSI_PLUGIN_PATH . 'includes/class-salesforce-connection-admin.php';
require_once WSI_PLUGIN_PATH . 'includes/class-acf-field-setup.php';
require_once WSI_PLUGIN_PATH . 'includes/class-acf-field-admin.php';
require_once WSI_PLUGIN_PATH . 'includes/class-manual-sync.php';
require_once WSI_PLUGIN_PATH . 'includes/class-data-transformer.php';
require_once WSI_PLUGIN_PATH . 'includes/class-post-sync-handler.php';
require_once WSI_PLUGIN_PATH . 'includes/class-admin-settings.php';
require_once WSI_PLUGIN_PATH . 'includes/class-logger.php';
require_once WSI_PLUGIN_PATH . 'includes/class-salesforce-object-mapper.php';
require_once WSI_PLUGIN_PATH . 'includes/class-user-registration-handler.php';
require_once WSI_PLUGIN_PATH . 'includes/class-comprehensive-field-mapper.php';
require_once WSI_PLUGIN_PATH . 'includes/class-complete-salesforce-mapper.php';
require_once WSI_PLUGIN_PATH . 'includes/class-csv-based-salesforce-mapper.php';
require_once WSI_PLUGIN_PATH . 'includes/class-migration-handler.php';
require_once WSI_PLUGIN_PATH . 'includes/class-miniorange-migration-handler.php';
require_once WSI_PLUGIN_PATH . 'includes/class-migration-admin.php';
require_once WSI_PLUGIN_PATH . 'includes/class-field-mapping-manager.php';
require_once WSI_PLUGIN_PATH . 'includes/class-audit-trail.php';
require_once WSI_PLUGIN_PATH . 'includes/class-admin-dashboard.php';
require_once WSI_PLUGIN_PATH . 'includes/class-field-mapping-admin.php';

// Include custom configuration (if exists)
if (file_exists(WSI_PLUGIN_PATH . 'waste-trading-config.php')) {
    require_once WSI_PLUGIN_PATH . 'waste-trading-config.php';
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'wsi_plugin_activation');
register_deactivation_hook(__FILE__, 'wsi_plugin_deactivation');

/**
 * Plugin activation function
 */
function wsi_plugin_activation() {
    // Create audit trail table
    $audit_trail = new WSI_Audit_Trail();
    $audit_trail->create_audit_table();
    
    // Create ACF fields
    $acf_setup = new WSI_ACF_Field_Setup();
    $result = $acf_setup->create_fields_on_activation();
    
    // Log activation
    $logger = new WSI_Logger();
    $logger->info('Plugin activated', array(
        'acf_fields_created' => $result['success'],
        'acf_message' => $result['message']
    ));
    
    // Set activation flag
    update_option('wsi_plugin_activated', true);
    update_option('wsi_activation_date', current_time('mysql'));
}

/**
 * Plugin deactivation function
 */
function wsi_plugin_deactivation() {
    // Log deactivation
    $logger = new WSI_Logger();
    $logger->info('Plugin deactivated');
    
    // Clear activation flag
    delete_option('wsi_plugin_activated');
}

/**
 * Main plugin class
 */
class WordPress_Salesforce_Integration {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Initialize the logger
        new WSI_Logger();
        
        // Initialize the post sync handler
        new WSI_Post_Sync_Handler();
        
        // Initialize user registration handler
        new WSI_User_Registration_Handler();
        
        // Initialize manual sync
        new WSI_Manual_Sync();
        
        // Initialize admin dashboard
        new WSI_Admin_Dashboard();
        
        // Initialize field mapping manager
        new WSI_Field_Mapping_Manager();
        
        // Initialize migration handler
        new WSI_Migration_Handler();
        
        // Initialize ACF field setup
        new WSI_ACF_Field_Setup();
        
        // Initialize ACF field admin
        new WSI_ACF_Field_Admin();
        
        // Initialize Salesforce connection admin
        new WSI_Salesforce_Connection_Admin();
        
        // Load admin settings if in admin
        if (is_admin()) {
            new WSI_Admin_Settings();
        }
    }
}

// Initialize the plugin
new WordPress_Salesforce_Integration();