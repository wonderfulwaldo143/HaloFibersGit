<?php
declare(strict_types=1);
// Exercise the REAL bundled SDK with a capability-free HttpClient replacement.
require dirname(__DIR__) . '/stripe/checkout-core.php';
require dirname(__DIR__) . '/stripe/stripe-php/init.php';
final class HFFixtureHTTP implements \Stripe\HttpClient\ClientInterface
{
    public $calls = [];
    public $parameters;
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->calls[] = [$method, $absUrl, $params];
        $id = 'cs_test_RealSDKFixture12345';
        if ($method === 'post' && $absUrl === 'https://api.stripe.com/v1/checkout/sessions') {
            $this->parameters = $params;
            return [json_encode(['id' => $id, 'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/' . $id . '#fixture']), 200, []];
        }
        if ($method !== 'get') { throw new RuntimeException('unexpected HTTP method'); }
        if ($absUrl === 'https://api.stripe.com/v1/checkout/sessions/' . $id) {
            return [json_encode(['id' => $id, 'object' => 'checkout.session', 'mode' => 'payment',
                'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'usd',
                'amount_subtotal' => 4495, 'amount_total' => 4495, 'metadata' => $this->parameters['metadata'],
                'total_details' => ['amount_discount' => 0, 'amount_tax' => 0, 'amount_shipping' => 0]]), 200, []];
        }
        if ($absUrl === 'https://api.stripe.com/v1/checkout/sessions/' . $id . '/line_items') {
            return [json_encode(['object' => 'list', 'has_more' => false, 'data' => [
                ['id' => 'li_synthetic', 'object' => 'item', 'currency' => 'usd', 'quantity' => 1,
                    'amount_subtotal' => 4495, 'amount_total' => 4495, 'amount_discount' => 0, 'amount_tax' => 0,
                    'price' => ['id' => 'price_synthetic', 'object' => 'price', 'currency' => 'usd', 'unit_amount' => 4495,
                        'product' => ['id' => 'prod_synthetic', 'object' => 'product',
                            'metadata' => ['hf_sku' => 'hf-pro', 'hf_shade' => 'black']]]]]]), 200, []];
        }
        throw new RuntimeException('unexpected URL');
    }
}
foreach (['curl_exec', 'fsockopen', 'stream_socket_client'] as $capability) {
    if (function_exists($capability)) { throw new RuntimeException('network capability not disabled'); }
}
\Stripe\Stripe::setApiKey('synthetic-not-a-provider-key');
\Stripe\Stripe::setMaxNetworkRetries(0);
\Stripe\HttpClient\CurlClient::instance()->setConnectTimeout(5);
\Stripe\HttpClient\CurlClient::instance()->setTimeout(15);
$http = new HFFixtureHTTP();
\Stripe\ApiRequestor::setHttpClient($http);
$provider = new HFStripeProvider();
$catalog = hf_catalog(['starter_unit_amount' => 1234]);
$cart = hf_cart(['items' => [['sku' => 'hf-pro', 'shade' => 'black', 'quantity' => 1]], 'analytics_consent' => true], $catalog);
$owner = [];
$created = hf_create($cart, 'https://halofibers.com', $owner, $provider, 1800000000);
$proof = hf_receipt($created['sessionId'], 'https://halofibers.com', $owner, $provider, 1800000000);
if (!$proof || $proof['ecommerce']['value_cents'] !== 4495 || count($http->calls) !== 3 ||
    $http->calls[1][0] !== 'get' || $http->calls[2][0] !== 'get' ||
    $http->calls[2][2] !== ['limit' => 100, 'expand' => ['data.price.product']]) {
    throw new RuntimeException('real SDK adapter contract failed');
}
echo json_encode(['suite' => 'backend-real-sdk', 'result' => 'PASS', 'fixture_transport_calls' => count($http->calls),
    'verification_methods' => ['GET', 'GET'], 'provider_network_requests' => 0]) . PHP_EOL;
