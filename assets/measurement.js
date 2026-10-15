// No cookies, persistent visitor IDs, message contents or contact details.
if (navigator.doNotTrack !== '1' && !navigator.globalPrivacyControl) {
  const send = (event, value) => {
    const body=JSON.stringify({event,path:location.pathname,device:matchMedia('(max-width:760px)').matches?'mobile':'desktop',...(value===undefined?{}:{value})});
    if (!navigator.sendBeacon('/olcum',new Blob([body],{type:'application/json'}))) fetch('/olcum',{method:'POST',body,headers:{'Content-Type':'application/json'},keepalive:true}).catch(()=>{});
  };
  document.addEventListener('click',event=>{
    const link=event.target.closest('a,button');if(!link)return;
    const explicit=link.dataset.contactEvent;
    if(explicit){send(explicit);return;}
    const href=link.getAttribute('href')||'';
    if(href.startsWith('tel:'))send('phone_click');else if(href.startsWith('https://wa.me/'))send('whatsapp_click');else if(href.startsWith('/iletisim'))send('quote_click');else if(link.hasAttribute('data-load-map'))send('map_click');
  });
  // The official library is served locally. Each metric ID contributes one final sample.
  if(document.querySelector('script[data-web-vitals="1"]')) import('./web-vitals.js').then(({onLCP,onINP,onCLS})=>{
    const values=new Map(),sent=new Set();
    const collect=metric=>values.set(metric.id,metric);
    onLCP(collect,{reportAllChanges:true});onINP(collect,{reportAllChanges:true});onCLS(collect,{reportAllChanges:true});
    const flush=()=>{for(const [id,metric] of values)if(!sent.has(id)){send(metric.name,metric.value);sent.add(id);}};
    // Library visibility handlers run first; the microtask includes their last updates.
    document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')queueMicrotask(flush);});
    addEventListener('pagehide',flush);
  }).catch(()=>{/* Click measurement still works if the optional library is unavailable. */});
}
