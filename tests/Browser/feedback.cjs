'use strict';
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({executablePath:require('./environment.cjs').chromiumPath,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const results=[];
 try {
  for (const scenario of ['expired','server-error','disconnected','timeout']) {
   const context=await browser.newContext({viewport:{width:360,height:800}});
   const page=await context.newPage();let posts=0;const errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   page.on('dialog',()=>{throw new Error('Native browser alert/confirm is forbidden');});
   await page.goto(require('./environment.cjs').base+'/login',{waitUntil:'networkidle'});
   if(scenario==='timeout') await page.clock.install();
   let received;const sent=new Promise(resolve=>received=resolve);
   await page.route('**/livewire-*/update',async route=>{
    posts++;received();
    if(scenario==='timeout') return;
    if(scenario==='disconnected') return route.abort('failed');
    return route.fulfill({status:scenario==='expired'?419:503,contentType:'text/html',body:'private exception must not appear'});
   });
   await page.locator('#email').fill('feedback-fixture@example.invalid');
   await page.locator('#password').fill('synthetic-unused-password');
   await page.locator('button[type=submit]').click();await sent;
   if(scenario==='timeout') await page.clock.fastForward(31000);
   const dialog=page.locator('#request-feedback');await dialog.waitFor({state:'visible',timeout:5000});
   assert.equal(await dialog.evaluate(el=>el.matches(':modal')),true);
   assert.equal(await page.locator('body').innerText().then(s=>s.includes('private exception')),false);
   assert.equal(await dialog.locator('[data-check-status]').evaluate(el=>el===document.activeElement),true);
   await page.keyboard.press('Escape');
   assert.equal(await dialog.isVisible(),scenario!=='expired');
   if(scenario!=='expired') {
    assert.equal(await dialog.locator('[data-dismiss-feedback]').isVisible(),false);
    await page.keyboard.press('Tab');
    assert.equal(await dialog.locator('[data-check-status]').evaluate(el=>el===document.activeElement),true);
    const navigation=page.waitForRequest(req=>req.isNavigationRequest() && req.method()==='GET');
    await dialog.locator('[data-check-status]').click();await navigation;
    await page.waitForLoadState('networkidle');assert.equal(posts,1);
   }
   assert.deepEqual(errors,[]);results.push({scenario,status:'PASS'});await context.close();
  }
  const page=await browser.newPage();
  await page.goto(require('./environment.cjs').base+'/login',{waitUntil:'networkidle'});
  await page.locator('#email').focus();
  const html=require('node:fs').readFileSync(require('./environment.cjs').fixturePath('qa-feedback.html'),'utf8');
  await page.evaluate(html=>document.body.insertAdjacentHTML('beforeend',html),html);
  const modal=page.locator('dialog[aria-labelledby="fixture-title"]');
  await modal.waitFor({state:'visible'});
  await modal.locator('button').focus();await page.keyboard.press('Tab');
  assert.equal(await modal.locator('input').evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Shift+Tab');
  assert.equal(await modal.locator('button').evaluate(el=>el===document.activeElement),true);
  await modal.evaluate(el=>el.classList.add('auth-is-busy'));await page.keyboard.press('Escape');
  assert.equal(await modal.isVisible(),true);
  await modal.evaluate(el=>el.classList.remove('auth-is-busy'));await page.keyboard.press('Escape');
  assert.equal(await modal.isVisible(),false);
  const upload=page.locator('#upload-fixture');
  await upload.dispatchEvent('livewire-upload-start');
  await upload.evaluate(el=>el.dispatchEvent(new CustomEvent('livewire-upload-progress',{detail:{progress:37},bubbles:true})));
  await page.waitForFunction(() => document.querySelector('#upload-fixture progress')?.value === 37);
  assert.equal(await upload.locator('progress').evaluate(el => el.value),37);
  await upload.dispatchEvent('livewire-upload-finish');
  await upload.locator('progress').waitFor({state:'hidden'});
  await upload.getByText('Transfer selesai.',{exact:false}).waitFor({state:'visible'});
  await page.locator('#toast-fixture button').click();
  await page.locator('#toast-fixture').waitFor({state:'hidden'});
  results.push({scenario:'shared modal keyboard, busy Escape, upload events and dismissible toast',status:'PASS'});
  await page.close();
 } finally {await browser.close();}
 const fs=require('node:fs'),out=require('./environment.cjs').artifactPath('tg009-browser');fs.mkdirSync(out,{recursive:true});fs.writeFileSync(out+'/summary.json',JSON.stringify({passed:results.length,results,javascriptErrors:[]},null,2)+'\n');
 process.stdout.write(JSON.stringify(results,null,2)+'\n');
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
