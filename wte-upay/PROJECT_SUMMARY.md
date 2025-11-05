# WP Travel Engine - UPay Gateway Plugin
## Project Summary

---

## 📁 Plugin Structure

```
wte-upay/
├── wte-upay.php                                    # Main plugin file
├── README.md                                       # Plugin documentation
├── SETUP_GUIDE.md                                  # Detailed setup instructions
├── includes/
│   ├── class-wte-upay-checkout.php                # Main initialization class
│   ├── class-wp-travel-engine-upay-gateway.php    # Admin settings & payment handler
│   ├── class-wte-upay-api.php                     # UPay API communication class
│   ├── class-wte-upay-request.php                 # Payment gateway (WTE < 6.0)
│   └── wte-upay.php                               # Base gateway (WTE 6.0+)
├── admin/
│   └── includes/
│       └── backend/
│           └── upay.php                           # Payment details meta box
└── languages/
    └── wte-upay.pot                               # Translation template
```

---

## 🔑 Key Features Implemented

### ✅ Core Functionality
- [x] Union Bank UPay API integration
- [x] POST /transactions - Create payment requests
- [x] GET /transactions/:billerUuid/status - Check payment status
- [x] Proper authentication with X-IBM-Client-Id, X-IBM-Client-Secret, X-Partner-Id
- [x] Support for multiple payment methods (InstaPay, UB Online, etc.)
- [x] QR code support for InstaPay payments

### ✅ WordPress Integration
- [x] WP Travel Engine 5.0+ compatibility
- [x] WP Travel Engine 6.0+ compatibility (BaseGateway)
- [x] Admin settings panel
- [x] Payment gateway registration
- [x] Booking status updates
- [x] Email notifications

### ✅ Security & Best Practices
- [x] Secure credential storage
- [x] API error handling
- [x] Debug logging
- [x] Input sanitization
- [x] Output escaping
- [x] Nonce verification (where applicable)

### ✅ Developer-Friendly
- [x] Clean, well-documented code
- [x] Action and filter hooks
- [x] Translation-ready
- [x] Debug mode support
- [x] Comprehensive error messages

---

## 🎯 UPay API Implementation

### Authentication
```php
Headers:
- X-IBM-Client-Id: {client_id}
- X-IBM-Client-Secret: {client_secret}
- X-Partner-Id: {partner_id}
- Content-Type: application/json
- Accept: application/json
```

### Endpoints Used

#### 1. Create Transaction
```
POST /transactions
```
**Request Body:**
```json
{
  "senderRefId": "WTE123456789",
  "tranRequestDate": "2025-01-01T10:00:00.000+08:00",
  "billerUuid": "5F3DDD5B-95E3-...",
  "emailAddress": "customer@example.com",
  "amount": "1000.00",
  "paymentMethod": "instapay",
  "mobileNumber": "+639123456789",
  "callbackUrl": "https://yoursite.com/?upay_callback=1",
  "references": [
    {"index": 0, "value": "Booking #123"},
    {"index": 1, "value": "Trip Name"}
  ]
}
```

**Response:**
```json
{
  "code": "200",
  "state": "success",
  "transactionId": "UB123456789",
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "qrCode": "...base64_string...",
  "message": "Transaction successful"
}
```

#### 2. Check Status
```
GET /transactions/{billerUuid}/status?transactionId={transactionId}
```

---

## 🔧 Configuration Required

### Union Bank Developer Portal
1. **Client ID** (X-IBM-Client-Id)
   - From Developer Portal → Application Details

2. **Client Secret** (X-IBM-Client-Secret)
   - From Developer Portal → Application Details

3. **Partner ID** (X-Partner-Id)
   - Provided by Union Bank after merchant setup

4. **Biller UUID**
   - Your biller identifier in UPay system

### WordPress Settings
```
WP Admin → WP Travel Engine → Settings → UPay Settings
```

---

## 🔐 Environment Setup

### Test Mode (UAT)
```php
// wp-config.php
define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', true );
```
**API URL:** `https://apiuat.unionbankph.com/ubp/external/upay/payments/v1`

### Production Mode
```php
// wp-config.php
define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', false );
// or remove the line
```
**API URL:** `https://api.unionbankph.com/ubp/external/upay/payments/v1`

---

## 📊 Payment Flow

```
1. Customer selects trip and clicks "Book Now"
   ↓
2. Customer fills booking form
   ↓
3. Customer selects "UPay Payment" method
   ↓
4. Customer submits booking
   ↓
5. Plugin calls UPay API POST /transactions
   ↓
6. UPay returns transaction details
   ↓
7. For InstaPay: Display QR code
   For Others: Redirect to payment page
   ↓
8. Customer completes payment
   ↓
9. UPay sends callback to site
   ↓
10. Plugin calls GET /transactions/status
   ↓
11. Plugin updates booking status
   ↓
12. Customer redirected to thank you page
   ↓
13. Confirmation email sent
```

---

## 🛠️ Key Classes & Methods

### WTE_UPay_API
**Purpose:** Handles all UPay API communication

**Key Methods:**
- `create_transaction($payment_data)` - Create payment request
- `check_status($transaction_id)` - Verify payment status
- `make_request($method, $endpoint, $body, $query_params)` - Generic API caller

### Wte_UPay_Admin
**Purpose:** Admin interface and payment processing

**Key Methods:**
- `process_upay_payment($payment_id, $type, $gateway)` - Process payment
- `handle_upay_callback()` - Handle payment callbacks
- `wte_upay_settings()` - Render settings page

### WTE_UPay_Checkout
**Purpose:** Plugin initialization

**Key Methods:**
- `instance()` - Singleton pattern
- `define_constants()` - Set up plugin constants
- `includes()` - Load required files

---

## 🧪 Testing Checklist

### Unit Testing
- [ ] API credential validation
- [ ] Payment data preparation
- [ ] API request formatting
- [ ] Response parsing
- [ ] Error handling

### Integration Testing
- [ ] Complete booking flow
- [ ] Payment callback handling
- [ ] Status updates
- [ ] Email notifications
- [ ] Admin display

### UAT Testing
- [ ] Test with real Union Bank UAT environment
- [ ] Test InstaPay QR code
- [ ] Test UB Online
- [ ] Test callback URL
- [ ] Test error scenarios

---

## 📝 Next Steps

### 1. Before Testing
- [ ] Upload plugin to WordPress
- [ ] Activate plugin
- [ ] Configure credentials in settings
- [ ] Enable test mode
- [ ] Enable debug logging

### 2. UAT Testing
- [ ] Create test trip
- [ ] Make test booking
- [ ] Complete payment in UAT
- [ ] Verify booking status
- [ ] Check email notifications

### 3. Production Deployment
- [ ] Get production credentials
- [ ] Update plugin settings
- [ ] Disable test mode
- [ ] Test with small real transaction
- [ ] Monitor for 24-48 hours

### 4. Optional Enhancements
- [ ] Add payment method selection in settings
- [ ] Add custom callback URL configuration
- [ ] Add transaction history page
- [ ] Add refund support (if UPay API supports it)
- [ ] Add webhook signature verification

---

## 🔗 Important Links

- **Union Bank Developer Portal (UAT):** https://developer-uat.unionbankph.com
- **Union Bank Developer Portal (Prod):** https://developer.unionbankph.com
- **WP Travel Engine Docs:** https://docs.wptravelengine.com
- **WordPress Plugin Handbook:** https://developer.wordpress.org/plugins/

---

## 📞 Support & Resources

### For Plugin Issues
- Check `/wp-content/debug.log`
- Enable WP_DEBUG mode
- Review SETUP_GUIDE.md

### For UPay API Issues
- Check API response in debug log
- Verify credentials are correct
- Contact Union Bank API support: apisupport@unionbankph.com

### For WP Travel Engine Issues
- Visit https://wptravelengine.com/support
- Check https://docs.wptravelengine.com

---

## 🎓 Technical Notes

### Why This Structure?

1. **Based on PayPal Express Reference** - Simple, proven pattern
2. **No Complex Encryption** - UPay uses simple header-based auth
3. **Standard WordPress Practices** - Follows WP coding standards
4. **WTE Compatibility** - Works with both old and new WTE versions
5. **Maintainable** - Clean separation of concerns

### Key Differences from HBL Gateway

| Feature | HBL Gateway | UPay Gateway |
|---------|-------------|--------------|
| **Authentication** | JOSE/JWT with 4 keys | Simple headers (3 keys) |
| **API Calls** | Guzzle with encryption | wp_remote_post() |
| **Complexity** | Very High | Low |
| **Files** | 15+ files | 7 core files |
| **Dependencies** | Multiple PHP libraries | None (native WP) |

---

## ✅ Deliverables Completed

1. ✅ Complete plugin structure
2. ✅ UPay API integration (POST /transactions, GET /status)
3. ✅ Admin settings interface
4. ✅ Payment processing logic
5. ✅ Callback handling
6. ✅ Status verification
7. ✅ WTE 5.0+ compatibility
8. ✅ WTE 6.0+ compatibility
9. ✅ Translation-ready
10. ✅ Documentation (README.md)
11. ✅ Setup guide (SETUP_GUIDE.md)
12. ✅ Code comments

---

## 🚀 Ready to Deploy!

The plugin is complete and ready for testing. Follow the SETUP_GUIDE.md for step-by-step instructions.

**Good luck with your project! 🎉**
