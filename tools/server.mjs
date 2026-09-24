/* Dependency-free local preview with real permanent redirects and 404 responses. */
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const config = JSON.parse(fs.readFileSync(path.join(root, 'vercel.json'), 'utf8'));
const mime = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.xml': 'application/xml', '.txt': 'text/plain', '.svg': 'image/svg+xml', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.png': 'image/png', '.webm': 'video/webm', '.mp4': 'video/mp4', '.woff2': 'font/woff2' };
export function createPreviewServer() {
  return http.createServer((req, res) => {
    let url;
    try { url = new URL(req.url, 'http://localhost'); decodeURIComponent(url.pathname); }
    catch { res.writeHead(400).end(); return; }
    for (const {key, value} of config.headers[0].headers) res.setHeader(key, value);
    const withoutSlash = url.pathname.replace(/\/$/, '') || '/';
    const redirect = config.redirects.find(item => item.source === url.pathname || item.source === withoutSlash);
    const rewrite = config.rewrites.find(item => item.source === withoutSlash);
    if (redirect || (rewrite && url.pathname !== withoutSlash)) {
      res.writeHead(301, { Location: (redirect?.destination || withoutSlash) + url.search }).end(); return;
    }
    if (url.pathname === '/api/country') {
      res.writeHead(200, {'Content-Type': 'application/json', 'Cache-Control': 'no-store'}).end('{"country":null}'); return;
    }
    if (url.pathname === '/api/brief') {
      res.writeHead(503, {'Content-Type': 'application/json'}).end('{"error":"Local preview: use the brief download."}'); return;
    }
    const pathname = decodeURIComponent(rewrite?.destination || url.pathname);
    let target = path.resolve(root, '.' + pathname);
    const allowed = target.startsWith(root + path.sep) && !pathname.split('/').some(part => part.startsWith('.'));
    let status = 200;
    if (!allowed || !fs.existsSync(target) || !fs.statSync(target).isFile()) {
      target = path.join(root, '404.html'); status = 404;
    }
    res.setHeader('Content-Type', mime[path.extname(target)] || 'application/octet-stream');
    res.setHeader('Cache-Control', 'no-cache');
    const size = fs.statSync(target).size;
    const range = req.headers.range?.match(/^bytes=(\d+)-(\d*)$/);
    if (range && status === 200) {
      const start = +range[1], end = range[2] ? Math.min(+range[2], size - 1) : size - 1;
      if (start >= size || end < start) { res.writeHead(416, {'Content-Range': `bytes */${size}`}).end(); return; }
      res.writeHead(206, {'Content-Range': `bytes ${start}-${end}/${size}`, 'Accept-Ranges': 'bytes', 'Content-Length': end - start + 1});
      if (req.method === 'HEAD') res.end(); else fs.createReadStream(target, {start, end}).pipe(res);
    } else {
      res.writeHead(status, {'Content-Length': size});
      if (req.method === 'HEAD') res.end(); else fs.createReadStream(target).pipe(res);
    }
  });
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const port = Number(process.env.PORT || 5500);
  const server = createPreviewServer();
  server.on('error', error => { console.error(error.message); process.exitCode = 1; });
  server.listen(port, '127.0.0.1', () => console.log(`Preview: http://127.0.0.1:${port}/anasayfa`));
}
