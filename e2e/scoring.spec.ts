// The two ways of counting a deal: with the value of the contract added (the federation's), or
// without (Tunisian). The choice is made in the menu and holds for a whole game.

import { expect, type Page } from '@playwright/test';
import { newGame, openGame, playUntil, points, prefs, sheetRows, test } from './helpers.ts';

const choice = (page: Page, name: string) => page.getByRole('group', { name: 'Comptage' }).getByRole('button', { name });
test('games are counted with the contract unless the player chooses otherwise', async ({ page }) => {
  await openGame(page);
  await expect(choice(page, 'Avec contrat')).toHaveAttribute('aria-pressed', 'true');
  await expect(choice(page, 'Sans contrat (tunisien)')).toHaveAttribute('aria-pressed', 'false');
});

test('without contract: the same deal scores what each side made, and no contract row is shown', async ({ page, browser }) => {
  // seed 7, every bid passed by the player: the opponents play 90 ♥ and make it
  await openGame(page, { storage: prefs({ undo: false }) });
  await choice(page, 'Sans contrat (tunisien)').click();
  await expect(choice(page, 'Sans contrat (tunisien)')).toHaveAttribute('aria-pressed', 'true');
  await newGame(page);
  await playUntil(page, 'round');
  const tunisian = await sheetRows(page);
  expect(tunisian.map((r) => r[0])).toEqual(['Plis', 'Belote', 'Total de la donne', 'Score']);
  const total = tunisian.find((r) => r[0] === 'Total de la donne')!;
  for (const col of [1, 2]) expect(points(tunisian[0][col]) + points(tunisian[1][col])).toBe(points(total[col]));

  // the same deal counted with the contract, in another browser: the takers get their bid on top
  const other = await (await browser.newContext({ locale: 'fr-FR' })).newPage();
  await openGame(other, { storage: prefs({ undo: false }) });
  await newGame(other);
  await playUntil(other, 'round');
  const ffb = await sheetRows(other);
  const contract = ffb.find((r) => r[0].startsWith('Contrat'))!;
  const ffbTotal = ffb.find((r) => r[0] === 'Total de la donne')!;
  expect(points(contract[1]) + points(contract[2])).toBeGreaterThan(0);
  for (const col of [1, 2]) expect(points(ffbTotal[col])).toBe(points(total[col]) + points(contract[col]));
  await other.context().close();

  // the rules and the score sheet say which count is in use
  await page.locator('#sheet [data-action="next"]').click();
  await page.locator('#scoreBox').click();
  await expect(page.locator('#sheet')).toContainText('Comptage : sans contrat (tunisien)');
  await page.keyboard.press('Escape');
  await page.locator('#bRules').click();
  await expect(page.locator('#sheet')).toContainText('la valeur annoncée ne s’ajoute jamais au score');
  await expect(page.locator('#sheet')).not.toContainText('160 + le contrat');
});

test('the choice is kept after a reload, and a game under way keeps the count it started with', async ({ page }) => {
  await openGame(page, { storage: prefs({ undo: false }) });
  await newGame(page);
  // changed from the menu in the middle of a game counted with the contract
  await page.locator('#bMenu').click();
  await choice(page, 'Sans contrat (tunisien)').click();
  await expect(page.locator('#sheet')).toContainText('vaudra pour la prochaine partie');
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.prefs')!).scoring)).toBe('tunisian');
  await page.getByRole('button', { name: 'Reprendre la partie' }).click();
  await playUntil(page, 'round');
  expect((await sheetRows(page)).some((r) => r[0].startsWith('Contrat'))).toBe(true);

  await page.reload();
  await expect(choice(page, 'Sans contrat (tunisien)')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('#sheet')).not.toContainText('vaudra pour la prochaine partie');
  await newGame(page);
  await playUntil(page, 'round');
  expect((await sheetRows(page)).some((r) => r[0].startsWith('Contrat'))).toBe(false);
});

test('a whole game counted without contract can be played to the end', async ({ page }) => {
  test.setTimeout(240_000);
  const errors = await openGame(page, {
    seed: 11,
    storage: prefs({ undo: false, autoForced: false, scoring: 'tunisian' }),
  });
  await newGame(page);
  await playUntil(page, 'end', 8000);
  const us = Number(await page.locator('#sUs').textContent());
  const them = Number(await page.locator('#sThem').textContent());
  expect(Math.max(us, them)).toBeGreaterThanOrEqual(1000);
  expect(errors).toEqual([]);
});
