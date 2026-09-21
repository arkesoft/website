/* Read-only browser QA. All geo answers are mocked; no external network allowed. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const os = require('node:os');
const root = path.resolve(__dirname, '..');
const files = fs.readdirSync(root).filter(f => f.endsWith('.html'));
const artifacts = process.env.TEST_ARTIFACTS || fs.mkdtempSync(path.join(os.tmpdir(), 'ajans-qa-'));
fs.mkdirSync(artifacts, { recursive: true });
const mime = { '.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.svg':'image/svg+xml','.webp':'image/webp','.webm':'video/webm','.mp4':'video/mp4' };
const security = JSON.parse(fs.readFileSync(path.join(root, 'vercel.json'))).headers[0].headers;
const server = http.createServer((req,res) => {
  const url = new URL(req.url, 'http://localhost');
  const target = path.resolve(root, '.' + decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname));
  if (!target.startsWith(root + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) { res.writeHead(404); res.end(); return; }
  security.forEach(({key,value}) => res.setHeader(key,value));
  res.setHeader('Content-Type', mime[path.extname(target)] || 'application/octet-stream');
  const size = fs.statSync(target).size, range = req.headers.range?.match(/^bytes=(\d+)-(\d*)$/);
  if (range) { const start=+range[1],end=range[2]?Math.min(+range[2],size-1):size-1; if(start>=size){res.writeHead(416);res.end();return}res.writeHead(206,{'Content-Range':`bytes ${start}-${end}/${size}`,'Accept-Ranges':'bytes','Content-Length':end-start+1});fs.createReadStream(target,{start,end}).pipe(res); }
  else {res.setHeader('Content-Length',size);fs.createReadStream(target).pipe(res);}
});
let browser;
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const base=`http://127.0.0.1:${server.address().port}/`;
  browser=await chromium.launch({headless:true, ...(process.env.BROWSER_EXECUTABLE?{executablePath:process.env.BROWSER_EXECUTABLE}:{}),args:['--no-sandbox']});
  const errors=[],unexpected=[],failed=[],requests=[];
  async function context({country='TR',locale='tr-TR',reducedMotion='reduce',slow=false,fail=false,...options}={}){
    const c=await browser.newContext({locale,reducedMotion,viewport:{width:1440,height:960},...options});
    await c.route('**/*',async route=>{
      const url=new URL(route.request().url());
      if(url.pathname==='/api/country'){if(slow)await new Promise(r=>setTimeout(r,650));return fail?route.abort():route.fulfill({json:{country}})}
      if(url.origin!==new URL(base).origin){unexpected.push(url.href);return route.abort()}
      requests.push(url.pathname);return route.continue();
    });
    c.on('page',page=>{page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)failed.push(r.url())});page.on('console',m=>{if(m.type()==='error' && !(fail && m.location().url.endsWith('/api/country')))errors.push(m.text())})});
    return c;
  }
  const c=await context({reducedMotion:'no-preference',locale:'en-US'}),p=await c.newPage();
  p.setDefaultTimeout(12000);
  await p.goto(base);
  await p.waitForFunction(()=>document.documentElement.dataset.countryStatus==='resolved');
  assert.equal(await p.locator('html').getAttribute('lang'),'tr');
  assert(await p.locator('#intro').isVisible());
  assert.equal(await p.locator('.hero-slide.active video').getAttribute('src'),null,'hero must not play behind intro');
  await p.keyboard.press('Escape');
  await p.waitForFunction(()=>{const v=document.querySelector('.hero-slide.active video');return v.readyState>=2&&!v.paused&&v.currentTime>0});
  await p.locator('.video-toggle').click();
  assert(await p.locator('.hero-slide.active video').evaluate(v=>v.paused));
  await p.screenshot({path:path.join(artifacts,'home-desktop.png')});
  await p.reload();assert(await p.locator('#intro').isVisible());
  await p.waitForFunction(()=>document.querySelector('#intro').hidden,null,{timeout:9000});
  await p.waitForFunction(()=>!document.querySelector('.hero-slide.active video').paused);
  await p.locator('[data-language=en]').click();await p.locator('.theme-toggle').click();
  await p.reload();await p.keyboard.press('Escape');
  assert.equal(await p.locator('html').getAttribute('lang'),'en');
  assert.equal(await p.locator('html').getAttribute('data-theme'),'light');
  await p.locator('[data-film]').click();await p.waitForFunction(()=>!document.querySelector('#film-dialog video').paused);
  assert(await p.locator('.hero-slide.active video').evaluate(v=>v.paused));
  await p.keyboard.press('Escape');assert(await p.locator('#film-dialog video').evaluate(v=>v.paused));
  // Native scroll choreography: full-screen expansion, then a quiet retreat.
  async function position(fraction){await p.evaluate(f=>{const e=document.querySelector('.immersive-scroll');scrollTo({top:e.offsetTop+(e.offsetHeight-innerHeight)*f,behavior:'instant'})},fraction);await p.waitForTimeout(100)}
  await position(0);const inset0=await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset')));
  await position(.5);const inset1=await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset')));assert(inset1<inset0);assert.equal(inset1,0);
  await p.screenshot({path:path.join(artifacts,'immersive-desktop.png')});
  await position(1);assert((await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset'))))>0);
  await p.locator('.menu-toggle').click();assert(await p.locator('#main').evaluate(e=>e.inert));
  await p.keyboard.press('Tab');assert(await p.evaluate(()=>document.activeElement.closest('#mega-menu')!==null));
  await p.locator('.menu-major a').filter({hasText:'Work'}).click();await p.waitForURL('**/projeler.html*');
  assert(await p.locator('#intro').isHidden(),'internal navigation skips intro');
  await p.locator('[data-filter=sosyal]').click();assert.equal(await p.locator('.works-grid .project-card:visible').count(),1);
  assert((await p.locator('.works-grid .project-card:visible h3').innerText()).includes('FORMA'));
  assert(new URL(p.url()).searchParams.get('filter')==='sosyal');
  await p.locator('#project-search').fill('does-not-exist');assert(await p.locator('#project-empty').isVisible());
  await p.locator('[data-reset-projects]').click();assert.equal(await p.locator('.works-grid .project-card:visible').count(),6);
  await p.locator('#project-search').fill('SAAS');assert.equal(await p.locator('.works-grid .project-card:visible').count(),1);
  await p.reload();await p.keyboard.press('Escape');assert.equal(await p.locator('#project-search').inputValue(),'SAAS');assert.equal(await p.locator('.works-grid .project-card:visible').count(),1);
  console.log('PASS intro / hero / theme / menu / scroll / portfolio search');
  await p.emulateMedia({reducedMotion:'reduce'});
  const translationIssues=[];
  for(const file of files){
    await p.setViewportSize({width:1440,height:960});
    await p.goto(base+file+'?lang=en&theme=light');
    await p.evaluate(()=>document.querySelectorAll('img').forEach(img=>img.loading='eager'));
    await p.waitForFunction(()=>[...document.images].every(img=>img.complete&&img.naturalWidth));
    assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),file+' desktop overflow');
    const untranslated=await p.evaluate(()=>{
      const missing=[],english=new Set(Object.values(window.ARKESOFT_LOCALES.en)),walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
      while(walker.nextNode()){const n=walker.currentNode;if(n.parentElement.closest('script,style,noscript,[data-no-translate],code'))continue;const s=n.textContent.replace(/\s+/g,' ').trim();if(!english.has(s)&&(/[çğıöşüÇĞİÖŞÜ]/.test(s)||(window.ARKESOFT_LOCALES.en[s]&&window.ARKESOFT_LOCALES.en[s]!==s)))missing.push(s)}
      for(const el of document.querySelectorAll('[alt],[aria-label],[placeholder]'))for(const attr of ['alt','aria-label','placeholder']){const s=el.getAttribute(attr)||'';if(!english.has(s)&&/[çğıöşüÇĞİÖŞÜ]/.test(s))missing.push(attr+': '+s)}
      return [...new Set(missing)];
    });
    if(untranslated.length)translationIssues.push({file,untranslated});
    for(const width of [390,320]){await p.setViewportSize({width,height:844});assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),file+' overflow at '+width)}
    if(['index.html','projeler.html','proje-orbit.html'].includes(file)){await p.setViewportSize({width:390,height:844});await p.screenshot({path:path.join(artifacts,'mobile-'+file+'.png')})}
    await p.locator('[data-language=tr]').click();assert.equal(await p.locator('html').getAttribute('lang'),'tr');
    console.log('PASS responsive / images / EN→TR',file);
  }
  assert.deepEqual(translationIssues,[],'Untranslated copy');
  await p.goto(base+'iletisim.html?hizmet=otomasyon&lang=en');
  await p.locator('[data-next]').first().click();await p.locator('[data-step="1"] [data-next]').click();assert((await p.locator('#form-error').innerText()).includes('Please'));
  await p.locator('[name=name]').fill('Test Brand');await p.locator('[name=email]').fill('qa@example.com');await p.locator('[name=message]').fill('A custom digital experience.');
  await p.locator('[data-step="1"] [data-next]').click();const download=p.waitForEvent('download');await p.locator('.form-download').click();const file=await download;assert(fs.readFileSync(await file.path(),'utf8').includes('PROJECT BRIEF'));
  const abroad=await context({country:'DE'}),a=await abroad.newPage();await a.goto(base);await a.waitForFunction(()=>document.documentElement.dataset.countryStatus==='resolved');assert.equal(await a.locator('html').getAttribute('lang'),'en');
  const race=await context({country:'US',slow:true}),r=await race.newPage();await r.goto(base);await r.locator('[data-language=tr]').click();await r.waitForTimeout(800);assert.equal(await r.locator('html').getAttribute('lang'),'tr');
  const fail=await context({fail:true,locale:'en-US'}),f=await fail.newPage();await f.goto(base);await f.waitForFunction(()=>document.documentElement.dataset.countryStatus==='fallback');assert.equal(await f.locator('html').getAttribute('lang'),'en');
  const nojs=await context({javaScriptEnabled:false}),n=await nojs.newPage();await n.goto(base);assert(await n.locator('h1').isVisible());assert(await n.locator('.journey-chapter').last().isVisible());assert(await n.locator('.immersive-caption').isVisible());
  const mp4=await context({reducedMotion:'no-preference'});await mp4.addInitScript(()=>{const original=HTMLMediaElement.prototype.canPlayType;HTMLMediaElement.prototype.canPlayType=function(type){return type==='video/webm'?'':original.call(this,type)}});
  const m=await mp4.newPage();await m.goto(base+'?lang=en');await m.keyboard.press('Escape');await m.waitForFunction(()=>{const v=document.querySelector('.hero-slide.active video');return v.currentSrc.endsWith('.mp4')&&v.readyState>=2&&!v.paused});
  const media=await m.locator('.hero-slide.active video').evaluate(v=>({duration:v.duration,width:v.videoWidth,height:v.videoHeight}));console.log('MP4 playback',media);
  assert(!requests.some(url=>/city-|interior\.jpg|fashion\.jpg|laptop\.jpg|watch\.jpg|\.woff/.test(url)),'archived stock/font requested');
  assert.deepEqual(unexpected,[]);assert.deepEqual(failed,[]);assert.deepEqual(errors,[]);
  console.log('PASS form download / country rules / no JS / MP4 fallback / security policy / no external requests');
  console.log('ARTIFACTS',artifacts);
})().catch(error=>{console.error(error);process.exitCode=1}).finally(async()=>{await browser?.close();server.closeAllConnections();server.close()});
