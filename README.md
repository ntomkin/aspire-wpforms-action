# Aspire Software: WPForms Actions for Pardot

## Description
This WordPress plugin extends WPForms to:
1. Post form submissions to Pardot endpoints via a custom URL field
2. Integrate with GA Connector for tracking form submissions

## Features

### Pardot Integration
- Custom action URL field in WPForms settings
- Field mapping interface to map WPForms fields to Pardot fields
- Support for various data types (form fields, POST data, custom values)

### GA Connector Integration
- Integration with gaconnector.com for advanced form tracking
- Automatically populates hidden form fields with GA Connector data
- Configurable tracking type (cookie-based or field-based)
- Admin settings page for GA Connector configuration

## Installation
1. Upload the plugin files to the `/wp-content/plugins/aspire-wpforms-action` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the GA Connector settings under Settings > GA Connector Settings
4. For each form, configure the Pardot URL and field mappings in the form settings

## Configuration

### GA Connector Settings
1. Go to Settings > GA Connector Settings
2. Enter your GA Connector Account ID
3. Select your preferred tracking type (Cookie Based or Field Based)
4. Save your settings

### Form Configuration
1. Edit your WPForms form
2. Go to Settings > Pardot Integration
3. Enter the Pardot endpoint URL
4. Add field mappings to map form fields to Pardot fields

## Usage
Once configured, the plugin will:
1. Automatically load the GA Connector tracking script on your site
2. Populate hidden form fields with GA Connector tracking data
3. Submit form data to your specified Pardot endpoint with mapped fields

## Requirements
- WordPress 5.0 or higher
- WPForms 1.5.0 or higher

## Changelog

### 1.3.0
- Added GA Connector integration
- Added admin settings page for GA Connector configuration
- Added tracking script loading functionality

### 1.2.0
- Added field mapping UI to form settings
- Improved error handling and validation

### 1.1.0
- Initial release with Pardot URL integration 