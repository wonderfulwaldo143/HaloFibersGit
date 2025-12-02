<?php
/**
 * Stripe Environment Test Script
 * 
 * This script helps diagnose issues with the Stripe integration.
 * It checks if all required files are in place and if PHP is configured correctly.
 * 
 * USAGE: Visit https://halofibers.com/stripe/test.php
 * DELETE THIS FILE after troubleshooting is complete (security best practice)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$results = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'checks' => [],
];

// Check 1: PHP Version
$phpVersion = phpversion();
$results['checks']['php_version'] = [
    'status' => version_compare($phpVersion, '7.1', '>=') ? 'pass' : 'fail',
    'value' => $phpVersion,
    'message' => version_compare($phpVersion, '7.1', '>=') 
        ? 'PHP version is compatible' 
        : 'PHP version too old (need 7.1+)',
];

// Check 2: Config file exists
$configPath = __DIR__ . '/config.php';
$configExists = file_exists($configPath);
$results['checks']['config_file'] = [
    'status' => $configExists ? 'pass' : 'fail',
    'path' => $configPath,
    'message' => $configExists ? 'config.php found' : 'config.php NOT FOUND - please upload',
];

// Check 3: Config file is readable
if ($configExists) {
    $configReadable = is_readable($configPath);
    $results['checks']['config_readable'] = [
        'status' => $configReadable ? 'pass' : 'fail',
        'message' => $configReadable ? 'config.php is readable' : 'config.php cannot be read (permissions issue)',
    ];
    
    // Check 4: Load config and verify constants
    if ($configReadable) {
        try {
            require_once($configPath);
            
            $requiredConstants = ['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'DOMAIN', 'CURRENCY'];
            $missingConstants = [];
            $configuredConstants = [];
            
            foreach ($requiredConstants as $constant) {
                if (defined($constant)) {
                    $value = constant($constant);
                    $configuredConstants[$constant] = [
                        'defined' => true,
                        'has_value' => !empty($value),
                        'is_placeholder' => strpos($value, 'YOUR_') !== false,
                    ];
                } else {
                    $missingConstants[] = $constant;
                }
            }
            
            $results['checks']['config_constants'] = [
                'status' => empty($missingConstants) ? 'pass' : 'fail',
                'constants' => $configuredConstants,
                'missing' => $missingConstants,
                'message' => empty($missingConstants) 
                    ? 'All required constants defined' 
                    : 'Missing constants: ' . implode(', ', $missingConstants),
            ];
            
            // Check if API keys are still placeholders
            if (defined('STRIPE_SECRET_KEY')) {
                $hasRealKey = strpos(STRIPE_SECRET_KEY, 'YOUR_') === false;
                $results['checks']['api_keys_configured'] = [
                    'status' => $hasRealKey ? 'pass' : 'warning',
                    'message' => $hasRealKey 
                        ? 'API keys appear to be configured' 
                        : 'API keys still contain placeholders - need real Stripe keys',
                ];
            }
            
        } catch (Exception $e) {
            $results['checks']['config_load'] = [
                'status' => 'fail',
                'message' => 'Error loading config: ' . $e->getMessage(),
            ];
        }
    }
}

// Check 5: Stripe PHP library exists
$stripePath = __DIR__ . '/stripe-php/init.php';
$stripeExists = file_exists($stripePath);
$results['checks']['stripe_library'] = [
    'status' => $stripeExists ? 'pass' : 'fail',
    'path' => $stripePath,
    'message' => $stripeExists ? 'Stripe PHP library found' : 'Stripe PHP library NOT FOUND - please upload stripe-php folder',
];

// Check 6: Stripe library is loadable
if ($stripeExists) {
    try {
        require_once($stripePath);
        $results['checks']['stripe_loadable'] = [
            'status' => 'pass',
            'message' => 'Stripe library loaded successfully',
            'stripe_version' => defined('Stripe\\Stripe::VERSION') ? \Stripe\Stripe::VERSION : 'unknown',
        ];
    } catch (Exception $e) {
        $results['checks']['stripe_loadable'] = [
            'status' => 'fail',
            'message' => 'Error loading Stripe library: ' . $e->getMessage(),
        ];
    }
}

// Check 7: Create checkout script exists
$checkoutPath = __DIR__ . '/create-checkout.php';
$checkoutExists = file_exists($checkoutPath);
$results['checks']['checkout_script'] = [
    'status' => $checkoutExists ? 'pass' : 'fail',
    'path' => $checkoutPath,
    'message' => $checkoutExists ? 'create-checkout.php found' : 'create-checkout.php NOT FOUND - please upload',
];

// Check 8: .htaccess exists
$htaccessPath = __DIR__ . '/.htaccess';
$htaccessExists = file_exists($htaccessPath);
$results['checks']['htaccess'] = [
    'status' => $htaccessExists ? 'pass' : 'warning',
    'path' => $htaccessPath,
    'message' => $htaccessExists ? '.htaccess found (security configured)' : '.htaccess NOT FOUND - config.php is not protected',
];

// Check 9: Test JSON encoding
try {
    $testData = ['test' => 'data', 'number' => 123];
    $encoded = json_encode($testData);
    if ($encoded === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    $results['checks']['json_support'] = [
        'status' => 'pass',
        'message' => 'JSON encoding/decoding works correctly',
    ];
} catch (Exception $e) {
    $results['checks']['json_support'] = [
        'status' => 'fail',
        'message' => 'JSON support issue: ' . $e->getMessage(),
    ];
}

// Overall status
$failCount = 0;
foreach ($results['checks'] as $check) {
    if ($check['status'] === 'fail') {
        $failCount++;
    }
}

$results['overall_status'] = $failCount === 0 ? 'ready' : 'issues_found';
$results['fail_count'] = $failCount;
$results['message'] = $failCount === 0 
    ? 'All checks passed! Stripe integration should work.' 
    : "Found {$failCount} issue(s) that need to be fixed.";

// Add security warning
$results['security_warning'] = 'IMPORTANT: Delete this test.php file after troubleshooting. It exposes system information.';

// Pretty print for browser viewing
if (!isset($_GET['raw'])) {
    echo '<html><head><title>Stripe Test Results</title><style>';
    echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }';
    echo '.pass { color: #16a34a; font-weight: bold; }';
    echo '.fail { color: #dc2626; font-weight: bold; }';
    echo '.warning { color: #ea580c; font-weight: bold; }';
    echo 'pre { background: #f3f4f6; padding: 15px; border-radius: 8px; overflow-x: auto; }';
    echo 'h1 { color: #1f2937; }';
    echo '.check { background: white; padding: 15px; margin: 10px 0; border: 1px solid #e5e7eb; border-radius: 8px; }';
    echo '.check h3 { margin-top: 0; }';
    echo '</style></head><body>';
    echo '<h1>🔍 Stripe Environment Test</h1>';
    echo '<p><strong>Status:</strong> <span class="' . ($results['overall_status'] === 'ready' ? 'pass' : 'fail') . '">' 
        . strtoupper($results['overall_status']) . '</span></p>';
    echo '<p>' . $results['message'] . '</p>';
    echo '<hr>';
    
    foreach ($results['checks'] as $name => $check) {
        echo '<div class="check">';
        echo '<h3>' . ucwords(str_replace('_', ' ', $name)) . ': <span class="' . $check['status'] . '">' 
            . strtoupper($check['status']) . '</span></h3>';
        echo '<p>' . $check['message'] . '</p>';
        if (isset($check['path'])) {
            echo '<p><small><code>' . $check['path'] . '</code></small></p>';
        }
        echo '</div>';
    }
    
    echo '<hr>';
    echo '<p style="color: #dc2626;"><strong>⚠️ ' . $results['security_warning'] . '</strong></p>';
    echo '<p><a href="?raw=1">View Raw JSON</a></p>';
    echo '<h3>Full Details (JSON):</h3>';
    echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
    echo '</body></html>';
} else {
    echo json_encode($results, JSON_PRETTY_PRINT);
}


