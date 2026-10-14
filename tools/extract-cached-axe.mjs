import {readdir,readFile,mkdir,writeFile} from 'node:fs/promises';
import path from 'node:path';import {execFileSync} from 'node:child_process';
const cache='C:/Users/metin/AppData/Local/npm-cache/_cacache';
async function* files(dir){for(const e of await readdir(dir,{withFileTypes:true})){const p=path.join(dir,e.name);if(e.isDirectory())yield*files(p);else yield p;}}
let entry;
for await(const p of files(cache+'/index-v5')){const data=await readFile(p,'utf8');if(!data.includes('axe-core-4.12.1.tgz'))continue;for(const line of data.split('\n')){try{const value=JSON.parse(line.slice(line.indexOf('\t')+1));if(value.key==='make-fetch-happen:request-cache:https://registry.npmjs.org/axe-core/-/axe-core-4.12.1.tgz'&&value.integrity)entry=value;}catch{}}}
if(!entry)throw Error('Cached axe archive unavailable');
const [algorithm,hash]=entry.integrity.split('-'),hex=Buffer.from(hash,'base64').toString('hex');
const data=await readFile(`${cache}/content-v2/${algorithm}/${hex.slice(0,2)}/${hex.slice(2,4)}/${hex.slice(4)}`);
await mkdir('tests/tooling',{recursive:true});await writeFile('tests/tooling/axe-core.tgz',data);
execFileSync('tar',['-xzf','tests/tooling/axe-core.tgz','-C','tests/tooling']);console.log('axe-core 4.12.1 extracted from local npm cache, no network request');
