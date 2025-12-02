# 🚨 URGENT: Stripe Files Deployment Guide

## Problem

Your website is showing "Unexpected end of JSON input" because the **Stripe integration files haven't been uploaded to your server yet** (or are in the wrong location).

The test page at `https://halofibers.com/stripe/test.php` returns **404 Not Found**, confirming the files aren't on the server.

---

## ✅ What You Need to Upload

From your **LOCAL** folder: `/Users/walid/Desktop/Websites/HaloFibers.com/HaloFibersGit/stripe/`

Upload ALL of these to your **SERVER** at: `public_html/stripe/`

### Required Files & Folders:

```
stripe/
├── .htaccess                  ← Upload this
├── config.php                 ← Upload this (with your API keys!)
├── create-checkout.php        ← Upload this (updated version)
├── test.php                   ← Upload this (for testing)
├── setup-waldo-coupon.php     ← Upload this
└── stripe-php/                ← Upload this ENTIRE folder (400+ files)
    ├── init.php
    ├── lib/
    │   └── [389 PHP files]
    ├── data/
    ├── VERSION
    ├── API_VERSION
    └── [more files]
```

---

## 📋 Step-by-Step Deployment

### Method 1: Using Hostinger hPanel File Manager (Recommended for beginners)

#### Step 1: Access File Manager

1. Log in to **Hostinger hPanel**
2. Find your domain `halofibers.com`
3. Click **Files** → **File Manager**
4. You should see `public_html/` folder

#### Step 2: Check Current Structure

1. Open `public_html/` folder
2. Look for a folder named `stripe/`
3. **If `stripe/` folder exists:**
   - Click into it
   - Check if it has files inside
   - If yes, you may need to replace them
4. **If `stripe/` folder DOESN'T exist:**
   - Click **New Folder**
   - Name it: `stripe`
   - Click **Create**

#### Step 3: Upload Files

**Upload Method A - Individual Files (if folder exists):**

1. Click into the `stripe/` folder
2. Click **Upload Files**
3. Select these files from your local computer:
   - `.htaccess`
   - `config.php`
   - `create-checkout.php`
   - `test.php`
   - `setup-waldo-coupon.php`
4. Click **Upload**
5. Wait for confirmation

**Upload Method B - Zip File (easier for many files):**

1. On your **Mac**, compress the stripe folder:
   - Right-click the `stripe/` folder in `/Users/walid/Desktop/Websites/HaloFibers.com/HaloFibersGit/`
   - Choose **Compress "stripe"**
   - Creates `stripe.zip`
2. In hPanel File Manager, go to `public_html/`
3. Click **Upload Files**
4. Select `stripe.zip`
5. Wait for upload to complete
6. Right-click `stripe.zip` → **Extract**
7. Delete `stripe.zip` after extraction

#### Step 4: Upload stripe-php Folder

This is the MOST IMPORTANT part - the Stripe SDK has 400+ files.

1. Go into `public_html/stripe/` folder
2. Check if `stripe-php/` folder exists
3. **If it doesn't exist:**
   - On your Mac, create a zip of JUST the stripe-php folder
   - Upload `stripe-php.zip` to `public_html/stripe/`
   - Extract it
   - You should now have `public_html/stripe/stripe-php/`

#### Step 5: Verify config.php Has Real Keys

1. In File Manager, open `public_html/stripe/config.php`
2. Check that it shows:
   ```php
   define('STRIPE_SECRET_KEY', 'sk_live_51S0Ctg7iQUmPowK7...');
   ```
3. **NOT** this:
   ```php
   define('STRIPE_SECRET_KEY', 'sk_live_YOUR_SECRET_KEY_HERE');
   ```
4. If it still has placeholders, edit it with your real keys

---

### Method 2: Using SFTP (Faster, recommended for large uploads)

#### Step 1: Get SFTP Credentials

1. In Hostinger hPanel, go to **Files** → **FTP Accounts**
2. Note down:
   - **Host/Server**: (usually `ftp.halofibers.com` or similar)
   - **Username**: (your FTP username)
   - **Password**: (create one if needed)
   - **Port**: 21 (FTP) or 22 (SFTP)

#### Step 2: Download FTP Client

If you don't have one:
- **FileZilla** (free): https://filezilla-project.org/
- **Cyberduck** (free, Mac-friendly): https://cyberduck.io/

#### Step 3: Connect

1. Open your FTP client
2. Enter:
   - Host: your FTP host
   - Username: your FTP username
   - Password: your FTP password
   - Port: 21 or 22
3. Click **Connect**

#### Step 4: Navigate and Upload

1. **Local side** (left): Navigate to `/Users/walid/Desktop/Websites/HaloFibers.com/HaloFibersGit/`
2. **Server side** (right): Navigate to `public_html/`
3. Drag the entire `stripe/` folder from left to right
4. Wait for all 400+ files to upload (may take 5-10 minutes)

---

## 🔍 Verification Checklist

After uploading, verify these URLs:

### Test 1: Test Page Should Load
Visit: `https://halofibers.com/stripe/test.php`

**Expected Result:** 
- ✅ Shows a page with colored checkmarks and status
- NOT 404 error

**If 404:** The files aren't in the right place. Double-check folder structure.

### Test 2: Config Should Be Protected
Visit: `https://halofibers.com/stripe/config.php`

**Expected Result:**
- ✅ "Access Denied" or "Forbidden" error (this is GOOD!)
- NOT the actual PHP code

**If you see PHP code:** The .htaccess isn't working. Security issue!

### Test 3: Checkout Should Work or Show Error
1. Go to `https://halofibers.com`
2. Add a product to cart
3. Click "Proceed to Checkout"

**Expected Result:**
- ✅ Redirects to Stripe checkout page (success!)
- OR shows a specific error like "Invalid API key" (at least it's communicating now!)
- NOT "Unexpected end of JSON input" (that means files still missing)

---

## 🎯 Final Server Structure

Your Hostinger should look like this:

```
halofibers.com/
└── public_html/
    ├── index.html
    ├── success.html
    ├── cancel.html
    ├── images/
    │   └── [your image files]
    └── stripe/                    ← YOU ARE HERE
        ├── .htaccess              ← Security file
        ├── config.php             ← Your API keys (protected)
        ├── create-checkout.php    ← Main checkout endpoint
        ├── test.php               ← Diagnostic tool
        ├── setup-waldo-coupon.php ← WALDO code setup
        └── stripe-php/            ← 400+ PHP files
            ├── init.php
            ├── lib/
            │   ├── Stripe.php
            │   ├── Checkout/
            │   │   └── Session.php
            │   └── [388 more files]
            ├── data/
            ├── VERSION
            └── API_VERSION
```

---

## ❌ Common Mistakes

### Mistake 1: Uploaded to Wrong Location
**Wrong:** `public_html/create-checkout.php`  
**Right:** `public_html/stripe/create-checkout.php`

### Mistake 2: Forgot stripe-php Folder
The `stripe-php/` folder has 400+ files and is easy to miss. **You MUST upload this entire folder.**

### Mistake 3: Didn't Extract Zip
If you uploaded a zip file, you must **extract** it on the server.

### Mistake 4: Old config.php
Make sure `config.php` has your real API keys:
```php
define('STRIPE_SECRET_KEY', 'sk_live_51S0Ctg7iQUmPowK7Z19Sy...');
```

### Mistake 5: Forgot .htaccess
The `.htaccess` file is hidden on Mac. Make sure to upload it too!

**To see hidden files on Mac:**
- Press `Cmd + Shift + .` in Finder

---

## 🆘 Still Not Working?

### If test.php still shows 404:

The files aren't uploaded correctly. Double-check:
1. Files are in `public_html/stripe/` (not `public_html/`)
2. You extracted any zip files
3. File names are exactly: `test.php`, `create-checkout.php`, etc. (lowercase)

### If test.php loads but shows errors:

Good! Now you're communicating with the server. The test page will tell you exactly what's wrong:
- ❌ "config.php NOT FOUND" → Upload config.php
- ❌ "Stripe library NOT FOUND" → Upload stripe-php folder
- ⚠️ "API keys still contain placeholders" → Edit config.php with real keys

### If checkout still fails:

1. Visit `https://halofibers.com/stripe/test.php`
2. Look for any RED (fail) items
3. Fix those issues first
4. Try checkout again

---

## 📞 Need More Help?

If you're stuck, take a screenshot of:
1. Your Hostinger File Manager showing the `public_html/stripe/` folder contents
2. The test.php results page
3. The error you're seeing in the browser

This will help diagnose exactly what's wrong.

---

## ✅ Success Indicators

You'll know it's working when:
1. ✅ `https://halofibers.com/stripe/test.php` shows all green checkmarks
2. ✅ `https://halofibers.com/stripe/config.php` shows "Access Denied"
3. ✅ Clicking "Proceed to Checkout" redirects to Stripe's payment page

**Good luck! You're very close to having payments working!** 🚀

