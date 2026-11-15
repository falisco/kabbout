// What a player does besides passing and following: taking a contract, contring, asking for
// advice, saying a word to the table, and going through the tutorial.

import { expect } from '@playwright/test';
import { PLAY_BY_HAND, handCards, newGame, nextMoment, openGame, playUntil, prefs, sheetRows, test } from './helpers.ts';

test('the player takes a contract, plays it out and finds it on the score sheet', async ({ page }) => {
  // seed 21: the player's hand is worth 90 at spades, and nobody goes higher
  const errors = await openGame(page, { seed: 21, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'bid');
  const announced = '90';
  const value = page.locator('#bid .goal');
  while ((await value.textContent()) !== announced) await page.locator('#bid [data-action="plus"]').click();
  await page.locator('#bid [data-suit="S"]').click();
  await expect(page.locator('#bid [data-suit="S"]')).toHaveAttribute('aria-pressed', 'true');
  await page.locator('#bid [data-action="bid"]').click();
  // whatever the others answer, the player passes from then on; the contract is theirs when the cards come out
  await playUntil(page, 'play');
  await expect(page.locator('#seat0 .chip.ctr')).toContainText(announced);
  await expect(page.locator('#contract .badge')).toContainText('vous');
  await playUntil(page, 'round');
  const rows = await sheetRows(page);
  expect(rows.map((row) => row[0])).toEqual(expect.arrayContaining(['Plis', 'Belote', 'Total de la donne']));
  await page.locator('#sheet [data-action="next"]').click();
  await page.locator('#scoreBox').click();
  await expect(page.locator('#sheet .sheet-tbl tbody tr')).toHaveCount(1);
  await expect(page.locator('#sheet .sheet-tbl tbody tr')).toContainText('vous');
  expect(errors).toEqual([]);
});

test('the player contres the other side’s bid, and the deal is counted double', async ({ page }) => {
  const errors = await openGame(page, { seed: 7, storage: PLAY_BY_HAND });
  await newGame(page);
  // seed 7: an opponent holds the best bid when the player first speaks
  await playUntil(page, 'bid');
  const contre = page.locator('#bid [data-action="contre"]');
  await expect(contre).toBeVisible();
  await contre.click();
  await playUntil(page, 'play');
  await expect(page.locator('#contract .badge .x')).toHaveText('Contré');
  await expect(page.locator('.seat .chip.ctr')).toContainText('×2');
  await playUntil(page, 'round');
  // contré: one side takes everything
  const total = (await sheetRows(page)).find((row) => row[0] === 'Total de la donne')!;
  expect([total[1], total[2]]).toContain('0');
  expect(errors).toEqual([]);
});

test('advice points at a bid, then at a card, without playing for the player', async ({ page }) => {
  const errors = await openGame(page, { storage: prefs({ undo: false, autoForced: false, learn: true }) });
  await newGame(page);
  await playUntil(page, 'bid');
  await page.locator('#bid [data-action="tip"]').click();
  await expect(page.locator('#toast')).toBeVisible();
  await expect(page.locator('#toast')).toContainText('Conseil');
  expect(await nextMoment(page)).toBe('bid');
  await playUntil(page, 'play');
  const hand = await handCards(page);
  await page.locator('#tipBtn').click();
  await expect(page.locator('#hand .card.tip')).toHaveCount(1);
  await expect(page.locator('#hand .card.tip')).toHaveClass(/ok/);
  expect(await handCards(page)).toEqual(hand);
  expect(errors).toEqual([]);
});

test('a quick phrase is said in a bubble and one of the others answers; the menu works from the keyboard', async ({ page }) => {
  const errors = await openGame(page, { storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  const button = page.locator('#chatBtn');
  const menu = page.locator('#chatMenu');
  await expect(button).toHaveAttribute('aria-expanded', 'false');
  await button.click();
  await expect(menu).toBeVisible();
  await expect(button).toHaveAttribute('aria-expanded', 'true');
  await expect(menu.locator('button').first()).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(menu).toBeHidden();
  await expect(button).toBeFocused();

  await button.click();
  await menu.locator('[data-phrase="hello"]').click();
  await expect(menu).toBeHidden();
  await expect(page.locator('#seat0 .bubble')).toBeVisible();
  await expect(page.locator('.seat:not(#seat0) .bubble')).toHaveCount(1);

  await button.click();
  await page.locator('#hand').click({ position: { x: 2, y: 2 } });
  await expect(menu).toBeHidden();
  expect(errors).toEqual([]);
});

test('the tutorial explains each step once, remembers it, and can be skipped or started again', async ({ page }) => {
  const errors = await openGame(page, { tutorial: true, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'bid');
  const stored = () => page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.tuto')!));
  const explanation = page.locator('#bid .coach-in p');
  const bidStep = (await explanation.textContent())!;
  await page.locator('#bid [data-answer="ok"]').click();
  // the step is not explained again; the next one (the contre) may follow at once
  await expect(explanation.filter({ hasText: bidStep })).toHaveCount(0);
  expect((await stored()).done.bid).toBe(true);

  await playUntil(page, 'play');
  await expect(page.locator('#coach')).toBeVisible();
  await page.locator('#coach [data-answer="skip"]').click();
  await expect(page.locator('#coach')).toBeHidden();
  expect((await stored()).off).toBe(true);

  await page.locator('#bSettings').click();
  await page.locator('#sheet [data-action="tutorial"]').click();
  await expect(page.locator('#coach')).toBeVisible();
  expect(await stored()).toEqual({ done: {}, off: false });
  expect(errors).toEqual([]);
});

test.describe('with animations on', () => {
  test.use({ reducedMotion: 'no-preference' });

  test('a deal can be played while cards fly and tricks are swept away', async ({ page }) => {
    const errors = await openGame(page, { storage: prefs({ undo: false }) });
    await newGame(page);
    expect(await page.locator('#hand').evaluate((hand) => getComputedStyle(hand.querySelector('.card')!).animationName)).not.toBe('none');
    await playUntil(page, 'round');
    await expect(page.locator('#sheet [data-action="next"]')).toBeFocused();
    expect(errors).toEqual([]);
  });
});
