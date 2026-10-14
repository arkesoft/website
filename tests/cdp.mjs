import {writeFile} from 'node:fs/promises';
export async function browser(){
 const target=await fetch('http://127.0.0.1:9227/json/new?about:blank',{method:'PUT'}).then(r=>r.json());
 const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
 let id=0;const pending=new Map(),events=[];
 ws.onmessage=e=>{const d=JSON.parse(e.data);if(d.id&&pending.has(d.id)){const p=pending.get(d.id);pending.delete(d.id);d.error?p.reject(Error(JSON.stringify(d.error))):p.resolve(d.result);}else events.push(d);};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
 const evaluate=async(expression)=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 async function wait(expression,timeout=10000){const end=Date.now()+timeout;while(Date.now()<end){try{if(await evaluate(expression))return;}catch{}await new Promise(r=>setTimeout(r,75));}throw Error('Timed out: '+expression);}
 async function go(url){await send('Page.navigate',{url});await wait(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`);}
 async function viewport(width=1440,height=1000){await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<700});}
 async function screenshot(name,full=false){await evaluate(`Promise.all([...document.images].map(i=>{i.loading='eager';return i.decode().catch(()=>null)}))`);const p={format:'png',captureBeyondViewport:full};if(full){const m=await send('Page.getLayoutMetrics');p.clip={x:0,y:0,width:m.cssContentSize.width,height:m.cssContentSize.height,scale:1};}const r=await send('Page.captureScreenshot',p);await writeFile(name,Buffer.from(r.data,'base64'));}
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
 return {target,send,evaluate,wait,go,viewport,screenshot,events,close:async()=>{ws.close();await fetch('http://127.0.0.1:9227/json/close/'+target.id);}};
}
