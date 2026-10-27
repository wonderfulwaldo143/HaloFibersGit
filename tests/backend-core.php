<?php
declare(strict_types=1);
require dirname(__DIR__) . '/stripe/checkout-core.php';
umask(0077);
$checks = 0;
function ok(bool $condition, string $label): void {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
function rejects(callable $function, string $label): void {
    try { $function(); } catch (HFError $e) { ok(true, $label); return; }
    ok(false, $label);
}
final class FakeProvider implements HFProvider {
    public $creates = 0;
    public $reads = 0;
    public $parameters;
    public $session;
    public $lineData;
    public function create(array $parameters): array {
        $this->creates++;
        $this->parameters = $parameters;
        return ['id' => 'cs_test_SyntheticFixture12345', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_SyntheticFixture12345#fixture'];
    }
    public function retrieve(string $id): array { $this->reads++; return $this->session; }
    public function lines(string $id): array { $this->reads++; return $this->lineData; }
}
$catalog = hf_catalog(['starter_unit_amount' => 1234]); // Synthetic, NOT an owner price.
rejects(function () { hf_catalog(['starter_unit_amount' => null]); }, 'pending fail closed');
foreach ([0, -1, 1.2, '1234', 100001, PHP_INT_MAX] as $bad) {
    rejects(function () use ($bad) { hf_catalog(['starter_unit_amount' => $bad]); }, 'catalog integer bound');
}
ok($catalog['products']['hf-pro']['unit_amount'] === 4495 && count($catalog['shades']) === 9, 'stable catalog');
$valid = ['items' => [['sku' => 'hf-starter', 'shade' => 'black', 'quantity' => 1]], 'analytics_consent' => true];
$cart = hf_cart($valid, $catalog);
ok($cart['subtotal'] === 1234, 'authoritative synthetic cents');
foreach (['price' => 0.01, 'currency' => 'eur', 'name' => 'free'] as $key => $value) {
    $bad = $valid; $bad['items'][0][$key] = $value;
    rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'reject client item field ' . $key);
    $bad = $valid; $bad[$key] = $value;
    rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'reject client top field ' . $key);
}
foreach ([1.0, 1.2, '1', -1, 0, 11, PHP_INT_MAX, true, null, [], 1e100] as $quantity) {
    $bad = $valid; $bad['items'][0]['quantity'] = $quantity;
    rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'reject quantity type/range');
}
foreach ([['sku', 'unknown'], ['sku', 1], ['shade', 'Black'], ['shade', 'unknown'], ['shade', []]] as $pair) {
    $bad = $valid; $bad['items'][0][$pair[0]] = $pair[1];
    rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'unknown SKU/shade');
}
rejects(function () use ($catalog) { hf_cart(['items' => [['name' => 'Starter Bundle', 'price' => 0.01, 'shade' => 'black', 'quantity' => 1]]], $catalog); }, 'old contract rejected');
foreach ([null, 'true', 1] as $consent) {
    $bad = $valid; $bad['analytics_consent'] = $consent;
    rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'consent boolean');
}
$dupe = $valid; $dupe['items'][] = $dupe['items'][0];
$merged = hf_cart($dupe, $catalog);
ok(count($merged['items']) === 1 && $merged['items'][0]['quantity'] === 2 && $merged['subtotal'] === 2468, 'duplicate merge');
$dupe['items'][0]['quantity'] = 10;
rejects(function () use ($dupe, $catalog) { hf_cart($dupe, $catalog); }, 'duplicate quantity bound');
$many = $valid; $many['items'] = [];
foreach (array_keys($catalog['shades']) as $shade) { $many['items'][] = ['sku' => 'hf-pro', 'shade' => $shade, 'quantity' => 10]; }
rejects(function () use ($many, $catalog) { hf_cart($many, $catalog); }, 'total quantity bound');
$bad = $valid; $bad['items'] = array_fill(0, 31, $valid['items'][0]);
rejects(function () use ($bad, $catalog) { hf_cart($bad, $catalog); }, 'input row bound');
foreach (['{}', '[]', '{', '{"items":{}}', '{"items":[[]]}', str_repeat(' ', 16385), '{"items":[{"quantity":1e100}]}'] as $raw) {
    rejects(function () use ($raw, $catalog) { hf_cart(hf_decode($raw), $catalog); }, 'JSON boundary');
}
$server = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'halofibers.com', 'HTTP_ORIGIN' => 'https://halofibers.com', 'CONTENT_TYPE' => 'application/json'];
hf_request($server, 'POST', 'https://halofibers.com'); ok(true, 'same origin');
foreach ([['HTTP_ORIGIN', 'https://evil.test'], ['HTTP_ORIGIN', 'null'], ['HTTP_HOST', 'evil.test'], ['CONTENT_TYPE', 'text/plain'], ['CONTENT_LENGTH', '9999999999999999999999999'], ['HTTP_SEC_FETCH_SITE', 'cross-site'], ['REQUEST_METHOD', 'GET']] as $pair) {
    $bad = $server; $bad[$pair[0]] = $pair[1];
    rejects(function () use ($bad) { hf_request($bad, 'POST', 'https://halofibers.com'); }, 'request boundary');
}
$bad = $server; unset($bad['HTTP_ORIGIN']);
rejects(function () use ($bad) { hf_request($bad, 'POST', 'https://halofibers.com'); }, 'missing origin');
$provider = new FakeProvider(); $owner = []; $now = 1800000000;
$created = hf_create($cart, 'https://halofibers.com', $owner, $provider, $now);
$id = $created['sessionId']; $binding = $owner['checkouts'][$id]['binding'];
ok($provider->creates === 1 && $provider->parameters['line_items'][0]['price_data']['unit_amount'] === 1234, 'real service creates server params');
ok($provider->parameters['shipping_address_collection']['allowed_countries'] === ['US', 'CA'] && !isset($provider->parameters['shipping_options']) && !isset($provider->parameters['automatic_tax']), 'shipping and tax preserved');
ok($provider->parameters['allow_promotion_codes'] === true && !isset($provider->parameters['customer_email']), 'promotion behavior preserved no PII');
$provider->session = ['id' => $id, 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid',
    'currency' => 'usd', 'metadata' => ['hf_binding' => $binding], 'amount_subtotal' => 1234, 'amount_total' => 1234,
    'total_details' => ['amount_discount' => 0, 'amount_tax' => 0, 'amount_shipping' => 0]];
$provider->lineData = ['has_more' => false, 'data' => [['quantity' => 1, 'currency' => 'usd',
    'price' => ['currency' => 'usd', 'unit_amount' => 1234, 'product' => ['metadata' => ['hf_sku' => 'hf-starter', 'hf_shade' => 'black']]],
    'amount_subtotal' => 1234, 'amount_total' => 1234, 'amount_discount' => 0, 'amount_tax' => 0]]];
$proof = hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now);
ok($proof !== null && $proof['ecommerce']['value_cents'] === 1234, 'paid proof');
foreach ([[], ['checkouts' => ['cs_test_UnknownFixture12345' => $owner['checkouts'][$id]]]] as $stranger) {
    $before = $provider->reads;
    ok(hf_receipt($id, 'https://halofibers.com', $stranger, $provider, $now) === null && $provider->reads === $before, 'unknown/cross user zero provider reads');
}
$before = $provider->reads;
ok(hf_receipt($id, 'https://evil.test', $owner, $provider, $now) === null && $provider->reads === $before, 'origin binding');
ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now + 604801) === null, 'expired ownership');
$paid = $provider->session; $paidLines = $provider->lineData;
foreach ([['status', 'open'], ['payment_status', 'unpaid'], ['payment_status', 'no_payment_required'], ['currency', 'eur'], ['amount_total', 1], ['amount_subtotal', 1], ['mode', 'subscription'], ['id', 'cs_test_OtherFixture12345']] as $pair) {
    $provider->session = $paid; $provider->session[$pair[0]] = $pair[1];
    ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject mismatched session proof');
}
$provider->session = $paid; $provider->session['metadata']['hf_binding'] = 'bad';
ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'wrong binding');
$provider->session = $paid;
foreach (['quantity', 'amount_subtotal', 'amount_total', 'amount_discount', 'amount_tax', 'currency'] as $key) {
    $provider->lineData = $paidLines; $provider->lineData['data'][0][$key] = $key === 'currency' ? 'eur' : 999;
    ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject line ' . $key);
}
foreach (['unit_amount', 'currency'] as $key) {
    $provider->lineData = $paidLines; $provider->lineData['data'][0]['price'][$key] = $key === 'currency' ? 'eur' : 1;
    ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject line price');
}
foreach (['hf_sku', 'hf_shade'] as $key) {
    $provider->lineData = $paidLines; $provider->lineData['data'][0]['price']['product']['metadata'][$key] = 'unknown';
    ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject line identity');
}
$provider->lineData = $paidLines; $provider->lineData['has_more'] = true;
ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject incomplete pagination');
$provider->lineData = $paidLines; $provider->lineData['data'][] = $paidLines['data'][0];
ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now) === null, 'reject extra lines');
$provider->lineData = $paidLines;
$provider->session['total_details']['amount_discount'] = 234; $provider->session['amount_total'] = 1000;
$provider->lineData['data'][0]['amount_discount'] = 234; $provider->lineData['data'][0]['amount_total'] = 1000;
$proof = hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now);
ok($proof !== null && $proof['ecommerce']['value_cents'] === 1000, 'approved promotion compatible verification');
$owner['checkouts'][$id]['cart']['analytics_consent'] = false;
ok(hf_receipt($id, 'https://halofibers.com', $owner, $provider, $now)['consent'] === false, 'checkout origin consent');
$directory = $argv[1] ?? '';
if (!is_dir($directory)) { throw new RuntimeException('private fixture directory required'); }
ok(hf_claim_purchase($directory, $id) === true, 'first durable claim');
ok(hf_claim_purchase($directory, $id) === false, 'replay dedupe');
$canary = $directory . '/outside'; file_put_contents($canary, 'canary');
$symlinkId = 'cs_test_SymlinkFixture12345';
symlink($canary, $directory . '/purchase-' . hash('sha256', $symlinkId) . '.lock');
rejects(function () use ($directory, $symlinkId) { hf_claim_purchase($directory, $symlinkId); }, 'symlink denied');
ok(file_get_contents($canary) === 'canary', 'canary unchanged');
echo json_encode(['suite' => 'backend-core', 'assertions' => $checks, 'result' => 'PASS', 'provider_network_requests' => 0]) . PHP_EOL;
