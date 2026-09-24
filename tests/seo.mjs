import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createPreviewServer } from '../tools/server.mjs';

const read = file => fs.readFileSync(new URL('../' + file, import.meta.url), 'utf8');
const routes = JSON.parse(read('routes.json'));
const englishRoutes = JSON.parse(read('routes-en.json'));
const allRoutes = { ...routes, ...Object.fromEntries(Object.values(englishRoutes).map(route => [route.slice(1) + '/index.html', route])) };
const config = JSON.parse(read('vercel.json'));
const sitemap = [...read('sitemap.xml').matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => match[1]);
const canonicals = [];
const titles = new Set();
for (const [file, route] of Object.entries(allRoutes)) {
  const html = read(file);
  const canonical = html.match(/<link rel="canonical" href="([^"]+)"/)[1];
  const title = html.match(/<title>([^<]+)<\/title>/)[1];
  assert.equal(new URL(canonical).pathname, route, `${file}: canonical path`);
  assert.equal(new URL(canonical).search, '', `${file}: canonical query`);
  assert(html.includes(`<html lang="${route.startsWith('/en/') ? 'en' : 'tr'}"`), `${file}: document language`);
  const originalFile = Object.keys(routes).find(source => routes[source] === route || englishRoutes[source] === route);
  for (const [language, alternate] of [['tr', routes[originalFile]], ['en', englishRoutes[originalFile]]]) {
    assert(html.includes(`<link rel="alternate" hreflang="${language}" href="${new URL(alternate, canonical)}">`), `${file}: reciprocal ${language} alternate`);
  }
  assert(!titles.has(title), `${file}: duplicate title`);
  titles.add(title);
  assert(html.includes(`<meta property="og:url" content="${canonical}">`), `${file}: social URL`);
  assert(html.includes('content="index, follow, max-image-preview:large"'), `${file}: indexable`);
  const schemas = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)]
    .flatMap(match => { const data = JSON.parse(match[1]); return data['@graph'] || [data]; });
  assert(schemas.some(data => data['@id'] === canonical + '#webpage' && data.url === canonical), `${file}: page schema`);
  for (const [, href] of html.matchAll(/<a\b[^>]*\bhref="([^"]+)"/g)) {
    const url = new URL(href, canonical);
    if (url.origin === new URL(canonical).origin) {
      assert(!url.pathname.endsWith('.html'), `${file}: old link ${href}`);
      assert(!url.searchParams.has('theme') && !url.searchParams.has('lang'), `${file}: preference in link`);
    }
  }
  assert.equal(read(route.slice(1) + '/index.html'), html, `${route}: stale generated copy; run npm run build`);
  canonicals.push(canonical);
}
assert.deepEqual(sitemap.sort(), canonicals.sort(), 'Sitemap must contain every canonical once');
assert(read('robots.txt').includes(`Sitemap: ${new URL('/sitemap.xml', canonicals[0])}`));
assert(read('404.html').includes('<meta name="robots" content="noindex">'));
assert(!read('404.html').includes('rel="canonical"'));

const server = createPreviewServer();
await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
const origin = `http://127.0.0.1:${server.address().port}`;
try {
  const request = pathname => fetch(origin + pathname, { redirect: 'manual' });
  for (const [file, route] of Object.entries(allRoutes)) {
    const response = await request(route);
    assert.equal(response.status, 200, `${route}: HTTP status`);
    assert.equal(await response.text(), read(file), `${route}: wrong page`);
    const slash = await request(route + '/?filter=web');
    assert.equal(slash.status, 301);
    assert.equal(slash.headers.get('location'), route + '?filter=web');
  }
  for (const { source, destination } of config.redirects) {
    const response = await request(source + '?lang=en&theme=light&filter=web');
    assert.equal(response.status, 301, `${source}: permanent redirect`);
    assert.equal(response.headers.get('location'), destination + '?lang=en&theme=light&filter=web');
    const target = await request(destination);
    assert.equal(target.status, 200, `${source}: redirect loop or broken target`);
    await target.arrayBuffer();
  }
  for (const pathname of ['/missing-page', '/missing-page.html', '/missing/deep/path', '/.git/config']) {
    const response = await request(pathname);
    assert.equal(response.status, 404, `${pathname}: must not be a soft 404`);
    assert((await response.text()).includes('content="noindex"'));
  }
  const invalid = await request('/%ZZ');
  assert.equal(invalid.status, 400);
  const head = await fetch(origin + '/anasayfa', { method: 'HEAD' });
  assert.equal(head.status, 200);
  assert.equal(await head.text(), '');
  console.log(`PASS: ${canonicals.length} canonical pages, sitemap, structured data, generated copies, ${config.redirects.length} permanent redirects, query preservation and real 404s.`);
} finally {
  server.closeAllConnections();
  await new Promise(resolve => server.close(resolve));
}
