'use strict';
const {chromium}=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
const base='http://127.0.0.1:8081',out='/workspace/.tg-cache/tg013-15-browser';fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});const checks=[];try{
 const context=await browser.newContext({viewport:{width:1280,height:900},permissions:['clipboard-read','clipboard-write']});const page=await context.newPage();page.setDefaultTimeout(12000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/produk/synthetic-checkout-ea',{waitUntil:'networkidle'});await page.getByRole('link',{name:'Sewa',exact:true}).click();await page.waitForURL('**/login');
 await page.locator('#email').fill('layout-client@example.invalid');await page.locator('#password').fill('test-only-password');await page.locator('.auth-submit').click();await page.waitForURL(url=>url.pathname==='/checkout/synthetic-checkout-ea'&&url.searchParams.has('intent'));
 const intentURL=page.url();checks.push('Login returns to checkout with persistent owned intent');
 await page.locator('#editor-mt5_account').fill('12345678');await page.locator('#editor-mt5_server').fill('Fixture-MT5-Demo');await page.locator('#editor-account_type').selectOption('demo');await page.locator('#method-fixture-bank').check();
 let committed=false;const intercept=async route=>{const response=await route.fetch();assert.equal(response.status(),200);committed=true;await route.abort('failed');};await page.route('**/livewire-*/update',intercept);
 await page.getByRole('button',{name:'Buat Pesanan',exact:true}).click();const unknown=page.locator('#request-feedback');await unknown.waitFor({state:'visible'});assert.equal(committed,true);await page.unroute('**/livewire-*/update',intercept);
 await Promise.all([page.waitForURL('**/pesanan/*'),unknown.getByRole('button',{name:'Cek Status',exact:true}).click()]);const orderURL=page.url();const code=await page.locator('h1').innerText();checks.push('Lost response after commit resolves original intent via GET without resubmission');
 assert.ok(await page.locator('body').innerText().then(text=>text.includes('Menunggu Pembayaran')&&!text.includes('Salin Key')));
 let posts=0;page.on('request',req=>{if(req.method()==='POST')posts++;});
 await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async text=>{window.__copiedInvoice=text;}}}));
 await page.getByRole('button',{name:'Salin Detail Pesanan',exact:true}).click();await page.getByText('Detail tersalin. Tempel sendiri saat menghubungi admin.',{exact:true}).waitFor();
 const copied=await page.evaluate(()=>window.__copiedInvoice);assert.ok(copied.includes(code)&&copied.includes('12345678')&&copied.includes('SYNTHETIC-NOT-A-BANK')&&copied.includes('31 Desember'));
 const telegram=page.getByRole('link',{name:'Chat Admin di Telegram',exact:false});assert.equal(await telegram.getAttribute('href'),'https://t.me/tg_fixture_contact');assert.equal(await telegram.getAttribute('target'),'_blank');assert.ok((await telegram.getAttribute('rel')).includes('noreferrer'));
 await telegram.evaluate(el=>el.addEventListener('click',e=>e.preventDefault()));await telegram.click();assert.equal(posts,0);checks.push('Clipboard contains actual invoice snapshot; chat is plain personal link without POST or payload');
 await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async()=>{throw new Error('Synthetic clipboard denial');}}}));await page.getByRole('button',{name:'Salin Detail Pesanan',exact:true}).click();await page.getByText('Belum dapat menyalin otomatis. Teks sudah dipilih; salin secara manual.',{exact:true}).waitFor();assert.equal(await page.locator('#order-text').evaluate(el=>el.selectionEnd-el.selectionStart===el.value.length),true);checks.push('Denied clipboard permission offers selected manual copy without fake success');
 await page.goto(intentURL,{waitUntil:'networkidle'});assert.equal(page.url(),orderURL);await page.goto(base+'/member/pesanan',{waitUntil:'networkidle'});assert.equal(await page.locator('.auth-client-row').count(),1);checks.push('Retry intent and order history show one waiting order');
 await page.goto(base+'/checkout/synthetic-checkout-ea',{waitUntil:'networkidle'});const nextIntentURL=page.url();
 for(const width of [360,768,1280]){
  await page.setViewportSize({width,height:900});for(const [name,url]of[['checkout',nextIntentURL],['order',orderURL]]){await page.goto(url,{waitUntil:'networkidle'});assert.equal(await page.locator('main').count(),1);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,name+' overflow '+width);await page.screenshot({path:out+'/'+name+'-'+width+'.png',fullPage:true});}
  checks.push('Checkout and invoice fit viewport '+width);
 }
 assert.deepEqual(errors,[]);const result={passed:checks.length,checks,javascriptErrors:errors};fs.writeFileSync(out+'/summary.json',JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result));
 }finally{await browser.close();}})().catch(e=>{console.error(e.stack);process.exitCode=1;});
