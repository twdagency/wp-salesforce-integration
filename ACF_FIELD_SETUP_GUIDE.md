# ACF Field Setup Guide

## Overview

The WordPress Salesforce Integration plugin automatically creates required Advanced Custom Fields (ACF) for user profiles on activation. These fields store Salesforce data and sync status information.

## Required ACF Fields

The plugin creates the following ACF fields for user profiles:

### 1. Salesforce Lead ID (`salesforce_lead_id`)
- **Type**: Text
- **Description**: The Salesforce Lead ID for this user
- **Required**: No
- **Usage**: Stores the Lead ID when a user registers

### 2. Salesforce Contact ID (`salesforce_contact_id`)
- **Type**: Text
- **Description**: The Salesforce Contact ID for this user
- **Required**: No
- **Usage**: Stores the Contact ID when user is approved

### 3. Salesforce Account ID (`salesforce_account_id`)
- **Type**: Text
- **Description**: The Salesforce Account ID for this user
- **Required**: No
- **Usage**: Stores the Account ID when user is approved

### 4. Original Lead ID (`original_lead_id`)
- **Type**: Text
- **Description**: The original Salesforce Lead ID before conversion to Contact
- **Required**: No
- **Usage**: Preserves the original Lead ID after conversion

### 5. Salesforce Sync Status (`salesforce_sync_status`)
- **Type**: Select
- **Description**: Current sync status with Salesforce
- **Required**: No
- **Options**:
  - `not_synced` - Not Synced
  - `lead_created` - Lead Created
  - `contact_created` - Contact Created
  - `account_created` - Account Created
  - `migrated_from_miniorange` - Migrated from miniOrange
  - `sync_error` - Sync Error

### 6. Salesforce Migration Date (`salesforce_migration_date`)
- **Type**: Date Time Picker
- **Description**: Date when user was migrated from miniOrange plugin
- **Required**: No
- **Usage**: Tracks migration from miniOrange plugin

### 7. Last Salesforce Sync (`salesforce_last_sync`)
- **Type**: Date Time Picker
- **Description**: Last time this user was synced with Salesforce
- **Required**: No
- **Usage**: Tracks sync history

### 8. Salesforce Sync Errors (`salesforce_sync_errors`)
- **Type**: Textarea
- **Description**: Any errors encountered during Salesforce sync
- **Required**: No
- **Usage**: Stores error details for debugging

## Automatic Field Creation

### On Plugin Activation

When the plugin is activated, it automatically:

1. **Checks for ACF Plugin**: Verifies that Advanced Custom Fields is installed
2. **Creates Field Group**: Creates "Salesforce Integration Fields" field group
3. **Creates Fields**: Creates all required fields for user profiles
4. **Sets Permissions**: Configures fields to appear on all user forms
5. **Logs Activity**: Records field creation in the audit trail

### Field Group Configuration

- **Title**: Salesforce Integration Fields
- **Location**: User forms (all)
- **Style**: Default
- **Label Placement**: Top
- **Instruction Placement**: Label
- **Active**: Yes

## Manual Field Creation

If fields are not created automatically, you can create them manually:

### Via Admin Interface

1. Go to **WordPress Admin** → **Salesforce** → **ACF Fields**
2. Check the field status
3. Click **"Create Required Fields"** if needed
4. Fields will be created automatically

### Via Code

```php
$acf_setup = new WSI_ACF_Field_Setup();
$result = $acf_setup->create_fields_programmatically();
```

## Field Usage in Code

### Getting Field Values

```php
// Get Salesforce Lead ID
$lead_id = get_field('salesforce_lead_id', 'user_' . $user_id);

// Get sync status
$sync_status = get_field('salesforce_sync_status', 'user_' . $user_id);

// Get last sync date
$last_sync = get_field('salesforce_last_sync', 'user_' . $user_id);
```

### Setting Field Values

```php
// Set Salesforce Lead ID
update_field('salesforce_lead_id', '00Q1234567890ABC', 'user_' . $user_id);

// Set sync status
update_field('salesforce_sync_status', 'lead_created', 'user_' . $user_id);

// Set last sync date
update_field('salesforce_last_sync', current_time('Y-m-d H:i:s'), 'user_' . $user_id);
```

### Checking Field Existence

```php
$acf_setup = new WSI_ACF_Field_Setup();
$status = $acf_setup->get_field_status();

if ($status['acf_available'] && $status['fields_exist']) {
    // Fields are available
} else {
    // Fields need to be created
}
```

## Field Validation

### Required Fields Check

The plugin checks for the following required fields:
- `salesforce_lead_id`
- `salesforce_contact_id`
- `salesforce_account_id`
- `original_lead_id`
- `salesforce_sync_status`
- `salesforce_migration_date`
- `salesforce_last_sync`
- `salesforce_sync_errors`

### Field Type Validation

Each field is validated to ensure:
- Correct field type is used
- Proper configuration is set
- Field appears in user forms
- Field group is properly configured

## Troubleshooting

### Common Issues

**1. ACF Plugin Not Available**
- **Error**: "ACF plugin is not available"
- **Solution**: Install Advanced Custom Fields plugin
- **Action**: Go to Plugins → Add New → Search "Advanced Custom Fields"

**2. Fields Not Created**
- **Error**: Fields show as missing
- **Solution**: Use manual field creation
- **Action**: Go to Salesforce → ACF Fields → Create Required Fields

**3. Fields Not Visible**
- **Error**: Fields don't appear in user profile
- **Solution**: Check field group location settings
- **Action**: Verify field group is set to "User forms (all)"

**4. Field Values Not Saving**
- **Error**: Values not persisting
- **Solution**: Check field configuration
- **Action**: Verify field names and types are correct

### Debug Steps

1. **Check ACF Status**
   - Go to Salesforce → ACF Fields
   - Look for error messages
   - Verify ACF plugin is active

2. **Check Field Group**
   - Go to Custom Fields → Field Groups
   - Look for "Salesforce Integration Fields"
   - Verify location settings

3. **Check Individual Fields**
   - Go to Custom Fields → Field Groups
   - Edit "Salesforce Integration Fields"
   - Verify all 8 fields exist

4. **Check User Profile**
   - Go to Users → Edit User
   - Look for "Salesforce Integration Fields" section
   - Verify fields are visible

## Field Management

### Viewing Fields

Fields can be viewed in:
- **User Profile**: Edit user page
- **Admin Interface**: Salesforce → ACF Fields
- **Custom Fields**: Field Groups section

### Editing Fields

Fields can be edited in:
- **Custom Fields**: Field Groups → Edit Field Group
- **Individual Fields**: Edit specific field settings
- **Field Group**: Modify field group settings

### Deleting Fields

**⚠️ Warning**: Deleting these fields will break the integration!

To safely remove fields:
1. Deactivate the plugin first
2. Delete fields from Custom Fields
3. Remove field group
4. Reactivate plugin to recreate

## Integration Points

### User Registration

When a user registers:
1. `salesforce_lead_id` is set with the Lead ID
2. `salesforce_sync_status` is set to "lead_created"
3. `salesforce_last_sync` is set to current time

### User Approval

When a user is approved:
1. `salesforce_contact_id` is set with the Contact ID
2. `salesforce_account_id` is set with the Account ID
3. `original_lead_id` is set with the original Lead ID
4. `salesforce_sync_status` is updated to "contact_created"

### Migration from miniOrange

During migration:
1. Existing Salesforce IDs are preserved
2. `salesforce_migration_date` is set
3. `salesforce_sync_status` is set to "migrated_from_miniorange"

### Error Handling

When sync errors occur:
1. `salesforce_sync_errors` is updated with error details
2. `salesforce_sync_status` is set to "sync_error"
3. Error details are logged in audit trail

## Best Practices

### Field Usage
- Always check if fields exist before using
- Use proper field names (case-sensitive)
- Handle missing fields gracefully
- Validate field values before saving

### Performance
- Fields are cached by ACF
- Use efficient queries for field access
- Avoid unnecessary field updates
- Monitor field usage in audit trail

### Security
- Fields are only accessible to administrators
- User data is protected by WordPress permissions
- Field values are sanitized before saving
- Access is controlled by ACF permissions

## Support

If you encounter issues with ACF fields:

1. **Check the ACF Fields page** for status information
2. **Review the audit trail** for field creation logs
3. **Verify ACF plugin** is installed and active
4. **Test field creation** using the admin interface
5. **Check WordPress error logs** for detailed errors

The ACF field setup ensures seamless integration between WordPress users and Salesforce, providing a robust foundation for data synchronization and management.
