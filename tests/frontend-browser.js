async (page) => {
    // Run through the existing Hermes Playwright tool with filename pointing here.
    // The fixture server must run on 8767. Never use this harness against production.
    const assert = (condition, label) => { if (!condition) throw new Error(label); };
    const results = [], errors = [], external = [];
    const origin = 'http://127.0.0.1:8767';
    await page.route('**/*', async route => {
        if (route.request().url().startsWith(origin + '/')) return route.continue();
        external.push(route.request().url().split('?')[0]); return route.abort();
    });
    page.on('pageerror', error => errors.push(error.message));
    const check = async (name, fn) => { await fn(); results.push({name,result:'pass'}); };
    const ready = async (suffix='') => {
        await page.goto(origin+'/index.html'+suffix);
        await page.waitForFunction(() => document.getElementById('catalogStatus').textContent.startsWith('Current bundle prices in USD.'));
    };
    const reset = async () => { await page.evaluate(() => localStorage.clear()); await ready(); };
    const cart = async () => page.evaluate(() => window.getCart());
    const close = async () => { if (await page.locator('#cartDrawer.active').count()) { await page.locator('#cartDrawer [aria-label="Close cart"]').click(); await page.waitForFunction(() => !document.getElementById('cartDrawer').classList.contains('active')); } };
    await page.setViewportSize({width:390,height:844});
    await ready(); await reset();
    await check('mobile module is first, catalog price+contents visible and Add reachable',async()=>{
        await page.locator('#shade-picker').scrollIntoViewIfNeeded();
        const bounds = await page.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,chooser:document.getElementById('shade-picker').getBoundingClientRect().top,image:document.querySelector('#product-details .product-image-container').getBoundingClientRect().top}));
        assert(bounds.width===390 && bounds.scroll<=390,'mobile overflow'); assert(bounds.chooser<bounds.image,'chooser not first');
        assert((await page.locator('#buyNowProductDetails').innerText()).includes('$12.34'),'catalog Starter price');
        assert((await page.locator('#purchaseContents').innerText()).includes('Pump Applicator'),'contents missing');
    });
    await check('both purchase entry orders merge with catalog price and no off-consent events',async()=>{
        for(const order of [0,1]) {
            await reset();
            const buttons = order===0 ? ['#buyNowProductDetails','[data-add-sku="hf-starter"]'] : ['[data-add-sku="hf-starter"]','#buyNowProductDetails'];
            for(const selector of buttons) { await page.locator(selector).click(); await close(); }
            const rows=await cart(); assert(rows.length===1&&rows[0].quantity===2&&rows[0].sku==='hf-starter','merge failed');
            assert((await page.locator('#cartTotal').textContent())==='$24.68','catalog subtotal');
            assert(await page.evaluate(()=>!dataLayer.some(e=>e.event==='add_to_cart')),'events before consent');
        }
    });
    await check('all nine UI shades synchronize both selectors, persist exact slug and merge correctly',async()=>{
        await reset();
        const shades=['black','dark-brown','medium-brown','light-brown','auburn','medium-blonde','light-blonde','gray','white'];
        for(const shade of shades) {
            await page.locator(`#shade-picker .shade-btn[data-shade="${shade}"]`).click();
            assert(await page.locator(`.shade-btn[data-shade="${shade}"][aria-pressed="true"]`).count()===2,'selectors not synchronized');
            await page.locator('#buyNowProductDetails').click();await close();
        }
        assert((await cart()).length===9,'missing shade rows');
        await page.locator('header [aria-controls="cartDrawer"]').click();
        await page.locator('#cartItems select').first().selectOption('dark-brown');
        assert((await cart()).length===8,'shade change did not merge');
        const focused=await page.evaluate(()=>({action:document.activeElement.dataset.action,id:document.activeElement.dataset.cartId}));
        assert(focused.action==='shade'&&focused.id==='hf-starter:dark-brown','shade change lost focus');
        await page.keyboard.press('Escape');
        await page.waitForFunction(()=>document.querySelector('header [aria-controls="cartDrawer"]')===document.activeElement);
        assert(await page.locator('header [aria-controls="cartDrawer"]').evaluate(n=>n===document.activeElement),'drawer focus did not return');
    });
    await check('old malformed IDs migrate and malicious stored HTML is not rendered',async()=>{
        await page.evaluate(()=>localStorage.setItem('haloFibersCart',JSON.stringify([
            {id:'Starter-dark-medium-brown',name:'Starter Bundle',shade:'medium-brown',quantity:1,price:0.01,image:'x" onerror="window.attacked=true'},
            {id:'Starter-medium-brown',name:'Starter Bundle',shade:'medium-brown',quantity:2,price:99999},
            {sku:'hf-pro',shade:'white',quantity:1,name:'<img src=x onerror="window.attacked=true">',image:'javascript:alert(1)',price:0.01},
            {id:'<svg onload=alert(1)>',name:'<script>',shade:'black',quantity:1},
            {sku:'hf-starter',shade:'gray',quantity:-1},{sku:'hf-starter',shade:'gray',quantity:1.5}
        ])));
        await ready();const rows=await cart();assert(rows.length===2&&rows[0].quantity===3,'migration failed');
        assert(await page.evaluate(()=>!window.attacked&&!document.querySelector('#cartItems img, #cartItems script, #cartItems svg')),'stored HTML rendered');
        assert((await page.locator('#cartTotal').textContent())==='$81.97','stale price survived');
    });
    await check('catalog 503 and malformed response disable all adds and checkout; retry recovers',async()=>{
        for(const mode of ['unavailable','malformed']) {
            await page.goto(origin+'/index.html?case='+mode);
            await page.waitForFunction(()=>document.getElementById('catalogStatus').textContent.startsWith('Purchasing temporarily unavailable'));
            assert(await page.locator('#buyNowProductDetails').isDisabled(),'primary Add enabled');
            assert(await page.locator('#checkoutBtn').isDisabled(),'checkout enabled');
            assert(await page.locator('[data-add-sku]:disabled').count()===3,'card Add enabled');
            await page.evaluate(()=>history.replaceState(null,'','/index.html'));
            await page.locator('#retryCatalog').click();
            await page.waitForFunction(()=>document.getElementById('catalogStatus').textContent.startsWith('Current bundle prices in USD.'));
            assert(await page.locator('#buyNowProductDetails').isEnabled(),'retry did not recover');
        }
    });
    await check('consented funnel events have canonical cents/items; local checkout error retains cart',async()=>{
        await reset(); await page.locator('#allowAnalytics').click();
        await page.locator('#shade-picker .shade-btn[data-shade="dark-brown"]').click();
        await page.locator('#buyNowProductDetails').click();
        await page.locator('#checkoutBtn').click();
        await page.waitForFunction(()=>document.getElementById('checkoutStatus').textContent.startsWith('Unable to open checkout'));
        const state=await page.evaluate(()=>({events:dataLayer.filter(e=>e.event),cart:getCart()}));
        for(const name of ['view_item','shade_select','add_to_cart','begin_checkout','checkout_error'])assert(state.events.some(e=>e.event===name),'missing '+name);
        const add=state.events.find(e=>e.event==='add_to_cart');assert(add.ecommerce.items[0].item_id==='hf-starter'&&add.ecommerce.items[0].price_cents===1234,'invalid analytics items');
        assert(!JSON.stringify(state.events).includes('@'),'PII in ecommerce events');assert(state.cart.length===1,'checkout error cleared cart');
        const posts=await page.evaluate(()=>fetch('/_fixture/posts').then(r=>r.json()));const last=posts[posts.length-1];
        assert(last.origin===origin&&last.body.analytics_consent===true,'checkout consent/origin mismatch');
        assert(Object.keys(last.body.items[0]).sort().join(',')==='quantity,shade,sku','client price/name in checkout');
        await close();await page.locator('#denyAnalytics').click();
        const before=await page.evaluate(()=>dataLayer.filter(e=>e.event==='add_to_cart').length);
        await page.locator('#buyNowProductDetails').click();
        assert(await page.evaluate(()=>dataLayer.filter(e=>e.event==='add_to_cart').length)===before,'revocation failed'); await close();
    });
    await check('quiz completion and suggested shade applied without guaranteed-match claim',async()=>{
        await page.locator('#allowAnalytics').click();await page.locator('#shade-picker button[onclick="openQuiz()"] ').click();
        await page.locator('#quizContent').getByRole('button',{name:'Brown',exact:true}).click();await page.locator('#quizContent').getByRole('button',{name:'Medium Brown',exact:true}).click();
        assert((await page.locator('#quizContent').innerText()).includes('Suggested Shade'),'quiz guarantee claim');
        await page.getByRole('button',{name:'Shop This Shade',exact:true}).last().click();
        await page.waitForFunction(()=>document.getElementById('selectedShadeName').textContent==='Medium Brown');
        assert(await page.evaluate(()=>dataLayer.some(e=>e.event==='quiz_complete'&&e.item_variant==='medium-brown')),'quiz event missing');
    });
    await check('direct and unverified success visits never confirm or emit purchase',async()=>{
        for(const suffix of ['', '?session_id=cs_test_unknown123456']) {
            await page.goto(origin+'/success.html'+suffix);
            await page.waitForFunction(()=>document.getElementById('orderHeading').textContent==='Order not yet verified');
            assert(await page.locator('#verifiedOrderInfo').isHidden(),'unverified fulfillment claims');
            assert(await page.evaluate(()=>!dataLayer.some(e=>e.event==='purchase')),'unverified purchase');
            assert(new URL(page.url()).search==='','session URL not scrubbed');
        }
    });
    await check('server verified confirmation without ecommerce still confirms, never emits purchase',async()=>{
        await page.goto(origin+'/success.html?session_id=cs_test_repeat123456');
        await page.waitForFunction(()=>document.getElementById('orderHeading').textContent==='Order confirmed');
        assert(await page.evaluate(()=>!dataLayer.some(e=>e.event==='purchase')),'receipt replay event');
    });
    await check('verified purchase waits for consent and is transaction-deduplicated across refresh',async()=>{
        await page.evaluate(()=>localStorage.clear());
        await page.goto(origin+'/success.html?session_id=cs_test_verified123456');
        await page.waitForFunction(()=>document.getElementById('orderHeading').textContent==='Order confirmed');
        assert(await page.evaluate(()=>!dataLayer.some(e=>e.event==='purchase')),'purchase before consent');
        await page.locator('#allowAnalytics').click();
        assert(await page.evaluate(()=>dataLayer.filter(e=>e.event==='purchase').length)===1,'verified purchase missing');
        assert(new URL(page.url()).search==='','purchase session leaked in URL');
        await page.goto(origin+'/success.html?session_id=cs_test_verified123456');
        await page.waitForFunction(()=>document.getElementById('orderHeading').textContent==='Order confirmed');
        assert(await page.evaluate(()=>!dataLayer.some(e=>e.event==='purchase')),'duplicate purchase on refresh');
    });
    await check('320/390/768/1440 layouts do not overflow and cart checkout stays in viewport',async()=>{
        await reset();
        for(const width of [320,390,768,1440]) {
            await page.setViewportSize({width,height:844});
            assert(await page.evaluate(()=>document.documentElement.scrollWidth===document.documentElement.clientWidth),'overflow at '+width);
            await page.locator('#buyNowProductDetails').click();
            const bounds=await page.locator('#checkoutBtn').boundingBox();assert(bounds&&bounds.x>=0&&bounds.y>=0&&bounds.y+bounds.height<=844,'checkout outside viewport '+width);
            await close();
        }
    });
    assert(errors.length===0,'uncaught errors: '+errors.join('; '));
    const report = {results,passed:results.length,uncaughtErrors:errors,blockedExternalRequests:external.length,externalMutations:0,scope:'Local fixture only; no Stripe/provider endpoints reached'};
    return report;
}
