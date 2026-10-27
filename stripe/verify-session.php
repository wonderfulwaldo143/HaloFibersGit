<?php
declare(strict_types=1);
require_once __DIR__ . '/checkout-bootstrap.php';
try {
    $origin = hf_configuration();
    hf_https();
    hf_request($_SERVER, 'GET', $origin);
    if (!hf_exact_keys($_GET, ['session_id']) || !hf_session_id($_GET['session_id'])) {
        hf_json(['verified' => false]);
        exit;
    }
    $private = hf_private_directory();
    hf_owner($private);
    // Resolve ownership BEFORE constructing a provider or retrieving any session.
    $id = $_GET['session_id'];
    if (!isset($_SESSION['checkouts'][$id])) {
        session_write_close();
        hf_json(['verified' => false]);
        exit;
    }
    $owner = $_SESSION;
    session_write_close();
    $proof = hf_receipt($id, $origin, $owner, hf_provider(), time());
    if ($proof === null) {
        hf_json(['verified' => false]);
        exit;
    }
    $response = ['verified' => true];
    // Consent belongs to the originating checkout, never a query/body parameter.
    if ($proof['consent'] === true && hf_claim_purchase($private . '/purchases', $id)) {
        $response['ecommerce'] = $proof['ecommerce'];
    }
    hf_json($response);
} catch (Throwable $error) {
    hf_failure($error, true);
}
