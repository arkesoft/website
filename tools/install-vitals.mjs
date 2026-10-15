// Pin and verify the official npm archive; no npm lifecycle scripts are executed.
import {createHash} from 'node:crypto';
import {gunzipSync} from 'node:zlib';
import {writeFile} from 'node:fs/promises';
import {existsSync} from 'node:fs';
const assetRoot=existsSync(new URL('../deployment.php',import.meta.url))?'../../public_html/assets/':'../assets/';
const version='5.1.0';
const get=async url=>{const r=await fetch(url,{signal:AbortSignal.timeout(20000)});if(!r.ok)throw Error(`HTTP ${r.status}: ${url}`);return r;};
const metadata=await (await get(`https://registry.npmjs.org/web-vitals/${version}`)).json();
if(metadata.name!=='web-vitals'||metadata.version!==version||!metadata.dist.tarball.startsWith('https://registry.npmjs.org/web-vitals/'))throw Error('Unexpected package');
const archive=Buffer.from(await(await get(metadata.dist.tarball)).arrayBuffer());
const [algorithm,expected]=metadata.dist.integrity.split('-');
if(algorithm!=='sha512'||createHash('sha512').update(archive).digest('base64')!==expected)throw Error('Package integrity mismatch');
const tar=gunzipSync(archive),files=new Map();
for(let offset=0;offset+512<=tar.length;){const header=tar.subarray(offset,offset+512);const name=header.subarray(0,100).toString().split('\0')[0];if(!name)break;const size=parseInt(header.subarray(124,136).toString().replace(/\0/g,'').trim(),8);if(!Number.isFinite(size)||size<0)throw Error('Invalid archive');if(['package/dist/web-vitals.js','package/LICENSE'].includes(name))files.set(name,tar.subarray(offset+512,offset+512+size));offset+=512+Math.ceil(size/512)*512;}
if(files.size!==2)throw Error('Missing library or license');
await writeFile(new URL(assetRoot+'web-vitals.js',import.meta.url),files.get('package/dist/web-vitals.js'));
await writeFile(new URL(assetRoot+'web-vitals-LICENSE.txt',import.meta.url),files.get('package/LICENSE'));
console.log(`Installed GoogleChrome/web-vitals ${version}; sha512 verified; Apache-2.0 license included.`);
