# UPay Gateway Quick Start

## 🎯 Accessing UPay Settings

The UPay settings page is now available as a **standalone menu item** in your WordPress admin.

### Where to Find It
Look in your WordPress admin sidebar for:
```
📋 Dashboard
📝 Posts
📄 Pages
...
💰 UPay Settings  ← Click here!
```

Or use direct URL: `wp-admin/admin.php?page=wte-upay-settings`

---

## ⚙️ Quick Setup (5 Steps)

### 1. Enable Test Mode
Add to `wp-config.php`:
```php
define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', true );
```

### 2. Get Test Credentials
Visit: https://developer-uat.unionbankph.com

### 3. Configure Settings
In WordPress admin:
1. Click **"UPay Settings"** in sidebar
2. Check **"Enable UPay Payment"**
3. Enter your credentials:
   - Client ID
   - Client Secret
4. Click **"Save UPay Settings"**

### 4. Verify
You should see:
- ✅ Success message
- 🧪 TEST MODE indicator (if using UAT)

### 5. Test Payment Flow
1. Create a test trip booking
2. Select UPay at checkout
3. Complete payment with InstaPay QR code
4. Verify booking confirmation

---

## 📋 What You Need

### Required Credentials (Both Required)
1. **Client ID** - From Union Bank Developer Portal
2. **Client Secret** - From Union Bank Developer Portal

### Environment URLs
- **Test (UAT)**: https://developer-uat.unionbankph.com
- **Production**: https://developer.unionbankph.com

---

## 🔧 Troubleshooting

**Can't see UPay Settings in sidebar?**
- Check plugin is activated
- Clear browser cache (Ctrl+F5)
- Verify you're logged in as Administrator

**Settings not saving?**
- Check both credentials are filled in
- Look for PHP errors in debug log
- Verify WordPress can write to database

**UPay not showing at checkout?**
- Ensure "Enable UPay Payment" is checked
- Verify both credentials are entered
- Check WTE Settings → Payment for gateway status

---

## 📚 Full Documentation

For detailed information, see:
- **STANDALONE_SETTINGS_GUIDE.md** - Complete settings page documentation
- **FIXES_APPLIED.md** - All bug fixes and technical details
- **ACCESSING_SETTINGS.md** - Troubleshooting settings access
- **PROJECT_SUMMARY.md** - Full project overview

---

**Need Help?**
Review the full documentation files above or contact Union Bank developer support for API-specific questions.
