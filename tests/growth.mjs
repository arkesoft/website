import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {writeFile,readFile} from 'node:fs/promises';
import {fixture} from './harness.mjs';
import {browser} from './cdp.mjs';
const f=await fixture(8137);let checks=0;
const ok=(v,label)=>{assert.ok(v,label);console.log('PASS '+label);checks++;};
const save=(values)=>f.owner.request('/yonetim',{csrf:f.token,...values});
const php=code=>execFileSync('php',['-d','extension=pdo_sqlite','-r',`require 'app/bootstrap.php'; ${code}`],{cwd:f.root,env:{...process.env,ARKESOFT_CONFIG:f.config},encoding:'utf8'});
const sql=(query,args=[])=>JSON.parse(php(`echo json_encode(query(${quote(query)},json_decode(${quote(JSON.stringify(args))},true))->fetchAll());`));
const quote=s=>"'"+s.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
const identity={action:'identity_save',email:'hello@example.test',phone:'5386415937',phone_secondary:'05380000000',whatsapp:'5386415937',business_kind:'ProfessionalService',business_country:'TR',business_city:'İstanbul',address:'Test adresi',maps_url:'https://www.google.com/maps?q=Istanbul',business_lat:'41.01',business_lng:'28.97',business_hours:'Mo-Fr 09:00-18:00',measurement_enabled:'1'};
try{
 for(const tab of ['identity','page-seo','authors','health','measurement','notifications'])ok((await f.owner.request('/yonetim?tab='+tab)).status===200,'New admin module '+tab);
 let r=await f.visitor.request('/');ok(!r.headers.get('set-cookie')&&r.headers.get('cache-control').startsWith('public'),'Anonymous content does not start a session');
 ok(!!r.headers.get('etag'),'Public HTML ETag');const etag=r.headers.get('etag');r=await fetch(f.base,{headers:{'If-None-Match':etag}});ok(r.status===304,'Conditional content request returns 304');
 r=await f.owner.request('/');ok(r.headers.get('cache-control').includes('no-store'),'Authenticated content never enters shared HTML cache');
 await save(identity);r=await f.visitor.request('/');ok(r.body.includes('905386415937')&&r.headers.get('etag')!==etag,'Identity changes invalidate cached public HTML');
 const graph=JSON.parse(r.body.match(/<script type="application\/ld\+json">(.*?)<\/script>/s)[1])['@graph'];
 ok(graph.some(n=>n['@type']==='WebSite')&&graph.some(n=>n['@type']==='ProfessionalService'&&n.geo.latitude===41.01&&n.contactPoint),'WebSite and physical business identity use saved facts');
 const contact=new f.Client();r=await contact.request('/iletisim?hizmet=web-tasarim&kaynak=%2Fhizmetler%2Fweb-tasarim&durum=Yeni&hedef=Gorunurluk');
 ok(r.headers.get('cache-control').includes('no-store')&&r.headers.get('set-cookie').includes('HttpOnly'),'Contact has private session and secure cookie attributes');
 ok(r.body.includes('selected>Web Tasarım')&&r.body.includes('Görüşme özetiniz'),'Service and discovery summary prefill contact form');
 ok(!r.body.includes('<iframe')&&r.body.includes('data-load-map'),'Google map waits for explicit visitor action');
 const token=r.body.match(/name="csrf" value="([a-f0-9]+)"/)[1],submission=r.body.match(/name="submission_id" value="([a-f0-9]+)"/)[1];
 const lead={csrf:token,short_form:'1',submission_id:submission,name:'Test müşterisi',phone:'05386415937',consent:'1',service:'Web Tasarım',source_path:'/hizmetler/web-tasarim',utm_source:'test-campaign'};
 await contact.request('/iletisim',lead);await contact.request('/iletisim',lead);
 let leads=sql("SELECT * FROM leads WHERE name='Test müşterisi'");ok(leads.length===1&&leads[0].email===''&&leads[0].message==='Kısa görüşme talebi','Phone-only short form is saved exactly once on retry');
 ok(sql('SELECT * FROM notification_queue').length===1,'Lead and notification are persisted together, once');
 ok(JSON.parse(sql("SELECT payload FROM content_meta WHERE entity='leads'")[0].payload).utm_source==='test-campaign','Campaign and source attribution retained');
 ok(sql("SELECT * FROM measurement_daily WHERE event='lead_saved'")[0].hits===1,'Successful lead is distinct from click events');
 await save({action:'lead_status',id:leads[0].id,status:'won'});ok(sql("SELECT status FROM leads WHERE name='Test müşterisi'")[0].status==='won','Confirmed customer outcome can be tracked separately');
 const other=new f.Client();let otherCsrf=await other.csrf('/iletisim');await other.request('/iletisim',{csrf:otherCsrf,short_form:'1',name:'No contact',consent:'1'});r=await other.request('/iletisim');ok(r.body.includes('Size ulaşabilmemiz')&&sql("SELECT * FROM leads WHERE name='Test müşterisi'").length===1,'Short form requires a real return contact channel');
 await save({action:'page_seo_save',page_key:'about',title:'Gerçek işletme başlığı',description:'Bu sayfaya özel yazılmış benzersiz açıklama metni.',image_id:'0'});r=await f.visitor.request('/hakkimizda');ok(r.body.includes('<title>Gerçek işletme başlığı</title>')&&r.body.includes('benzersiz açıklama'),'Per-page SEO renders saved metadata');
 const descriptions=[];for(const route of ['/','/hizmetler','/projeler','/hakkimizda','/iletisim','/blog','/cozumler']){r=await f.visitor.request(route);descriptions.push(r.body.match(/name="description" content="([^"]+)"/)[1]);}ok(new Set(descriptions).size===7,'Main pages have unique descriptions');
 await save({action:'author_save',name:'Test Editörü',slug:'test-editoru',kind:'Person',role:'Yazılım editörü',bio:'Bu profil yalnızca otomatik testte kullanılan, gerçek içerikten ayrı bir kayıttır.',profile_url:'',image_id:'0',status:'published'});
 const author=sql('SELECT * FROM authors')[0];ok(!!author,'Author profile saved');
 php(`for($i=0;$i<14;$i++){query('INSERT INTO articles (title,slug,excerpt,body,author,category,sources,image_id,status,published_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',['Araştırma '.$i,'growth-test-'.$i,'Yalnızca test yazısı','İçerik '.str_repeat('test ',30),'Test Editörü','Test kategorisi','',0,'published',gmdate('c',time()-$i*60),gmdate('c')]);$id=(int)db()->lastInsertId();growth_save_extra('articles',$id,['author_id'=>${author.id},'tags'=>['test'=>'Test'],'service_ids'=>[1]]);}`);
 r=await f.visitor.request('/blog?kategori=Test%20kategorisi');ok((r.body.match(/class="article-cover"/g)||[]).length===12&&r.body.includes('sayfa=2'),'Blog filters paginate in the database');
 ok(r.headers.get('x-robots-tag').includes('noindex'),'Search/filter variants are noindex');
 r=await f.visitor.request('/blog?sayfa=2');ok(r.body.includes(`href="${f.base}/blog?sayfa=2"`),'Blog page 2 has its own canonical');
 ok((await f.visitor.request('/blog?sayfa=9999')).status===404,'Out-of-range blog pages return 404');
 ok((await f.visitor.request('/blog?q=nonexistent')).body.includes('yazı bulunamadı'),'Empty blog results are understandable');
 r=await f.visitor.request('/blog/growth-test-0');ok(r.body.includes('/yazar/test-editoru')&&r.body.includes('İlgili rehberler'),'Article links author and related readings');
 const breadcrumbs=JSON.parse(r.body.match(/<script type="application\/ld\+json">(.*?)<\/script>/s)[1])['@graph'].find(n=>n['@type']==='BreadcrumbList');ok(breadcrumbs.itemListElement.length===3&&r.body.includes('aria-current="page"'),'Visible and structured breadcrumbs include parent');
 ok((await f.visitor.request('/yazar/test-editoru')).body.includes('Yazıları'),'Public author page lists linked posts');
 ok((await f.visitor.request('/sitemap.xml')).body.includes('/yazar/test-editoru'),'Published author included in dynamic sitemap');
 const metric=async(payload,headers={})=>fetch(f.base+'/olcum',{method:'POST',headers:{Origin:f.base,'Content-Type':'application/json',...headers},body:JSON.stringify(payload)});
 ok((await metric({event:'phone_click',path:'/hizmetler/web-tasarim',device:'mobile'})).status===204,'First-party contact metric accepted');
 ok((await metric({event:'phone_click',path:'/',device:'mobile'},{Origin:'https://attacker.test'})).status===403,'Cross-origin metrics rejected');
 ok((await metric({event:'lead_saved',path:'/',device:'mobile'})).status===400,'Client cannot forge confirmed lead event');
 ok((await metric({event:'LCP',path:'/',device:'mobile',value:-1})).status===400,'Invalid performance value rejected');
 await metric({event:'phone_click',path:'/',device:'mobile'},{DNT:'1'});ok(sql("SELECT * FROM measurement_daily WHERE event='phone_click'").length===1,'DNT request does not create measurement');
 php(`setting_write('mail_enabled','1');$config['mail_from']='sender@example.test';$config['mail_transport']='test-fail';growth_mail_worker();`);
 let queue=sql('SELECT * FROM notification_queue')[0];ok(queue.attempts===1&&queue.status==='pending'&&queue.available_at>Math.floor(Date.now()/1000),'Mail transport failure schedules retry without losing lead');
 php(`query('UPDATE notification_queue SET available_at=0');$config['mail_from']='sender@example.test';$config['mail_transport']='test';echo json_encode(growth_mail_worker());`);
 ok(sql('SELECT * FROM notification_queue')[0].status==='accepted','Worker records acceptance without claiming inbox delivery');
 php(`$config['mail_from']='sender@example.test';$config['mail_transport']='test';growth_mail_worker();`);ok(sql('SELECT * FROM notification_queue')[0].attempts===2,'Accepted notification is not sent again');
 r=await f.visitor.request('/');ok(r.body.includes('srcset=')&&!r.body.includes('/assets/inner.css')&&!r.body.includes('/assets/page-designs.css'),'Responsive media and page-specific CSS delivery');
 // Growth metadata must not restore stale core editor fields.
 const service=sql('SELECT * FROM services WHERE id=1')[0];await save({...service,action:'save',table:'services',growth_extra:'1',published:'1',seo_title:'Yeni SEO başlığı',seo_description:'Yeni ve özgün hizmet açıklaması, eski alanlardan korunmalı.',support_scope:'Teslim sonrası destek kapsamı test kaydı.'});
 const serviceMeta=JSON.parse(sql("SELECT payload FROM content_meta WHERE entity='services' AND record_id=1")[0].payload);ok(serviceMeta.seo_title==='Yeni SEO başlığı'&&serviceMeta.support_scope.includes('destek'),'Core and relationship metadata commit together');
 await save({action:'author_save',id:author.id,name:author.name,slug:'yeni-editor',kind:'Person',role:author.role,bio:author.bio,profile_url:'',image_id:'0',status:'published'});r=await f.visitor.request('/yazar/test-editoru');ok(r.status===301&&r.headers.get('location')==='/yazar/yeni-editor','Author URL change maintains old inbound links');
 if(process.env.ARKESOFT_GROWTH_BROWSER==='1'){
  const b=await browser();const axe=await readFile('tests/tooling/package/axe.min.js','utf8');
  try{
   const cookie=f.owner.cookie.split('=');await b.send('Network.setCookie',{name:cookie[0],value:cookie[1],url:f.base,path:'/'});
   const report=[];for(const width of [1440,390,320]){await b.viewport(width,900);for(const route of ['/','/blog','/iletisim?hizmet=web-tasarim','/ihtiyacini-belirle','/yazar/yeni-editor','/yonetim?tab=identity','/yonetim?tab=page-seo','/yonetim?tab=authors&edit='+author.id,'/yonetim?tab=measurement','/yonetim?tab=notifications','/yonetim?tab=health']){
    await b.go(f.base+route);ok(await b.evaluate(`document.documentElement.scrollWidth<=${width+1}`),`${width}px reflow ${route}`);
    await b.evaluate(axe);const result=await b.evaluate(`axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}}).then(r=>r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))})))`);report.push({width,route,violations:result});
   }}await writeFile('tests/reports/growth-accessibility.json',JSON.stringify(report,null,2));ok(report.every(r=>r.violations.length===0),'New surfaces pass automated accessibility rules');
   await b.viewport(390,844);await b.go(f.base+'/iletisim?hizmet=web-tasarim');await b.screenshot('tests/growth-contact-mobile.png');
  }finally{await b.close();}
 }
 console.log(`${checks} growth checks passed.`);
}finally{f.stop();}
