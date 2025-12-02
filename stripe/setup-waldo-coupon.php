<?php
/**
 * WALDO Coupon Setup Script
 * 
 * This script automatically creates the WALDO discount coupon (99% off) in your Stripe account.
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your server: public_html/stripe/setup-waldo-coupon.php
 * 2. Visit: https://halofibers.com/stripe/setup-waldo-coupon.php
 * 3. See the success message
 * 4. DELETE this file from your server (security best practice)
 * 
 * SECURITY: Delete this file after running it once!
 */

// Load Stripe configuration
require_once(__DIR__ . '/config.php');

// Load Stripe PHP library
require_once(__DIR__ . '/stripe-php/init.php');

// Set Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// HTML Header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WALDO Coupon Setup - Halo Fibers</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #1a202c;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .detail {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #4a5568;
            width: 140px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #2d3748;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        .steps {
            margin-top: 20px;
            padding-left: 20px;
        }
        .steps li {
            margin: 10px 0;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        try {
            // Step 1: Check if coupon already exists
            $couponExists = false;
            $promotionCodeExists = false;
            
            try {
                $existingCoupon = \Stripe\Coupon::retrieve('WALDO');
                $couponExists = true;
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Coupon doesn't exist, which is fine
            }
            
            if ($couponExists) {
                echo '<div class="icon">ℹ️</div>';
                echo '<h1>Coupon Already Exists</h1>';
                echo '<p class="subtitle">The WALDO coupon is already configured in your Stripe account.</p>';
                
                echo '<div class="status info">';
                echo '<strong>Good news!</strong> The WALDO coupon already exists. No action needed.';
                echo '</div>';
                
                // Try to get promotion code details
                try {
                    $promotionCodes = \Stripe\PromotionCode::all(['code' => 'WALDO', 'limit' => 1]);
                    if (!empty($promotionCodes->data)) {
                        $promoCode = $promotionCodes->data[0];
                        
                        echo '<div class="detail">';
                        echo '<div class="detail-row"><div class="detail-label">Coupon ID:</div><div class="detail-value">WALDO</div></div>';
                        echo '<div class="detail-row"><div class="detail-label">Discount:</div><div class="detail-value">99% OFF</div></div>';
                        echo '<div class="detail-row"><div class="detail-label">Promotion Code:</div><div class="detail-value">WALDO</div></div>';
                        echo '<div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value">Active ✅</div></div>';
                        echo '<div class="detail-row"><div class="detail-label">Times Redeemed:</div><div class="detail-value">' . ($promoCode->times_redeemed ?? 0) . '</div></div>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    // Couldn't get promotion code, but that's okay
                }
                
            } else {
                // Step 2: Create the coupon
                $coupon = \Stripe\Coupon::create([
                    'id' => 'WALDO',
                    'name' => 'WALDO - 99% Off Special',
                    'percent_off' => 99,
                    'duration' => 'forever',
                    'currency' => 'usd',
                ]);
                
                // Step 3: Create the promotion code
                $promotionCode = \Stripe\PromotionCode::create([
                    'coupon' => 'WALDO',
                    'code' => 'WALDO',
                ]);
                
                // Success!
                echo '<div class="icon">🎉</div>';
                echo '<h1>Success! WALDO Coupon Created</h1>';
                echo '<p class="subtitle">Your 99% discount code is now active and ready to use.</p>';
                
                echo '<div class="status success">';
                echo '<strong>✅ Coupon created successfully!</strong><br>';
                echo 'Customers can now use the code <strong>WALDO</strong> at checkout for 99% off.';
                echo '</div>';
                
                echo '<div class="detail">';
                echo '<div class="detail-row"><div class="detail-label">Coupon ID:</div><div class="detail-value">' . $coupon->id . '</div></div>';
                echo '<div class="detail-row"><div class="detail-label">Name:</div><div class="detail-value">' . $coupon->name . '</div></div>';
                echo '<div class="detail-row"><div class="detail-label">Discount:</div><div class="detail-value">' . $coupon->percent_off . '% OFF</div></div>';
                echo '<div class="detail-row"><div class="detail-label">Duration:</div><div class="detail-value">' . ucfirst($coupon->duration) . '</div></div>';
                echo '<div class="detail-row"><div class="detail-label">Promotion Code:</div><div class="detail-value">' . $promotionCode->code . '</div></div>';
                echo '<div class="detail-row"><div class="detail-label">Stripe Dashboard:</div><div class="detail-value"><a href="https://dashboard.stripe.com/coupons/' . $coupon->id . '" target="_blank">View in Stripe</a></div></div>';
                echo '</div>';
            }
            
            // Next steps
            echo '<div class="status warning">';
            echo '<strong>⚠️ IMPORTANT - Security Step:</strong><br>';
            echo 'Please DELETE this file from your server now: <code>stripe/setup-waldo-coupon.php</code>';
            echo '</div>';
            
            echo '<div class="status info">';
            echo '<strong>How customers will use WALDO:</strong>';
            echo '<ol class="steps">';
            echo '<li>Add products to cart on halofibers.com</li>';
            echo '<li>Click "Proceed to Checkout"</li>';
            echo '<li>On Stripe checkout page, click "Add promotion code"</li>';
            echo '<li>Enter: <code>WALDO</code></li>';
            echo '<li>99% discount applied! 🎊</li>';
            echo '</ol>';
            echo '</div>';
            
            echo '<a href="https://dashboard.stripe.com/coupons/WALDO" class="btn" target="_blank">View in Stripe Dashboard</a>';
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Stripe API error
            echo '<div class="icon">❌</div>';
            echo '<h1>Error Creating Coupon</h1>';
            echo '<p class="subtitle">There was a problem communicating with Stripe.</p>';
            
            echo '<div class="status error">';
            echo '<strong>Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
            
            echo '<div class="status info">';
            echo '<strong>Possible causes:</strong><br>';
            echo '• Invalid API keys in config.php<br>';
            echo '• Network connectivity issue<br>';
            echo '• Stripe API is temporarily unavailable';
            echo '</div>';
            
        } catch (Exception $e) {
            // General error
            echo '<div class="icon">❌</div>';
            echo '<h1>Unexpected Error</h1>';
            echo '<p class="subtitle">An unexpected error occurred.</p>';
            
            echo '<div class="status error">';
            echo '<strong>Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

