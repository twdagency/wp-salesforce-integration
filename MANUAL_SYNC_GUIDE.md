# Manual Sync Guide

## Overview

The WordPress Salesforce Integration plugin provides comprehensive manual sync capabilities for individual posts and users, as well as bulk sync operations. This allows administrators to have granular control over what gets synced to Salesforce and when.

## Individual Sync Features

### Post-Level Sync

**Location**: Individual post edit pages
**Access**: Edit any post → Salesforce Sync meta box (right sidebar)

#### Features:
- **Sync Status Display**: Shows current sync status (Synced, Not Synced, Error)
- **Salesforce ID Display**: Shows the Salesforce record ID if synced
- **Last Sync Time**: Displays when the post was last synced
- **One-Click Sync**: Button to sync individual posts to Salesforce
- **Status Check**: Button to verify current sync status
- **Progress Indicator**: Visual progress bar during sync
- **Error Handling**: Clear error messages if sync fails

#### Supported Post Types:
- **Posts** → Sales Listing
- **Waste Listings** → Sales Listing  
- **Wanted Listings** → Wanted Listings
- **Offers** → Offers
- **Haulage Offers** → Haulage Offers
- **Haulage Loads** → Haulage Loads
- **Sample Requests** → Sample Requests
- **MFI Tests** → MFI Tests

#### Sync Process:
1. **Data Transformation**: WordPress post data is transformed to Salesforce format
2. **Field Mapping**: Uses CSV-based field mappings for accurate data transfer
3. **WordPress Integration Fields**: Adds WordPress-specific fields to Salesforce
4. **Create/Update Logic**: Creates new records or updates existing ones
5. **Meta Updates**: Updates post meta with Salesforce ID and sync status

### User-Level Sync

**Location**: User profile pages
**Access**: Users → Edit User → Salesforce Sync section

#### Features:
- **Sync Status Display**: Shows current sync status with visual indicators
- **Salesforce IDs Display**: Shows Lead, Contact, and Account IDs
- **Last Sync Time**: Displays when the user was last synced
- **One-Click Sync**: Button to sync individual users to Salesforce
- **Status Check**: Button to verify current sync status
- **Progress Indicator**: Visual progress bar during sync
- **Error Handling**: Clear error messages if sync fails

#### Sync Logic:
- **New Users**: Creates Salesforce Lead
- **Existing Users**: Updates existing Lead, Contact, and Account records
- **Lead Conversion**: Tracks Lead → Contact/Account conversion
- **Migration Support**: Handles users migrated from miniOrange plugin

## Bulk Sync Features

### Dashboard Bulk Sync

**Location**: Salesforce Dashboard
**Access**: WordPress Admin → Salesforce → Dashboard

#### Bulk Sync Users:
- **Scope**: All WordPress users
- **Process**: Creates Leads for new users, updates existing records
- **Progress**: Real-time progress indication
- **Results**: Summary of synced, errors, and skipped users

#### Bulk Sync Posts:
- **Scope**: All published posts of supported types
- **Process**: Creates/updates Salesforce records based on post type
- **Progress**: Real-time progress indication
- **Results**: Summary of synced, errors, and skipped posts

## Sync Status Indicators

### Visual Status Indicators

**Green (Synced)**:
- Item has been successfully synced to Salesforce
- Salesforce ID is available
- Last sync time is recorded

**Red (Not Synced)**:
- Item has not been synced to Salesforce
- No Salesforce ID available
- Ready for sync

**Yellow (Error)**:
- Sync attempt failed
- Error details available
- Requires attention

### Status Values

**For Users**:
- `not_synced` - Not Synced
- `lead_created` - Lead Created
- `contact_created` - Contact Created
- `account_created` - Account Created
- `migrated_from_miniorange` - Migrated from miniOrange
- `sync_error` - Sync Error

**For Posts**:
- `synced` - Successfully synced
- `not-synced` - Not synced
- `error` - Sync error occurred

## Data Transformation

### WordPress to Salesforce Mapping

**Post Data**:
- Post title → Name field
- Post content → Description field
- Post author → Author information
- Post date → Created date
- Post modified → Last modified date
- Custom fields → Mapped Salesforce fields

**User Data**:
- User display name → First Name/Last Name
- User email → Email
- User meta → Mapped Salesforce fields
- ACF fields → Mapped Salesforce fields

### WordPress Integration Fields

**Added to Salesforce Records**:
- `WordPress_User_ID__c` - WordPress user ID
- `WP_Post_ID__c` - WordPress post ID
- `WP_Author_ID__c` - WordPress author ID
- `WP_Published_Datetime__c` - WordPress publish date
- `WP_Modified_Datetime__c` - WordPress modified date
- `WP_Permalink__c` - WordPress permalink
- `WP_Slug__c` - WordPress post slug

## Error Handling

### Error Types

**Connection Errors**:
- Salesforce API unavailable
- Authentication failures
- Network timeouts

**Data Errors**:
- Invalid field values
- Missing required fields
- Data type mismatches

**Permission Errors**:
- Insufficient Salesforce permissions
- Field access restrictions
- Object access limitations

### Error Recovery

**Automatic Retry**:
- Built-in retry logic for transient errors
- Exponential backoff for rate limiting
- Maximum retry attempts

**Manual Recovery**:
- Clear error status and retry
- Fix data issues and resync
- Check Salesforce permissions

**Error Logging**:
- Detailed error messages in audit trail
- WordPress error logs
- Salesforce debug logs

## Performance Considerations

### Individual Sync
- **Speed**: Fast for single items
- **Resource Usage**: Low
- **User Experience**: Immediate feedback
- **Error Handling**: Detailed error messages

### Bulk Sync
- **Speed**: Slower for large datasets
- **Resource Usage**: Higher
- **User Experience**: Progress indication
- **Error Handling**: Summary reporting

### Optimization Tips
- **Batch Processing**: Process items in batches
- **Rate Limiting**: Respect Salesforce API limits
- **Caching**: Cache field mappings and configurations
- **Background Processing**: Use WordPress cron for large operations

## Security Features

### Permission Checks
- **Admin Only**: Manual sync requires administrator privileges
- **Nonce Verification**: CSRF protection for all AJAX requests
- **Capability Checks**: WordPress capability system integration

### Data Validation
- **Input Sanitization**: All user input is sanitized
- **Output Escaping**: All output is properly escaped
- **SQL Injection Protection**: Prepared statements used

### Audit Trail
- **Operation Logging**: All sync operations are logged
- **User Tracking**: Tracks which user performed sync
- **Timestamp Recording**: Records when sync occurred
- **Error Logging**: Detailed error information

## Usage Examples

### Sync Individual Post
```php
$manual_sync = new WSI_Manual_Sync();
$result = $manual_sync->sync_post_to_salesforce($post_id);
```

### Sync Individual User
```php
$manual_sync = new WSI_Manual_Sync();
$result = $manual_sync->sync_user_to_salesforce($user_id);
```

### Check Sync Status
```php
$manual_sync = new WSI_Manual_Sync();
$status = $manual_sync->get_post_sync_status($post_id);
$user_status = $manual_sync->get_user_sync_status($user_id);
```

### Bulk Sync via Dashboard
```javascript
// Trigger bulk user sync
jQuery('#bulk-sync-users').click();

// Trigger bulk post sync
jQuery('#bulk-sync-posts').click();
```

## Troubleshooting

### Common Issues

**1. Sync Button Not Appearing**
- Check if user has administrator privileges
- Verify plugin is active
- Check for JavaScript errors

**2. Sync Fails Immediately**
- Check Salesforce connection
- Verify field mappings exist
- Check WordPress error logs

**3. Partial Sync Success**
- Check individual item errors
- Review audit trail for details
- Verify data completeness

**4. Performance Issues**
- Reduce batch sizes
- Check server resources
- Monitor API rate limits

### Debug Steps

1. **Check Connection**: Verify Salesforce connection in settings
2. **Review Logs**: Check audit trail and error logs
3. **Test Individual**: Try syncing single items first
4. **Verify Data**: Ensure data is complete and valid
5. **Check Permissions**: Verify Salesforce permissions

### Support Resources

- **Audit Trail**: Detailed operation logs
- **Error Logs**: WordPress and Salesforce logs
- **Field Mappings**: Verify mapping configuration
- **Connection Test**: Test Salesforce connectivity

## Best Practices

### Before Syncing
- **Backup Data**: Always backup before bulk operations
- **Test Connection**: Verify Salesforce connectivity
- **Review Mappings**: Check field mappings are correct
- **Check Permissions**: Ensure proper Salesforce permissions

### During Syncing
- **Monitor Progress**: Watch for errors and issues
- **Don't Interrupt**: Let bulk operations complete
- **Check Logs**: Monitor audit trail for issues
- **Verify Results**: Confirm sync success

### After Syncing
- **Review Results**: Check sync summary
- **Verify Data**: Confirm data in Salesforce
- **Address Errors**: Fix any sync errors
- **Update Documentation**: Record any changes

## Integration Points

### WordPress Hooks
- **Post Save**: Automatic sync on post save
- **User Update**: Automatic sync on user update
- **Status Change**: Sync on status changes

### Salesforce Events
- **Record Creation**: Logs creation events
- **Record Updates**: Tracks update operations
- **Error Events**: Records error occurrences

### Admin Interface
- **Dashboard Integration**: Bulk sync from dashboard
- **Individual Pages**: Sync from post/user pages
- **Status Monitoring**: Real-time status updates

The manual sync system provides complete control over WordPress-Salesforce synchronization, ensuring data integrity and providing flexibility for administrators to manage their integration needs.
