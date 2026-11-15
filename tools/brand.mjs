// Draws everything that carries the Kabbout emblem outside the page itself, from the same star
// as the game (app/web/src/star.js): the icon, the app icons, the card back and the share image.
//   node tools/brand.mjs        (needs Playwright's browser, and python3 with Pillow for the WebP)
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { CORE, POINTS, SPADE } from '../app/web/src/star.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const web = join(root, 'app/web');
const NIGHT = '#0b1c36';
const BLUE = '#173a68';
const BRASS = '#d6ae62';
const CREAM = '#f6f1e4';

/** the emblem, centred on the origin, 100 units across */
const emblem = ({ spade = true, core = NIGHT } = {}) =>
  `${POINTS.map((p) => `<polygon points="${p}" fill="${BRASS}"/>`).join('')}<polygon points="${CORE}" fill="${core}"/>${spade ? `<path d="${SPADE}" fill="${CREAM}"/>` : ''}`;
/** the star as a line drawing, for patterns */
const outline = () => {
  const pts = POINTS.flatMap((p) => p.split(' ').slice(1));
  return `<polygon points="${pts.join(' ')}" fill="none" stroke="${BRASS}" stroke-width="3" stroke-linejoin="round"/>`;
};

/** `scale`: how much of the square the star takes (a maskable icon keeps clear of the edge) */
const icon = (rx, scale) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><radialGradient id="g" cx="50%" cy="45%" r="75%"><stop offset="0" stop-color="${BLUE}"/><stop offset="1" stop-color="${NIGHT}"/></radialGradient></defs><rect width="64" height="64" rx="${rx}" fill="url(#g)"/><g transform="translate(32 32) scale(${scale})">${emblem()}</g></svg>\n`;

function cardBack() {
  const W = 330;
  const H = 514;
  const step = 46;
  let lattice = '';
  for (let y = -1; y * step < H + step; y++)
    for (let x = -1; x * step < W + step; x++) {
      const cx = x * step + (W % step) / 2;
      const cy = y * step + (H % step) / 2;
      lattice += `<g transform="translate(${cx} ${cy}) scale(.36)" opacity=".5">${outline()}</g>`;
    }
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
  <defs><clipPath id="in"><rect x="20" y="20" width="${W - 40}" height="${H - 40}" rx="10"/></clipPath></defs>
  <rect width="${W}" height="${H}" rx="16" fill="${CREAM}"/>
  <rect x="11" y="11" width="${W - 22}" height="${H - 22}" rx="12" fill="${NIGHT}"/>
  <g clip-path="url(#in)"><rect x="20" y="20" width="${W - 40}" height="${H - 40}" fill="${BLUE}"/>${lattice}</g>
  <rect x="20" y="20" width="${W - 40}" height="${H - 40}" rx="10" fill="none" stroke="${BRASS}" stroke-width="2"/>
  <circle cx="${W / 2}" cy="${H / 2}" r="62" fill="${NIGHT}" stroke="${BRASS}" stroke-width="2"/>
  <g transform="translate(${W / 2} ${H / 2})">${emblem()}</g>
</svg>`;
}

const font = (file) => `url(data:font/woff2;base64,${readFileSync(join(web, 'fonts', file)).toString('base64')}) format("woff2")`;
const social = () => `<!doctype html><meta charset="utf-8"><style>
@font-face{font-family:'El Messiri';font-weight:600;src:${font('el-messiri-latin.woff2')};unicode-range:U+0000-00FF}
@font-face{font-family:'El Messiri';font-weight:600;src:${font('el-messiri-arabic.woff2')};unicode-range:U+0600-06FF,U+FB50-FDFF,U+FE70-FEFC}
@font-face{font-family:'Source Serif 4';font-weight:600;src:${font('source-serif-4.woff2')}}
html,body{margin:0}
body{width:1200px;height:630px;display:flex;align-items:center;gap:64px;padding:0 96px;box-sizing:border-box;background:radial-gradient(120% 120% at 30% 45%,#245591,${BLUE} 55%,${NIGHT});color:${CREAM};font-family:'El Messiri'}
svg{width:360px;height:360px;flex:none;filter:drop-shadow(0 18px 40px #0009)}
h1{margin:0;font-size:150px;line-height:1;color:${BRASS};font-weight:600;text-shadow:0 6px 0 #0006}
.ar{font-size:64px;line-height:1.3}
p{margin:14px 0 0;font:600 34px/1.3 'Source Serif 4',serif}
</style><svg viewBox="-50 -50 100 100">${emblem()}</svg><div><div class="ar" lang="ar" dir="rtl">كبّوط</div><h1>Kabbout</h1><p>Belote contrée, café rules.<br>Eight tricks or it doesn’t count.</p></div>`;

mkdirSync(join(web, 'brand'), { recursive: true });
writeFileSync(join(web, 'icon.svg'), icon(14, 0.56));

const tmp = mkdtempSync(join(tmpdir(), 'kabbout-brand-'));
const browser = await chromium.launch({ channel: process.env.CI ? undefined : 'chrome' });
try {
  const shot = async (html, width, height, out, transparent = false) => {
    const page = await browser.newPage({ viewport: { width, height } });
    await page.setContent(html.startsWith('<!doctype') ? html : `<body style="margin:0">${html}</body>`);
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: out, omitBackground: transparent });
    await page.close();
  };
  const sized = (svg, px) => svg.replace('<svg ', `<svg width="${px}" height="${px}" style="display:block" `);
  await shot(sized(icon(0, 0.56), 180), 180, 180, join(web, 'brand/icon-180.png'));
  await shot(sized(icon(14, 0.56), 192), 192, 192, join(web, 'brand/icon-192.png'), true);
  await shot(sized(icon(14, 0.56), 512), 512, 512, join(web, 'brand/icon-512.png'), true);
  await shot(sized(icon(0, 0.42), 512), 512, 512, join(web, 'brand/icon-maskable-512.png'));
  await shot(social(), 1200, 630, join(web, 'brand/social.png'));
  const back = join(tmp, 'dos.png');
  await shot(cardBack(), 330, 514, back, true);
  execFileSync('python3', [
    '-c',
    'import sys\nfrom PIL import Image\nImage.open(sys.argv[1]).convert("RGBA").save(sys.argv[2], "WEBP", quality=90, method=6)',
    back,
    join(web, 'cards/back.webp'),
  ]);
} finally {
  await browser.close();
  rmSync(tmp, { recursive: true, force: true });
}
console.log('brand: icon.svg, 4 app icons, share image, card back');
