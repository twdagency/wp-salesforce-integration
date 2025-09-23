# Admin Interface Guide

## Overview

The WordPress Salesforce Integration plugin provides comprehensive admin interfaces for managing field mappings, monitoring sync operations, and maintaining system health.

## Admin Menu Structure

### 1. Dashboard (`Salesforce` → `Dashboard`)
**Main dashboard with system overview and quick actions**

**Features:**
- **Connection Status**: Shows if Salesforce API is connected
- **Migration Status**: Displays miniOrange migration status
- **Field Mappings**: Shows total number of configured mappings
- **Sync Statistics**: Success rate, total operations, response times
- **Recent Activity**: Latest sync operations with status
- **System Health**: Health checks for all components
- **Quick Actions**: One-click sync, manage mappings, view logs

**Key Metrics:**
- Total sync operations
- Success rate percentage
- Today's operations count
- Average response time
- System health indicators

### 2. Field Mappings (`Salesforce` → `Field Mappings`)
**Manage field mappings between WordPress and Salesforce**

**Features:**
- **Object Selection**: Choose Salesforce object to manage
- **Mapping Statistics**: Shows mapped vs unmapped fields
- **Add/Edit Mappings**: Create new field mappings
- **Test Mappings**: Test field transformations
- **Import/Export**: Backup and restore mappings
- **Real-time Editing**: Edit mappings directly in the table

**Field Mapping Options:**
- **WordPress Field**: Field name in WordPress
- **Source**: User, User Meta, Post, ACF, or Computed
- **Transformation**: Text, Email, Phone, URL, Number, Boolean, DateTime, etc.
- **Required**: Mark field as required for sync
- **Description**: Optional field description

**Available Transformations:**
- `text` - Plain text
- `email` - Email validation
- `phone` - Phone number formatting
- `url` - URL validation
- `textarea` - Multi-line text
- `number` - Numeric values
- `boolean` - True/false values
- `datetime` - Date and time
- `date` - Date only
- `array_to_text` - Convert arrays to text
- `currency` - Currency formatting
- `json` - JSON data

### 3. Audit Trail (`Salesforce` → `Audit Trail`)
**Monitor all sync operations and troubleshoot issues**

**Features:**
- **Operation Logs**: Complete history of all sync operations
- **Advanced Filtering**: Filter by operation, object, status, date range
- **Detailed Views**: View complete operation details
- **Export Logs**: Download logs as JSON
- **Clear Logs**: Remove old log entries
- **Real-time Updates**: See operations as they happen

**Filter Options:**
- **Operation Type**: Create, Update, Delete, Upsert, Sync
- **Object Type**: Lead, Contact, Account, etc.
- **Status**: Success, Error, Warning
- **Date Range**: From/To date selection
- **Pagination**: Navigate through large log sets

**Log Details Include:**
- Timestamp and execution time
- Operation type and object details
- Data sent to Salesforce
- Response received
- Error details (if any)
- User and IP information

### 4. Migration (`Salesforce` → `Migration`)
**Manage migration from miniOrange plugin**

**Features:**
- **Migration Status**: Check if migration is needed
- **One-Click Migration**: Start migration process
- **Progress Tracking**: Real-time migration progress
- **Cleanup Tools**: Remove miniOrange data after migration
- **Verification**: Confirm successful migration

**Migration Process:**
1. **Detection**: Automatically detects miniOrange data
2. **Mapping**: Maps WordPress users to existing Salesforce records
3. **Update**: Updates user meta with Salesforce IDs
4. **Verification**: Confirms successful migration
5. **Cleanup**: Optional removal of miniOrange data

### 5. Settings (`Salesforce` → `Settings`)
**Configure Salesforce API and plugin settings**

**Features:**
- **API Configuration**: Salesforce credentials
- **Sync Settings**: Automatic sync options
- **Field Mapping**: Default field mappings
- **Logging**: Log level and retention settings
- **Advanced**: Custom configurations

## Key Features

### 1. Real-time Field Mapping Management
- **Visual Interface**: Easy-to-use table for managing mappings
- **Live Editing**: Edit mappings directly without page reload
- **Validation**: Real-time validation of field mappings
- **Testing**: Test mappings with actual data
- **Bulk Operations**: Import/export multiple mappings

### 2. Comprehensive Audit Trail
- **Complete Logging**: Every sync operation is logged
- **Detailed Information**: Full request/response data
- **Error Tracking**: Detailed error information
- **Performance Metrics**: Execution times and success rates
- **Search and Filter**: Find specific operations quickly

### 3. System Health Monitoring
- **Connection Status**: Salesforce API connectivity
- **Integration Health**: WordPress integration status
- **Database Health**: Audit trail table status
- **Mapping Health**: Field mapping configuration
- **Performance Metrics**: Response times and success rates

### 4. Migration Safety
- **Duplicate Prevention**: Prevents creating duplicate records
- **Data Preservation**: Maintains all existing relationships
- **Rollback Capability**: Can revert if issues occur
- **Progress Tracking**: Real-time migration status
- **Verification**: Confirms successful migration

## Usage Examples

### Adding a New Field Mapping

1. Go to **Salesforce** → **Field Mappings**
2. Select the Salesforce object (e.g., "Lead")
3. Click **"Add New Mapping"**
4. Fill in the mapping details:
   - **Salesforce Field**: `FirstName`
   - **WordPress Field**: `first_name`
   - **Source**: `user_meta`
   - **Transformation**: `text`
   - **Required**: ✓
5. Click **"Save Mapping"**

### Testing a Field Mapping

1. Go to **Salesforce** → **Field Mappings**
2. Find the mapping you want to test
3. Click the **test button** (wrench icon)
4. Check the results in the notification

### Viewing Sync History

1. Go to **Salesforce** → **Audit Trail**
2. Use filters to narrow down results:
   - **Operation**: "Create"
   - **Object**: "Lead"
   - **Status**: "Success"
3. Click **"Details"** on any log entry to see full information

### Running Migration

1. Go to **Salesforce** → **Migration**
2. Check the migration status
3. If migration is needed, click **"Start Migration"**
4. Wait for completion
5. Verify the results
6. Optionally cleanup miniOrange data

## Troubleshooting

### Common Issues

**1. Field Mapping Not Working**
- Check if the WordPress field exists
- Verify the source type is correct
- Test the transformation
- Check for typos in field names

**2. Sync Operations Failing**
- Check Salesforce API credentials
- Verify field mappings are correct
- Check audit trail for error details
- Ensure required fields are mapped

**3. Migration Issues**
- Check miniOrange plugin data exists
- Verify Salesforce API access
- Check for duplicate records
- Review migration logs

**4. Performance Issues**
- Check audit trail for slow operations
- Review field mapping complexity
- Consider reducing sync frequency
- Check server resources

### Getting Help

**1. Check Audit Trail**
- Look for error messages
- Check execution times
- Review data sent/received

**2. Test Connections**
- Use dashboard health checks
- Test Salesforce API connection
- Verify field mappings

**3. Review Logs**
- Check WordPress error logs
- Review audit trail details
- Look for specific error patterns

## Best Practices

### Field Mapping
- Use descriptive field names
- Test mappings before going live
- Keep transformations simple
- Document complex mappings
- Regular review and cleanup

### Monitoring
- Check dashboard regularly
- Monitor success rates
- Review failed operations
- Keep audit logs manageable
- Set up alerts for critical failures

### Maintenance
- Regular cleanup of old logs
- Update field mappings as needed
- Monitor system health
- Keep backups of configurations
- Test after any changes

## Security Considerations

- **Access Control**: Only administrators can access admin interfaces
- **Data Protection**: Sensitive data is logged securely
- **API Security**: Credentials are stored securely
- **Audit Logging**: All admin actions are logged
- **Nonce Protection**: All AJAX requests are protected

## Performance Optimization

- **Pagination**: Large datasets are paginated
- **Caching**: Field mappings are cached
- **Lazy Loading**: Data loads as needed
- **Efficient Queries**: Optimized database queries
- **Background Processing**: Heavy operations run in background

This admin interface provides complete control over your WordPress-Salesforce integration while maintaining security, performance, and ease of use.
