# WordPress Salesforce Integration

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

A comprehensive WordPress plugin that seamlessly integrates WordPress with Salesforce, providing bidirectional data synchronization, user management, and advanced admin controls.

## 🚀 Features

### Core Integration
- **Complete Salesforce Integration**: Syncs with 10 Salesforce objects
- **705 Salesforce Fields**: Mapped from actual CSV data with accurate field types
- **Bidirectional Sync**: WordPress to Salesforce and Salesforce to WordPress
- **Real-time Updates**: Automatic sync on content changes

### User Management
- **Automatic Lead Creation**: New users become Salesforce Leads
- **Lead Conversion**: Leads convert to Contacts and Accounts upon approval
- **Migration Support**: Safe migration from miniOrange plugin
- **Duplicate Prevention**: Prevents duplicate records during migration

### Manual Sync Controls
- **Individual Post Sync**: Meta boxes on all post edit pages
- **Individual User Sync**: Fields on user profile pages
- **Bulk Sync Operations**: Dashboard buttons for mass operations
- **Status Monitoring**: Real-time sync status indicators
- **Error Recovery**: Clear error messages and retry options

### Admin Interface
- **Main Dashboard**: System overview with health monitoring
- **Field Mapping Manager**: Visual field mapping interface
- **Audit Trail**: Comprehensive operation logging
- **ACF Field Management**: Automatic field creation and management
- **Connection Management**: OAuth2 setup and testing
- **Migration Tools**: Safe transition from other plugins

## 📦 Installation

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Advanced Custom Fields plugin (recommended)
- Salesforce API access with OAuth2

### Installation Steps

1. **Download the Plugin**
   ```bash
   git clone https://github.com/yourusername/wp-salesforce-integration.git
   ```

2. **Upload to WordPress**
   - Upload the plugin files to `/wp-content/plugins/wp-salesforce-integration/`
   - Or install via WordPress admin → Plugins → Add New → Upload Plugin

3. **Activate the Plugin**
   - Go to WordPress admin → Plugins
   - Find "WordPress Salesforce Integration" and click "Activate"

4. **Configure Salesforce Connection**
   - Go to **Salesforce** → **Connection** in WordPress admin
   - Enter your Salesforce OAuth2 credentials
   - Test the connection

## ⚙️ Configuration

### Salesforce Connection Setup

1. **Get OAuth2 Credentials**
   - Log into your Salesforce org
   - Go to Setup → App Manager
   - Create a new Connected App
   - Enable OAuth2 and get your credentials

2. **Configure in WordPress**
   - Go to **Salesforce** → **Connection**
   - Enter your OAuth2 details:
     - Authorization URI
     - Application ID
     - Client Secret
     - Redirect URI
   - Test the connection

### Field Mapping Configuration

1. **Access Field Mappings**
   - Go to **Salesforce** → **Field Mappings**
   - Select the Salesforce object to configure

2. **Map Fields**
   - Drag and drop WordPress fields to Salesforce fields
   - Set field types and validation rules
   - Test mappings to ensure accuracy

## 🎯 Usage

### Automatic Sync

**User Registration Flow:**
1. User registers on WordPress
2. Automatic Lead creation in Salesforce
3. ACF fields updated with Lead ID
4. Sync status tracked in audit trail

**User Approval Flow:**
1. Admin approves user
2. Lead converts to Contact and Account
3. ACF fields updated with new IDs
4. Original Lead ID preserved
5. Sync status updated

### Manual Sync Operations

**Individual Post Sync:**
- Edit any post in WordPress
- Use the "Salesforce Sync" meta box in the right sidebar
- Click "Sync to Salesforce" to sync individual posts
- Check status and view Salesforce ID

**Individual User Sync:**
- Edit any user in WordPress
- Use the "Salesforce Sync" section in the user profile
- Click "Sync to Salesforce" to sync individual users
- View Lead, Contact, and Account IDs

**Bulk Sync Operations:**
- Go to **Salesforce** → **Dashboard**
- Use "Bulk Sync Users" or "Bulk Sync Posts" buttons
- Monitor progress and view results summary

## 🏗️ Supported Salesforce Objects

| Object | Description | WordPress Mapping |
|--------|-------------|-------------------|
| **Account** | Company/Organization records | User accounts |
| **Lead** | Potential customers | New user registrations |
| **Contact** | Individual people | Approved users |
| **Sales Listing** | Products/Services for sale | Posts, Waste Listings |
| **Wanted Listings** | Items wanted by users | Wanted Listings posts |
| **Offers** | Business offers | Offers posts |
| **Haulage Offers** | Transportation offers | Haulage Offers posts |
| **Haulage Loads** | Transportation loads | Haulage Loads posts |
| **Sample Requests** | Sample requests | Sample Requests posts |
| **MFI Tests** | Test results | MFI Tests posts |

## 📚 Documentation

- **[Implementation Summary](IMPLEMENTATION_SUMMARY.md)** - Technical implementation details
- **[Admin Interface Guide](ADMIN_INTERFACE_GUIDE.md)** - Complete admin interface documentation
- **[Manual Sync Guide](MANUAL_SYNC_GUIDE.md)** - Manual sync operations guide
- **[ACF Field Setup Guide](ACF_FIELD_SETUP_GUIDE.md)** - ACF field configuration
- **[Salesforce OAuth Setup Guide](SALESFORCE_OAUTH_SETUP_GUIDE.md)** - OAuth2 setup instructions
- **[Migration Guide](MIGRATION_GUIDE.md)** - Migration from miniOrange plugin
- **[Plugin Structure](PLUGIN-STRUCTURE.md)** - Technical architecture overview
- **[Final Plugin Summary](FINAL_PLUGIN_SUMMARY.md)** - Complete feature overview

## 🔧 Development

### File Structure
```
wp-salesforce-integration/
├── wp-salesforce-integration.php     # Main plugin file
├── includes/                         # PHP classes (22 files)
├── assets/                          # CSS and JavaScript
├── Objects/                         # CSV files for Salesforce objects
├── *.json                          # Field mapping data
├── *.md                            # Documentation files
└── waste-trading-config.php        # Custom configuration
```

### Key Classes
- `WSI_Salesforce_API` - Core Salesforce API integration
- `WSI_Manual_Sync` - Manual sync controls
- `WSI_Admin_Dashboard` - Main admin interface
- `WSI_Field_Mapping_Manager` - Field mapping management
- `WSI_Audit_Trail` - Operation logging
- `WSI_ACF_Field_Setup` - ACF field management

## 🐛 Troubleshooting

### Common Issues

**Connection Problems:**
- Verify OAuth2 credentials are correct
- Check Salesforce org permissions
- Ensure API access is enabled

**Sync Failures:**
- Check field mappings are correct
- Verify required fields are present
- Review audit trail for error details

**ACF Field Issues:**
- Ensure ACF plugin is installed and active
- Check field group configuration
- Verify field names are correct

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🎉 Changelog

### 1.0.0 (2024-01-XX)
- **Initial Release**
- Complete WordPress-Salesforce integration
- 10 Salesforce objects supported
- 705 Salesforce fields mapped from CSV data
- Individual post and user sync controls
- Bulk sync operations from dashboard
- ACF field auto-creation on activation
- OAuth2 authentication with custom URLs
- Migration system from miniOrange plugin
- Comprehensive admin interface with health monitoring
- Audit trail logging for all operations
- Visual field mapping management
- Manual sync controls with status monitoring
- Robust error handling and recovery
- Complete documentation and setup guides

---

**Made with ❤️ for the WordPress and Salesforce communities**
