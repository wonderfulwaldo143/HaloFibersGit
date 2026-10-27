<?php
declare(strict_types=1);
require_once __DIR__ . '/checkout-bootstrap.php';
try {
    $origin = hf_configuration();
    hf_https();
    hf_request($_SERVER, 'POST', $origin);
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false) {
        throw new HFError('invalid_json');
    }
    $data = hf_decode($raw);
    $catalog = hf_catalog(require __DIR__ . '/catalog-config.php');
    $cart = hf_cart($data, $catalog);
    $private = hf_private_directory();
    hf_owner($private);
    $response = hf_create($cart, $origin, $_SESSION, hf_provider(), time());
    // Do not return an unowned redirect if session persistence fails.
    if (!session_write_close()) {
        throw new HFError('payment_unavailable', 503);
    }
    hf_json($response);
} catch (Throwable $error) {
    hf_failure($error);
}
