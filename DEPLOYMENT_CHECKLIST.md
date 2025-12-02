# 🚀 Stripe Integration Deployment Checklist

## Before You Deploy

- [ ] **Get Stripe API Keys**
  - Log in to https://dashboard.stripe.com/
  - Go to Developers → API keys
  - Copy Secret Key (sk_live_...)
  - Copy Publishable Key (pk_live_...)

- [ ] **Configure Local Files**
  - [ ] Open `stripe/config.php`
  - [ ] Replace `sk_live_YOUR_SECRET_KEY_HERE` with your actual secret key
  - [ ] Replace `pk_live_YOUR_PUBLISHABLE_KEY_HERE` with your actual publishable key
  - [ ] Update `DOMAIN` to `https://halofibers.com` (your actual domain)
  - [ ] Save the file

## Files to Upload to Hostinger

Copy these files to `public_html/` on your Hostinger account:

### New Files:
- [ ] `stripe/` folder (entire directory)
  - [ ] `stripe/.htaccess`
  - [ ] `stripe/config.php` (with your API keys!)
  - [ ] `stripe/create-checkout.php`
  - [ ] `stripe/stripe-php/` (entire folder with all SDK files)
- [ ] `success.html`
- [ ] `cancel.html`

### Updated Files:
- [ ] `index.html` (checkout function updated)

## After Upload - Testing

- [ ] Visit your website homepage
- [ ] Add a product to cart (try "Ultimate Bundle")
- [ ] Open cart (click cart icon in header)
- [ ] Click "Proceed to Checkout" button
- [ ] **Expected**: Redirected to Stripe's checkout page
- [ ] Enter shipping address
- [ ] Enter test card: 4242 4242 4242 4242
- [ ] Use any future expiry date and any 3-digit CVC
- [ ] Complete payment
- [ ] **Expected**: Redirected to success.html with confirmation
- [ ] Check Stripe Dashboard for the payment record

## Security Verification

- [ ] Try to access `https://halofibers.com/stripe/config.php` directly
  - [ ] **Expected**: Access denied (403 error) ✅
- [ ] Verify `.htaccess` file was uploaded to `stripe/` folder
- [ ] Check that API keys are NOT visible in browser console

## Final Checks

- [ ] Test on mobile device
- [ ] Test with different product bundles
- [ ] Test with different shade selections
- [ ] Test cart quantity changes
- [ ] Verify success email from Stripe arrives
- [ ] Test cancel flow (click back during checkout)

## Go Live!

- [ ] Switch from test mode to live mode (if you used test keys)
- [ ] Set `DEBUG_MODE` to `false` in `stripe/config.php`
- [ ] Announce your store is open! 🎉

---

## Quick Reference

**Stripe Dashboard**: https://dashboard.stripe.com/  
**Test Cards**: https://stripe.com/docs/testing  
**Support Email**: info@halofibers.com

**Need help?** See `STRIPE_SETUP_GUIDE.md` for detailed instructions.

