/* Read-only browser QA. All geo answers are mocked; no external network allowed. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const checkLayout = require('./layout-check.cjs');
const root = path.resolve(__dirname, '..');
const files = fs.readdirSync(root).filter(f => f.endsWith('.html'));
const routes = JSON.parse(fs.readFileSync(path.join(root, 'routes.json'), 'utf8'));
const englishRoutes = JSON.parse(fs.readFileSync(path.join(root, 'routes-en.json'), 'utf8'));
const artifacts = process.env.TEST_ARTIFACTS || fs.mkdtempSync(path.join(os.tmpdir(), 'ajans-qa-'));
fs.mkdirSync(artifacts, { recursive: true });
let browser, server;
(async()=>{
  server = (await import('../tools/server.mjs')).createPreviewServer();
  await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(0,'127.0.0.1',resolve)});
  const base=`http://127.0.0.1:${server.address().port}/`;
  browser=await chromium.launch({headless:true, ...(process.env.BROWSER_EXECUTABLE?{executablePath:process.env.BROWSER_EXECUTABLE}:{}),args:['--no-sandbox']});
  const errors=[],unexpected=[],failed=[],requests=[];
  async function context({country='TR',locale='tr-TR',reducedMotion='reduce',slow=false,fail=false,...options}={}){
    const c=await browser.newContext({locale,reducedMotion,viewport:{width:1440,height:960},...options});
    await c.route('**/*',async route=>{
      const url=new URL(route.request().url());
      if(url.pathname==='/api/country'){if(slow)await new Promise(r=>setTimeout(r,650));return fail?route.abort():route.fulfill({json:{country}})}
      if(url.origin!==new URL(base).origin){unexpected.push(url.href);return route.abort()}
      if(url.pathname==='/api/brief')return route.fulfill({status:503,json:{error:'Test: download fallback'}});
      requests.push(url.pathname);return route.continue();
    });
    const expectedFailure = url => new URL(url).pathname === '/api/brief' || new URL(url).pathname === '/404.html' || (fail && new URL(url).pathname === '/api/country');
    c.on('page',page=>{page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400 && !expectedFailure(r.url()))failed.push(r.url())});page.on('console',m=>{if(m.type()==='error' && !(m.location().url && expectedFailure(m.location().url)))errors.push(m.text())})});
    return c;
  }
  const c=await context({reducedMotion:'no-preference',locale:'en-US'}),p=await c.newPage();
  p.setDefaultTimeout(12000);
  await p.goto(base);
  await p.waitForFunction(()=>document.documentElement.dataset.countryStatus==='resolved');
  assert.equal(await p.locator('html').getAttribute('lang'),'tr');
  assert(await p.locator('#intro').isVisible());
  assert(!(await p.locator('#film-dialog').evaluate(dialog=>dialog.open)));
  await p.keyboard.press('Escape');
  assert(await p.locator('#intro').isHidden());
  await p.waitForFunction(()=>{const img=document.querySelector('.hero-slide.active img');return img.complete&&img.naturalWidth>0});
  await p.locator('.video-toggle').click();
  assert.equal(await p.locator('.video-toggle').getAttribute('aria-pressed'),'true');
  await p.locator('[data-slide="1"]').click();
  assert.equal(await p.locator('[data-slide="1"]').getAttribute('aria-pressed'),'true');
  await p.locator('.wa-float').click();
  assert(await p.locator('.whatsapp-chooser').evaluate(dialog=>dialog.open));
  assert.equal(await p.locator('.whatsapp-option').first().evaluate(element=>getComputedStyle(element).cursor),'auto');
  assert.equal(await p.locator('.whatsapp-option').count(),2);
  assert((await p.locator('.whatsapp-option').nth(0).innerText()).includes('+90 506 145 89 71'));
  assert((await p.locator('.whatsapp-option').nth(1).innerText()).includes('+90 538 641 59 37'));
  assert.equal(await p.locator('.whatsapp-option strong').nth(0).innerText(),'Ana hat');
  assert.equal(await p.locator('.whatsapp-option strong').nth(1).innerText(),'Ana hat');
  await p.locator('.whatsapp-chooser-close').click();
  await p.screenshot({path:path.join(artifacts,'home-desktop.png')});
  await p.reload();assert(await p.locator('#intro').isVisible());
  await p.waitForFunction(()=>document.querySelector('#intro').hidden,null,{timeout:9000});
  assert(await p.locator('.hero-slide.active img').isVisible());
  await p.locator('[data-language=en]').click();await p.locator('.theme-toggle').click();
  await p.reload();await p.keyboard.press('Escape');
  assert.equal(await p.locator('html').getAttribute('lang'),'en');
  assert.equal(await p.locator('html').getAttribute('data-theme'),'light');
  await p.locator('[data-film]').click();
  await p.waitForFunction(()=>{const img=document.querySelector('#film-dialog img');return img.complete&&img.naturalWidth>0});
  assert(await p.locator('#film-dialog').evaluate(dialog=>dialog.open));
  await p.keyboard.press('Escape');assert(!(await p.locator('#film-dialog').evaluate(dialog=>dialog.open)));
  // Native scroll choreography: full-screen expansion, then a quiet retreat.
  async function position(fraction){await p.evaluate(f=>{const e=document.querySelector('.immersive-scroll');scrollTo({top:e.offsetTop+(e.offsetHeight-innerHeight)*f,behavior:'instant'})},fraction);await p.waitForTimeout(100)}
  await position(0);const inset0=await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset')));
  await position(.5);const inset1=await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset')));assert(inset1<inset0);assert.equal(inset1,0);
  await p.screenshot({path:path.join(artifacts,'immersive-desktop.png')});
  await position(1);assert((await p.locator('.immersive-scroll').evaluate(e=>parseFloat(e.style.getPropertyValue('--image-inset'))))>0);
  await p.locator('.menu-toggle').click();assert(await p.locator('#main').evaluate(e=>e.inert));
  await p.keyboard.press('Tab');assert(await p.evaluate(()=>document.activeElement.closest('#mega-menu')!==null));
  await p.locator('.menu-major a').filter({hasText:'Work'}).click();await p.waitForURL('**/en/projects');
  assert.equal(await p.locator('html').getAttribute('lang'),'en');
  assert.equal(await p.locator('html').getAttribute('data-theme'),'light');
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
  const translationIssues=[],overflowIssues=[];
  for(const file of files){
    await p.setViewportSize({width:1440,height:960});
    await p.goto(englishRoutes[file] ? new URL(englishRoutes[file]+'?theme=light',base).href : base+file+'?lang=en&theme=light');
    if (englishRoutes[file]) assert.equal(new URL(p.url()).pathname, englishRoutes[file]);
    await p.evaluate(()=>document.querySelectorAll('img').forEach(img=>img.loading='eager'));
    await p.waitForFunction(()=>[...document.images].every(img=>img.complete&&img.naturalWidth));
    if(!(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)))overflowIssues.push({file,width:1440});
    const untranslated=await p.evaluate(()=>{
      const missing=[],english=new Set(Object.values(window.ARKESOFT_LOCALES.en)),walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
      while(walker.nextNode()){const n=walker.currentNode;if(n.parentElement.closest('script,style,noscript,[data-no-translate],code'))continue;const s=n.textContent.replace(/\s+/g,' ').trim();if(!english.has(s)&&(/[çğıöşüÇĞİÖŞÜ]/.test(s)||(window.ARKESOFT_LOCALES.en[s]&&window.ARKESOFT_LOCALES.en[s]!==s)))missing.push(s)}
      for(const el of document.querySelectorAll('[alt],[aria-label],[placeholder]'))for(const attr of ['alt','aria-label','placeholder']){const s=el.getAttribute(attr)||'';if(!english.has(s)&&/[çğıöşüÇĞİÖŞÜ]/.test(s))missing.push(attr+': '+s)}
      return [...new Set(missing)];
    });
    if(untranslated.length)translationIssues.push({file,untranslated});
    for(const width of [1440,1024,768,390,320]){
      await p.setViewportSize({width,height:width>760?900:width===320?568:844});
      const issues=await p.evaluate(checkLayout);
      if(issues.length)overflowIssues.push({file,width,issues});
    }
    if(['index.html','projeler.html','proje-orbit.html'].includes(file)){await p.setViewportSize({width:390,height:844});await p.screenshot({path:path.join(artifacts,'mobile-'+file+'.png')})}
    await p.locator('[data-language=tr]').click();assert.equal(await p.locator('html').getAttribute('lang'),'tr');
    if (routes[file]) assert.equal(new URL(p.url()).pathname, routes[file]);
    console.log('CHECKED responsive / images / EN→TR',file);
  }
  assert.deepEqual(overflowIssues,[],'Horizontal overflow');
  assert.deepEqual(translationIssues,[],'Untranslated copy');
  // Old bookmarks normalize without dropping filters or section anchors.
  await p.goto(base+'projeler.html?lang=en&theme=light&filter=sosyal#main');
  assert.equal(new URL(p.url()).pathname,'/en/projects');
  assert.equal(new URL(p.url()).search,'?filter=sosyal');
  assert.equal(new URL(p.url()).hash,'#main');
  assert.equal(await p.locator('.works-grid .project-card:visible').count(),1);
  await p.locator('[data-language=tr]').click();
  assert.equal(new URL(p.url()).pathname,'/projeler');
  assert.equal(new URL(p.url()).search,'?filter=sosyal');
  assert.equal(new URL(p.url()).hash,'#main');
  await p.locator('[data-language=en]').click();
  await p.reload();
  assert.equal(new URL(p.url()).pathname,'/en/projects');
  assert.equal(await p.locator('.works-grid .project-card:visible').count(),1);
  const blocked=await context();
  await blocked.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new DOMException('Blocked','SecurityError')}}));
  const bp=await blocked.newPage();
  await bp.goto(base+'studyo.html?lang=en&theme=light');
  await bp.locator('.header-links a[href="/en/projects"]').click();
  await bp.waitForURL('**/en/projects');
  assert.equal(await bp.locator('html').getAttribute('lang'),'en');
  assert.equal(await bp.locator('html').getAttribute('data-theme'),'light');
  assert.equal(new URL(bp.url()).search,'');
  await blocked.close();
  const noStorage=await context();
  await noStorage.addInitScript(()=>{for(const key of ['localStorage','sessionStorage'])Object.defineProperty(window,key,{get(){throw new DOMException('Blocked','SecurityError')}})});
  const ns=await noStorage.newPage();
  await ns.goto(new URL('/en/about',base).href);
  await ns.locator('.header-links a[href="/en/projects"]').click();await ns.waitForURL('**/en/projects');
  assert.equal(await ns.locator('html').getAttribute('lang'),'en');
  await ns.reload();assert.equal(await ns.locator('html').getAttribute('lang'),'en');
  await noStorage.close();
  console.log('PASS clean URLs / query and hash preservation / session storage fallback');
  await p.goto(base+'iletisim.html?hizmet=otomasyon&lang=en');
  await p.locator('[data-next]').first().click();await p.locator('[data-step="1"] [data-next]').click();assert((await p.locator('#form-error').innerText()).includes('Please'));
  await p.locator('[name=name]').fill('Test Brand');await p.locator('[name=email]').fill('qa@example.com');await p.locator('[name=message]').fill('A custom digital experience.');
  await p.locator('[data-language=tr]').click();
  assert.equal(new URL(p.url()).pathname,'/iletisim');
  await p.locator('[data-language=en]').click();
  assert.equal(new URL(p.url()).pathname,'/en/contact');
  assert.equal(await p.locator('[name=name]').inputValue(),'Test Brand');
  assert.equal(await p.locator('[name=message]').inputValue(),'A custom digital experience.');
  await p.locator('[data-step="1"] [data-next]').click();const download=p.waitForEvent('download');await p.locator('.form-download').click();const file=await download;assert(fs.readFileSync(await file.path(),'utf8').includes('PROJECT BRIEF'));
  const abroad=await context({country:'DE'}),a=await abroad.newPage();await a.goto(base);await a.waitForFunction(()=>document.documentElement.dataset.countryStatus==='resolved');assert.equal(await a.locator('html').getAttribute('lang'),'en');
  const race=await context({country:'US',slow:true}),r=await race.newPage();await r.goto(base);await r.locator('[data-language=tr]').click();await r.waitForTimeout(800);assert.equal(await r.locator('html').getAttribute('lang'),'tr');
  const fail=await context({fail:true,locale:'en-US'}),f=await fail.newPage();await f.goto(base);await f.waitForFunction(()=>document.documentElement.dataset.countryStatus==='fallback');assert.equal(await f.locator('html').getAttribute('lang'),'en');
  const nojs=await context({javaScriptEnabled:false}),n=await nojs.newPage();await n.goto(base);assert(await n.locator('h1').isVisible());assert(await n.locator('.journey-chapter').last().isVisible());assert(await n.locator('.immersive-caption').isVisible());
  for(const route of Object.values(englishRoutes)){
    const response=await n.goto(new URL(route,base).href);
    assert.equal(response.status(),200,route);
    assert.equal(await n.locator('html').getAttribute('lang'),'en');
    assert(!/[çğıöşüÇĞİÖŞÜ]/.test(await n.locator('h1').innerText()),route+' must be English without JavaScript');
    assert.equal(await n.locator('.header .brand').getAttribute('href'),'/en/home');
  }
  assert(!requests.some(url=>/city-|interior\.jpg|fashion\.jpg|laptop\.jpg|watch\.jpg|\.woff/.test(url)),'archived stock/font requested');
  assert.deepEqual(unexpected,[]);assert.deepEqual(failed,[]);assert.deepEqual(errors,[]);
  console.log('PASS form download / country rules / no JS / security policy / no external requests');
  console.log('ARTIFACTS',artifacts);
})().catch(error=>{console.error(error);process.exitCode=1}).finally(async()=>{await browser?.close();server?.closeAllConnections();server?.close()});
