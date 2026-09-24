import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const pages = fs.readdirSync(root).filter(file => file.endsWith('.html'));
const routes = JSON.parse(fs.readFileSync(path.join(root, 'routes.json'), 'utf8'));
const englishRoutes = JSON.parse(fs.readFileSync(path.join(root, 'routes-en.json'), 'utf8'));
pages.push(...Object.values(englishRoutes).map(route => route.slice(1) + '/index.html'));
const routeFiles = Object.fromEntries(Object.entries(routes).map(([file, route]) => [route, file]));
Object.assign(routeFiles, Object.fromEntries(Object.values(englishRoutes).map(route => [route, route.slice(1) + '/index.html'])));
const archived = /(?:city-(?:film|loop|poster)|interior\.jpg|fashion\.jpg|laptop\.jpg|studio\.jpg|watch\.jpg)/;
let links = 0;
for (const page of pages) {
  const html = fs.readFileSync(path.join(root, page), 'utf8');
  assert.equal((html.match(/<h1(?:\s|>)/g) || []).length, 1, `${page}: exactly one h1`);
  for (const tag of ['h1','h2','h3','section','figure']) assert.equal((html.match(new RegExp(`<${tag}(?:\\s|>)`,'g'))||[]).length,(html.match(new RegExp(`</${tag}>`,'g'))||[]).length,`${page}: unbalanced ${tag}`);
  assert(!archived.test(html), `${page}: archived stock referenced`);
  assert(html.includes('name="description"'), `${page}: missing description`);
  const ids = [...html.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]);
  assert.equal(ids.length, new Set(ids).size, `${page}: duplicate ids`);
  for (const [, value] of html.matchAll(/\b(?:href|src|data-src|data-fallback|data-film-src)="([^"]+)"/g)) {
    if (/^(?:https?:|mailto:|tel:|data:|blob:)/.test(value)) continue;
    const url = new URL(value, `https://local.test/${page}`);
    const pathname = decodeURIComponent(url.pathname);
    const target = path.join(root, routeFiles[pathname] || pathname);
    assert(fs.existsSync(target), `${page}: missing ${value}`);
    if (url.hash && target.endsWith('.html')) {
      const other = fs.readFileSync(target, 'utf8');
      assert(other.includes(`id="${decodeURIComponent(url.hash.slice(1))}"`), `${page}: missing anchor ${value}`);
    }
    links++;
  }
}
const context = { window: {} };
vm.createContext(context);
for (const language of ['tr', 'en']) vm.runInContext(fs.readFileSync(path.join(root, 'locales', language + '.js'), 'utf8'), context);
const { tr, en } = context.window.ARKESOFT_LOCALES;
assert.deepEqual(Object.keys(tr).sort(), Object.keys(en).sort(), 'Locale keys differ');
for (const key of Object.keys(tr)) assert(en[key] && tr[key], `Empty translation: ${key}`);
for (const file of ['site.js','experience.js','i18n.js','preferences.js']) {
  const source = fs.readFileSync(path.join(root,file),'utf8');
  new vm.Script(source, { filename: file });
  assert(!/api\.country\.is/.test(source), 'External geolocation service must not be restored');
}
const handler = (await import('../api/country.mjs')).default;
for (const country of ['TR', 'DE', 'US', 'XX', '']) {
  let data;
  const response = { setHeader(){}, status(){return this}, json(value){data=value} };
  handler({headers:{'x-vercel-ip-country':country}},response);
  assert.equal(data.country, ['TR','DE','US'].includes(country)?country:null);
}
console.log(`PASS: ${pages.length} pages, ${links} local references, ${Object.keys(tr).length} bilingual strings, JS syntax and country adapter.`);
