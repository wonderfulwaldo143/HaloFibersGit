<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
umask(0077);
require_once __DIR__ . '/checkout-core.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function hf_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

function hf_configuration(): string
{
    // Keep the existing privately provisioned configuration and SDK untouched.
    // No environment/query-selected config, provider URL, credentials or adapter.
    $path = __DIR__ . '/config.php';
    if (!is_file($path) || !is_readable($path)) {
        throw new HFError('payment_unavailable', 503);
    }
    ob_start();
    try {
        require_once $path;
    } finally {
        ob_end_clean();
    }
    if (!defined('DOMAIN') || DOMAIN !== 'https://halofibers.com' ||
        !defined('CURRENCY') || CURRENCY !== 'usd' ||
        !defined('STRIPE_SECRET_KEY') || !is_string(STRIPE_SECRET_KEY) || STRIPE_SECRET_KEY === '') {
        throw new HFError('payment_unavailable', 503);
    }
    return DOMAIN;
}

function hf_provider(): HFProvider
{
    if (!is_file(__DIR__ . '/stripe-php/init.php')) {
        throw new HFError('payment_unavailable', 503);
    }
    require_once __DIR__ . '/stripe-php/init.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    \Stripe\Stripe::setMaxNetworkRetries(0);
    // Keep provider base URL and API version unchanged. Bound the existing SDK.
    $http = \Stripe\HttpClient\CurlClient::instance();
    $http->setConnectTimeout(5);
    $http->setTimeout(15);
    return new HFStripeProvider();
}

function hf_https(): void
{
    // Do not trust caller-supplied Forwarded/X-Forwarded-Proto headers.
    if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['SERVER_PORT'] ?? '') !== '443') {
        throw new HFError('https_required', 403);
    }
}

function hf_private_directory(): string
{
    $webroot = realpath(dirname(__DIR__));
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$webroot || !$documentRoot) {
        throw new HFError('payment_unavailable', 503);
    }
    $path = dirname($webroot) . '/.halofibers-checkout';
    // Reject a nested-webroot layout before creating even an empty directory.
    if ($path === $documentRoot || strpos($path, $documentRoot . '/') === 0 ||
        $path === $webroot || strpos($path, $webroot . '/') === 0) {
        throw new HFError('payment_unavailable', 503);
    }
    foreach ([$path, $path . '/sessions', $path . '/purchases'] as $directory) {
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700))) {
            throw new HFError('payment_unavailable', 503);
        }
        $real = realpath($directory);
        $stat = stat($directory);
        if (!$real || !$stat || ($stat['mode'] & 0077) !== 0 ||
            $real === $documentRoot || strpos($real, $documentRoot . '/') === 0 ||
            $real === $webroot || strpos($real, $webroot . '/') === 0) {
            throw new HFError('payment_unavailable', 503);
        }
    }
    return $path;
}

function hf_owner(string $directory): void
{
    if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
        throw new HFError('payment_unavailable', 503);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '604800');
    session_save_path($directory . '/sessions');
    session_name('__Host-hf_checkout');
    session_set_cookie_params(['lifetime' => 604800, 'path' => '/', 'domain' => '',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) {
        throw new HFError('payment_unavailable', 503);
    }
}

function hf_failure(Throwable $error, bool $receipt = false): void
{
    // Never serialize or log raw provider errors, keys, local paths or PII.
    $status = $error instanceof HFError ? $error->httpStatus : 503;
    $code = $error instanceof HFError ? $error->getMessage() : 'payment_unavailable';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    hf_json($receipt ? ['verified' => false] : ['success' => false, 'error' => $code], $status);
}
