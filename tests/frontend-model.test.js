'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const api = require('../assets/conversion.js');
const fixture = () => ({currency:'USD', products:Object.fromEntries(api.SKUS.map((sku,i) => [sku,{sku,name:['Starter Bundle','Ultimate Bundle','Pro Bundle'][i],unit_amount:[1234,6995,4495][i],contents:[`${i+1} fixture bottles`,'Fixture applicator']} ])),shades:Object.fromEntries(api.SHADES.map(s => [s,s]))});
const storage = (raw) => {const map = new Map([['haloFibersCart',raw]]); return {getItem:k=>map.get(k)||null,setItem:(k,v)=>map.set(k,v),map};};
const legacy = (shade,quantity=1,price=0.01,id=`Starter-${shade}`) => ({id,name:'Starter Bundle',shade,quantity,price,image:'<img src=x onerror=alert(1)>'});

test('shipping eligibility is strictly over $39, never inclusive or a fabricated below-threshold rate', () => {
 for(const amount of [0,3899,3900]) assert(!api.shippingSummary(amount).startsWith('Eligible'));
 assert(api.shippingSummary(3901).startsWith('Eligible'));
 assert(api.shippingSummary(null).includes('not yet available'));
 assert(!api.shippingSummary(3900).includes('$0'));
});

test('catalog uses authoritative integer cents including a deliberately synthetic Starter price', () => {
 const c=api.validateCatalog(fixture()); assert.equal(c.products['hf-starter'].unit_amount,1234); assert.equal(c.products['hf-ultimate'].unit_amount,6995); assert.equal(c.products['hf-pro'].unit_amount,4495);
 for (const amount of [null,0,-1,12.34,'1234',Infinity,Number.MAX_SAFE_INTEGER]) { const f=fixture(); f.products['hf-starter'].unit_amount=amount; assert.throws(()=>api.validateCatalog(f)); }
});
test('catalog fails closed on missing SKU, missing shade, malformed contents or currency', () => {
 for (const modify of [x=>delete x.products['hf-starter'],x=>delete x.shades['dark-brown'],x=>x.currency='usd',x=>x.products['hf-pro'].contents='bottle',x=>x.products['hf-pro'].sku='hf-starter']) {const f=fixture();modify(f);assert.throws(()=>api.validateCatalog(f));}
});
test('both Starter entry orders ignore every old client price and merge to one current-price row', () => {
 for (const prices of [[24.95,29.95],[29.95,24.95],[-100,999999]]) {
  const st=storage(JSON.stringify([legacy('black',1,prices[0])])); const m=api.createModel(st); m.add('hf-starter','black',prices[1]);
  assert.deepEqual(m.get(),[{sku:'hf-starter',shade:'black',quantity:2}]);
  const items=api.ecommerceItems(m.get(),api.validateCatalog(fixture()));assert.equal(items[0].price_cents,1234);assert.equal(items[0].quantity,2);
  assert(!st.map.get('haloFibersCart').includes('price'));
 }
});
test('documented malformed shade-change ID migrates and merges with canonical row', () => {
 const rows=api.normalizeCart([legacy('medium-brown',1,29.95,'Starter-dark-medium-brown'),legacy('medium-brown',2)]);
 assert.deepEqual(rows,[{sku:'hf-starter',shade:'medium-brown',quantity:3}]);
});
test('repeated changes across all nine shades and mixed bundles keep stable SKU identity', () => {
 for (const sku of api.SKUS) for (const from of api.SHADES) for (const to of api.SHADES) {
  const m=api.createModel(storage('[]'));m.add(sku,from);
  assert(m.changeShade(`${sku}:${from}`,to));m.add(sku,to);
  assert.deepEqual(m.get(),[{sku,shade:to,quantity:2}]);
 }
});
test('shade change merges into existing exact SKU/shade without merging different bundles', () => {
 const m=api.createModel(storage('[]'));m.add('hf-starter','dark-brown');m.add('hf-starter','medium-brown');m.add('hf-pro','medium-brown');
 assert(m.changeShade('hf-starter:dark-brown','medium-brown'));assert.equal(m.get().length,2);assert.equal(m.get()[0].quantity,2);assert.equal(m.get()[1].sku,'hf-pro');
});
test('quantity limits match backend: 10 per SKU/shade, 30 per cart; no shade-change data loss', () => {
 const m=api.createModel(storage('[]'));
 for (let i=0;i<10;i++)assert(m.add('hf-pro','black'));
 assert.equal(m.add('hf-pro','black'),false);m.add('hf-pro','gray');assert.equal(m.changeShade('hf-pro:gray','black'),false);assert.equal(m.get()[1].quantity,1);
 for (const sku of ['hf-starter','hf-ultimate'])for(let i=0;i<10;i++)m.add(sku,'white');
 assert.equal(m.get().reduce((n,r)=>n+r.quantity,0),30);assert.equal(m.add('hf-pro','auburn'),false);
});
test('negative, zero, fractional, strings, overflow and NaN quantities are discarded', () => {
 for(const quantity of [-1,0,1.5,'2',11,1e30,NaN,null,undefined])assert.deepEqual(api.normalizeCart([{...legacy('black'),quantity}]),[]);
});
test('unknown SKUs, malicious IDs and invalid shades are rejected; stored HTML is never retained', () => {
 const bad=[{sku:'__proto__',shade:'black',quantity:1},legacy('black',1,0,'Starter-evil'),legacy('BLACK'),legacy('<img onerror=alert(1)>'),{id:'Starter-black',name:'<svg onload=alert(1)>',shade:'black',quantity:1}];
 assert.deepEqual(api.normalizeCart(bad),[]);
 const good=api.normalizeCart([{sku:'hf-starter',shade:'black',quantity:1,name:'<script>alert(1)</script>',price:0.01,image:'javascript:alert(1)',id:'<img onerror=alert(1)>'}]);
 assert.deepEqual(good,[{sku:'hf-starter',shade:'black',quantity:1}]);assert(!JSON.stringify(good).includes('<'));
});
test('corrupt JSON, null/object storage, and disabled storage are safe and preserve in-memory behavior', () => {
 for(const raw of ['{oops','null','{}','"cart"'])assert.deepEqual(api.createModel(storage(raw)).get(),[]);
 const m=api.createModel({getItem(){throw Error('blocked');},setItem(){throw Error('blocked');}});assert(m.add('hf-pro','gray'));assert.equal(m.get().length,1);
});
test('returned carts cannot mutate internal state; remove, decrement and reload work', () => {
 const st=storage('[]'),m=api.createModel(st);m.add('hf-pro','white');m.get()[0].quantity=100;assert.equal(m.get()[0].quantity,1);
 assert.equal(m.quantity('hf-pro:white',0.5),false);assert(m.quantity('hf-pro:white',1));assert.equal(api.createModel(st).get()[0].quantity,2);
 m.quantity('hf-pro:white',-1);m.quantity('hf-pro:white',-1);assert.deepEqual(m.get(),[]);
});
test('checkout payload allows only canonical identifiers, shade, integer quantity and explicit boolean consent', () => {
 const rows=[{sku:'hf-pro',shade:'gray',quantity:2,price:0.01,name:'Email: fake@example.test'}];
 assert.deepEqual(api.checkoutPayload(rows,true),{items:[{sku:'hf-pro',shade:'gray',quantity:2}],analytics_consent:true});
 assert.equal(api.checkoutPayload(rows,'granted').analytics_consent,false);
});
test('purchase requires backend verified flag plus valid approved items, strips arbitrary fields and names', () => {
 const data={verified:true,ecommerce:{transaction_id:'cs_test_fixture123456',currency:'USD',value_cents:2468,email:'fixture@example.test',items:[{item_id:'hf-starter',item_name:'fixture@example.test',item_variant:'black',quantity:2,price_cents:1234,address:'fixture'}]}};
 assert.equal(api.verifiedPurchase({...data,verified:false}),null);assert.equal(api.verifiedPurchase({verified:true}),null);assert.equal(api.verifiedPurchase({session_id:'anything'}),null);
 const p=api.verifiedPurchase(data);assert.equal(p.value,24.68);assert.equal(p.items[0].item_name,'Starter Bundle');assert(!JSON.stringify(p).includes('@'));
 for(const change of [x=>x.ecommerce.items[0].price_cents=1.2,x=>x.ecommerce.items[0].quantity=-1,x=>x.ecommerce.items[0].item_variant='unknown',x=>x.ecommerce.transaction_id='<img>']) {const x=structuredClone(data);change(x);assert.equal(api.verifiedPurchase(x),null);}
});
test('all inline JavaScript parses and all JSON-LD parses', () => {
 for(const filename of ['index.html','success.html']) {
  const html=fs.readFileSync(path.join(__dirname,'..',filename),'utf8');
  for(const match of html.matchAll(/<script([^>]*)>([\s\S]*?)<\/script>/g)) {
   if(match[1].includes('application/ld+json'))JSON.parse(match[2]); else if(!match[1].includes('src='))new vm.Script(match[2],{filename});
  }
 }
});
test('all offer entry points are catalog based; old promotions, prices, viewer simulation and PII logs removed', () => {
 const html=fs.readFileSync(path.join(__dirname,'../index.html'),'utf8');
 for(const forbidden of [/29\.95/,/24\.95/,/Math\.random/,/viewersCount/,/15%/,/HALO10/,/console\.(log|error)/,/2,847/,/4\.9/,/As Seen In/,/Low Stock/,/300%/])assert(!forbidden.test(html),String(forbidden));
 for(const sku of api.SKUS) {assert(html.includes(`data-add-sku="${sku}" disabled`));assert(html.includes(`data-catalog-price="${sku}"`));assert(html.includes(`data-catalog-contents="${sku}"`));}
 assert(!html.includes('min-w-[600px]'));assert(!html.includes('emailInput'));
});
test('excluded results, how-it-works, spray FAQ and slider code are byte-for-byte live baseline', () => {
 const baselinePath=process.env.HF_BASELINE || '/Users/walidi/Downloads/halofibers-conversion-implementation/baseline-live-index.html';
 const before=fs.readFileSync(baselinePath,'utf8'),after=fs.readFileSync(path.join(__dirname,'../index.html'),'utf8');
 for(const id of ['results','how-it-works']) {const re=new RegExp(`<section id="${id}"[\\s\\S]*?<\\/section>`);assert.equal(after.match(re)[0],before.match(re)[0],id);}
 const spray=/<!-- Q2 -->[\s\S]*?<!-- Q3 -->/;assert.equal(after.match(spray)[0],before.match(spray)[0]);
 const slider=/        \/\/ 2\. Slider Logic[\s\S]*?        initSlider\('beforeAfterResults'\);/;assert.equal(after.match(slider)[0],before.match(slider)[0]);
});
