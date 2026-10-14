import {readFile} from 'node:fs/promises';
const r=JSON.parse(await readFile('tests/reports/comprehensive.json','utf8')),seen=new Set();
for(const a of r.accessibility)for(const v of a.violations)for(const n of v.nodes){const key=v.id+'|'+n.summary;if(seen.has(key))continue;seen.add(key);console.log(a.width,a.route,v.id,n.target.join(' '),n.html,n.summary);}
