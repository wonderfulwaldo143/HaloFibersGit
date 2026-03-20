<?php
/**
 * Stripe Checkout Session Creation Endpoint
 * 
 * This script receives cart data from the frontend and creates a Stripe Checkout Session.
 * The customer is then redirected to Stripe's hosted checkout page.
 */

// Clean any output buffers and start fresh
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://halofibers.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Please use POST.']);
    exit();
}

try {
    // Check if config file exists
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found. Please ensure config.php is uploaded to the stripe folder.');
    }
    
    // Load Stripe configuration
    require_once($configPath);
    
    // Verify constants are defined
    if (!defined('STRIPE_SECRET_KEY') || !defined('DOMAIN') || !defined('CURRENCY')) {
        throw new Exception('Configuration incomplete. Please check config.php settings.');
    }
    
    // Check if Stripe PHP library exists
    $stripePath = __DIR__ . '/stripe-php/init.php';
    if (!file_exists($stripePath)) {
        throw new Exception('Stripe PHP library not found. Please ensure stripe-php folder is uploaded.');
    }
    
    // Load Stripe PHP library
    require_once($stripePath);
    
    // Set Stripe API key
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    
    // Get cart data from POST request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Detailed validation with better error messages
    if (!$input) {
        throw new Exception('No data received. Please ensure cart data is being sent.');
    }
    
    if (!$data) {
        throw new Exception('Invalid JSON data received. Error: ' . json_last_error_msg());
    }
    
    if (!isset($data['items'])) {
        throw new Exception('Cart items missing from request.');
    }
    
    if (empty($data['items'])) {
        throw new Exception('Your cart is empty. Please add items to your cart.');
    }
    
    $cartItems = $data['items'];
    $lineItems = [];
    
    // Build line items for Stripe Checkout
    foreach ($cartItems as $index => $item) {
        if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception('Invalid item data at position ' . ($index + 1) . '. Missing name, price, or quantity.');
        }
        
        // Get the shade information if available
        $shade = isset($item['shade']) ? $item['shade'] : 'Not specified';
        
        // Create product description with shade info
        $description = "Shade: {$shade}";
        
        // Build line item
        // Note: Stripe expects amount in cents, so multiply by 100
        $lineItems[] = [
            'price_data' => [
                'currency' => CURRENCY,
                'product_data' => [
                    'name' => $item['name'],
                    'description' => $description,
                    'images' => [DOMAIN . '/images/product-bottle-transparent.png'], // Product image
                ],
                'unit_amount' => intval($item['price'] * 100), // Convert to cents
            ],
            'quantity' => intval($item['quantity']),
        ];
    }
    
    // Create Stripe Checkout Session
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => DOMAIN . '/success.html?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => DOMAIN . '/cancel.html',
        'shipping_address_collection' => [
            'allowed_countries' => ['US', 'CA'], // Add more countries as needed
        ],
        'billing_address_collection' => 'required',
        'phone_number_collection' => [
            'enabled' => true,
        ],
        'allow_promotion_codes' => true, // Enable promotion code field (e.g., WALDO for 99% off)
        'customer_email' => isset($data['email']) ? $data['email'] : null,
        'metadata' => [
            'order_source' => 'HaloFibers Website',
            'cart_items_count' => count($cartItems),
        ],
    ]);
    
    // Clean output buffer before sending JSON
    ob_end_clean();
    
    // Return the checkout session URL
    echo json_encode([
        'success' => true,
        'sessionId' => $session->id,
        'url' => $session->url,
    ]);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    // Stripe API error
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Payment system error: ' . $e->getMessage(),
    ]);
    
    // Log the error (optional)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('Stripe API Error: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    // General error
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    
    // Log the error (optional)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('Checkout Error: ' . $e->getMessage());
    }
} catch (Error $e) {
    // Fatal errors (PHP 7+)
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System error: ' . $e->getMessage(),
    ]);
    
    error_log('Fatal Error in checkout: ' . $e->getMessage());
}
