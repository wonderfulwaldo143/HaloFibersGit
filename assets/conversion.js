/* Shared storefront model. Prices and contents come only from the server catalog. */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root.document) { root.HaloConversion = api; api.boot(root); }
})(typeof window === 'undefined' ? globalThis : window, function () {
    'use strict';
    const SKUS = ['hf-starter', 'hf-ultimate', 'hf-pro'];
    const SHADES = ['black', 'dark-brown', 'medium-brown', 'light-brown', 'auburn', 'medium-blonde', 'light-blonde', 'gray', 'white'];
    const LEGACY = {Starter: 'hf-starter', Ultimate: 'hf-ultimate', Pro: 'hf-pro'};
    const CART_KEY = 'haloFibersCart';
    const CONSENT_KEY = 'haloAnalyticsConsent';
    const MAX_QUANTITY = 10;
    const MAX_TOTAL = 30;
    const own = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
    const validQuantity = n => Number.isSafeInteger(n) && n > 0 && n <= MAX_QUANTITY;
    const key = item => `${item.sku}:${item.shade}`;
    const money = cents => new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(cents / 100);
    function shippingSummary(subtotal) {
        if (!Number.isSafeInteger(subtotal) || subtotal < 0) return 'Shipping and tax are not yet available.';
        return subtotal > 3900 ? 'Eligible for free U.S. shipping (orders over $39). Tax is not estimated here; review checkout before paying.' : 'U.S. shipping is free on orders over $39. Shipping charges and tax are not estimated here; contact support before paying if checkout does not state them.';
    }
    function validateCatalog(raw) {
        if (!raw || raw.currency !== 'USD' || !raw.products || !raw.shades) throw new Error('Invalid catalog');
        const products = {}, shades = {};
        SHADES.forEach(shade => {
            if (!own(raw.shades, shade) || typeof raw.shades[shade] !== 'string' || !raw.shades[shade].trim() || raw.shades[shade].length > 60) throw new Error('Invalid shade');
            shades[shade] = raw.shades[shade];
        });
        SKUS.forEach(sku => {
            const p = raw.products[sku];
            if (!own(raw.products, sku) || !p || p.sku !== sku || typeof p.name !== 'string' || !p.name.trim() || p.name.length > 100 || !Number.isSafeInteger(p.unit_amount) || p.unit_amount < 1 || p.unit_amount > 1000000 || !Array.isArray(p.contents) || !p.contents.length || p.contents.some(s => typeof s !== 'string' || !s.trim() || s.length > 200)) throw new Error('Invalid product');
            products[sku] = {sku, name: p.name, unit_amount: p.unit_amount, contents: [...p.contents]};
        });
        return {currency: 'USD', products, shades};
    }
    function normalizeCart(raw) {
        if (!Array.isArray(raw)) return [];
        const merged = new Map();
        raw.slice(0, 1000).forEach(row => {
            if (!row || typeof row !== 'object' || !validQuantity(row.quantity) || !SHADES.includes(row.shade)) return;
            let sku = row.sku;
            if (sku === undefined) {
                // Recover legacy IDs, including the documented multi-hyphen shade-change bug.
                const match = typeof row.id === 'string' && /^(Starter|Ultimate|Pro)-((?:black|dark|medium|light|brown|auburn|blonde|gray|white)(?:-(?:black|dark|medium|light|brown|auburn|blonde|gray|white))*)$/.exec(row.id);
                if (!match || row.name !== `${match[1]} Bundle`) return;
                sku = LEGACY[match[1]];
            }
            if (!SKUS.includes(sku)) return;
            const item = {sku, shade: row.shade, quantity: row.quantity};
            const id = key(item);
            if (merged.has(id)) merged.get(id).quantity = Math.min(MAX_QUANTITY, merged.get(id).quantity + item.quantity);
            else merged.set(id, item);
        });
        let remaining = MAX_TOTAL;
        return [...merged.values()].filter(row => {
            row.quantity = Math.min(row.quantity, remaining); remaining -= row.quantity; return row.quantity > 0;
        });
    }
    function createModel(storage) {
        let cart = [];
        try { cart = normalizeCart(JSON.parse(storage.getItem(CART_KEY))); } catch (_) { /* Broken/blocked storage never breaks buying. */ }
        const save = () => { try { storage.setItem(CART_KEY, JSON.stringify(cart)); } catch (_) {} };
        save();
        return {
            get: () => cart.map(row => ({...row})),
            add(sku, shade) {
                if (!SKUS.includes(sku) || !SHADES.includes(shade)) return false;
                const row = cart.find(x => x.sku === sku && x.shade === shade);
                if ((row && row.quantity === MAX_QUANTITY) || cart.reduce((n, x) => n + x.quantity, 0) >= MAX_TOTAL) return false;
                if (row) row.quantity++; else cart.push({sku, shade, quantity: 1});
                save(); return true;
            },
            changeShade(id, shade) {
                if (!SHADES.includes(shade)) return false;
                const row = cart.find(x => key(x) === id);
                if (!row) return false;
                const target = cart.find(x => x.sku === row.sku && x.shade === shade && x !== row);
                if (target && target.quantity + row.quantity > MAX_QUANTITY) return false;
                row.shade = shade; cart = normalizeCart(cart); save(); return true;
            },
            quantity(id, delta) {
                if (delta !== -1 && delta !== 1) return false;
                const row = cart.find(x => key(x) === id);
                if (!row || row.quantity + delta > MAX_QUANTITY || (delta > 0 && cart.reduce((n, x) => n + x.quantity, 0) >= MAX_TOTAL)) return false;
                row.quantity += delta; cart = cart.filter(x => x.quantity > 0); save(); return true;
            },
            remove(id) { cart = cart.filter(x => key(x) !== id); save(); },
            clear() { cart = []; save(); }
        };
    }
    function checkoutPayload(cart, consent) {
        return {items: normalizeCart(cart), analytics_consent: consent === true};
    }
    function ecommerceItems(cart, catalog) {
        return normalizeCart(cart).map(row => ({item_id: row.sku, item_name: catalog.products[row.sku].name, item_variant: row.shade, price: catalog.products[row.sku].unit_amount / 100, price_cents: catalog.products[row.sku].unit_amount, quantity: row.quantity}));
    }
    function verifiedPurchase(data) {
        const e = data && data.verified === true && data.ecommerce;
        if (!e || typeof e.transaction_id !== 'string' || !/^[A-Za-z0-9_-]{1,200}$/.test(e.transaction_id) || e.currency !== 'USD' || !Number.isSafeInteger(e.value_cents) || e.value_cents < 0 || !Array.isArray(e.items) || !e.items.length) return null;
        if (e.items.some(i => !SKUS.includes(i.item_id) || !SHADES.includes(i.item_variant) || !validQuantity(i.quantity) || !Number.isSafeInteger(i.price_cents) || i.price_cents < 1)) return null;
        // Never forward arbitrary backend fields or names that might contain customer data.
        const names = {'hf-starter': 'Starter Bundle', 'hf-ultimate': 'Ultimate Bundle', 'hf-pro': 'Pro Bundle'};
        return {transaction_id: e.transaction_id, currency: 'USD', value: e.value_cents / 100, value_cents: e.value_cents, items: e.items.map(i => ({item_id: i.item_id, item_name: names[i.item_id], item_variant: i.item_variant, price: i.price_cents / 100, price_cents: i.price_cents, quantity: i.quantity}))};
    }
    function boot(w) {
        const d = w.document;
        const storage = {getItem: k => w.localStorage.getItem(k), setItem: (k, v) => w.localStorage.setItem(k, v)};
        let consent = false;
        try { consent = storage.getItem(CONSENT_KEY) === 'granted'; } catch (_) {}
        let catalog = null, shade = 'black', selectedSku = 'hf-starter', busy = false, purchase = null, sentPurchase = false;
        const model = createModel(storage);
        const el = id => d.getElementById(id);
        const status = text => { if (el('catalogStatus')) el('catalogStatus').textContent = text; };
        const emit = (event, ecommerce, fields = {}) => {
            if (!consent) return;
            w.dataLayer = w.dataLayer || [];
            if (ecommerce) w.dataLayer.push({ecommerce: null});
            w.dataLayer.push({event, ...fields, ...(ecommerce ? {ecommerce} : {})});
        };
        function trackCart(event, cart) {
            if (!catalog) return;
            const items = ecommerceItems(cart, catalog);
            emit(event, {currency: 'USD', value: items.reduce((n, i) => n + i.price_cents * i.quantity, 0) / 100, value_cents: items.reduce((n, i) => n + i.price_cents * i.quantity, 0), items});
        }
        function purchaseEvent() {
            if (!purchase || !consent || sentPurchase) return;
            const storageKey = `haloPurchase:${purchase.transaction_id}`;
            try {
                if (storage.getItem(storageKey)) { sentPurchase = true; return; }
                // If storage is blocked, fail closed rather than duplicating purchase on refresh.
                storage.setItem(storageKey, 'sent');
            } catch (_) { return; }
            emit('purchase', purchase); sentPurchase = true;
        }
        function setConsent(allowed) {
            consent = allowed === true;
            try { storage.setItem(CONSENT_KEY, consent ? 'granted' : 'denied'); } catch (_) {}
            w.gtag('consent', 'update', {analytics_storage: consent ? 'granted' : 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'});
            if (typeof w.haloLoadAnalytics === 'function' && consent) w.haloLoadAnalytics();
            el('analyticsChoice').textContent = consent ? 'Optional analytics allowed.' : 'Optional analytics off.';
            el('allowAnalytics').setAttribute('aria-pressed', String(consent));
            el('denyAnalytics').setAttribute('aria-pressed', String(!consent));
            if (consent && catalog) trackCart('view_item', [{sku: selectedSku, shade, quantity: 1}]);
            purchaseEvent();
        }
        function node(tag, text, className) {
            const n = d.createElement(tag);
            if (text !== undefined) n.textContent = text;
            if (className) n.className = className;
            return n;
        }
        function syncOffer() {
            d.querySelectorAll('[data-catalog-price]').forEach(n => { n.textContent = catalog ? money(catalog.products[n.dataset.catalogPrice].unit_amount) : 'Unavailable'; n.dataset.unavailable = String(!catalog); });
            d.querySelectorAll('[data-add-sku]').forEach(n => { n.disabled = !catalog; });
            d.querySelectorAll('[data-catalog-contents]').forEach(n => {
                n.replaceChildren();
                if (catalog) catalog.products[n.dataset.catalogContents].contents.forEach(text => n.appendChild(node('li', text, 'mb-3')));
                else n.appendChild(node('li', 'Bundle details unavailable.'));
            });
            if (!el('purchasePack')) return;
            const p = catalog && catalog.products[selectedSku];
            [...el('purchasePack').options].forEach(option => { option.textContent = catalog ? `${catalog.products[option.value].name} — ${money(catalog.products[option.value].unit_amount)}` : `${option.dataset.name} — unavailable`; });
            el('purchaseContents').textContent = p ? p.contents.join(' · ') : 'Prices and bundle contents are temporarily unavailable. Please retry.';
            el('buyNowProductDetails').textContent = p ? `Add ${p.name} — ${money(p.unit_amount)}` : 'Purchasing unavailable';
            el('buyNowProductDetails').disabled = !catalog;
            el('purchaseShadeName').textContent = catalog ? catalog.shades[shade] : shade.replaceAll('-', ' ');
        }
        function renderCart(focusId, action) {
            if (!el('cartItems')) return;
            const active = d.activeElement;
            if (!focusId && active && active.dataset.cartId) { focusId = active.dataset.cartId; action = active.dataset.action; }
            const cart = model.get();
            const count = cart.reduce((n, row) => n + row.quantity, 0);
            ['cartBadge', 'cartBadgeMobile'].forEach(id => { if (el(id)) { el(id).textContent = count > 99 ? '99+' : count; el(id).style.display = count ? 'flex' : 'none'; } });
            el('cartItems').replaceChildren();
            el('cartFooter').style.display = cart.length ? 'block' : 'none';
            if (!cart.length) {
                const empty = node('div', undefined, 'text-center py-12');
                empty.appendChild(node('p', 'Your cart is empty', 'text-slate-600 mb-4'));
                const shop = node('button', 'Choose a bundle', 'conversion-button');
                shop.onclick = () => { w.toggleCart(); w.location.hash = 'shade-picker'; };
                empty.appendChild(shop); el('cartItems').appendChild(empty);
            }
            cart.forEach(row => {
                const p = catalog && catalog.products[row.sku];
                const id = key(row);
                const container = node('div', undefined, 'cart-item');
                const heading = node('h3', p ? p.name : row.sku.replace('hf-', '') + ' bundle', 'font-bold text-slate-900 mb-2');
                container.appendChild(heading);
                const select = node('select', undefined, 'conversion-select');
                select.setAttribute('aria-label', `Shade for ${heading.textContent}`);
                select.dataset.cartId = id; select.dataset.action = 'shade';
                SHADES.forEach(s => { const option = node('option', catalog ? catalog.shades[s] : s.replaceAll('-', ' ')); option.value = s; option.selected = s === row.shade; select.appendChild(option); });
                select.onchange = () => { const next = select.value;
                    if (!model.changeShade(id, next)) { el('checkoutStatus').textContent = 'That shade already has too many items. Maximum 10 per bundle and shade.'; renderCart(id, 'shade'); return; }
                    renderCart(`${row.sku}:${next}`, 'shade');
                };
                container.appendChild(select);
                container.appendChild(node('p', p ? `${money(p.unit_amount)} each · ${money(p.unit_amount * row.quantity)}` : 'Price unavailable — checkout disabled', 'text-sm text-slate-600 mt-2 mb-2'));
                const controls = node('div', undefined, 'flex items-center gap-3 flex-wrap');
                [['minus', '−', -1], ['plus', '+', 1], ['remove', 'Remove', 0]].forEach(([act, label, delta]) => {
                    const b = node('button', label, act === 'remove' ? 'conversion-remove' : 'quantity-btn');
                    b.dataset.cartId = id; b.dataset.action = act;
                    b.setAttribute('aria-label', `${act === 'minus' ? 'Decrease quantity of' : act === 'plus' ? 'Increase quantity of' : 'Remove'} ${heading.textContent}`);
                    b.disabled = act === 'plus' && (row.quantity === MAX_QUANTITY || count >= MAX_TOTAL);
                    b.onclick = () => { if (delta) model.quantity(id, delta); else model.remove(id); renderCart(id, act); };
                    controls.appendChild(b);
                    if (act === 'minus') controls.appendChild(node('span', String(row.quantity), 'font-semibold text-slate-900'));
                });
                container.appendChild(controls); el('cartItems').appendChild(container);
            });
            const subtotal = catalog ? cart.reduce((n, row) => n + catalog.products[row.sku].unit_amount * row.quantity, 0) : null;
            el('cartTotal').textContent = subtotal === null ? 'Unavailable' : money(subtotal);
            el('cartShipping').textContent = shippingSummary(subtotal);
            el('checkoutBtn').disabled = !catalog || !cart.length || busy;
            if (focusId && el('cartDrawer').classList.contains('active')) {
                const target = [...el('cartItems').querySelectorAll('button, select')].find(n => n.dataset.cartId === focusId && n.dataset.action === action);
                (target || d.querySelector('#cartDrawer [aria-label="Close cart"]')).focus({preventScroll: true});
            }
        }
        function add(button, sku) {
            if (!catalog || !SKUS.includes(sku) || (button && button.disabled)) return;
            if (!model.add(sku, shade)) { status('Maximum 10 of a bundle/shade and 30 items per cart.'); return; }
            renderCart(); trackCart('add_to_cart', [{sku, shade, quantity: 1}]);
            if (!el('cartDrawer').classList.contains('active')) w.toggleCart();
        }
        async function loadCatalog() {
            catalog = null; status('Loading current prices and bundle contents…'); syncOffer(); renderCart();
            const controller = new AbortController(); const timer = w.setTimeout(() => controller.abort(), 8000);
            try {
                const response = await w.fetch('/stripe/catalog.php', {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
                if (!response.ok) throw new Error('Catalog unavailable');
                catalog = validateCatalog(await response.json());
                status(`Current bundle prices in USD. Saved carts are repriced to the current catalog; Starter is ${money(catalog.products['hf-starter'].unit_amount)}. Your valid bundle, shade and quantity selections are retained.`); syncOffer(); renderCart();
                trackCart('view_item', [{sku: selectedSku, shade, quantity: 1}]);
            } catch (_) { catalog = null; status('Purchasing temporarily unavailable. Retry prices or contact support before ordering.'); syncOffer(); renderCart(); }
            finally { w.clearTimeout(timer); }
        }
        async function checkout() {
            if (!catalog || busy || !model.get().length) return;
            busy = true; el('checkoutStatus').textContent = 'Opening secure checkout…'; renderCart();
            trackCart('begin_checkout', model.get());
            const controller = new AbortController(); const timer = w.setTimeout(() => controller.abort(), 15000);
            try {
                const response = await w.fetch('/stripe/create-checkout.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, signal: controller.signal, body: JSON.stringify(checkoutPayload(model.get(), consent))});
                if (!response.ok) throw new Error('Checkout unavailable');
                const data = await response.json();
                const url = new URL(data.url);
                if (!data.success || url.protocol !== 'https:' || url.hostname !== 'checkout.stripe.com' || url.username || url.password || url.port) throw new Error('Invalid checkout');
                w.location.assign(url.href);
            } catch (_) { emit('checkout_error', null, {error_code: 'checkout_unavailable'}); el('checkoutStatus').textContent = 'Unable to open checkout. Your cart is saved. Please retry or contact support.'; }
            finally { w.clearTimeout(timer); busy = false; renderCart(); }
        }
        async function verifyOrder() {
            const message = el('orderStatus'); if (!message) return;
            const fail = () => { el('orderHeading').textContent = 'Order not yet verified'; message.textContent = 'We cannot confirm payment here. Check your order confirmation or contact support before ordering again.'; };
            const id = new URLSearchParams(w.location.search).get('session_id');
            // Strip query identifiers before loading any optional third-party analytics.
            w.history.replaceState(null, '', w.location.pathname);
            if (!id || !/^cs_(?:test_|live_)?[A-Za-z0-9]{8,200}$/.test(id)) { fail(); return; }
            const controller = new AbortController(); const timer = w.setTimeout(() => controller.abort(), 10000);
            try {
                const response = await w.fetch(`/stripe/verify-session.php?session_id=${encodeURIComponent(id)}`, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
                if (!response.ok) throw new Error('Unverified');
                const data = await response.json();
                if (!data || data.verified !== true) { fail(); return; }
                purchase = verifiedPurchase(data);
                el('orderHeading').textContent = 'Order confirmed'; message.textContent = 'Payment has been verified. Thank you for your order.';
                el('verifiedOrderInfo').hidden = false; model.clear();
            } catch (_) { fail(); }
            finally { w.clearTimeout(timer); }
        }
        function ready() {
            el('allowAnalytics').onclick = () => setConsent(true);
            el('denyAnalytics').onclick = () => setConsent(false);
            el('analyticsChoice').textContent = consent ? 'Optional analytics allowed.' : 'Optional analytics off.';
            el('allowAnalytics').setAttribute('aria-pressed', String(consent)); el('denyAnalytics').setAttribute('aria-pressed', String(!consent));
            if (el('orderStatus')) { verifyOrder().finally(() => setConsent(consent)); return; }
            w.addToCart = add;
            w.buyNowWithShade = () => add(el('buyNowProductDetails'), selectedSku);
            w.checkout = checkout;
            w.getCart = () => model.get();
            w.selectConversionShade = next => {
                if (!SHADES.includes(next)) return;
                shade = next;
                d.querySelectorAll('.shade-btn').forEach(b => { const selected = b.dataset.shade === shade; b.classList.toggle('selected', selected); b.setAttribute('aria-pressed', String(selected)); });
                syncOffer(); emit('shade_select', null, {item_variant: shade});
            };
            w.trackQuizCompletion = next => { if (SHADES.includes(next)) emit('quiz_complete', null, {item_variant: next}); };
            el('purchasePack').onchange = event => { if (!SKUS.includes(event.target.value)) return; selectedSku = event.target.value; syncOffer(); if (catalog) trackCart('view_item', [{sku: selectedSku, shade, quantity: 1}]); };
            el('retryCatalog').onclick = loadCatalog;
            d.querySelectorAll('[data-add-sku]').forEach(b => { b.onclick = () => add(b, b.dataset.addSku); });
            loadCatalog();
            setConsent(consent);
        }
        if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', ready, {once: true}); else ready();
    }
    return {SKUS, SHADES, MAX_QUANTITY, MAX_TOTAL, key, money, shippingSummary, validateCatalog, normalizeCart, createModel, checkoutPayload, ecommerceItems, verifiedPurchase, boot};
});
