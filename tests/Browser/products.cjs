'use strict';
const {chromium}=require('playwright');const assert=require('node:assert/strict');const fs=require('node:fs');const crypto=require('node:crypto');
const origin=require('./environment.cjs').base;const out=require('./environment.cjs').artifactPath('tg011-browser');fs.mkdirSync(out,{recursive:true});
function otp(){const n=Buffer.alloc(8);n.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const d=crypto.createHmac('sha1',Buffer.from('12345678901234567890')).update(n).digest();return ((d.readUInt32BE(d[d.length-1]&15)&0x7fffffff)%1000000).toString().padStart(6,'0');}
(async()=>{const browser=await chromium.launch({executablePath:require('./environment.cjs').chromiumPath,headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});const results=[];try{
 const page=await browser.newPage({viewport:{width:1280,height:900}});page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(origin+'/login');await page.locator('#email').fill('layout-admin@example.invalid');await page.locator('#password').fill('test-only-password');
 await Promise.all([page.waitForURL('**/admin/2fa/challenge'),page.locator('button.auth-submit[type=submit]').click()]);await page.locator('#otp').fill(otp());await Promise.all([page.waitForURL('**/admin'),page.locator('button.auth-submit[type=submit]').click()]);
 await page.goto(origin+'/admin/produk/create',{waitUntil:'networkidle'});
 const fill=async(field,value)=>page.locator('#editor-'+field.replaceAll('.','-')).fill(value);
 const tab=async(label)=>{await page.getByRole('tab',{name:label,exact:true}).click();};
 const saved=async(text)=>page.locator('.ui-toast').filter({hasText:text}).waitFor({state:'visible'});
 await fill('form.name','Synthetic browser EA');await fill('form.slug','synthetic-browser-ea-'+crypto.randomBytes(4).toString('hex'));await fill('form.description','<p>Browser fixture only.</p><script>throw new Error("XSS")</script>');
 await tab('Harga');await fill('form.base_price_idr','125000.10');await fill('form.minimum_price_idr','100000');
 await Promise.all([page.waitForURL(/\/admin\/produk\/\d+\/edit$/),page.getByRole('button',{name:'Simpan informasi, harga & SEO',exact:true}).click()]);
 results.push('Create product through Livewire UI; sales remain disabled');
 await tab('FAQ');await fill('faqForm.question','Synthetic question');await fill('faqForm.answer','<p>Synthetic answer.</p>');await page.getByRole('button',{name:'Simpan FAQ',exact:true}).click();await saved('FAQ tersimpan');assert.equal(await page.getByRole('heading',{name:'Synthetic question',exact:true}).count(),1);
 await tab('Preview & cara pakai');await page.getByRole('button',{name:'Tambah langkah',exact:true}).click();await fill('steps.0.body','<p>Synthetic installation step.</p>');await page.getByRole('button',{name:'Simpan panduan',exact:true}).click();await saved('Langkah panduan tersimpan');
 const png=fs.readFileSync(require('./environment.cjs').fixturePath('qa-image.png'));await page.locator('#editor-image').setInputFiles({name:'synthetic.png',mimeType:'image/png',buffer:png});await page.getByText('Transfer selesai.',{exact:false}).first().waitFor();
 await fill('mediaForm.alt_text','Synthetic screenshot');await page.getByRole('button',{name:'Tambah media',exact:true}).click();await saved('Media tersimpan');await page.locator('.editor-media-list img').waitFor();
 await page.getByText('Transfer selesai.',{exact:false}).first().waitFor({state:'hidden'});results.push('FAQ, tutorial and actual image upload/re-encode/save');
 await tab('Performa');await page.locator('#editor-performanceForm-test_type').selectOption('backtest');await page.locator('#editor-performanceForm-account_environment').selectOption('demo');
 for(const [key,value] of Object.entries({starts_on:'2026-01-01',ends_on:'2026-01-31',symbol:'TEST',timeframe:'H1',methodology_note:'Synthetic browser fixture, no real return claims.'}))await fill('performanceForm.'+key,value);
 await page.getByRole('button',{name:'Simpan data pengujian',exact:true}).click();await saved('Data pengujian tersimpan');results.push('Performance metadata with absent metrics preserved');
 await tab('File & versi');await fill('versionForm.version','1.0');await page.locator('#editor-versionFile').setInputFiles({name:'synthetic.ex5',mimeType:'application/octet-stream',buffer:Buffer.concat([Buffer.from('EX5\0'),Buffer.alloc(200,0xff)])});await page.locator('#editor-versionFile').locator('xpath=../..').getByText('Transfer selesai.',{exact:false}).waitFor();
 await page.getByRole('button',{name:'Unggah versi',exact:true}).click();await saved('File versi tersimpan privat');
 await page.getByRole('button',{name:'Aktifkan versi',exact:true}).click();let dialog=page.getByRole('dialog',{name:'Konfirmasi perubahan produk'});await dialog.waitFor();await dialog.getByRole('button',{name:'Konfirmasi',exact:true}).click();await dialog.waitFor({state:'hidden'});await page.getByRole('heading',{name:'1.0 · Aktif',exact:true}).waitFor();results.push('Private version upload/checksum and modal activation');
 await page.getByRole('button',{name:'Publikasikan',exact:true}).click();dialog=page.getByRole('dialog',{name:'Konfirmasi perubahan produk'});await dialog.waitFor();await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});
 await page.getByRole('button',{name:'Publikasikan',exact:true}).click();await dialog.waitFor();await dialog.getByRole('button',{name:'Konfirmasi',exact:true}).click();await dialog.waitFor({state:'hidden'});await page.locator('.ui-badge').filter({hasText:'Dipublikasikan'}).waitFor();results.push('Publish confirmation, Escape cancellation and server result');
 for(const width of [360,768,1280]){await page.setViewportSize({width,height:900});for(const name of ['Informasi','Preview & cara pakai','Performa','File & versi','FAQ','Harga','SEO']){await tab(name);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,'Overflow at '+width+' '+name);}await tab('Informasi');await page.screenshot({path:out+'/editor-'+width+'.png',fullPage:true});results.push('All seven tabs fit viewport '+width);}
 await page.getByRole('tab',{name:'Informasi',exact:true}).focus();await page.keyboard.press('ArrowRight');await page.waitForFunction(()=>document.activeElement?.textContent==='Preview & cara pakai');
 assert.deepEqual(errors,[]);results.push('Keyboard tabs and no JavaScript errors');
 fs.writeFileSync(out+'/summary.json',JSON.stringify({passed:results.length,results},null,2)+'\n');console.log(JSON.stringify({passed:results.length,results}));
 }finally{await browser.close();}})().catch(e=>{console.error(e.stack?.replace(/\b\d{6}\b/g,'[code]'));process.exitCode=1;});
