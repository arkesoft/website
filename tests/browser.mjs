import {writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const target=await fetch('http://127.0.0.1:9227/json/new?about:blank',{method:'PUT'}).then(r=>r.json());
const socket=new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
let next=1;const pending=new Map();
socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id && pending.has(data.id)){const {resolve,reject}=pending.get(data.id);pending.delete(data.id);data.error?reject(data.error):resolve(data.result);}};
function cdp(method,params={}){return new Promise((resolve,reject)=>{const id=next++;pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
async function navigate(url){await cdp('Page.navigate',{url});for(let i=0;i<60;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate('document.readyState === "complete"'))break;}}
try{
 await cdp('Page.enable');
 for(const [name,width,height,mobile] of [['desktop',1440,1000,false],['mobile',390,844,true]]){
  await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile});
  await navigate('http://127.0.0.1:8127/');
  const pictures=await evaluate(`(async()=>{const pictures=[...document.images];pictures.forEach(i=>i.loading='eager');await Promise.all(pictures.map(i=>i.decode().catch(()=>null)));return pictures.filter(i=>!i.naturalWidth).map(i=>i.src);})()`);
  assert.deepEqual(pictures,[],name+' images decode');
  if(!mobile){
   await evaluate('document.querySelector("[data-open-reel]").click()');
   await new Promise(r=>setTimeout(r,1200));
   const film=await evaluate('({open:document.querySelector("dialog").open,time:document.querySelector("[data-reel-video]").currentTime,error:document.querySelector("[data-reel-video]").error?.code})');
   assert.ok(film.open&&film.time>0&&!film.error,'Showreel opens and plays');
   await evaluate('document.querySelector("[data-close-reel]").click()');
   await new Promise(r=>setTimeout(r,500));
   assert.ok(await evaluate('!document.querySelector("[data-hero-video]").paused'),'Hero video resumes');
   await evaluate('document.querySelector("[data-pause-hero]").click()');
   assert.ok(await evaluate('document.querySelector("[data-hero-video]").paused'),'Hero pause control works');
  }
  const metrics=await evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth,links:[...document.querySelectorAll("a")].filter(a=>a.getAttribute("href")==="/yonetim").length})');
  console.log(name,metrics);assert.ok(metrics.scroll<=width,`${name} horizontal overflow`);assert.equal(metrics.links,0);
  if(!mobile){await evaluate('document.querySelector(".mega-trigger").click()');assert.ok(await evaluate('!document.querySelector(".mega-menu").hidden'),'Mega menu opens');await evaluate('document.querySelector(".mega-trigger").click()');}
  const dimensions=await cdp('Page.getLayoutMetrics');
  const screenshot=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height:dimensions.cssContentSize.height,scale:1}});
  await writeFile(`tests/${name}-full.png`,Buffer.from(screenshot.data,'base64'));
  if(mobile){await evaluate('document.querySelector(".agency-menu-toggle").click()');assert.ok(await evaluate('!document.querySelector("#agency-mobile").hidden'),'Mobile navigation opens');await evaluate('document.querySelector(".agency-menu-toggle").click()');assert.ok(await evaluate('document.querySelector("#agency-mobile").hidden'),'Mobile navigation closes');}
 }
 for(const route of ['/hizmetler/mobil-uygulama','/blog/programatik-seo-faydali-sayfalar','/iletisim','/projeler','/cozumler']){await navigate('http://127.0.0.1:8127'+route);const state=await evaluate('({scroll:document.documentElement.scrollWidth,title:document.title,h1:document.querySelectorAll("h1").length})');assert.ok(state.scroll<=390,route+' mobile overflow');assert.equal(state.h1,1,route+' single H1');console.log('PASS mobile detail '+route);}
 await cdp('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
 await navigate('http://127.0.0.1:8127/');
 assert.ok(await evaluate('document.querySelector("[data-hero-video]").paused&&!document.querySelector("[data-hero-video]").getAttribute("src")'),'Reduced motion does not download or autoplay hero film');
 console.log('PASS reduced-motion preference');
 console.log('PASS desktop/mobile layout and navigation');
}finally{socket.close();await fetch('http://127.0.0.1:9227/json/close/'+target.id);}
