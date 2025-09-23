# WordPress to Salesforce Integration

A comprehensive WordPress plugin that syncs custom post types with ACF (Advanced Custom Fields) data to Salesforce objects. This plugin handles complex data transformations, including ACF checkbox fields that aren't compatible with Salesforce boolean fields, and supports both regular and AJAX post updates.

## Features

- **Automatic Sync**: Automatically syncs WordPress posts to Salesforce when updated
- **ACF Support**: Full support for Advanced Custom Fields with intelligent data transformation
- **Checkbox Handling**: Multiple strategies for converting ACF checkbox arrays to Salesforce-compatible formats
- **AJAX Support**: Handles post updates triggered via AJAX calls
- **Field Mapping**: Flexible field mapping configuration between WordPress/ACF and Salesforce
- **Error Handling**: Comprehensive logging and error handling system
- **Background Processing**: Queue-based sync system to avoid blocking WordPress operations
- **Admin Interface**: User-friendly admin interface for configuration and monitoring

## Requirements

- WordPress 5.0 or higher
- Advanced Custom Fields (ACF) plugin
- Salesforce account with API access
- PHP 7.4 or higher

## Installation

1. Upload the `wp-salesforce-integration` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Salesforce Integration to configure the plugin

## Salesforce Setup

### 1. Create a Connected App

1. In Salesforce, go to Setup > App Manager
2. Click "New Connected App"
3. Fill in the required fields:
   - Connected App Name: "WordPress Integration"
   - API Name: "WordPress_Integration"
   - Contact Email: Your email
4. Enable OAuth Settings:
   - Callback URL: `https://yoursite.com/wp-admin/options-general.php?page=wp-salesforce-integration`
   - Selected OAuth Scopes:
     - Access and manage your data (api)
     - Perform requests on your behalf at any time (refresh_token, offline_access)
5. Save the Connected App
6. Note down the Consumer Key (Client ID) and Consumer Secret (Client Secret)

### 2. Create Custom Fields in Salesforce

Create the following custom fields in your Salesforce object:

- `WordPress_Post_ID__c` (Text, External ID)
- `WordPress_Post_Type__c` (Text)
- `WordPress_Status__c` (Text)
- `WordPress_Last_Updated__c` (DateTime)

### 3. Get Security Token

1. In Salesforce, go to your user settings
2. Click "Reset My Security Token"
3. Check your email for the security token

## Configuration

### 1. Connection Settings

Go to Settings > Salesforce Integration > Connection tab:

- **Client ID**: Your Salesforce Connected App Consumer Key
- **Client Secret**: Your Salesforce Connected App Consumer Secret
- **Username**: Your Salesforce username
- **Password**: Your Salesforce password
- **Security Token**: Your Salesforce security token
- **Sandbox Mode**: Check if using Salesforce sandbox

Click "Test Connection" to verify your credentials.

### 2. Post Type Configuration

In the Post Types tab:

1. Select which post types should be synced
2. For each post type, configure:
   - **Salesforce Object**: The Salesforce object name (e.g., "Custom_Object__c")
   - **External ID Field**: The field to use as external ID (default: "WordPress_Post_ID__c")

### 3. Field Mapping

In the Field Mapping tab:

1. For each WordPress/ACF field, specify the corresponding Salesforce field
2. For ACF checkbox fields, choose a transformation strategy:
   - **Semicolon Separated**: Values joined with semicolons
   - **Comma Separated**: Values joined with commas
   - **Pipe Separated**: Values joined with pipes
   - **JSON Array**: Values as JSON array string
   - **First Value Only**: Only the first selected value
   - **Count**: Number of selected values
   - **Boolean**: True if any values selected, false otherwise
   - **Custom Delimiter**: Use a custom separator

## ACF Checkbox Field Handling

The plugin provides multiple strategies for handling ACF checkbox fields that aren't compatible with Salesforce boolean fields:

### Example: ACF Checkbox Field "Interests"

If your ACF checkbox field has options like:
- Web Development
- Mobile Development
- Design
- Marketing

And a user selects "Web Development" and "Design", here's how each strategy works:

1. **Semicolon Separated**: `Web Development;Design`
2. **Comma Separated**: `Web Development,Design`
3. **JSON Array**: `["Web Development","Design"]`
4. **First Value Only**: `Web Development`
5. **Count**: `2`
6. **Boolean**: `true`

## AJAX Support

The plugin automatically handles AJAX post updates through several mechanisms:

1. **WordPress AJAX Hooks**: Listens for standard WordPress AJAX save actions
2. **ACF AJAX**: Handles ACF field saves triggered via AJAX
3. **Custom AJAX Endpoint**: Provides a custom AJAX endpoint for manual syncs

### Manual AJAX Sync

You can trigger a sync via AJAX using this JavaScript:

```javascript
jQuery.post(ajaxurl, {
    action: 'wsi_sync_post',
    nonce: 'your_nonce',
    post_id: postId
}, function(response) {
    if (response.success) {
        console.log('Sync successful');
    } else {
        console.log('Sync failed: ' + response.data);
    }
});
```

## Monitoring and Logs

### Sync Logs

The Sync Logs tab shows:
- Recent sync activity
- Success/failure status
- Trigger type (save_post, ajax, manual, etc.)
- Timestamps
- Manual re-sync capability

### Error Handling

The plugin includes comprehensive error handling:

- **Connection Errors**: Invalid credentials or network issues
- **Field Mapping Errors**: Missing or invalid field mappings
- **Data Transformation Errors**: Issues converting data formats
- **Salesforce API Errors**: Salesforce-specific error messages

All errors are logged and can be viewed in the admin interface.

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Verify your Salesforce credentials
   - Check if your security token is correct
   - Ensure your Connected App is properly configured

2. **Field Mapping Issues**
   - Verify Salesforce field names are correct
   - Check field types match (text fields can't store arrays)
   - Ensure external ID field is properly configured

3. **Checkbox Fields Not Syncing**
   - Choose appropriate transformation strategy
   - Verify target Salesforce field can accept the transformed data
   - Check field mapping configuration

4. **AJAX Updates Not Syncing**
   - Ensure the plugin hooks are properly loaded
   - Check for JavaScript errors in browser console
   - Verify AJAX actions are being triggered

### Debug Mode

Enable debug logging in the plugin settings to get detailed information about sync operations.

## API Reference

### Hooks and Filters

#### Actions

- `wsi_before_sync`: Fired before a post is synced to Salesforce
- `wsi_after_sync`: Fired after a successful sync
- `wsi_sync_failed`: Fired when a sync fails

#### Filters

- `wsi_field_mappings`: Modify field mappings before sync
- `wsi_salesforce_data`: Modify data before sending to Salesforce
- `wsi_should_sync_post`: Determine if a post should be synced

### Custom Integration

```php
// Add custom field mapping
add_filter('wsi_field_mappings', function($mappings) {
    $mappings['custom_field'] = array(
        'salesforce_field' => 'Custom_Field__c',
        'checkbox_strategy' => 'semicolon_separated'
    );
    return $mappings;
});

// Modify data before sync
add_filter('wsi_salesforce_data', function($data, $post_id) {
    $data['Custom_Field__c'] = 'Custom Value';
    return $data;
}, 10, 2);

// Skip sync for certain posts
add_filter('wsi_should_sync_post', function($should_sync, $post_id) {
    $post = get_post($post_id);
    if ($post->post_status === 'draft') {
        return false;
    }
    return $should_sync;
}, 10, 2);
```

## Support

For support and feature requests, please contact the plugin developer or create an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- ACF checkbox field transformation support
- AJAX update handling
- Comprehensive admin interface
- Error handling and logging system
