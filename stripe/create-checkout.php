<?php
/**
 * Stripe Checkout Session Creation Endpoint
 * 
 * This script receives cart data from the frontend and creates a Stripe Checkout Session.
 * The customer is then redirected to Stripe's hosted checkout page.
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
    echo json_encode(['error' => 'Method not allowed. Please use POST.']);
    exit();
}

try {
    // Load Stripe configuration
    require_once(__DIR__ . '/config.php');
    
    // Load Stripe PHP library
    require_once(__DIR__ . '/stripe-php/init.php');
    
    // Set Stripe API key
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    
    // Get cart data from POST request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Invalid cart data. Please add items to your cart.');
    }
    
    $cartItems = $data['items'];
    $lineItems = [];
    
    // Build line items for Stripe Checkout
    foreach ($cartItems as $item) {
        if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception('Invalid item data.');
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
        'customer_email' => isset($data['email']) ? $data['email'] : null,
        'metadata' => [
            'order_source' => 'HaloFibers Website',
            'cart_items_count' => count($cartItems),
        ],
    ]);
    
    // Return the checkout session URL
    echo json_encode([
        'success' => true,
        'sessionId' => $session->id,
        'url' => $session->url,
    ]);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    // Stripe API error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Payment system error: ' . $e->getMessage(),
    ]);
    
    // Log the error (optional)
    if (DEBUG_MODE) {
        error_log('Stripe API Error: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    // General error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    
    // Log the error (optional)
    if (DEBUG_MODE) {
        error_log('Checkout Error: ' . $e->getMessage());
    }
}

