# Frontend / PHP integration contract

Read alongside `stripe/checkout-core.php` and backend-report.md. Test fixtures are local only; they do not authorize a Starter price or create provider sessions.

## Catalog
`GET /stripe/catalog.php`, same-origin, no-store. Success:
```json
{"currency":"USD","products":{"hf-starter":{"sku":"hf-starter","name":"Starter Bundle","unit_amount":1234,"contents":["fixture contents only"]},"hf-ultimate":{"sku":"hf-ultimate","name":"Ultimate Bundle","unit_amount":6995,"contents":["3x Halo Fibers Bottles","Premium Glass Applicator"]},"hf-pro":{"sku":"hf-pro","name":"Pro Bundle","unit_amount":4495,"contents":["2x Halo Fibers Bottles","1x Pump Applicator"]}},"shades":{"black":"Black","dark-brown":"Dark Brown","medium-brown":"Medium Brown","light-brown":"Light Brown","auburn":"Auburn","medium-blonde":"Medium Blonde","light-blonde":"Light Blonde","gray":"Gray","white":"White"}}
```
**1234 is a deliberately artificial TEST FIXTURE, not an approved or proposed Starter price.** PHP must return 503 until the owner supplies the actual Starter integer-cent price. There is no frontend price fallback. Missing/malformed/failed catalog disables every Add and checkout; retry is explicit. Ultimate 6995 / Pro 4495 are inherited backend values, not changed by this frontend.

## Cart / checkout
Canonical browser storage key: `haloFibersCart`, JSON array of `{sku,shade,quantity}`. No name, image, composite ID or price is stored. Identity is `${sku}:${shade}`. Legacy Starter/Ultimate/Pro Bundle rows and the documented old `Starter-dark-medium-brown` ID migrate using the explicit shade; invalid values and negative/fractional quantities are discarded. Legacy prices and all extra fields are removed. Backend caps: 10 per SKU/shade, 30 items total. Migration caps merged old rows to these limits; ordinary shade changes reject over-cap merges without losing either row.

`POST /stripe/create-checkout.php`, JSON, same-origin credentials:
```json
{"items":[{"sku":"hf-starter","shade":"dark-brown","quantity":1}],"analytics_consent":false}
```
No client name, amount, currency, image, promotion code or customer data. Backend computes catalog amounts and validates limits. Expected success `{success:true,url:"https://checkout.stripe.com/...",sessionId:"..."}`. Redirect accepts only HTTPS `checkout.stripe.com`, no userinfo or nondefault port. Errors/timeouts retain cart and show generic status, without logging payloads/provider errors.

## Receipt
`GET /stripe/verify-session.php?session_id=...`, same-origin credentials, no-store. Server must bind originating secure session and verified paid payment record. A URL alone is never proof.
- `{verified:false}`: no success wording / no purchase.
- `{verified:true}` without ecommerce: confirmation is valid, but **no purchase event**. Required for a durable server claim replay or checkout without original analytics consent.
- `{verified:true,ecommerce:{transaction_id,currency:"USD",value_cents,items:[{item_id,item_name,item_variant,price_cents,quantity}]}}`: confirmation; purchase only if the current browser also explicitly allows analytics. Browser strips arbitrary fields, uses approved item names, validates all items/cents, and transaction-deduplicates in localStorage. No event if dedupe storage is unavailable.

The backend's durable at-most-once claim can lose an analytics event if the response is lost; the frontend does not retry a claim or pretend exactly-once delivery. Success URL query is stripped before optional GTM loads. Cart is cleared only on backend `verified:true`, not direct success-page visits.

## Analytics
GTM identifier/config is unchanged (`GTM-MSBNKS77`). Consent default: analytics/ad storage/ad user data/ad personalization denied. Explicit Allow updates analytics consent and loads GTM; Keep Off/revoke denies future custom events. No custom event replay on grant. Events: `view_item`, `shade_select`, `quiz_complete`, `add_to_cart`, `begin_checkout`, `checkout_error`, backend-verified/deduplicated `purchase`. Ecommerce contains allowlisted product IDs/names, shade, quantity, integer cents, USD and GA decimal companions—no emails, addresses, session URL, free text or payment data. GTM property/tag correctness and reporting are owner-side verification, not proven by dataLayer tests.

## Shipping / returns
Inherited terms only: free U.S. shipping on orders **over $39** (strict `>3900`); typical processing 1–2 business days, delivery 5–7 business days; returns within 30 days of receipt, original condition, >=75% remaining. Subtotal is not a shipping/tax-inclusive total. Nonqualifying shipping cost, return steps, return-shipping payer and shade-exchange process remain owner/backend policy blockers. No rates or procedures are invented by the frontend.
