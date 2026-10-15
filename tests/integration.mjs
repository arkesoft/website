import { spawn, execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { randomBytes } from 'node:crypto';
import assert from 'node:assert/strict';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runtime=path.join(root,'tests','runtime',String(Date.now()));
await mkdir(runtime,{recursive:true});
const port=8133, base=`http://127.0.0.1:${port}`;
const token=randomBytes(32).toString('hex'), password=randomBytes(20).toString('hex');
const configFile=path.join(runtime,'config.php');
const phpQuote=value=>"'"+value.replaceAll('\\','/').replaceAll("'","\\'")+"'";
const mysqlPort=Number(process.env.ARKESOFT_TEST_MYSQL_PORT || 0);
let dbConfig=`'test_sqlite'=>${phpQuote(path.join(runtime,'test.sqlite'))}`;
if(mysqlPort){
  assert.ok(Number.isInteger(mysqlPort) && mysqlPort>1024 && mysqlPort<65536);
  const dbName='arkesoft_test_'+Date.now();
  execFileSync('php',['-r',`$db=new PDO('mysql:host=127.0.0.1;port=${mysqlPort}','root','');$db->exec('CREATE DATABASE ${dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');`]);
  dbConfig=`'db_host'=>'127.0.0.1','db_port'=>${mysqlPort},'db_name'=>'${dbName}','db_user'=>'root','db_pass'=>''`;
}
await writeFile(configFile,`<?php return ['env_file'=>${phpQuote(path.join(runtime,'.env'))},'site_url'=>${phpQuote(base)},${dbConfig},'setup_token'=>${phpQuote(token)}];`);
let errors='';
const server=spawn('php',['-d','extension=pdo_sqlite','-S',`127.0.0.1:${port}`,'router.php'],{cwd:root,env:{...process.env,ARKESOFT_CONFIG:configFile},stdio:['ignore','pipe','pipe']});
server.stderr.on('data',d=>{errors+=d.toString();});
let checks=0;
function ok(condition,label){assert.ok(condition,label);checks++;console.log(`PASS ${label}`);}
class Client {
  cookie='';
  async request(route,values){
    const init={redirect:'manual',headers:{}};
    if(this.cookie)init.headers.cookie=this.cookie;
    if(values){init.method='POST';if(values instanceof FormData)init.body=values;else{const form=new URLSearchParams(); for(const [k,v] of Object.entries(values)){if(Array.isArray(v))v.forEach(item=>form.append(k,item));else form.append(k,v);}init.body=form;}}
    const response=await fetch(base+route,init);
    const cookie=response.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];
    const body=await response.text();return {status:response.status,headers:response.headers,body};
  }
  async csrf(route){const r=await this.request(route);const token=r.body.match(/name="csrf" value="([a-f0-9]+)"/)?.[1];assert.ok(token,`CSRF on ${route}: ${r.body.slice(0,200)}`);return token;}
}
const owner=new Client(),visitor=new Client();
try {
  for(let i=0;i<40;i++){try{await (await fetch(base)).arrayBuffer();break;}catch{await new Promise(r=>setTimeout(r,100));}}
  ok((await visitor.request('/')).status===200,'Uninstalled homepage renders');
  const setupCsrf=await owner.csrf('/kurulum');
  let r=await owner.request('/kurulum',{csrf:setupCsrf,token:'wrong',email:'owner@example.test',password,password_confirm:password});
  ok(r.body.includes('Kurulum anahtarı geçersiz'),'Setup requires a private token');
  r=await owner.request('/kurulum',{csrf:setupCsrf,token,email:'owner@example.test',password,password_confirm:password});
  ok(r.status===303 && r.headers.get('location')==='/yonetim','MySQL-compatible schema and initial data install');
  ok((await visitor.request('/kurulum')).status===303,'Installer locks after first admin');
  r=await visitor.request('/yonetim');ok(r.body.includes('Tekrar merhaba.') && !r.body.includes('stat-grid'),'Anonymous visitor cannot see admin dashboard');
  r=await visitor.request('/yonetim',{csrf:'bad',action:'save',table:'services',name:'bad'});ok(r.status===419,'Invalid CSRF rejected');
  const visitorToken=await visitor.csrf('/yonetim');
  r=await visitor.request('/yonetim',{csrf:visitorToken,action:'generate','services[]':['1'],'cities[]':['1']});ok(r.status===303,'Unauthenticated writes rejected');
  const loginToken=await owner.csrf('/yonetim');
  r=await owner.request('/yonetim',{csrf:loginToken,action:'login',email:'owner@example.test',password});ok(r.status===303,'Admin login succeeds');
  r=await owner.request('/yonetim');ok(r.body.includes('stat-grid'),'Authenticated dashboard renders');
  if(process.env.ARKESOFT_CAPTURE_ADMIN==='1'){
    const {captureAdmin}=await import('./admin-browser.mjs');await captureAdmin(owner.cookie,base);
  }
  const adminToken=await owner.csrf('/yonetim');
  const initialPages=((await owner.request('/yonetim?tab=pages')).body.match(/data-search-row/g)||[]).length;
  r=await owner.request('/yonetim?tab=generate',{csrf:adminToken,action:'generate','services[]':['1','3'],'cities[]':['1','2']});ok(r.status===303,'Programmatic generation creates draft pages');
  r=await owner.request('/yonetim?tab=pages');ok((r.body.match(/data-search-row/g)||[]).length===initialPages+4,'Service × city produces four pages');
  const targetRow=r.body.match(/<tr data-search-row[^>]*>[\s\S]*?<\/tr>/g).find(row=>row.includes('/cozumler/istanbul-web-tasarim'));
  const targetId=targetRow.match(/name="ids\[\]" value="(\d+)"/)[1];
  await owner.request('/yonetim?tab=generate',{csrf:adminToken,action:'generate','services[]':['1','3'],'cities[]':['1','2']});
  r=await owner.request('/yonetim?tab=pages');ok((r.body.match(/data-search-row/g)||[]).length===initialPages+4,'Repeated generation preserves existing pages');
  ok((await visitor.request('/cozumler/istanbul-web-tasarim')).status===404,'Draft hidden from public');
  ok((await visitor.request('/cozumler/istanbul-web-tasarim?onizleme=1')).status===404,'Preview requires admin authentication');
  r=await owner.request('/cozumler/istanbul-web-tasarim?onizleme=1');ok(r.status===200 && r.headers.get('x-robots-tag')?.includes('noindex'),'Authenticated preview has noindex');
  r=await visitor.request('/sitemap.xml');ok(!r.body.includes('istanbul-web-tasarim'),'Draft excluded from sitemap');
  await owner.request('/yonetim?tab=pages',{csrf:adminToken,action:'bulk','ids[]':[targetId],status:'published'});
  ok((await visitor.request('/cozumler/istanbul-web-tasarim')).status===404,'Unreviewed page blocked from bulk publishing');
  const edit=await owner.request('/yonetim?tab=pages&edit='+targetId);
  const decode=v=>v.replaceAll('&quot;','"').replaceAll('&#039;',"'").replaceAll('&lt;','<').replaceAll('&gt;','>').replaceAll('&amp;','&');
  const values={csrf:adminToken,action:'save',table:'pages',id:targetId,status:'published',reviewed:'1',unique_value:'İstanbul projesinde farklı ilçelerden gelen talepleri ayrı hizmet kapsamlarına yönlendiren bir form planlanır. İlçe seçiminin ardından ihtiyaç ve teslim kapsamı editör tarafından doğrulanır.'};
  for(const name of ['title','heading','faq_question'])values[name]=decode(edit.body.match(new RegExp(`name="${name}" value="([^"]*)"`))[1]);
  for(const name of ['description','intro','body','faq_answer'])values[name]=decode(edit.body.match(new RegExp(`<textarea name="${name}"[^>]*>([\\s\\S]*?)</textarea>`))[1]);
  r=await owner.request('/yonetim?tab=pages&edit='+targetId,values);ok(r.status===303,'Reviewed content publishes');
  r=await visitor.request('/cozumler/istanbul-web-tasarim');
  ok(r.status===200 && r.body.includes('<h1>İstanbul Web Tasarım</h1>'),'Published SEO route returns server-rendered H1');
  ok(r.body.includes('rel="canonical"') && r.body.includes('application/ld+json') && r.body.includes('name="description"'),'Canonical, meta and JSON-LD rendered');
  r=await visitor.request('/sitemap.xml');ok(r.body.includes('istanbul-web-tasarim') && !r.body.includes('ankara-seo'),'Sitemap contains only published pages');
  r=await visitor.request('/');ok(!r.body.includes('/yonetim') && !r.body.includes('Giriş yap'),'Public site contains no admin login link');
  ok((await visitor.request('/olmayan-sayfa')).status===404,'Missing page returns 404');
  ok((await visitor.request('/config.php')).status===403,'Config is inaccessible');
  ok((await visitor.request('/storage/error.log')).status===403,'Storage is inaccessible');
  const contactToken=await visitor.csrf('/iletisim');
  r=await visitor.request('/iletisim',{csrf:contactToken,name:'Test <script>alert(1)</script>',email:'lead@example.test',phone:'0555 555 55 55',service:'Web Tasarım',message:'Yeni şirketimiz için özgün bir kurumsal web sitesi istiyoruz.',consent:'1',website:''});
  ok(r.status===303,'Contact request accepted');
  r=await owner.request('/yonetim?tab=leads');ok(r.body.includes('lead@example.test'),'Lead persisted in admin inbox');
  ok(r.body.includes('&lt;script&gt;') && !r.body.includes('<script>alert(1)</script>'),'Stored XSS safely escaped');
  await owner.request('/yonetim?tab=pages',{csrf:adminToken,action:'bulk','ids[]':[targetId],status:'draft'});
  ok((await visitor.request('/cozumler/istanbul-web-tasarim')).status===404,'Unpublishing removes public access');
  r=await owner.request('/yonetim?tab=services',{csrf:adminToken,action:'delete',table:'services',id:'1'});ok(r.body.includes('Önce bu kayda bağlı'),'Service deletion blocked while pages reference it');
  const {checkCMS}=await import('./cms-v2.mjs');await checkCMS({owner,visitor,Client,token:adminToken,ok});
  const {checkStudio}=await import('./studio-v3.mjs');await checkStudio({owner,visitor,token:adminToken,ok});
  execFileSync('php',['-d','extension=pdo_sqlite','tests/scale-fixture.php'],{cwd:root,env:{...process.env,ARKESOFT_CONFIG:configFile}});
  r=await visitor.request('/sitemap.xml');ok(r.body.includes('<sitemapindex')&&r.body.includes('sitemap-2.xml'),'Large sitemap splits into index and chunks');
  r=await visitor.request('/sitemap-1.xml');ok((r.body.match(/<url>/g)||[]).length===1000,'Sitemap chunk bounded to 1000 URLs');
  r=await visitor.request('/sitemap-2.xml');ok((r.body.match(/<url>/g)||[]).length===5,'Final sitemap chunk includes remaining URLs');
  r=await owner.request('/yonetim?tab=pages');ok((r.body.match(/data-search-row/g)||[]).length===100,'Admin query bounded to 100 rows');
  r=await owner.request('/yonetim?tab=pages&q=scale-1004');ok((r.body.match(/data-search-row/g)||[]).length===1,'Server search finds records across all pages');
  r=await visitor.request('/cozumler?sayfa=2');ok(r.status===200&&r.body.includes('rel="canonical" href="'+base+'/cozumler?sayfa=2"'),'Paginated directory has its own canonical');
  ok((await visitor.request('/cozumler?sayfa=99999')).status===404,'Out-of-range directory cannot create infinite pages');
  r=await owner.request('/yonetim',{csrf:adminToken,action:'password',current_password:password,new_password:password+'new'});ok(r.status===303,'Password change verifies old password');
  await owner.request('/yonetim',{csrf:adminToken,action:'logout'});
  ok((await owner.request('/yonetim')).body.includes('Tekrar merhaba.'),'Logout revokes dashboard access');
  for(let i=0;i<9;i++)await visitor.request('/yonetim',{csrf:await visitor.csrf('/yonetim'),action:'login',email:'owner@example.test',password:'incorrect'});
  ok((await visitor.request('/yonetim')).body.includes('Çok fazla giriş denemesi'),'Login attempts are rate limited');
  for(const route of ['/hizmetler','/hizmetler/web-tasarim','/projeler','/hakkimizda','/iletisim','/gizlilik','/cozumler','/robots.txt'])ok((await visitor.request(route)).status===200,`${route} renders`);
  console.log(`\n${checks} integration checks passed.`);
} catch(error) { console.error(error);console.error(errors.slice(-3000));process.exitCode=1; }
finally {server.kill();}
