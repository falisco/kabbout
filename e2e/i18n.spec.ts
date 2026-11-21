// The three languages, the switch between them, and what the game is called.

import { expect } from '@playwright/test';
import { PLAY_BY_HAND, newGame, nextMoment, openGame, playUntil, prefs, tapCard, test } from './helpers.ts';

test.describe('in a browser that speaks English', () => {
  test.use({ locale: 'en-US' });

  test('the game is in English, court cards included', async ({ page }) => {
    const errors = await openGame(page);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByRole('button', { name: 'New game' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Settings' })).toHaveAttribute('title', 'Settings');
    await expect(page.locator('#scoreBox')).toContainText('Us');
    await newGame(page);
    await playUntil(page, 'bid');
    for (const line of await page.locator('#bid .hist li').allTextContents())
      expect(line).toMatch(/^(Uncle Salah|Selim|Auntie Mongia|You) (pass|passed|bids?|says?) ?/);
    // J Q K on the court cards, from their own folder; the other cards are shared
    const srcs = await page
      .locator('#hand .card')
      .evaluateAll((els) => els.map((e) => [(e as HTMLElement).dataset.card!, e.querySelector('img')!.getAttribute('src')!]));
    for (const [card, src] of srcs) expect(src).toBe('JQK'.includes(card[0]) ? `cards/en/${card}.webp` : `cards/${card}.webp`);
    const loaded = await page.locator('#hand .card img').evaluateAll((imgs) => imgs.every((i) => (i as HTMLImageElement).naturalWidth > 0));
    expect(loaded).toBe(true);
    await expect(page.locator('body')).not.toContainText(/undefined|\[object|NaN/);
    expect(errors).toEqual([]);
  });

  test('the advice on the player’s own hand gives its reason in English', async ({ page }) => {
    await openGame(page, { storage: prefs({ learn: true }) });
    await newGame(page);
    await playUntil(page, 'bid');
    await page.locator('#bid [data-action="tip"]').click();
    await expect(page.locator('#toast')).toBeVisible();
    const advice = (await page.locator('#toast').textContent()) ?? '';
    expect(advice).toMatch(/trump|support|\b[AJ] [♠♥♦♣]|J-9|10 [♠♥♦♣]|9 [♠♥♦♣]|belote|ruffs|margin|contract|raise|tricks|worth/);
    expect(advice).not.toMatch(/atout|soutien|\bAs\b|\bV\b|pas /);
    // and no reason of a robot is beside its bid
    await expect(page.locator('#bid .hist small')).toHaveCount(0);
  });
});

test.describe('in a browser that speaks Arabic', () => {
  test.use({ locale: 'ar-TN' });

  test('the game is in Tunisian Arabic: texts read right to left, the table stays as it is', async ({ page }) => {
    const errors = await openGame(page, { storage: prefs({ why: true }) });
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar-TN');
    await expect(page.getByRole('button', { name: 'بارتية جديدة' })).toBeVisible();
    await expect(page.getByRole('group', { name: 'اللغة' }).getByRole('button', { name: 'تونسي' })).toHaveAttribute('aria-pressed', 'true');
    const dir = (sel: string) => page.locator(sel).evaluate((e) => getComputedStyle(e).direction);
    expect(await dir('#sheet')).toBe('rtl');
    await newGame(page);
    await playUntil(page, 'bid');
    expect(await dir('#bid')).toBe('rtl');
    expect(await dir('#hand')).toBe('ltr');
    expect(await dir('#top')).toBe('ltr');
    await expect(page.locator('#seat2 .name')).toContainText('سليم');
    for (const line of await page.locator('#bid .hist li').allTextContents())
      expect(line).toMatch(/^(عم صالح|سليم|خالتي منجية|إنت) (قال|قلت|طلب|طلبت) ?/);
    // a bid keeps its order, value then suit, inside right-to-left text
    const bid = page.locator('#bid .bid-sub bdi').first();
    await expect(bid).toHaveText(/^\d+ [♠♥♦♣]$/);
    const box = await bid.evaluate((e) => {
      const r = document.createRange();
      r.setStart(e.firstChild!, 0);
      r.setEnd(e.firstChild!, 1);
      return { digit: r.getBoundingClientRect().left, suit: e.querySelector('span')!.getBoundingClientRect().left };
    });
    expect(box.digit).toBeLessThan(box.suit);
    // court cards keep the French index letters
    for (const src of await page.locator('#hand .card img').evaluateAll((imgs) => imgs.map((i) => i.getAttribute('src')!)))
      expect(src).not.toContain('/en/');
    await expect(page.locator('body')).not.toContainText(/undefined|\[object|NaN/);
    expect(errors).toEqual([]);
  });
});

test('a browser that speaks neither language gets English', async ({ browser }) => {
  const ctx = await browser.newContext({ locale: 'de-DE' });
  const page = await ctx.newPage();
  await openGame(page);
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await ctx.close();
});

test('the language is switched mid-game, everywhere at once, and kept after a reload', async ({ page }) => {
  // the words checked here are the game's own: with no server answering, the player's seat says "you"
  await page.route('**/api/session', (route) => route.fulfill({ status: 204 }));
  const errors = await openGame(page, { storage: PLAY_BY_HAND });
  await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  await newGame(page);
  await playUntil(page, 'play');
  const first = page.locator('#hand .card').first();
  const frName = await first.getAttribute('aria-label');
  await expect(page.locator('#hint')).toHaveText('À vous de jouer');

  await page.locator('#bSettings').click();
  const english = page.getByRole('group', { name: 'Langue' }).getByRole('button', { name: 'English' });
  await english.click();
  // the dialog is redrawn in English and the focus stays on the switch
  await expect(page.getByRole('group', { name: 'Language' }).getByRole('button', { name: 'English' })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByRole('group', { name: 'Language' }).getByRole('button', { name: 'English' })).toBeFocused();
  await expect(page.locator('#sheet h2')).toHaveText('Settings');
  await page.keyboard.press('Escape');

  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.locator('#hint')).toHaveText('Your turn');
  await expect(page.locator('#seat0 .name')).toHaveText('You');
  await expect(page.locator('#bRules')).toHaveAttribute('aria-label', 'Rules');
  expect(await first.getAttribute('aria-label')).not.toBe(frName);
  for (const src of await page.locator('#hand .card img').evaluateAll((imgs) => imgs.map((i) => i.getAttribute('src')!)))
    if (/\/[JQK][SHDC]\.webp$/.test(src)) expect(src).toMatch(/^cards\/en\//);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.prefs')!).lang)).toBe('en');

  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.getByRole('button', { name: 'New game' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('what was stored under the former name is brought over', async ({ page }) => {
  await openGame(page, {
    storage: { 'contree.prefs': JSON.stringify({ level: 'expert', hl: false }), 'contree.stats': JSON.stringify({ played: 3, won: 2 }) },
  });
  await expect(page.getByRole('group', { name: 'Niveau des robots' }).getByRole('button', { name: 'Expert' })).toHaveAttribute(
    'aria-pressed',
    'true',
  );
  await expect(page.locator('#sheet .stats')).toHaveText('Parties gagnées : 2 sur 3');
  const keys = await page.evaluate(() => Object.keys(localStorage).sort());
  expect(keys.filter((k) => k.startsWith('contree.'))).toEqual([]);
  expect(keys).toEqual(expect.arrayContaining(['kabbout.prefs', 'kabbout.stats']));
});

test('the star in the middle lights one point per trick', async ({ page }) => {
  await openGame(page, { storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  const lit = () => page.locator('#tstar .pt.us, #tstar .pt.them').count();
  const played = async () => 8 - (await page.locator('#hand .card').count());
  expect(await lit()).toBe(await played());
  for (let i = 0; i < 3; i++) {
    await tapCard(page.locator('#hand.myturn .card.ok').first());
    await playUntil(page, 'play');
  }
  expect(await played()).toBeGreaterThanOrEqual(3);
  expect(await lit()).toBe(await played());
  await expect(page.locator('#tstar svg')).toHaveAttribute('aria-label', /^Plis : nous \d, eux \d$/);
  await playUntil(page, 'round');
  await expect(page.locator('#sheet .rstar .pt.us, #sheet .rstar .pt.them')).toHaveCount(8);
});

test('all eight tricks to one side is a kabbout, and the screen says so', async ({ page }) => {
  // seed 5: the opponents take every trick of the first deal
  await openGame(page, { seed: 5, storage: prefs({ undo: false }) });
  await newGame(page);
  await playUntil(page, 'round');
  await expect(page.locator('#sheet .rstar .pt.them')).toHaveCount(8);
  await expect(page.locator('#sheet h2')).toHaveText('Vous êtes kabbout…');
  // the full-screen announcement came first (it has gone by now, robots being instant here)
  expect(await page.locator('#kabbout h2').textContent()).toBe('Vous êtes kabbout…');
  await expect(page.locator('#kabbout')).toBeHidden();
  expect(await nextMoment(page)).toBe('round');
});

test('the kabbout screen holds the table, takes the focus and closes on a key', async ({ page }) => {
  test.setTimeout(120_000);
  // the same deal at the table's real pace, so the screen stays up
  await openGame(page, { seed: 5, fast: false, storage: prefs({ undo: false }) });
  await newGame(page);
  const screen = page.locator('#kabbout');
  await playUntil(page, 'kabbout');
  await expect(screen).toBeVisible();
  await expect(screen).toBeFocused();
  await expect(screen.locator('.pt.them')).toHaveCount(8);
  expect(await page.evaluate(() => (document.getElementById('app') as HTMLElement).inert)).toBe(true);
  // reduced motion (set for every test): the star stands still, upright
  expect(await screen.locator('.star').evaluate((e) => getComputedStyle(e).animationName)).toBe('none');
  await page.keyboard.press('Escape');
  await expect(screen).toBeHidden();
  await expect(page.locator('#sheet h2')).toHaveText('Vous êtes kabbout…');
  await expect(page.locator('#sheet [data-action="next"]')).toBeFocused();
});
