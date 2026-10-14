import {writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
export async function captureAdmin(cookie,base){
 const target=await fetch('http://127.0.0.1:9227/json/new?about:blank',{method:'PUT'}).then(r=>r.json());
 const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((res,rej)=>{ws.onopen=res;ws.onerror=rej;});
 let id=0;const pending=new Map();ws.onmessage=e=>{const d=JSON.parse(e.data);if(pending.has(d.id)){const p=pending.get(d.id);pending.delete(d.id);d.error?p.reject(d.error):p.resolve(d.result);}};
 const cdp=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
 const evaluate=async expression=>(await cdp('Runtime.evaluate',{expression,returnByValue:true})).result.value;
 try{
  await cdp('Page.enable');await cdp('Network.enable');
  const split=cookie.indexOf('=');await cdp('Network.setCookie',{name:cookie.slice(0,split),value:cookie.slice(split+1),url:base,path:'/'});
  for(const [name,width,height,mobile,route] of [['admin',1440,1000,false,'/yonetim'],['admin-generator',1440,1000,false,'/yonetim?tab=generate'],['admin-catalog',1440,1000,false,'/yonetim?tab=locations-import&level=neighborhood&province=34'],['admin-films',1440,1000,false,'/yonetim?tab=films'],['admin-studio',1440,1000,false,'/yonetim?tab=studio'],['admin-mobile',390,844,true,'/yonetim'],['admin-films-mobile',390,844,true,'/yonetim?tab=films'],['admin-catalog-mobile',390,844,true,'/yonetim?tab=locations-import&level=neighborhood&province=34'],['admin-editor-mobile',390,844,true,'/yonetim?tab=services&edit=1']]){
   await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile});await cdp('Page.navigate',{url:base+route});
   for(let i=0;i<40;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate('document.readyState === "complete" && !!document.querySelector(".admin-sidebar")'))break;}
   assert.ok(await evaluate('!!document.querySelector(".admin-sidebar")'),'Admin browser is authenticated');
   assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),`${name} has no horizontal overflow`);
   const result=await cdp('Page.captureScreenshot',{format:'png'});await writeFile(`tests/${name}.png`,Buffer.from(result.data,'base64'));
   console.log(`PASS ${name} browser layout`);
  }
 }finally{ws.close();await fetch('http://127.0.0.1:9227/json/close/'+target.id);}
}
