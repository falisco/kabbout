import { expect } from '@playwright/test';
import { PLAY_BY_HAND, handCards, newGame, nextMoment, openGame, playUntil, points, prefs, sheetRows, tapCard, test } from './helpers.ts';

test('menu opens without errors', async ({ page }) => {
  const errors = await openGame(page);
  await expect(page).toHaveTitle('Kabbout — belote contrée');
  await expect(page.getByRole('heading', { name: /Kabbout/ })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Nouvelle partie' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('a new game deals 8 cards to everyone', async ({ page }) => {
  const errors = await openGame(page);
  await newGame(page);
  for (const seat of [1, 2, 3]) await expect(page.locator(`#seat${seat} .mini`)).toHaveCount(8);
  expect(errors).toEqual([]);
});

test('bidding: pick a suit and announce; the bid shows at my seat', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  await playUntil(page, 'bid');
  await expect(page.locator('#bid [data-action="bid"]')).toBeDisabled();
  await page.locator('#bid [data-suit="S"]').click();
  const announce = page.locator('#bid [data-action="bid"]');
  await expect(announce).toBeEnabled();
  const label = (await announce.textContent())!.replace('Annoncer ', '').trim();
  await announce.click();
  await expect(page.locator('#seat0 .bubble')).toContainText(label.split(' ')[0]);
});

test('the auction so far is listed in the bidding panel, in order', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  await playUntil(page, 'bid');
  const lines = await page.locator('#bid .hist li').allTextContents();
  expect(lines.length).toBeGreaterThan(0);
  for (const line of lines)
    expect(line).toMatch(/^(Tonton Salah|Selim|Tata Mongia|Vous) (a passé|passez|annonce|annoncez|contre|contrez|surcontre|surcontrez)/);
  await expect(page.locator('body')).not.toContainText(/undefined|\[object|NaN/);
});

test('only legal cards can be played, and a played card reaches the trick', async ({ page }) => {
  // normal robot speed, so the trick stays on the table long enough to be checked
  const errors = await openGame(page, { fast: false, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  const before = await handCards(page);
  const ok = page.locator('#hand .card.ok');
  const no = page.locator('#hand .card.no');
  expect((await ok.count()) + (await no.count())).toBe(before.length);
  // seed 7: the player has to follow suit, so some cards are forbidden; clicking one plays nothing
  expect(await no.count()).toBeGreaterThan(0);
  await tapCard(no.first(), { force: true });
  await expect(page.locator('#hand .card.nope')).toHaveCount(1);
  await expect(page.locator('#hand .card')).toHaveCount(before.length);
  const card = await ok.first().getAttribute('data-card');
  await tapCard(ok.first());
  await expect(page.locator(`#trick .slot.s .card img[src="cards/${card}.webp"]`)).toHaveCount(1);
  expect(await handCards(page)).not.toContain(card);
  expect(errors).toEqual([]);
});

test('keyboard: playable cards are focusable and Enter plays them', async ({ page }) => {
  await openGame(page, { fast: false, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  await expect(page.locator('#hand .card.ok').first()).toHaveAttribute('tabindex', '0');
  await expect(page.locator('#hand .card.no').first()).toHaveAttribute('tabindex', '-1');
  const card = page.locator('#hand .card.ok').first();
  const id = await card.getAttribute('data-card');
  await card.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator(`#trick .slot.s .card img[src="cards/${id}.webp"]`)).toHaveCount(1);
});

test('undo puts the played card back in the hand', async ({ page }) => {
  await openGame(page, { storage: prefs({ undo: true, autoForced: false }) });
  await newGame(page);
  await playUntil(page, 'play');
  const before = await handCards(page);
  await tapCard(page.locator('#hand .card.ok').first());
  await page.locator('#undoBtn').click();
  await expect(page.locator('#hand .card')).toHaveCount(before.length);
  expect((await handCards(page)).sort()).toEqual([...before].sort());
  await expect(page.locator('#trick .slot.s')).toHaveCount(0);
});

test('settings are kept after a reload', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  await page.locator('#bSettings').click();
  await page.locator('#sheet [data-setting="highlight"][data-value="off"]').click();
  await expect(page.locator('#sheet [data-setting="highlight"][data-value="off"]')).toHaveClass(/on/);
  await page.reload();
  await newGame(page);
  await page.locator('#bSettings').click();
  await expect(page.locator('#sheet [data-setting="highlight"][data-value="off"]')).toHaveClass(/on/);
});

test('the robots’ level is chosen in the menu or the settings, and kept after a reload', async ({ page }) => {
  await openGame(page);
  const level = (name: string) => page.getByRole('group', { name: 'Niveau des robots' }).getByRole('button', { name });
  await expect(level('Normal')).toHaveAttribute('aria-pressed', 'true');
  await level('Expert').click();
  await expect(level('Expert')).toHaveAttribute('aria-pressed', 'true');
  await expect(level('Normal')).toHaveAttribute('aria-pressed', 'false');
  await page.reload();
  await expect(level('Expert')).toHaveAttribute('aria-pressed', 'true');
  await newGame(page);
  await page.locator('#bSettings').click();
  await level('Facile').click();
  await expect(level('Facile')).toHaveAttribute('aria-pressed', 'true');
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.prefs')!).level)).toBe('easy');
});

test('corrupted stored data never blocks the start, and a game left by an earlier version is dropped', async ({ page }) => {
  const errors = await openGame(page, {
    storage: { 'contree.save': '5', 'kabbout.stats': '"x"', 'kabbout.tuto': '{"done":1}', 'kabbout.prefs': '[1]' },
  });
  await expect(page.getByRole('button', { name: /Reprendre/ })).toHaveCount(0);
  expect(await page.evaluate(() => localStorage.getItem('contree.save'))).toBeNull();
  await newGame(page);
  expect(errors).toEqual([]);
});

test('the previous trick can only be looked at while the next one lies complete on the table', async ({ page }) => {
  await page.clock.install();
  await openGame(page, { fast: false, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  // about to play: not allowed
  await page.locator('#bLast').click();
  await expect(page.locator('#sheet')).toContainText('ne se consulte qu’une fois le pli suivant complet');
  await expect(page.locator('#sheet .lt')).toHaveCount(0);
  // the auction is recalled there, on every screen size
  await expect(page.locator('#sheet .hist li').first()).toBeVisible();
  await page.keyboard.press('Escape');
  // the second trick lies complete: the first one may be seen, and the game waits meanwhile
  const complete = () => document.querySelectorAll('#trick .slot').length === 4 && document.querySelectorAll('#hand .card').length === 6;
  for (let i = 0; i < 2; i++) {
    await playUntil(page, 'play');
    await tapCard(page.locator('#hand.myturn .card.ok').first());
    if (i === 1) await page.waitForFunction(complete, undefined, { polling: 50 });
    else await page.waitForFunction(() => document.querySelectorAll('#hand .card').length === 7);
  }
  await page.evaluate(() => document.getElementById('bLast')!.click());
  await expect(page.locator('#sheet .lt figure')).toHaveCount(4);
  // the table's clock jumps well past the moment the trick would have been collected: nothing moved
  await page.clock.runFor(10_000);
  expect(await page.evaluate(complete)).toBe(true);
});

test('round result: each column adds up to its total', async ({ page }) => {
  await openGame(page, { storage: prefs({ undo: false }) });
  await newGame(page);
  await playUntil(page, 'round');
  const rows = await sheetRows(page);
  const total = rows.find((r) => r[0] === 'Total de la donne')!;
  const parts = rows.filter((r) => ['Plis', 'Belote'].includes(r[0]) || r[0].startsWith('Contrat'));
  expect(parts).toHaveLength(3);
  for (const col of [1, 2]) expect(parts.reduce((s, r) => s + points(r[col]), 0)).toBe(points(total[col]));
});

test('a whole game can be played to the end through the interface', async ({ page }) => {
  test.setTimeout(240_000);
  const errors = await openGame(page, { seed: 11, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'end', 5000);
  await expect(page.locator('#sheet h1')).toHaveText(/Victoire !|Défaite/);
  const us = Number(await page.locator('#sUs').textContent());
  const them = Number(await page.locator('#sThem').textContent());
  expect(Math.max(us, them)).toBeGreaterThanOrEqual(1000);
  const stats = await page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.stats') ?? 'null'));
  expect(stats.played).toBe(1);
  expect(await nextMoment(page)).toBe('end');
  // and another one started from there, from zero
  await page.locator('#sheet [data-action="new"]').click();
  await expect(page.locator('#hand .card')).toHaveCount(8);
  await expect(page.locator('#sUs')).toHaveText('0');
  await expect(page.locator('#sThem')).toHaveText('0');
  expect(errors).toEqual([]);
});
