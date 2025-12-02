# Stripe Integration Setup Guide for HaloFibers

## 🎉 Integration Complete!

Your HaloFibers website now has full Stripe Checkout integration. This guide will walk you through the final configuration steps to go live.

---

## 📋 What Was Implemented

✅ Stripe PHP SDK (no Composer required)  
✅ Secure configuration file for API keys  
✅ PHP endpoint to create Stripe Checkout Sessions  
✅ Updated checkout flow in index.html  
✅ Professional success page (order confirmation)  
✅ Professional cancel page (abandoned checkout)  
✅ Security via .htaccess (protects API keys)

---

## 🚀 Deployment Steps

### Step 1: Get Your Stripe API Keys

1. Log in to your Stripe Dashboard: https://dashboard.stripe.com/
2. Navigate to **Developers** → **API keys**
3. Copy both keys:
   - **Secret key** (starts with `sk_live_...`)
   - **Publishable key** (starts with `pk_live_...`)

⚠️ **Important**: Keep your secret key private! Never share it or commit it to Git.

### Step 2: Configure Your API Keys

1. Open `/stripe/config.php` in your local editor
2. Replace the placeholder values:

```php
define('STRIPE_SECRET_KEY', 'sk_live_YOUR_ACTUAL_SECRET_KEY');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_YOUR_ACTUAL_PUBLISHABLE_KEY');
```

3. Update your domain (replace with your actual domain):

```php
define('DOMAIN', 'https://halofibers.com');
```

4. Save the file

### Step 3: Upload Files to Hostinger

Using **hPanel File Manager** or **SFTP**, upload these files to your `public_html/` directory:

#### New Files to Upload:
```
public_html/
├── stripe/
│   ├── .htaccess                    ✨ NEW
│   ├── config.php                   ✨ NEW (with your API keys)
│   ├── create-checkout.php          ✨ NEW
│   └── stripe-php/                  ✨ NEW (entire folder)
│       └── [all SDK files]
├── success.html                     ✨ NEW
├── cancel.html                      ✨ NEW
└── index.html                       🔄 UPDATED (checkout function)
```

#### Upload Instructions:

**Option A: Via hPanel File Manager**
1. Log in to Hostinger hPanel
2. Go to **Files** → **File Manager**
3. Navigate to `public_html/`
4. Create a new folder called `stripe`
5. Upload all files from your local `stripe/` folder
6. Upload `success.html` and `cancel.html` to `public_html/`
7. Replace `index.html` with the updated version

**Option B: Via SFTP (Recommended for faster upload)**
1. Use an SFTP client (FileZilla, Cyberduck, etc.)
2. Get SFTP credentials from Hostinger hPanel
3. Connect and upload the files to `public_html/`

### Step 4: Test Your Integration

1. Visit your website: `https://halofibers.com`
2. Add a product to cart
3. Click "Proceed to Checkout"
4. You should be redirected to Stripe's hosted checkout page
5. Complete a test purchase using a real card (it will charge you!)
6. Verify you're redirected to the success page
7. Check your Stripe Dashboard to see the payment

**Test Card Numbers (for TEST mode only):**
- Success: `4242 4242 4242 4242`
- Declined: `4000 0000 0000 0002`
- Any future expiry date, any 3-digit CVC

---

## 🔒 Security Features

✅ API keys stored server-side (not in browser)  
✅ `.htaccess` prevents direct access to `config.php`  
✅ CORS headers configured  
✅ Input validation on cart data  
✅ Error handling without exposing sensitive info  
✅ PCI compliant (Stripe handles all card data)

---

## 🛠 How It Works

1. **Customer adds items to cart** → Cart stored in browser localStorage
2. **Customer clicks checkout** → JavaScript sends cart data to `/stripe/create-checkout.php`
3. **PHP creates Stripe Session** → Includes line items, prices, shipping collection
4. **Customer redirected to Stripe** → Enters payment and shipping info securely
5. **Payment processed** → Customer redirected to `success.html` or `cancel.html`

---

## 📦 Shipping Configuration

Currently configured to collect shipping addresses for:
- United States (US)
- Canada (CA)

To add more countries, edit `/stripe/create-checkout.php` line 78:

```php
'allowed_countries' => ['US', 'CA', 'GB', 'AU', 'DE', 'FR'],
```

Full list of country codes: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2

---

## 💰 Product Configuration

Your current products (automatically passed from cart):

| Product | Price | Description |
|---------|-------|-------------|
| Starter Bundle | $24.99 | 1 bottle |
| Pro Bundle | $44.95 | 2 bottles |
| Ultimate Bundle | $69.95 | 3 bottles |

Products include shade information (Black, Dark Brown, etc.) in the Stripe order.

---

## 🧪 Testing Checklist

Before going live, test these scenarios:

- [ ] Add single item to cart, checkout successfully
- [ ] Add multiple items with different shades, checkout successfully
- [ ] Click "back" during Stripe checkout (should show cancel page)
- [ ] Complete payment (should show success page)
- [ ] Check Stripe Dashboard for payment record
- [ ] Verify order email is sent (Stripe sends automatic receipts)
- [ ] Test on mobile device
- [ ] Test with different browsers

---

## ⚙️ Configuration Options

### Enable Test Mode (for testing without real charges)

In `/stripe/config.php`, replace LIVE keys with TEST keys:

```php
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
```

Get test keys from: https://dashboard.stripe.com/test/apikeys

### Enable Debug Mode

In `/stripe/config.php`, set:

```php
define('DEBUG_MODE', true);
```

This will log errors to your server's error log (check in hPanel or via SFTP).

⚠️ **Remember to disable debug mode in production!**

---

## 🚨 Troubleshooting

### Error: "Failed to create checkout session"

**Causes:**
- API keys not configured correctly
- Domain mismatch in `config.php`
- PHP version too old (requires PHP 7.1+)

**Solution:**
1. Double-check your API keys in `config.php`
2. Verify DOMAIN matches your actual website URL
3. Check Hostinger PHP version (should be 7.4+ or 8.x)

### Error: "Access denied" when accessing config.php

**This is correct!** The `.htaccess` file prevents direct access to protect your API keys.

### Cart not sending to Stripe

**Solution:**
1. Open browser console (F12)
2. Check for JavaScript errors
3. Verify `/stripe/create-checkout.php` exists on server
4. Test the endpoint directly: `curl -X POST https://yourdomain.com/stripe/create-checkout.php`

### Checkout works but success page not showing

**Solution:**
1. Verify `success.html` is in `public_html/`
2. Check the DOMAIN in `config.php` matches your actual domain
3. Look for redirect issues in browser console

---

## 📞 Support Resources

- **Stripe Documentation**: https://stripe.com/docs
- **Stripe Dashboard**: https://dashboard.stripe.com/
- **Test Cards**: https://stripe.com/docs/testing
- **Hostinger Support**: https://www.hostinger.com/contact

---

## 🎯 Next Steps (Optional Enhancements)

Consider adding these features later:

1. **Webhooks**: Receive real-time payment notifications
2. **Order tracking**: Save orders to a database
3. **Email notifications**: Send custom order confirmations
4. **Coupon codes**: Stripe supports discount codes
5. **Subscriptions**: If you want recurring billing
6. **Analytics**: Track conversions with Google Analytics

---

## 📝 Change Summary

**Goal**: Integrate Stripe Checkout to accept payments for HaloFibers products

**Files Changed**:
- `index.html` - Updated `checkout()` function to call Stripe API
- `stripe/config.php` - NEW (API keys configuration)
- `stripe/create-checkout.php` - NEW (Stripe Session endpoint)
- `stripe/.htaccess` - NEW (security configuration)
- `stripe/stripe-php/` - NEW (Stripe PHP SDK)
- `success.html` - NEW (order confirmation page)
- `cancel.html` - NEW (checkout cancellation page)

**Notes**:
- No server runtime required (pure PHP, works on shared hosting)
- PCI compliant (Stripe handles all card data)
- Shipping address collection enabled for US and Canada
- 30-day money-back guarantee messaging included
- Mobile-responsive design matching your brand

**Verify**:
1. Upload all files to `public_html/`
2. Configure API keys in `stripe/config.php`
3. Test checkout flow end-to-end
4. Verify success/cancel pages work
5. Check Stripe Dashboard for test payment

---

## 🎊 You're All Set!

Once you complete the deployment steps above, your HaloFibers website will be accepting real payments through Stripe. Congratulations! 🚀

If you need any assistance, refer to the troubleshooting section or contact Stripe support.

**Happy selling! 💰**

