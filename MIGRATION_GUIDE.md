# Migration Guide: From miniOrange to WordPress Salesforce Integration

## Overview

This guide helps you safely migrate from the miniOrange "Object Data Sync For Salesforce" plugin to the new WordPress Salesforce Integration plugin without creating duplicates in your Salesforce environment.

## Why Migration is Important

- **Prevents Duplicates**: Avoids creating duplicate records in Salesforce
- **Preserves Data**: Maintains all existing Salesforce relationships
- **Seamless Transition**: Ensures continuity of your integration
- **Data Integrity**: Preserves all user mappings and field data

## Pre-Migration Checklist

### 1. Backup Your Data
- **WordPress Database**: Full backup of your WordPress database
- **Salesforce Data**: Export critical Salesforce records if needed
- **User Meta Data**: Backup user meta fields

### 2. Document Current Setup
- Note which Salesforce objects you're syncing (Lead, Contact, Account)
- Document field mappings from miniOrange
- List any custom configurations

### 3. Deactivate miniOrange Plugin
- Go to WordPress Admin → Plugins
- Deactivate "Object Data Sync For Salesforce" plugin
- **DO NOT DELETE** the plugin yet (we need the database table)

## Migration Process

### Step 1: Install New Plugin
1. Upload the new WordPress Salesforce Integration plugin
2. Activate the plugin
3. Configure Salesforce API credentials

### Step 2: Run Migration
1. Go to **WordPress Admin → Settings → Salesforce Integration → Migration**
2. Review the migration status
3. Click **"Start Migration"** if migration is needed
4. Wait for the migration to complete

### Step 3: Verify Migration
1. Check that users have Salesforce IDs in their meta
2. Verify no duplicate records were created
3. Test user registration flow
4. Test user approval flow

### Step 4: Cleanup (Optional)
1. After successful migration, click **"Cleanup MiniOrange Data"**
2. This removes the miniOrange database table
3. You can now safely delete the miniOrange plugin

## What the Migration Does

### 1. Identifies Existing Records
- Queries Salesforce for records with `WordPress_User_ID__c` field
- Finds Leads, Contacts, and Accounts created by miniOrange
- Maps them to WordPress users by User ID

### 2. Updates User Meta
- Sets `salesforce_lead_id` for users with existing Leads
- Sets `salesforce_contact_id` for users with existing Contacts  
- Sets `salesforce_account_id` for users with existing Accounts
- Marks users as `migrated_from_miniorange`

### 3. Prevents Duplicates
- New plugin checks for existing records before creating new ones
- Uses upsert functionality to update existing records
- Maintains data integrity throughout the process

## Migration Status Indicators

| Status | Meaning | Action Required |
|--------|---------|-----------------|
| 🟡 Migration Needed | MiniOrange data found | Run migration |
| 🟢 Migration Completed | Successfully migrated | Verify data |
| 🔴 Migration Failed | Error occurred | Check logs and retry |
| ⚪ No Migration Needed | No miniOrange data | Continue with setup |

## Field Mapping Differences

### MiniOrange Approach
- Used `nomenclature` field for external ID
- Stored mappings in `mo_sf_sync_object_field_mapping` table
- Limited to basic field mappings

### New Plugin Approach
- Uses `WordPress_User_ID__c` as external ID
- Comprehensive field mapping system
- Supports all 10 Salesforce objects from your CSV files
- Advanced Lead → Contact/Account conversion flow

## Troubleshooting

### Migration Fails
1. **Check Salesforce API Credentials**: Ensure they're correctly configured
2. **Check Permissions**: Verify API user has access to all objects
3. **Check Logs**: Review error logs for specific issues
4. **Retry Migration**: Some temporary issues may resolve on retry

### Duplicate Records Found
1. **Stop New Sync**: Disable automatic sync temporarily
2. **Review Salesforce**: Check for duplicate records
3. **Manual Cleanup**: Remove duplicates in Salesforce
4. **Re-run Migration**: Start migration process again

### Missing User Mappings
1. **Check User Meta**: Verify `salesforce_*_id` fields exist
2. **Manual Mapping**: Map users manually if needed
3. **Re-sync Users**: Trigger manual sync for affected users

## Post-Migration Verification

### 1. Check User Meta
```sql
SELECT user_id, meta_key, meta_value 
FROM wp_usermeta 
WHERE meta_key LIKE 'salesforce_%' 
ORDER BY user_id, meta_key;
```

### 2. Verify Salesforce Records
- Check that existing records weren't duplicated
- Verify `WordPress_User_ID__c` field is populated
- Confirm Lead → Contact/Account relationships

### 3. Test New Functionality
- Register a new user (should create Lead)
- Approve user (should convert Lead to Contact/Account)
- Update user profile (should sync to Salesforce)

## Rollback Plan

If migration fails or causes issues:

1. **Deactivate New Plugin**: Disable the new integration
2. **Reactivate MiniOrange**: Re-enable the original plugin
3. **Restore Database**: Use your backup to restore data
4. **Contact Support**: Get help with the migration process

## Support

If you encounter issues during migration:

1. **Check Logs**: Review WordPress error logs
2. **Review Status**: Check migration status page
3. **Contact Support**: Provide detailed error information
4. **Document Issues**: Note specific error messages and steps

## Best Practices

### Before Migration
- Test in staging environment first
- Document current field mappings
- Backup all data
- Plan for downtime if needed

### During Migration
- Monitor migration progress
- Don't interrupt the process
- Check for errors immediately
- Verify data integrity

### After Migration
- Test all functionality
- Monitor for issues
- Update documentation
- Train users on new features

## Conclusion

The migration process is designed to be safe and preserve all your existing data while preventing duplicates. The new plugin provides enhanced functionality and better integration with your specific Salesforce objects and field mappings.

Follow this guide carefully, and you'll have a smooth transition from miniOrange to the new WordPress Salesforce Integration plugin.
