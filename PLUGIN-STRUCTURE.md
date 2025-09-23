# WordPress to Salesforce Integration - Plugin Structure

## Complete Plugin Overview

This WordPress plugin provides comprehensive integration between WordPress custom post types with ACF fields and Salesforce objects. It handles complex data transformations, including ACF checkbox fields that aren't compatible with Salesforce boolean fields, and supports both regular and AJAX post updates.

## File Structure

```
wp-salesforce-integration/
├── wp-salesforce-integration.php          # Main plugin file
├── README.md                              # Comprehensive documentation
├── PLUGIN-STRUCTURE.md                    # This file
├── example-usage.php                      # Usage examples and customization
├── includes/
│   ├── class-salesforce-api.php          # Salesforce API integration
│   ├── class-data-transformer.php        # ACF field transformation
│   ├── class-post-sync-handler.php       # WordPress hooks and sync logic
│   ├── class-admin-settings.php          # Admin interface
│   └── class-logger.php                  # Error handling and logging
└── assets/
    ├── admin.js                          # Admin interface JavaScript
    ├── admin.css                         # Admin interface styles
    └── frontend-sync.js                  # Frontend AJAX sync functionality
```

## Core Features Implemented

### 1. Salesforce API Integration (`class-salesforce-api.php`)
- OAuth 2.0 authentication with username/password flow
- Automatic token refresh handling
- Upsert operations for creating/updating records
- Comprehensive error handling
- Connection testing functionality

### 2. Data Transformation (`class-data-transformer.php`)
- **ACF Checkbox Handling**: Multiple strategies for converting checkbox arrays:
  - Semicolon separated
  - Comma separated
  - Pipe separated
  - JSON array
  - First value only
  - Count (number)
  - Boolean (true if any selected)
  - Custom delimiter
- Support for all ACF field types:
  - Text, Textarea, WYSIWYG
  - Number, Email, URL
  - Date, DateTime
  - True/False (boolean)
  - Select, Radio
  - Relationship, Post Object
  - Image, File, Gallery
  - Repeater, Group fields
- Custom transformation functions

### 3. Post Sync Handler (`class-post-sync-handler.php`)
- **WordPress Hooks**: Handles all post update scenarios:
  - `save_post` - Standard post saves
  - `post_updated` - Post modifications
  - `acf/save_post` - ACF field updates
  - `trash_post` / `untrash_post` - Post status changes
- **AJAX Support**: 
  - WordPress AJAX hooks
  - Custom AJAX endpoints
  - ACF AJAX field saves
- **Background Processing**: Queue-based sync to avoid blocking
- **Manual Sync**: Programmatic sync capabilities

### 4. Admin Interface (`class-admin-settings.php`)
- **Tabbed Interface**:
  - Connection settings
  - Post type configuration
  - Field mapping
  - Sync logs
- **Real-time Features**:
  - Connection testing
  - Manual sync triggers
  - Live field mapping
  - Sync status monitoring

### 5. Logging System (`class-logger.php`)
- **Database Logging**: Custom table for persistent logs
- **Log Levels**: Error, Warning, Info, Debug
- **Advanced Features**:
  - Log filtering and search
  - Export functionality (CSV/JSON)
  - Automatic cleanup
  - Statistics and analytics

## Key Solutions for Your Requirements

### ACF Checkbox Compatibility
The plugin solves the ACF checkbox → Salesforce boolean incompatibility by providing multiple transformation strategies:

```php
// Example: ACF checkbox with values ['Web Dev', 'Design']
// Strategy: 'semicolon_separated' → 'Web Dev;Design'
// Strategy: 'boolean' → true (if any selected)
// Strategy: 'count' → 2 (number of selections)
```

### AJAX Update Handling
Comprehensive AJAX support through multiple mechanisms:
- WordPress AJAX hooks (`wp_ajax_*`)
- ACF AJAX field saves
- Custom AJAX endpoints
- Background queue processing

### Post Update Triggers
The plugin automatically detects and handles:
- Standard WordPress post saves
- ACF field updates (both manual and AJAX)
- Post status changes
- Bulk operations
- Frontend form submissions

## Installation & Setup

1. **Upload Plugin**: Place in `/wp-content/plugins/`
2. **Activate**: Through WordPress admin
3. **Configure**: Go to Settings > Salesforce Integration
4. **Setup Salesforce**: Create Connected App and custom fields
5. **Map Fields**: Configure field mappings in admin interface

## Usage Examples

### Basic Field Mapping
```php
// Map ACF checkbox to Salesforce text field
$mappings['interests'] = array(
    'salesforce_field' => 'Interests__c',
    'checkbox_strategy' => 'semicolon_separated'
);
```

### Custom Transformation
```php
// Custom checkbox transformation
add_filter('wsi_checkbox_transformation', function($value, $field_name) {
    if ($field_name === 'special_categories') {
        return implode(' | ', $value);
    }
    return $value;
}, 10, 2);
```

### AJAX Sync
```javascript
// Frontend AJAX sync
jQuery.post(ajaxurl, {
    action: 'wsi_sync_post',
    post_id: 123,
    nonce: nonce
}, function(response) {
    console.log('Sync result:', response);
});
```

## Error Handling

- **Connection Errors**: Invalid credentials, network issues
- **Field Mapping Errors**: Missing or invalid mappings
- **Data Transformation Errors**: Format conversion issues
- **Salesforce API Errors**: Salesforce-specific errors
- **Comprehensive Logging**: All errors logged with context

## Performance Features

- **Background Processing**: Non-blocking sync operations
- **Queue System**: Handles high-volume updates
- **Token Caching**: Reduces authentication overhead
- **Batch Operations**: Efficient bulk processing
- **Error Recovery**: Automatic retry mechanisms

## Security Features

- **Nonce Verification**: CSRF protection for AJAX calls
- **Capability Checks**: Proper permission validation
- **Data Sanitization**: Input validation and sanitization
- **Secure Storage**: Encrypted credential storage
- **Access Control**: Admin-only configuration

## Extensibility

- **Hooks & Filters**: Extensive customization points
- **Custom Transformations**: User-defined data processing
- **Event System**: Custom actions and filters
- **API Integration**: Programmatic access to all features
- **Modular Design**: Easy to extend and modify

This plugin provides a complete, production-ready solution for syncing WordPress custom post types with ACF fields to Salesforce, with special attention to handling incompatible field types like ACF checkboxes and ensuring AJAX updates work seamlessly.
