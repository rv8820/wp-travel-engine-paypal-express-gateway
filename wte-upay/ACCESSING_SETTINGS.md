# 🔧 How to Access UPay Settings

## 📍 Location of Settings

The UPay gateway settings can be accessed at:

**WordPress Admin → WP Travel Engine → Settings → Payment → UPay Settings**

The exact path is:
```
1. Log in to WordPress Admin
2. Click "WP Travel Engine" in the left sidebar
3. Click "Settings"
4. Click "Payment" tab
5. Look for "UPay Settings" sub-tab
```

---

## ✅ Checklist Before Accessing Settings

### 1. Plugin Installation
- [ ] WP Travel Engine plugin is installed and activated
- [ ] WP Travel Engine - UPay Gateway plugin is installed
- [ ] UPay Gateway plugin is activated

### 2. Plugin Activation Check
To verify the UPay plugin is activated:
1. Go to **Plugins → Installed Plugins**
2. Look for "WP Travel Engine - UPay Gateway"
3. Status should show "Active" (blue text)
4. If not active, click "Activate"

### 3. WordPress Requirements
- [ ] WordPress 5.0 or higher
- [ ] PHP 7.4 or higher
- [ ] WP Travel Engine 5.0 or higher

---

## 🔍 Troubleshooting

### Issue 1: "UPay Settings" tab not visible

**Possible Causes:**
1. **UPay plugin not activated**
   - Go to Plugins → Installed Plugins
   - Find "WP Travel Engine - UPay Gateway"
   - Click "Activate" if not already active

2. **WP Travel Engine not installed**
   - The UPay plugin requires WP Travel Engine to work
   - Install WP Travel Engine first
   - Then activate UPay Gateway

3. **Outdated WP Travel Engine version**
   - Update WP Travel Engine to version 5.0 or higher
   - Then reactivate UPay Gateway

4. **Browser cache issue**
   - Clear your browser cache (Ctrl+Shift+Delete)
   - Refresh the settings page (Ctrl+F5)
   - Try in incognito/private mode

5. **WordPress cache issue**
   - Deactivate UPay Gateway
   - Reactivate UPay Gateway
   - Refresh the admin page

**Solution Steps:**
```
1. Deactivate the UPay Gateway plugin
2. Clear any WordPress caches (if using caching plugin)
3. Reactivate the UPay Gateway plugin
4. Hard refresh your browser (Ctrl+F5 or Cmd+Shift+R)
5. Navigate to WP Travel Engine → Settings → Payment
6. Look for "UPay Settings" tab
```

---

### Issue 2: Settings page shows but fields are empty

**This is normal on first installation!**

The UPay settings will be empty until you enter your credentials from Union Bank.

**What you need to fill in:**
1. ✅ Client ID (from Union Bank Developer Portal)
2. ✅ Client Secret (from Union Bank Developer Portal)
3. ✅ Partner ID (provided by Union Bank)
4. ✅ Biller UUID (provided by Union Bank)

---

### Issue 3: Settings won't save

**Possible Causes:**
1. **File permissions issue**
   - Check that WordPress can write to the database
   - Check error logs: `/wp-content/debug.log`

2. **JavaScript errors**
   - Open browser console (F12)
   - Look for JavaScript errors
   - Disable other plugins temporarily

3. **PHP errors**
   - Enable WP_DEBUG in wp-config.php
   - Check for PHP errors in debug.log

**Solution:**
```php
// Add to wp-config.php temporarily
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

---

## 📋 Settings Fields Explained

When you access the UPay Settings page, you'll see these fields:

### 1. **Enable UPay Payment** (Checkbox)
- Check this box to enable UPay as a payment option
- Customers will see "UPay Payment" at checkout

### 2. **Client ID (X-IBM-Client-Id)** (Text field)
- Your application's Client ID from Union Bank Developer Portal
- Found at: https://developer.unionbankph.com → Your Application → Credentials
- Example: `a1b2c3d4-e5f6-7890-abcd-ef1234567890`

### 3. **Client Secret (X-IBM-Client-Secret)** (Password field)
- Your application's Client Secret from Union Bank Developer Portal
- Keep this confidential - it's like a password
- Example: `x9y8z7w6-v5u4-3210-zyxw-vu9876543210`

### 4. **Partner ID (X-Partner-Id)** (Text field)
- Provided directly by Union Bank
- Contact Union Bank support to get this
- Example: `PARTNER123` or similar

### 5. **Biller UUID** (Text field)
- Your biller identifier in the UPay system
- Provided by Union Bank after merchant setup
- Example: `5F3DDD5B-95E3-4C5E-9876-543210ABCDEF`

---

## 🚀 Quick Start Guide

### Step 1: Get Credentials from Union Bank

**For Testing (UAT):**
1. Register at https://developer-uat.unionbankph.com
2. Create an application
3. Subscribe to "UPay by UnionBank" API
4. Get your Client ID and Client Secret
5. Contact Union Bank to get Partner ID and Biller UUID

**For Production:**
1. Register at https://developer.unionbankph.com
2. Follow same steps as UAT
3. Use production credentials

### Step 2: Configure Plugin

1. **Navigate to settings:**
   ```
   WP Admin → WP Travel Engine → Settings → Payment → UPay Settings
   ```

2. **Enable the gateway:**
   - Check the "Enable UPay Payment" checkbox

3. **Enter credentials:**
   - Client ID: `[paste from developer portal]`
   - Client Secret: `[paste from developer portal]`
   - Partner ID: `[from Union Bank]`
   - Biller UUID: `[from Union Bank]`

4. **Save settings:**
   - Scroll to bottom
   - Click "Save Settings" or "Update Settings"

### Step 3: Enable Test Mode (for UAT testing)

Add this to your `wp-config.php`:
```php
// Enable UPay test mode (uses UAT environment)
define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', true );
```

This will use Union Bank's UAT (testing) API URL instead of production.

### Step 4: Test the Gateway

1. Create a test trip
2. Go to the trip page on frontend
3. Click "Book Now"
4. Fill in booking details
5. At payment step, you should see "UPay Payment" option
6. Select it and test the payment flow

---

## 🔐 Security Notes

### Keeping Credentials Safe

1. **Never share credentials publicly**
   - Don't commit credentials to Git/GitHub
   - Don't share in screenshots
   - Don't post in support forums

2. **Use environment-specific credentials**
   - UAT credentials for testing
   - Production credentials for live site
   - Never mix them up!

3. **Regenerate if compromised**
   - If credentials are exposed, contact Union Bank
   - Generate new credentials
   - Update plugin settings

---

## 📞 Getting Help

### If UPay Settings Still Don't Appear:

1. **Check WordPress version:**
   ```
   Dashboard → At a Glance → WordPress Version
   Should be 5.0 or higher
   ```

2. **Check WTE version:**
   ```
   Plugins → Installed Plugins → WP Travel Engine
   Should show version 5.0 or higher
   ```

3. **Check PHP version:**
   ```
   Dashboard → Site Health → Info → Server
   Should be PHP 7.4 or higher
   ```

4. **Enable debug mode:**
   ```php
   // wp-config.php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

5. **Check debug log:**
   ```
   /wp-content/debug.log
   Look for errors related to "upay" or "UPay"
   ```

### Contact Information

**For Plugin Issues:**
- Check FIXES_APPLIED.md for known issues
- Check SETUP_GUIDE.md for setup help
- Enable debug logging and check logs

**For Union Bank API Issues:**
- Email: apisupport@unionbankph.com
- Developer Portal: https://developer.unionbankph.com

**For WP Travel Engine Issues:**
- Support: https://wptravelengine.com/support
- Docs: https://docs.wptravelengine.com

---

## ✅ Success Checklist

Once you can access and configure settings, verify:

- [ ] UPay Settings tab is visible
- [ ] All 4 credential fields are present
- [ ] Enable checkbox works
- [ ] Settings save successfully
- [ ] No JavaScript errors in console
- [ ] No PHP errors in debug.log
- [ ] UPay appears as payment option on frontend

---

## 🎉 You're Ready!

Once you can access the settings and enter your credentials:
1. ✅ Enable UPay Payment checkbox
2. ✅ Enter all 4 credentials
3. ✅ Save settings
4. ✅ Test with a booking

**Your UPay payment gateway should now be operational!**

---

*Last Updated: January 7, 2025*
*Plugin Version: 1.0.0*
