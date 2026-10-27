<?php
declare(strict_types=1);

// Pure validation/service logic. No provider calls occur on include.
final class HFError extends RuntimeException
{
    public $httpStatus;
    public function __construct(string $code, int $status = 400)
    {
        parent::__construct($code);
        $this->httpStatus = $status;
    }
}

function hf_catalog(array $settings): array
{
    $starter = $settings['starter_unit_amount'] ?? null;
    if (!is_int($starter) || $starter < 1 || $starter > 100000) {
        throw new HFError('catalog_unavailable', 503);
    }
    return [
        'currency' => 'USD',
        'products' => [
            'hf-starter' => ['sku' => 'hf-starter', 'name' => 'Starter Bundle',
                'unit_amount' => $starter, 'contents' => ['1x Halo Fibers Bottle', '1x Pump Applicator']],
            'hf-ultimate' => ['sku' => 'hf-ultimate', 'name' => 'Ultimate Bundle',
                'unit_amount' => 6995, 'contents' => ['3x Halo Fibers Bottles', 'Premium Glass Applicator']],
            'hf-pro' => ['sku' => 'hf-pro', 'name' => 'Pro Bundle',
                'unit_amount' => 4495, 'contents' => ['2x Halo Fibers Bottles', '1x Pump Applicator']],
        ],
        'shades' => ['black' => 'Black', 'dark-brown' => 'Dark Brown', 'medium-brown' => 'Medium Brown',
            'light-brown' => 'Light Brown', 'auburn' => 'Auburn', 'medium-blonde' => 'Medium Blonde',
            'light-blonde' => 'Light Blonde', 'gray' => 'Gray', 'white' => 'White'],
    ];
}

function hf_exact_keys(array $value, array $keys): bool
{
    $actual = array_keys($value);
    sort($actual);
    sort($keys);
    return $actual === $keys;
}

function hf_cart($data, array $catalog): array
{
    if (!is_array($data) || !hf_exact_keys($data, ['items', 'analytics_consent']) ||
        !is_bool($data['analytics_consent']) || !is_array($data['items']) ||
        array_keys($data['items']) !== range(0, count($data['items']) - 1) ||
        count($data['items']) < 1 || count($data['items']) > 30) {
        throw new HFError('invalid_cart');
    }
    $merged = [];
    $quantity = 0;
    foreach ($data['items'] as $item) {
        if (!is_array($item) || !hf_exact_keys($item, ['sku', 'shade', 'quantity']) ||
            !is_string($item['sku']) || !isset($catalog['products'][$item['sku']]) ||
            !is_string($item['shade']) || !isset($catalog['shades'][$item['shade']]) ||
            !is_int($item['quantity']) || $item['quantity'] < 1 || $item['quantity'] > 10) {
            throw new HFError('invalid_cart');
        }
        $key = $item['sku'] . ':' . $item['shade'];
        $newQuantity = ($merged[$key]['quantity'] ?? 0) + $item['quantity'];
        $quantity += $item['quantity'];
        if ($newQuantity > 10 || $quantity > 30) {
            throw new HFError('cart_limit');
        }
        $product = $catalog['products'][$item['sku']];
        $merged[$key] = ['sku' => $item['sku'], 'shade' => $item['shade'], 'quantity' => $newQuantity,
            'name' => $product['name'], 'unit_amount' => $product['unit_amount']];
    }
    ksort($merged);
    $subtotal = 0;
    foreach ($merged as $item) {
        $subtotal += $item['quantity'] * $item['unit_amount'];
    }
    // Catalog and quantity caps make overflow impossible even on 32-bit PHP.
    return ['items' => array_values($merged), 'subtotal' => $subtotal,
        'currency' => 'usd', 'analytics_consent' => $data['analytics_consent']];
}

function hf_request(array $server, string $method, string $origin): void
{
    if (($server['REQUEST_METHOD'] ?? '') !== $method) {
        throw new HFError('method_not_allowed', 405);
    }
    $host = parse_url($origin, PHP_URL_HOST);
    if (($server['HTTP_HOST'] ?? '') !== $host ||
        (isset($server['HTTP_ORIGIN']) && $server['HTTP_ORIGIN'] !== $origin) ||
        (isset($server['HTTP_SEC_FETCH_SITE']) && !in_array($server['HTTP_SEC_FETCH_SITE'], ['same-origin', 'none'], true))) {
        throw new HFError('origin_not_allowed', 403);
    }
    if (strlen($server['QUERY_STRING'] ?? '') > 512) {
        throw new HFError('request_too_large', 413);
    }
    if ($method === 'POST') {
        if (($server['HTTP_ORIGIN'] ?? '') !== $origin) {
            throw new HFError('origin_not_allowed', 403);
        }
        if (!preg_match('/\Aapplication\/json(?:\s*;\s*charset=utf-8)?\z/iD', $server['CONTENT_TYPE'] ?? '')) {
            throw new HFError('unsupported_media_type', 415);
        }
        if (isset($server['CONTENT_LENGTH']) &&
            (!ctype_digit((string)$server['CONTENT_LENGTH']) || (float)$server['CONTENT_LENGTH'] > 16384)) {
            throw new HFError('request_too_large', 413);
        }
    }
}

function hf_decode(string $raw): array
{
    if (strlen($raw) > 16384) {
        throw new HFError('request_too_large', 413);
    }
    // Decode objects first, rejecting JSON objects masquerading as arrays and vice versa.
    $object = json_decode($raw, false, 12);
    if (json_last_error() !== JSON_ERROR_NONE || !($object instanceof stdClass) ||
        !isset($object->items) || !is_array($object->items)) {
        throw new HFError('invalid_json');
    }
    foreach ($object->items as $item) {
        if (!($item instanceof stdClass)) {
            throw new HFError('invalid_cart');
        }
    }
    return json_decode($raw, true, 12);
}

function hf_parameters(array $cart, string $origin, string $binding): array
{
    $lines = [];
    foreach ($cart['items'] as $item) {
        $lines[] = ['price_data' => ['currency' => $cart['currency'], 'unit_amount' => $item['unit_amount'],
            'product_data' => ['name' => $item['name'], 'description' => 'Shade: ' . $item['shade'],
                'images' => [$origin . '/images/product-bottle-transparent.png'],
                'metadata' => ['hf_sku' => $item['sku'], 'hf_shade' => $item['shade']]]],
            'quantity' => $item['quantity']];
    }
    return ['payment_method_types' => ['card'], 'line_items' => $lines, 'mode' => 'payment',
        'success_url' => $origin . '/success.html?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $origin . '/cancel.html',
        'shipping_address_collection' => ['allowed_countries' => ['US', 'CA']],
        'billing_address_collection' => 'required', 'phone_number_collection' => ['enabled' => true],
        'allow_promotion_codes' => true,
        'metadata' => ['order_source' => 'HaloFibers Website', 'cart_items_count' => count($lines),
            'hf_binding' => $binding]];
}

function hf_session_id($id): bool
{
    return is_string($id) && preg_match('/\Acs_(?:test_|live_)[A-Za-z0-9]{8,220}\z/D', $id) === 1;
}

interface HFProvider
{
    public function create(array $parameters): array;
    public function retrieve(string $id): array;
    public function lines(string $id): array;
}

final class HFStripeProvider implements HFProvider
{
    public function create(array $parameters): array
    {
        return \Stripe\Checkout\Session::create($parameters)->toArray();
    }
    public function retrieve(string $id): array
    {
        return \Stripe\Checkout\Session::retrieve($id)->toArray();
    }
    public function lines(string $id): array
    {
        return \Stripe\Checkout\Session::allLineItems($id,
            ['limit' => 100, 'expand' => ['data.price.product']])->toArray();
    }
}

function hf_create(array $cart, string $origin, array &$owner, HFProvider $provider, int $now): array
{
    $owner['checkouts'] = $owner['checkouts'] ?? [];
    foreach ($owner['checkouts'] as $id => $record) {
        if (($record['created'] ?? 0) < $now - 604800) {
            unset($owner['checkouts'][$id]);
        }
    }
    if (count($owner['checkouts']) >= 10) {
        throw new HFError('checkout_limit', 429);
    }
    $binding = bin2hex(random_bytes(32));
    $session = $provider->create(hf_parameters($cart, $origin, $binding));
    if (!hf_session_id($session['id'] ?? null) || !is_string($session['url'] ?? null) ||
        preg_match('@\Ahttps://checkout\.stripe\.com/c/pay/[A-Za-z0-9_\-\#%=/+.!~*\x27()]+\z@D', $session['url']) !== 1) {
        throw new HFError('payment_unavailable', 502);
    }
    if (isset($owner['checkouts'][$session['id']])) {
        throw new HFError('payment_unavailable', 502);
    }
    $owner['checkouts'][$session['id']] = ['cart' => $cart, 'origin' => $origin,
        'binding' => $binding, 'created' => $now];
    return ['success' => true, 'sessionId' => $session['id'], 'url' => $session['url']];
}

function hf_money($value): bool
{
    return is_int($value) && $value >= 0 && $value <= 10000000;
}

// Read-only provider proof; no analytics or customer/provider mutation here.
function hf_receipt(string $id, string $origin, array $owner, HFProvider $provider, int $now): ?array
{
    $record = $owner['checkouts'][$id] ?? null;
    if (!hf_session_id($id) || !is_array($record) || $record['origin'] !== $origin ||
        $record['created'] < $now - 604800 || $record['created'] > $now) {
        return null;
    }
    $session = $provider->retrieve($id);
    if (($session['id'] ?? null) !== $id || ($session['mode'] ?? null) !== 'payment' ||
        ($session['status'] ?? null) !== 'complete' || ($session['payment_status'] ?? null) !== 'paid' ||
        ($session['currency'] ?? null) !== $record['cart']['currency'] ||
        !is_string($session['metadata']['hf_binding'] ?? null) ||
        !hash_equals($record['binding'], $session['metadata']['hf_binding']) ||
        ($session['amount_subtotal'] ?? null) !== $record['cart']['subtotal']) {
        return null;
    }
    $totals = $session['total_details'] ?? [];
    foreach (['amount_discount', 'amount_tax', 'amount_shipping'] as $key) {
        if (!hf_money($totals[$key] ?? null)) {
            return null;
        }
    }
    $subtotal = $record['cart']['subtotal'];
    if ($totals['amount_discount'] > $subtotal || !hf_money($session['amount_total'] ?? null) ||
        $session['amount_total'] !== $subtotal - $totals['amount_discount'] + $totals['amount_tax'] + $totals['amount_shipping']) {
        return null;
    }
    $lines = $provider->lines($id);
    if (($lines['has_more'] ?? null) !== false || !is_array($lines['data'] ?? null) ||
        count($lines['data']) !== count($record['cart']['items'])) {
        return null;
    }
    $expected = [];
    foreach ($record['cart']['items'] as $item) {
        $expected[$item['sku'] . ':' . $item['shade']] = $item;
    }
    $discount = 0;
    $tax = 0;
    $ecommerce = [];
    foreach ($lines['data'] as $line) {
        $metadata = $line['price']['product']['metadata'] ?? [];
        if (!is_string($metadata['hf_sku'] ?? null) || !is_string($metadata['hf_shade'] ?? null)) {
            return null;
        }
        $key = $metadata['hf_sku'] . ':' . $metadata['hf_shade'];
        $item = $expected[$key] ?? null;
        if (!$item || ($line['quantity'] ?? null) !== $item['quantity'] ||
            ($line['price']['currency'] ?? null) !== $record['cart']['currency'] ||
            ($line['currency'] ?? null) !== $record['cart']['currency'] ||
            ($line['price']['unit_amount'] ?? null) !== $item['unit_amount'] ||
            ($line['amount_subtotal'] ?? null) !== $item['unit_amount'] * $item['quantity'] ||
            !hf_money($line['amount_discount'] ?? null) || $line['amount_discount'] > $line['amount_subtotal'] ||
            !hf_money($line['amount_tax'] ?? null) ||
            ($line['amount_total'] ?? null) !== $line['amount_subtotal'] - $line['amount_discount'] + $line['amount_tax']) {
            return null;
        }
        unset($expected[$key]);
        $discount += $line['amount_discount'];
        $tax += $line['amount_tax'];
        $ecommerce[] = ['item_id' => $item['sku'], 'item_name' => $item['name'],
            'item_variant' => $item['shade'], 'price_cents' => $item['unit_amount'],
            'quantity' => $item['quantity'], 'discount_cents' => $line['amount_discount']];
    }
    if ($expected || $discount !== $totals['amount_discount'] || $tax !== $totals['amount_tax']) {
        return null;
    }
    return ['consent' => $record['cart']['analytics_consent'], 'ecommerce' => [
        'transaction_id' => $id, 'currency' => strtoupper($record['cart']['currency']),
        'value_cents' => $subtotal - $discount, 'items' => $ecommerce]];
}

// A durable at-most-once claim, NOT exactly-once network delivery. A lost response
// may lose an analytics event; a replay can never reclaim it. No PII is persisted.
function hf_claim_purchase(string $directory, string $id): bool
{
    if (!hf_session_id($id)) {
        throw new HFError('verification_unavailable', 503);
    }
    $path = $directory . '/purchase-' . hash('sha256', $id) . '.lock';
    if (is_link($path)) {
        throw new HFError('verification_unavailable', 503);
    }
    $handle = fopen($path, 'c+b');
    if ($handle === false) {
        throw new HFError('verification_unavailable', 503);
    }
    try {
        $stat = fstat($handle);
        if (!$stat || $stat['nlink'] !== 1 || ($stat['mode'] & 0170000) !== 0100000 ||
            ($stat['mode'] & 0077) !== 0 || !flock($handle, LOCK_EX)) {
            throw new HFError('verification_unavailable', 503);
        }
        if (fread($handle, 1) !== '') {
            return false;
        }
        if (fwrite($handle, '1') !== 1 || !fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
            throw new HFError('verification_unavailable', 503);
        }
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
