# UPay Standalone Settings Page Guide

## Overview

Due to compatibility issues with WP Travel Engine 6.6.9's tab system, we've created a **standalone settings page** that appears directly in your WordPress admin sidebar. This completely bypasses WTE's tab registration system and provides full access to all UPay configuration options.

## How to Access

### Option 1: Admin Menu (Recommended)
1. Log in to your WordPress admin dashboard
2. Look in the left sidebar menu
3. Find **"UPay Settings"** (with a money icon 💰)
4. Click to access the settings page

### Option 2: Direct URL
Navigate to: `wp-admin/admin.php?page=wte-upay-settings`

Example:
```
https://yourdomain.com/wp-admin/admin.php?page=wte-upay-settings
```

## Available Settings

The standalone page provides access to:

### 1. Enable/Disable UPay Gateway
- ✅ Checkbox to enable Union Bank UPay payment gateway
- Toggle on/off without affecting other payment methods

### 2. API Credentials (Required)
All four credentials are required for UPay to function:

- **Client ID (X-IBM-Client-Id)**: Your UPay Client ID from Developer Portal
- **Client Secret (X-IBM-Client-Secret)**: Your UPay Client Secret from Developer Portal
- **Partner ID (X-Partner-Id)**: Partner ID provided by Union Bank
- **Biller UUID**: Your unique Biller UUID from Union Bank UPay

### 3. Test Mode Indicator
- Shows current environment (UAT vs Production)
- Displays which API URL is being used
- Instructions for enabling test mode via wp-config.php

## Configuration Steps

### Step 1: Enable Test Mode (Recommended for Development)

Add this to your `wp-config.php` file (before "That's all, stop editing!"):

```php
// Enable UPay test mode (UAT environment)
define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', true );
```

This switches to Union Bank's UAT environment:
- **UAT API**: `https://apiuat.unionbankph.com/ubp/external/upay/payments/v1`
- **Production API**: `https://api.unionbankph.com/ubp/external/upay/payments/v1`

### Step 2: Obtain UPay Credentials

#### For UAT Testing:
1. Visit: https://developer-uat.unionbankph.com
2. Register/login to the developer portal
3. Subscribe to UPay API
4. Obtain your test credentials:
   - Client ID
   - Client Secret
   - Partner ID
   - Biller UUID

#### For Production:
1. Visit: https://developer.unionbankph.com
2. Contact Union Bank to set up production access
3. Obtain production credentials

### Step 3: Configure Settings

1. Navigate to **UPay Settings** in WordPress admin
2. Check **"Enable UPay Payment"**
3. Enter all four credentials:
   - Client ID
   - Client Secret
   - Partner ID
   - Biller UUID
4. Click **"Save UPay Settings"**

### Step 4: Verify Configuration

After saving, you should see:
- ✅ Success message: "UPay settings saved successfully!"
- Current mode indicator showing TEST or PRODUCTION
- API URL being used

## Features

### Settings Storage
- All settings are stored in the standard WTE settings option: `wp_travel_engine_settings`
- Uses nested structure: `['upay_settings']['client_id']`, etc.
- Compatible with WTE's payment gateway system
- No conflicts with other payment gateways

### Security
- Client Secret field uses password input (hidden characters)
- All inputs are sanitized before saving
- Credentials are never displayed in frontend
- Security notes prominently displayed

### Quick Links Section
The page includes helpful links to:
- Union Bank Developer Portal (UAT)
- Union Bank Developer Portal (Production)
- WP Travel Engine main settings page

## Troubleshooting

### Settings Page Not Appearing?

1. **Check plugin activation**: Ensure "WP Travel Engine - UPay Gateway" is activated
2. **Check WTE version**: Requires WP Travel Engine 5.0+
3. **Clear browser cache**: Force refresh (Ctrl+F5 or Cmd+Shift+R)
4. **Check user permissions**: You need "manage_options" capability (Administrator role)

### Settings Not Saving?

1. **Check PHP errors**: Look in WordPress debug log
2. **Verify permissions**: Ensure PHP can write to the database
3. **Test with another admin user**: Rule out user-specific issues

### UPay Not Appearing at Checkout?

1. **Verify "Enable UPay Payment" is checked** in settings
2. **Check all credentials are entered** (all 4 fields required)
3. **Review payment gateway registration**: Check WTE → Settings → Payment to confirm gateway is active

## Technical Details

### File Location
- **Settings class**: `wte-upay/includes/class-wte-upay-standalone-settings.php`
- **Loaded by**: `wte-upay/includes/class-wte-upay-checkout.php` (line 79-81)

### WordPress Hooks Used
- `admin_menu` (priority 99): Adds menu page
- `admin_init`: Registers settings
- `admin_notices`: Shows success/error messages

### Settings API
- **Option name**: `wp_travel_engine_settings`
- **Settings group**: `wte_upay_settings_group`
- Uses WordPress Settings API for security and validation

## Next Steps

After configuring the standalone settings page:

1. ✅ **Test in UAT environment first**
   - Enable test mode in wp-config.php
   - Use UAT credentials
   - Make test bookings

2. 🧪 **Verify payment flow**
   - Create a test trip booking
   - Select UPay as payment method
   - Complete payment (InstaPay QR code)
   - Verify booking status updates

3. 📱 **Test InstaPay QR Code**
   - Ensure QR code displays correctly
   - Test scanning with UnionBank app
   - Verify payment confirmation

4. 🚀 **Move to production**
   - Remove/comment out WP_TRAVEL_ENGINE_PAYMENT_DEBUG
   - Update with production credentials
   - Test with real transactions

## Support

For issues specific to:
- **UPay API**: Contact Union Bank developer support
- **WP Travel Engine**: Check WTE documentation
- **This plugin**: Review FIXES_APPLIED.md and ACCESSING_SETTINGS.md

## Why Standalone Page?

WP Travel Engine 6.6.9 introduced architectural changes to its settings tab system that prevented the standard tab registration from working. Despite:
- Correct filter usage (`wpte_settings_get_global_tabs`)
- Proper tab configuration
- File path verification
- Format matching other working tabs

The UPay tab would not appear in the Payment settings section. The standalone page provides:
- ✅ Guaranteed access to settings
- ✅ Independent of WTE's internal tab system
- ✅ All functionality of the original tab-based approach
- ✅ Better user experience (direct access from sidebar)
- ✅ Future-proof against WTE architecture changes

---

**Last Updated**: 2025-11-07
**Plugin Version**: 1.0.0
**Tested With**: WP Travel Engine 6.6.9
