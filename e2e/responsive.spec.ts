// Runs on desktop and on phones, portrait and landscape, small and large: every control the player
// needs stays on screen, and nothing the player has to read is hidden behind something else.

import { expect, type Locator, type Page } from '@playwright/test';
import { CARD_INDEX, PLAY_BY_HAND, newGame, openGame, playUntil, test } from './helpers.ts';

async function inViewport(page: Page, loc: Locator, what: string) {
  const vp = page.viewportSize()!;
  const n = await loc.count();
  expect(n, `${what}: nothing found`).toBeGreaterThan(0);
  for (let i = 0; i < n; i++) {
    const b = (await loc.nth(i).boundingBox())!;
    expect(b, `${what} #${i} not rendered`).not.toBeNull();
    expect(b.x, `${what} #${i} left`).toBeGreaterThanOrEqual(-1);
    expect(b.y, `${what} #${i} top`).toBeGreaterThanOrEqual(-1);
    expect(b.x + b.width, `${what} #${i} right`).toBeLessThanOrEqual(vp.width + 1);
    expect(b.y + b.height, `${what} #${i} bottom`).toBeLessThanOrEqual(vp.height + 1);
  }
}

async function noHorizontalScroll(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

/** The elements are really what is drawn at a point of theirs (their centre by default): nothing lies on top of them. */
async function uncovered(page: Page, selector: string, what: string, at: [number, number] = [0.5, 0.5]) {
  const hidden = await page.evaluate(
    ([sel, fx, fy]) => {
      const out: string[] = [];
      document.querySelectorAll(sel).forEach((el, i) => {
        const r = el.getBoundingClientRect();
        const top = document.elementFromPoint(r.left + r.width * fx, r.top + r.height * fy);
        if (!top || !(el === top || el.contains(top))) out.push(`#${i} is under ${top ? top.id || top.className : 'nothing'}`);
      });
      return out;
    },
    [selector, ...at] as const,
  );
  expect(hidden, what).toEqual([]);
}

for (const [name, opts, names] of [
  ['', {}, true],
  // the first-time tutorial makes the panel taller: the names may go under it, never the faces or the bids
  [' (with tutorial)', { tutorial: true }, false],
] as const) {
  test(`while bidding, the other players and what they announced stay readable around the panel${name}`, async ({ page }) => {
    await openGame(page, { seed: 7, ...opts });
    await newGame(page);
    await playUntil(page, 'bid');
    await expect(page.locator('#bid [data-action="pass"]')).toBeVisible();
    for (const s of [1, 2, 3]) await uncovered(page, `#seat${s} .avatar${names ? `, #seat${s} .name` : ''}`, `seat ${s}`);
    // seed 7: the three robots have spoken before the player
    await expect(page.locator('.seat .bubble')).toHaveCount(3);
    await uncovered(page, '.seat .bubble', 'announcements');
    await inViewport(page, page.locator('.seat .bubble'), 'announcements');
    // the corner of every card, where its rank and suit are printed
    await uncovered(page, '#hand .card', 'hand', CARD_INDEX);
  });
}

test('the font files are served by the app itself', async ({ page }) => {
  const foreign: string[] = [];
  page.on('request', (r) => {
    if (!r.url().startsWith('http://127.0.0.1') && !r.url().startsWith('data:')) foreign.push(r.url());
  });
  await openGame(page);
  await newGame(page);
  await page.evaluate(() => document.fonts.ready);
  expect(
    await page.evaluate(() =>
      ['600 20px "Source Serif 4"', '400 14px "Source Sans 3"', '700 14px "Source Sans 3"', '600 40px "El Messiri"'].map((f) =>
        document.fonts.check(f),
      ),
    ),
  ).toEqual([true, true, true, true]);
  // the name of the game is also written in Arabic, from the same embedded family
  expect(await page.evaluate(() => document.fonts.check('600 20px "El Messiri"', 'كبّوط'))).toBe(true);
  expect(foreign).toEqual([]);
});

for (const [tutorial, lang] of [
  [false, 'fr'],
  [true, 'fr'],
  // English words are not the same length: the same controls must still fit
  [false, 'en'],
  [true, 'en'],
  // Tunisian Arabic, read right to left
  [false, 'ar'],
  [true, 'ar'],
] as const) {
  test(`bidding controls and hand fit on screen${tutorial ? ' (with tutorial)' : ''} (${lang})`, async ({ page }) => {
    await openGame(page, { tutorial, lang });
    await newGame(page);
    await playUntil(page, 'bid');
    await noHorizontalScroll(page);
    await inViewport(page, page.locator('#bid [data-action="pass"]'), 'Passer');
    await inViewport(page, page.locator('#bid [data-suit]'), 'suit buttons');
    await inViewport(page, page.locator('#bid .step'), 'stepper');
    await inViewport(page, page.locator('#bid [data-action="bid"]'), 'Annoncer');
    await inViewport(page, page.locator('#top .ib'), 'top bar buttons');
  });
}

test('hand and trick stay visible while playing', async ({ page }) => {
  await openGame(page, { storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  await noHorizontalScroll(page);
  await inViewport(page, page.locator('#hand .card'), 'hand cards');
  await inViewport(page, page.locator('#trick'), 'trick');
  for (const s of [1, 2, 3]) await inViewport(page, page.locator(`#seat${s} .avatar`), `seat ${s}`);
});

/** the dialogs a button of the table opens, by that button */
const DIALOGS = { settings: '#bSettings', rules: '#bRules', 'score sheet': '#scoreBox', 'previous trick': '#bLast', 'card values': '#valBtn' };

for (const lang of ['fr', 'ar'] as const) {
  test(`every dialog fits on screen and its buttons can be reached (${lang})`, async ({ page }) => {
    await openGame(page, { lang, storage: PLAY_BY_HAND });
    await newGame(page);
    await playUntil(page, 'play');
    for (const [name, opener] of Object.entries(DIALOGS)) {
      await page.locator(opener).click();
      await inViewport(page, page.locator('#sheet'), name);
      await noHorizontalScroll(page);
      // a long dialog scrolls inside itself: nothing in it is wider than it, and its last button comes into view
      expect(await page.locator('#sheet').evaluate((sheet) => sheet.scrollWidth <= sheet.clientWidth + 1), `${name} is not wider than its box`).toBe(
        true,
      );
      const close = page.locator('#sheet [data-action="close"]');
      await close.scrollIntoViewIfNeeded();
      await inViewport(page, close, `${name}: close`);
      await close.click();
      await expect(page.locator('#modal')).toBeHidden();
    }
  });
}

test('each settings row holds its label and its switch side by side, neither pushed out', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  await page.locator('#bSettings').click();
  const broken = await page.locator('#sheet .setrow').evaluateAll((rows) =>
    rows
      .filter((row) => {
        const [label, control] = [row.firstElementChild!.getBoundingClientRect(), row.lastElementChild!.getBoundingClientRect()];
        const box = row.getBoundingClientRect();
        // the label keeps room for a word, and the switch ends inside the row
        return label.width < 40 || label.right > control.left + 1 || control.right > box.right + 1;
      })
      .map((row) => row.firstElementChild!.textContent),
  );
  expect(broken).toEqual([]);
});

test('the round result fits on screen, down to the button that deals again', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  await playUntil(page, 'round');
  await inViewport(page, page.locator('#sheet'), 'round result');
  await noHorizontalScroll(page);
  const next = page.locator('#sheet [data-action="next"]');
  await next.scrollIntoViewIfNeeded();
  await inViewport(page, next, 'next deal');
});

test('in Arabic the texts read right to left while the table keeps its places', async ({ page }) => {
  await openGame(page, { lang: 'ar' });
  await newGame(page);
  await playUntil(page, 'bid');
  const direction = (selector: string) =>
    page
      .locator(selector)
      .first()
      .evaluate((el) => getComputedStyle(el).direction);
  expect(await direction('#bid .bid-sub')).toBe('rtl');
  expect(await direction('#table')).toBe('ltr');
  // the partner still sits opposite, the next player still on the right
  const box = async (selector: string) => (await page.locator(selector).boundingBox())!;
  expect((await box('#seat1')).x).toBeGreaterThan((await box('#seat3')).x);
  expect((await box('#seat2')).y).toBeLessThan((await box('#seat0')).y);
});

test('the tutorial bubble stays inside the screen', async ({ page }) => {
  await openGame(page, { tutorial: true, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  await inViewport(page, page.locator('#coach'), 'tutorial');
  await inViewport(page, page.locator('#coach button'), 'tutorial answers');
});
