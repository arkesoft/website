import {fixture} from './harness.mjs';import {mkdir,mkdtemp,cp,readFile,writeFile} from 'node:fs/promises';import {spawn,execFileSync} from 'node:child_process';import os from 'node:os';import path from 'node:path';
if(!process.env.ARKESOFT_TEST_MYSQL_PORT)throw Error('MariaDB test port required');
const f=await fixture(8140),root=(process.env.ARKESOFT_APACHE_TEMP?await mkdtemp(path.join(os.tmpdir(),'arkesoft-apache-')):f.runtime).replaceAll('\\','/'),site=root+'/apache-site',nativeRoot=process.env.ARKESOFT_APACHE_TEMP?root:f.runtime.replace(f.root,process.env.ARKESOFT_SHORT_ROOT||f.root).replaceAll('\\','/');let server,logs='';const checks=[];
const check=(pass,label)=>{checks.push({pass:!!pass,label});console.log((pass?'PASS ':'FAIL ')+label);};
try{
 await mkdir(site,{recursive:true});for(const name of ['app','assets','index.php','.htaccess','config.example.php'])await cp(f.root+'/'+name,site+'/'+name,{recursive:true});for(const name of ['storage','uploads']){await mkdir(site+'/'+name);await cp(f.root+'/'+name+'/.htaccess',site+'/'+name+'/.htaccess');}
 const config=(await readFile(f.config,'utf8')).replaceAll(f.base,'http://127.0.0.1:8137');await writeFile(site+'/config.php',config);
 await writeFile(site+'/uploads/attack.php','<?php echo "EXECUTED_UPLOAD";');await writeFile(site+'/uploads/attack.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
 const modules=['authz_core','authz_host','dir','mime','rewrite','headers','log_config','env','access_compat'];
 const conf=`ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8137
ServerName 127.0.0.1
PidFile "${root}/httpd.pid"
ErrorLog "${root}/httpd-error.log"
LogLevel warn
${modules.map(m=>`LoadModule ${m}_module modules/mod_${m}.so`).join('\n')}
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig "C:/xampp/apache/conf/mime.types"
AddType image/webp .webp
AddType video/webm .webm
DocumentRoot "${site}"
<Directory "${site}">
 AllowOverride All
 Options FollowSymLinks
 Require all granted
</Directory>
<FilesMatch "\\.php$">
 SetHandler application/x-httpd-php
</FilesMatch>
`;
 await writeFile(root+'/httpd.conf',conf.replaceAll(root,nativeRoot));execFileSync('C:/xampp/apache/bin/httpd.exe',['-t','-f',nativeRoot+'/httpd.conf']);
 server=spawn('C:/xampp/apache/bin/httpd.exe',['-X','-f',nativeRoot+'/httpd.conf'],{stdio:['ignore','pipe','pipe']});server.stderr.on('data',d=>logs+=d);
 const base='http://127.0.0.1:8137';for(let i=0;i<80;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
 for(const route of ['/','/hizmetler/web-tasarim','/blog','/yonetim']){const r=await fetch(base+route);check(r.status===200,'Apache/PHP/MySQL route '+route);}
 for(const route of ['/config.php','/config.example.php','/app/bootstrap.php','/storage/error.log','/uploads/attack.php','/uploads/attack.svg','/.htaccess','/app/data/provinces.json','/uploads/']){const r=await fetch(base+route);check([403,404].includes(r.status),'Apache denies '+route);const body=await r.text();check(!body.includes('EXECUTED_UPLOAD'),'No uploaded execution/source disclosure '+route);}
 const film=await fetch(base+'/assets/showreel-v3.webm',{headers:{Range:'bytes=0-31'}});check(film.status===206&&film.headers.get('content-type')?.includes('video/webm')&&(await film.arrayBuffer()).byteLength===32,'Apache WebM MIME and byte-range playback');
 const image=await fetch(base+'/assets/coffee-v3.webp');check(image.status===200&&image.headers.get('content-type')?.includes('image/webp')&&image.headers.get('x-content-type-options')==='nosniff','Apache WebP MIME and nosniff');
 const trailing=await fetch(base+'/hizmetler/',{redirect:'manual'});check(trailing.status===301&&trailing.headers.get('location')==='/hizmetler','Apache canonical trailing-slash redirect');
 console.log('APACHE SUMMARY',checks.length,checks.filter(c=>!c.pass).length+' failed');
}catch(e){console.error(e,logs);checks.push({pass:false,label:e.message});process.exitCode=1;}finally{server?.kill();f.stop();await writeFile('tests/reports/apache-security.json',JSON.stringify({date:new Date().toISOString(),checks,limitations:['Local Apache HTTP test; Hostinger LiteSpeed, SSL, DNS and hosting account were not accessed.']},null,2));if(checks.some(c=>!c.pass))process.exitCode=1;}
