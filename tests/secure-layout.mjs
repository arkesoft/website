import {fixture} from './harness.mjs';
import {readFile,writeFile,copyFile,readdir,stat} from 'node:fs/promises';
import {spawn,execFileSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import path from 'node:path';
import assert from 'node:assert/strict';
const f=await fixture(8141),target=path.join(f.root,'tests/runtime/deploy-'+randomBytes(6).toString('hex'));
const priv=path.join(target,'arkesoft-private'),pub=path.join(target,'public_html'),base='http://127.0.0.1:8142';let server,checks=0,cookie='';
const ok=(value,label)=>{assert.ok(value,label);checks++;console.log('PASS '+label);};
const exists=async p=>!!(await stat(p).catch(()=>null));
const build=()=>execFileSync('php',['tools/build-server-tree.php',target],{cwd:f.root,stdio:'pipe'});
async function request(route,values){const headers={};if(cookie)headers.cookie=cookie;const r=await fetch(base+route,{redirect:'manual',headers,...(values?{method:'POST',body:values instanceof FormData?values:new URLSearchParams(values)}:{})});if(r.headers.get('set-cookie'))cookie=r.headers.get('set-cookie').split(';')[0];return {status:r.status,body:await r.text(),headers:r.headers};}
const token=body=>body.match(/name="csrf" value="([a-f0-9]+)"/)?.[1];
try{
 build();const configBefore=await readFile(path.join(priv,'config.php'),'utf8'),envBefore=await readFile(path.join(priv,'.env'),'utf8');build();
 ok(configBefore===await readFile(path.join(priv,'config.php'),'utf8')&&envBefore===await readFile(path.join(priv,'.env'),'utf8'),'Regeneration preserves private config and env');
 ok(/'setup_token' => '[a-f0-9]{64}'/.test(configBefore),'Fresh server config has a random setup token');
 for(const name of ['config.php','.env','app','storage','tools','.git','tests','arkesoft-private'])ok(!await exists(path.join(pub,name)),'Private material absent from document root: '+name);
 ok((await readdir(pub)).sort().join(',')==='.htaccess,assets,index.php,uploads','Only public entrypoint and assets are in document root');
 ok(await exists(path.join(priv,'app/bootstrap.php'))&&await exists(path.join(priv,'tools/mail-worker.php')),'Application and maintenance commands live outside web root');
 await copyFile(path.join(f.runtime,'test.sqlite'),path.join(priv,'storage/test.sqlite'));
 let config=(await readFile(f.config,'utf8')).replaceAll(f.base,base).replaceAll(f.runtime.replaceAll('\\','/'),path.join(priv,'storage').replaceAll('\\','/'));
 await writeFile(path.join(priv,'config.php'),config+'\n// PRIVATE-CONFIG-MARKER\n');
 await writeFile(path.join(priv,'.env'),'# PRIVATE-ENV-MARKER\n');await writeFile(path.join(priv,'storage/error.log'),'PRIVATE-LOG-MARKER');
 const router=path.join(target,'test-router.php');const phpQuote=s=>"'"+s.replaceAll('\\','/').replaceAll("'","\\'")+"'";
 await writeFile(router,`<?php $public=${phpQuote(pub)};$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');if(preg_match('~^/(assets|uploads)/[a-zA-Z0-9._-]+\\.(css|js|svg|png|webp|jpg|webm|mp4)$~D',$path)&&is_file($public.$path))return false;require $public.'/index.php';`);
 server=spawn('php',['-d','extension=pdo_sqlite','-S','127.0.0.1:8142','-t',pub,router],{cwd:target,env:{...process.env,ARKESOFT_CONFIG:'',ARKESOFT_ENV:''},stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',d=>logs+=d);
 for(let i=0;i<70;i++){try{await (await fetch(base)).arrayBuffer();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 for(const route of ['/','/blog','/iletisim','/robots.txt','/sitemap.xml','/assets/branding.css','/assets/arkesoft-logo-transparent.png'])ok((await request(route)).status===200,'Split deployment serves '+route);
 for(const route of ['/config.php','/.env','/app/bootstrap.php','/arkesoft-private/config.php','/arkesoft-private/.env','/storage/error.log','/tools/mail-worker.php','/../arkesoft-private/config.php']){const r=await request(route);ok([403,404].includes(r.status)&&!r.body.includes('PRIVATE-'),'Private request blocked: '+route);}
 const login=await request('/yonetim');await request('/yonetim',{csrf:token(login.body),action:'login',email:'owner@example.test',password:f.password});const admin=await request('/yonetim?tab=media');
 ok(admin.status===200&&admin.body.includes('name="action" value="upload"'),'Admin authenticates with private database configuration');
 const upload=new FormData();upload.set('csrf',token(admin.body));upload.set('action','upload');upload.set('alt','Secure deployment media');upload.set('image',new Blob([await readFile(path.join(pub,'assets/coffee-v3.webp'))],{type:'image/webp'}),'example.webp');
 ok((await request('/yonetim',upload)).status===303,'Media upload succeeds in split deployment');
 const files=(await readdir(path.join(pub,'uploads'))).filter(n=>n.endsWith('.webp'));ok(files.length>1&&!await exists(path.join(priv,'uploads')),'Uploaded image and responsive variants are written only to public uploads');
 ok((await request('/uploads/'+files[0])).status===200,'Uploaded media is publicly served');
 const home=await request('/');ok(home.body.includes('arkesoft-logo-transparent.png')&&home.body.includes('brand-logo'),'Transparent brand is used by deployed pages');
 ok(!/Fatal error|Uncaught|Warning:/.test(logs),'No deployment PHP runtime errors');
 console.log(checks+' secure-layout checks passed.');
}finally{server?.kill();f.stop();}
