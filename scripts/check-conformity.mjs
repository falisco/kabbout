// Conformity checks run in CI: assets and their licences, what the page may load, and no secrets in the repo.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { extname } from 'node:path';

const problems = [];
const fail = (msg) => problems.push(msg);

// 1. Card images: exactly the 32 belote cards and the back, nothing else.
//    The court cards exist twice: French indices (V D R) at the root, English ones (J Q K) in en/.
const expected = new Set(['back.webp', 'en']);
for (const s of 'SHDC') for (const r of '789TJQKA') expected.add(`${r}${s}.webp`);
const expectedEn = new Set();
for (const s of 'SHDC') for (const r of 'JQK') expectedEn.add(`${r}${s}.webp`);
for (const [dir, want] of [
  ['app/web/cards', expected],
  ['app/web/cards/en', expectedEn],
]) {
  const present = new Set(readdirSync(dir));
  for (const f of want) if (!present.has(f)) fail(`missing card image ${dir}/${f}`);
  for (const f of present) if (!want.has(f)) fail(`unexpected file in ${dir}: ${f}`);
}

// 2. Every bundled asset family is credited with its public-domain licence.
const credits = readFileSync('CREDITS.md', 'utf8');
for (const needle of [
  'Mesmedir',
  'Dmitry Fomin',
  'Kenney',
  'CC0',
  'Source Serif 4',
  'Source Sans 3',
  'El Messiri',
  'Noto Sans Arabic',
  'Open Font License',
])
  if (!credits.includes(needle)) fail(`CREDITS.md does not mention ${needle}`);

// 3. The page and its scripts load nothing from another site; no dynamic code; no third-party brand names.
const sources = readdirSync('app/web/src', { recursive: true })
  .filter((f) => f.endsWith('.js'))
  .map((f) => `app/web/src/${f}`);
if (!sources.length) fail('no source found in app/web/src');
const page = ['app/web/index.html', 'app/web/styles.css', 'app/web/manifest.webmanifest', ...sources].map((f) => readFileSync(f, 'utf8')).join('\n');
// An absolute or protocol-relative address in an attribute, a stylesheet, the manifest, or a script that fetches or imports.
const REMOTE = /(?:https?:)?\/\/[^"'`)\s]+/.source;
const LOADERS = [
  `(?:src|href|action)\\s*=\\s*["'](${REMOTE})`,
  `url\\(\\s*["']?(${REMOTE})`,
  `"(?:src|start_url|scope|url)"\\s*:\\s*"(${REMOTE})`,
  `(?:fetch|import|importScripts|Worker|WebSocket|EventSource)\\s*\\(\\s*["'\`](${REMOTE})`,
];
for (const m of page.matchAll(new RegExp(LOADERS.join('|'), 'g'))) {
  fail(`app/web loads from ${new URL(m.slice(1).find(Boolean), 'https://x').host}`);
}
if (!/http-equiv="Content-Security-Policy"/.test(page)) fail('app/web/index.html has no Content-Security-Policy');
if (/<style[\s>]|<script(?![^>]*\bsrc=)[^>]*>|\son[a-z]+\s*=\s*["']/.test(readFileSync('app/web/index.html', 'utf8')))
  fail('app/web/index.html has an inline style, script or event handler, which its Content-Security-Policy forbids');
if (/\beval\s*\(|new\s+Function\s*\(/.test(page)) fail('app/web uses eval / new Function');
for (const brand of ['Belote.com', 'GameDuell']) if (page.includes(brand)) fail(`app/web mentions the trademark "${brand}"`);

// 4. No private keys or tokens in tracked text files.
const SECRET =
  /-----BEGIN [A-Z ]*PRIVATE KEY-----|\bAKIA[0-9A-Z]{16}\b|\bgh[pousr]_[A-Za-z0-9]{36}\b|\bsk-[A-Za-z0-9]{32,}\b|\bAIza[0-9A-Za-z_-]{35}\b/;
const BINARY = new Set(['.webp', '.png', '.jpg', '.mp3', '.ogg', '.woff2']);
const tracked = execSync('git ls-files -co --exclude-standard', { encoding: 'utf8' }).split('\n').filter(Boolean);
for (const f of tracked) {
  if (BINARY.has(extname(f)) || f === 'package-lock.json') continue;
  let text;
  try {
    if (statSync(f).size > 5_000_000) continue;
    text = readFileSync(f, 'utf8');
  } catch {
    continue;
  }
  if (SECRET.test(text)) fail(`possible secret in ${f}`);
}

if (problems.length) {
  console.error('Conformity check failed:\n- ' + problems.join('\n- '));
  process.exit(1);
}
console.log(`Conformity OK: ${expected.size - 1 + expectedEn.size} card images, credits, page hosts, ${tracked.length} files scanned for secrets.`);
