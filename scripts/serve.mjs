// Minimal static file server for local play and browser tests: node scripts/serve.mjs <dir> [port]
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize, resolve, sep } from 'node:path';

const root = resolve(process.argv[2] ?? 'app/web');
const port = Number(process.argv[3] ?? 4173);
const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json',
  '.webmanifest': 'application/manifest+json',
  '.webp': 'image/webp',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.mp3': 'audio/mpeg',
  '.woff2': 'font/woff2',
};
/** the same policy as the page's own meta tag, so a host that sends headers is tested too */
const CSP = "default-src 'self'; img-src 'self' data:; style-src 'self'; style-src-attr 'unsafe-inline'; object-src 'none'; base-uri 'none'";
const HEADERS = { 'cache-control': 'no-store', 'x-content-type-options': 'nosniff', 'content-security-policy': CSP };

/** @returns the file a request path names, or null when it is malformed or points outside the served directory */
function fileFor(url) {
  let path;
  try {
    path = decodeURIComponent(new URL(url ?? '/', 'http://x').pathname);
  } catch {
    return null;
  }
  const file = normalize(join(root, path.endsWith('/') ? path + 'index.html' : path));
  return file.startsWith(root + sep) ? file : null;
}

createServer(async (req, res) => {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    res.writeHead(405, { allow: 'GET, HEAD' }).end();
    return;
  }
  const file = fileFor(req.url);
  if (!file) {
    res.writeHead(400, HEADERS).end('bad request');
    return;
  }
  try {
    const body = await readFile(file);
    res.writeHead(200, { ...HEADERS, 'content-type': TYPES[extname(file)] ?? 'application/octet-stream' });
    res.end(req.method === 'HEAD' ? undefined : body);
  } catch {
    res.writeHead(404, HEADERS).end('not found');
  }
}).listen(port, '127.0.0.1', () => console.log(`serving ${root} on http://127.0.0.1:${port}`));
