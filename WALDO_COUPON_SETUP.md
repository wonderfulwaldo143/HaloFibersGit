# WALDO Discount Code Setup Guide

## 🎉 Overview

The WALDO discount code provides **99% OFF** all products with unlimited uses. Customers can enter this code during Stripe Checkout.

---

## ✅ Step 1: Create the Coupon (AUTOMATED!)

### Option A: Automated Setup (Recommended) ⚡

**The easiest way** - Just visit a URL and it's done automatically:

1. **Upload the setup script**
   - Upload `stripe/setup-waldo-coupon.php` to your server
   - Path: `public_html/stripe/setup-waldo-coupon.php`

2. **Run the script**
   - Visit: `https://halofibers.com/stripe/setup-waldo-coupon.php`
   - You'll see a success message
   - The WALDO coupon is created automatically!

3. **Delete the script** (security)
   - Remove `stripe/setup-waldo-coupon.php` from your server
   - Done! ✅

**That's it!** The script uses the Stripe API to create everything automatically.

---

### Option B: Manual Setup in Dashboard

If you prefer to create it manually:

1. **Log in to Stripe Dashboard**
   - Visit: https://dashboard.stripe.com/
   - Use your Stripe account credentials

2. **Navigate to Coupons**
   - Click **"Products"** in the left sidebar
   - Click **"Coupons"**
   - Click the **"+ Create coupon"** button

3. **Configure the Coupon**

   Fill in these exact values:

   | Field | Value |
   |-------|-------|
   | **Name** | `WALDO - 99% Off Special` |
   | **Coupon ID** | `WALDO` (this is what customers type) |
   | **Type** | Percentage discount |
   | **Percent off** | `99` |
   | **Duration** | Forever (or set expiration if desired) |
   | **Currency** | USD |
   | **Max redemptions** | Leave blank (unlimited) |
   | **Redeem by** | Leave blank (no expiry) |

4. **Create Promotion Code (Important!)**
   
   After creating the coupon, you need to create a promotion code:
   
   - Click on the newly created WALDO coupon
   - Click **"Create promotion code"**
   - Enter `WALDO` as the promotion code
   - Leave "Redemption limit" blank for unlimited uses
   - Click **"Create promotion code"**

5. **Click "Create coupon"** to save

---

## 🎯 How Customers Use the Code

### Customer Experience:

1. Customer shops on **halofibers.com**
2. Adds products to cart (e.g., Ultimate Bundle $69.95)
3. Clicks **"Proceed to Checkout"**
4. Redirected to **Stripe Checkout** page
5. Clicks **"Add promotion code"** link
6. Enters: `WALDO`
7. **Discount applied!** 
   - Original: $69.95
   - With WALDO: **$0.70** (99% off)
8. Completes payment with discounted price

---

## 💡 Visual Example

**Without WALDO:**
```
Ultimate Bundle: $69.95
Tax: $0.00
Total: $69.95
```

**With WALDO:**
```
Ultimate Bundle: $69.95
Discount (WALDO -99%): -$69.25
Tax: $0.00
Total: $0.70
```

---

## 🔒 Managing the Code

### To View Usage Statistics:

1. Go to Stripe Dashboard → **Products** → **Coupons**
2. Click on **WALDO**
3. View:
   - Total redemptions
   - Total amount discounted
   - Recent usage

### To Disable the Code:

1. Go to Stripe Dashboard → **Products** → **Coupons**
2. Find **WALDO** coupon
3. Click the **"..." menu** → **"Archive"**
4. Code immediately stops working
5. Customers will see "Invalid promotion code" if they try to use it

### To Re-enable the Code:

1. Go to Stripe Dashboard → **Products** → **Coupons**
2. Filter by **"Archived"**
3. Find **WALDO**
4. Click **"Unarchive"**
5. Code works again immediately

---

## 🧪 Testing Before Going Live

**Recommended: Test in Test Mode First**

1. **Switch to Test Mode**
   - In Stripe Dashboard, toggle to **"Test mode"** (top right)

2. **Create Test WALDO Coupon**
   - Follow the same steps above in Test mode
   - Create coupon ID: `WALDO`
   - Set to 99% off

3. **Update Your Site to Test Mode** (temporarily)
   - Edit `stripe/config.php`
   - Replace live keys with test keys:
     ```php
     define('STRIPE_SECRET_KEY', 'sk_test_...');
     define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
     ```

4. **Test the Checkout**
   - Add product to cart
   - Go to checkout
   - Enter WALDO code
   - Use test card: `4242 4242 4242 4242`
   - Verify 99% discount is applied

5. **Switch Back to Live Mode**
   - Restore live API keys in `stripe/config.php`
   - Create the WALDO coupon in **Live mode**
   - You're ready to go!

---

## 📊 Tracking & Analytics

### Where to See WALDO Usage:

1. **Stripe Dashboard → Payments**
   - Filter by "Discount applied"
   - See all orders that used WALDO

2. **Stripe Dashboard → Coupons → WALDO**
   - Total times redeemed
   - Total amount saved by customers
   - Revenue impact

3. **Individual Orders**
   - Click any payment in Dashboard
   - See "Discount: WALDO (-99%)" in order details

---

## ⚠️ Important Notes

### Free Shipping:

The WALDO code only handles the **price discount (99% off)**. Since you're collecting shipping addresses through Stripe but not charging for shipping in the checkout flow, you'll need to:

1. Check each order in Stripe Dashboard
2. Look for orders with WALDO discount applied
3. Apply free shipping when you fulfill those orders

### Combining with Other Discounts:

Stripe allows only **one promotion code per checkout**. If you create other codes in the future, customers can only use one at a time.

### Minimum Order Value:

There's no minimum. A customer could order:
- Starter Bundle ($24.99) → Pay $0.25 with WALDO
- Pro Bundle ($44.95) → Pay $0.45 with WALDO
- Ultimate Bundle ($69.95) → Pay $0.70 with WALDO

---

## 🚀 Quick Setup Checklist

- [ ] Log in to https://dashboard.stripe.com/
- [ ] Go to Products → Coupons
- [ ] Click "Create coupon"
- [ ] Set Name: "WALDO - 99% Off Special"
- [ ] Set ID: "WALDO"
- [ ] Set Type: Percentage discount
- [ ] Set Percent: 99
- [ ] Set Duration: Forever
- [ ] Click "Create coupon"
- [ ] Click "Create promotion code"
- [ ] Enter "WALDO" as promotion code
- [ ] Click "Create promotion code"
- [ ] ✅ Done! Code is live immediately

---

## 📞 Support

If you have issues creating the coupon:

- **Stripe Support**: https://support.stripe.com/
- **Stripe Docs**: https://stripe.com/docs/billing/subscriptions/coupons
- **Contact**: info@halofibers.com

---

## ✨ You're All Set!

Once you create the WALDO coupon in Stripe Dashboard following the steps above, customers will be able to use it immediately. The code field is already enabled in your checkout flow.

**Happy discounting! 🎊**

