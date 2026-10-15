import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {mkdir,writeFile,readFile} from 'node:fs/promises';
import net from 'node:net';
import tls from 'node:tls';
import path from 'node:path';

const root=process.cwd(),dir=path.join(root,'tests/runtime/smtp-'+Date.now());await mkdir(dir,{recursive:true});
const openssl=process.env.ARKESOFT_TEST_OPENSSL||(process.platform==='win32'?'C:/xampp/apache/bin/openssl.exe':'openssl');
const certConfig=path.join(dir,'cert.cnf');
await writeFile(certConfig,'[req]\ndistinguished_name=dn\nprompt=no\n[dn]\nCN=ARKESOFT test CA\n[ca_ext]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n[leaf_ext]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=IP:127.0.0.1\n');
const ca=path.join(dir,'ca.pem'),key=path.join(dir,'server.key'),cert=path.join(dir,'server.pem');
const ssl=args=>execFileSync(openssl,args,{cwd:dir,stdio:'pipe'});
ssl(['req','-x509','-newkey','rsa:2048','-nodes','-days','1','-config',certConfig,'-extensions','ca_ext','-keyout','ca.key','-out',ca]);
ssl(['req','-new','-newkey','rsa:2048','-nodes','-config',certConfig,'-subj','/CN=127.0.0.1','-keyout',key,'-out','server.csr']);
ssl(['x509','-req','-in','server.csr','-CA',ca,'-CAkey','ca.key','-CAcreateserial','-days','1','-extfile',certConfig,'-extensions','leaf_ext','-out',cert]);
const tlsOptions={key:await readFile(key),cert:await readFile(cert),minVersion:'TLSv1.2'};
const runner=path.join(dir,'send.php');
await writeFile(runner,`<?php
require getcwd().'/app/environment.php';require getcwd().'/app/smtp.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
try{(new ArkesoftSmtp())->send($input['config'],'recipient@example.test','Yeni talep: İstanbul',$input['body'],'visitor@example.test','<lead-1@arkesoft.test>');echo json_encode(['ok'=>true]);}
catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
`);
let checks=0;const ok=(condition,label)=>{assert.ok(condition,label);checks++;console.log('PASS '+label);};
const body='İstanbul, görüşelim.\n.\nQUIT\nŞifre yok.';
async function scenario({mode='ssl',plain=false,fail='',trust=true,name='ARKESOFT, Studio'}={}){
 const state={commands:[],data:'',authenticated:false,encryptedAuth:false};const sockets=new Set();
 function attach(socket,encrypted,greet=true){
  sockets.add(socket);socket.on('error',()=>{});socket.on('close',()=>sockets.delete(socket));
  let pending='',authStage=0,inData=false;const reply=s=>socket.write(s+'\r\n');
  if(greet)reply('220 localhost test SMTP');
  const onData=chunk=>{pending+=chunk.toString();let end;
   while((end=pending.indexOf('\r\n'))>=0){const line=pending.slice(0,end);pending=pending.slice(end+2);
    if(inData){if(line==='.'){
      if(fail==='data')reply('550 message refused');else socket.end('250 message accepted\r\n');inData=false;
     }else state.data+=line+'\r\n';continue;}
    if(authStage){if(authStage===1){assert.equal(Buffer.from(line,'base64').toString(),'test-user');authStage=2;reply('334 UGFzc3dvcmQ6');}
     else {assert.equal(Buffer.from(line,'base64').toString(),'secret-marker');authStage=0;if(fail==='auth')reply('535 secret-marker refused');else{state.authenticated=true;reply('235 authenticated');}}continue;}
    state.commands.push(line.split(' ')[0]);
    if(line.startsWith('EHLO')){if(!encrypted){reply(fail==='no-starttls'?'250 AUTH LOGIN':'250-test\r\n250 STARTTLS');}else reply('250-test\r\n250 AUTH '+(plain?'PLAIN':'LOGIN'));}
    else if(line==='STARTTLS'){reply('220 upgrade');socket.removeListener('data',onData);const secure=new tls.TLSSocket(socket,{isServer:true,secureContext:tls.createSecureContext(tlsOptions)});attach(secure,true,false);return;}
    else if(line==='AUTH LOGIN'){state.encryptedAuth=encrypted;authStage=1;reply('334 VXNlcm5hbWU6');}
    else if(line.startsWith('AUTH PLAIN ')){state.encryptedAuth=encrypted;assert.equal(Buffer.from(line.slice(11),'base64').toString(),'\0test-user\0secret-marker');state.authenticated=true;reply('235 authenticated');}
    else if(line.startsWith('MAIL FROM:')){assert.equal(line,'MAIL FROM:<sender@example.test>');reply('250 sender');}
    else if(line.startsWith('RCPT TO:'))reply(fail==='recipient'?'550 recipient refused':'250 recipient');
    else if(line==='DATA'){inData=true;reply('354 send data');}
    else if(line==='QUIT')socket.end('221 bye\r\n');else reply('500 unknown command');
   }
  };socket.on('data',onData);
 }
 const server=mode==='ssl'?tls.createServer(tlsOptions,s=>attach(s,true)):net.createServer(s=>attach(s,false));
 server.on('tlsClientError',()=>{});await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(0,'127.0.0.1',resolve);});
 const config={mail_transport:'smtp',mail_from:'sender@example.test',mail_from_name:name,smtp_host:'127.0.0.1',smtp_port:server.address().port,smtp_encryption:mode,smtp_user:'test-user',smtp_pass:'secret-marker',...(trust?{smtp_ca_file:ca}:{})};
 try{
  const child=spawn('php',[runner],{cwd:root,stdio:['pipe','pipe','pipe']});let output='',errors='';
  child.stdout.on('data',d=>output+=d);child.stderr.on('data',d=>errors+=d);child.stdin.end(JSON.stringify({config,body}));
  const timeout=setTimeout(()=>child.kill(),15000);
  const code=await new Promise((resolve,reject)=>{child.on('error',reject);child.on('close',resolve);});clearTimeout(timeout);
  assert.equal(code,0,'SMTP fixture process exited');assert.equal(errors,'','No warning or credential output');
  return {...JSON.parse(output),state};
 }finally{for(const socket of sockets)socket.destroy();await new Promise(resolve=>server.close(resolve));}
}
const implicit=await scenario();ok(implicit.ok&&implicit.state.authenticated&&implicit.state.encryptedAuth,'Implicit TLS authenticates only after certificate verification');
ok(implicit.state.data.includes('From: "ARKESOFT, Studio" <sender@example.test>'),'ASCII display name safely quoted');
ok(implicit.state.data.includes('Reply-To: <visitor@example.test>')&&implicit.state.data.includes('Message-ID: <lead-1@arkesoft.test>'),'Reply-To and stable message ID');
const encoded=implicit.state.data.split('\r\n\r\n')[1];ok(Buffer.from(encoded.replaceAll('\r\n',''),'base64').toString()===body.replaceAll('\n','\r\n'),'UTF-8 body and DATA terminator text preserved');
ok(implicit.ok,'Server closure after acceptance does not trigger retry');
const starttls=await scenario({mode:'tls',name:'ARKESOFT Tasarım'});ok(starttls.ok&&starttls.state.encryptedAuth&&starttls.state.commands.filter(x=>x==='EHLO').length===2,'STARTTLS upgrades then authenticates');
ok(/^From: .*\=\?UTF-8\?B\?/mi.test(starttls.state.data),'UTF-8 display name encoded');
const plain=await scenario({plain:true});ok(plain.ok&&plain.state.encryptedAuth,'AUTH PLAIN operates inside verified TLS');
const untrusted=await scenario({trust:false});ok(!untrusted.ok&&!untrusted.state.authenticated,'Untrusted TLS certificate fails closed');
const noTls=await scenario({mode:'tls',fail:'no-starttls'});ok(!noTls.ok&&!noTls.state.commands.includes('AUTH'),'No credential fallback when STARTTLS unavailable');
const authFailure=await scenario({fail:'auth'});ok(!authFailure.ok&&authFailure.error.includes('535')&&!authFailure.error.includes('secret-marker'),'Authentication failure reports code without server secrets');
const rcptFailure=await scenario({fail:'recipient'});ok(!rcptFailure.ok&&rcptFailure.error.includes('550')&&!rcptFailure.state.data,'Rejected recipient cannot reach DATA');
const dataFailure=await scenario({fail:'data'});ok(!dataFailure.ok&&dataFailure.error.includes('550'),'DATA rejection is not treated as delivery');
console.log(checks+' SMTP checks passed. No external server contacted.');
