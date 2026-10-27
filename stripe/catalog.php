<?php
declare(strict_types=1);
require_once __DIR__ . '/checkout-bootstrap.php';
try {
    hf_https();
    hf_request($_SERVER, 'GET', 'https://halofibers.com');
    hf_json(hf_catalog(require __DIR__ . '/catalog-config.php'));
} catch (Throwable $error) {
    hf_failure($error);
}
