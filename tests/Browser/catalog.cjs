'use strict';
const { chromium }=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
const base=require('./environment.cjs').base,out=require('./environment.cjs').artifactPath('tg012-browser');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({executablePath:require('./environment.cjs').chromiumPath,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});const results=[];const errors=[];try{
 const page=await browser.newPage({colorScheme:'dark'});page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
 for(const width of [360,768,1280]){await page.setViewportSize({width,height:900});for(const uri of ['/produk','/produk/synthetic-public-ea','/produk/synthetic-empty-indicator','/broker']){
  const response=await page.goto(base+uri,{waitUntil:'networkidle'});assert.equal(response.status(),200);assert.equal(await page.locator('main').count(),1);assert.equal(await page.locator('h1').count(),1);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,'Overflow '+uri+' '+width);
  assert.equal(await page.locator('body').innerText().then(text=>text.includes('PRIVATE draft fixture')),false);
  assert.ok(await page.locator('link[rel=canonical]').getAttribute('href').then(url=>url.endsWith(uri)));
  if(uri==='/produk'){await page.locator('#editor-search').fill('Synthetic empty indicator');await page.getByRole('button',{name:'Tampilkan produk',exact:true}).click();await page.waitForFunction(()=>[...document.querySelectorAll('.catalog-card h3')].length===1);assert.equal(await page.locator('.catalog-card h3').innerText(),'Synthetic empty indicator');}
  if(uri.endsWith('synthetic-public-ea')){for(const id of ['preview','cara-pakai','performa','versi','faq','ulasan'])assert.equal(await page.locator('#'+id).count(),1);assert.equal(await page.getByText('-2.5000',{exact:true}).count(),1);assert.equal(await page.getByText('Win rate (%)',{exact:true}).count(),0);assert.equal(await page.locator('img').evaluateAll(images=>images.every(img=>img.complete&&img.naturalWidth>0)),true);}
  if(uri.endsWith('synthetic-empty-indicator')){assert.equal(await page.locator('#preview,#performa,#versi,#faq,#ulasan').count(),0);}
  if(uri==='/broker'){await page.locator('#editor-brokerId').selectOption({label:'Synthetic partner'});await page.locator('#editor-lots').fill('0.1');await page.getByRole('button',{name:'Hitung estimasi',exact:true}).click();await page.getByText('Estimasi: USD 0.1234',{exact:true}).waitFor();}
  await page.screenshot({path:out+'/'+uri.replaceAll('/','-').slice(1)+'-'+width+'.png',fullPage:true});results.push({width,uri,status:'PASS'});
 }}
 assert.deepEqual(errors,[]);const summary={passed:results.length,results,javascriptErrors:errors};fs.writeFileSync(out+'/summary.json',JSON.stringify(summary,null,2)+'\n');console.log(JSON.stringify(summary));
 }finally{await browser.close();}})().catch(error=>{console.error(error.stack);process.exitCode=1;});
