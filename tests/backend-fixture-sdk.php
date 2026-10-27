<?php
// ONLY copied into an isolated test webroot; never loaded by application code.
namespace Stripe;
final class Stripe {
    public static function setApiKey($key) { if ($key !== 'synthetic-not-a-provider-key') { throw new \RuntimeException('fixture key required'); } }
    public static function setMaxNetworkRetries($value) {}
}
namespace Stripe\HttpClient;
final class CurlClient {
    public static function instance() { return new self(); }
    public function setConnectTimeout($value) {}
    public function setTimeout($value) {}
}
namespace Stripe\Checkout;
final class FixtureValue {
    private $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}
final class Session {
    private static function path(): string { return dirname(__DIR__, 3) . '/fixtures.json'; }
    private static function state(): array {
        foreach (['curl_exec', 'fsockopen', 'stream_socket_client', 'exec', 'mail'] as $function) {
            if (function_exists($function)) { throw new \RuntimeException('outbound capability not disabled'); }
        }
        return json_decode(file_get_contents(self::path()), true);
    }
    private static function save(array $state): void { file_put_contents(self::path(), json_encode($state)); }
    public static function create(array $parameters): FixtureValue {
        $state = self::state();
        $state['creates']++;
        if (!empty($state['throw'])) { throw new \RuntimeException('__PRIVATE_SENTINEL__ /private/key synthetic'); }
        $id = 'cs_test_Fixture' . str_pad((string)$state['creates'], 12, '0', STR_PAD_LEFT);
        $subtotal = 0; $lines = [];
        foreach ($parameters['line_items'] as $line) {
            $amount = $line['price_data']['unit_amount'] * $line['quantity'];
            $subtotal += $amount;
            $lines[] = ['quantity' => $line['quantity'], 'currency' => $line['price_data']['currency'],
                'price' => ['currency' => $line['price_data']['currency'], 'unit_amount' => $line['price_data']['unit_amount'],
                    'product' => $line['price_data']['product_data']],
                'amount_subtotal' => $amount, 'amount_total' => $amount, 'amount_discount' => 0, 'amount_tax' => 0];
        }
        $session = ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/' . $id . '#synthetic',
            'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'currency' => 'usd',
            'metadata' => $parameters['metadata'], 'amount_subtotal' => $subtotal, 'amount_total' => $subtotal,
            'total_details' => ['amount_discount' => 0, 'amount_tax' => 0, 'amount_shipping' => 0]];
        $state['sessions'][$id] = ['session' => $session, 'lines' => ['data' => $lines, 'has_more' => false], 'parameters' => $parameters];
        self::save($state);
        return new FixtureValue($session);
    }
    public static function retrieve(string $id): FixtureValue {
        $state = self::state(); $state['retrieves']++;
        self::save($state);
        if (!empty($state['throw'])) { throw new \RuntimeException('__PRIVATE_SENTINEL__'); }
        return new FixtureValue($state['sessions'][$id]['session']);
    }
    public static function allLineItems(string $id, array $parameters): FixtureValue {
        $state = self::state(); $state['line_reads']++;
        self::save($state);
        return new FixtureValue($state['sessions'][$id]['lines']);
    }
}
