// Automated accessibility audit (axe-core, WCAG 2.0, 2.1 and 2.2, levels A and AA) of every screen and dialog.

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import { PLAY_BY_HAND, newGame, openGame, playUntil, test } from './helpers.ts';

const RULES = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function audit(page: Page) {
  const report = await new AxeBuilder({ page }).withTags(RULES).analyze();
  return report.violations.map((v) => `${v.impact} ${v.id}: ${v.nodes.length} × ${v.nodes[0]?.target.join(' ')}`);
}

/** the dialogs a button of the table opens, by that button */
const DIALOGS = { settings: '#bSettings', rules: '#bRules', 'score sheet': '#scoreBox', 'previous trick': '#bLast', 'card values': '#valBtn' };

for (const lang of ['fr', 'en', 'ar'] as const) {
  test(`menu has no accessibility violations (${lang})`, async ({ page }) => {
    await openGame(page, { lang });
    expect(await audit(page)).toEqual([]);
  });

  test(`bidding screen has no accessibility violations (${lang})`, async ({ page }) => {
    await openGame(page, { lang });
    await newGame(page);
    await playUntil(page, 'bid');
    expect(await audit(page)).toEqual([]);
  });

  test(`play screen has no accessibility violations (${lang})`, async ({ page }) => {
    await openGame(page, { lang, storage: PLAY_BY_HAND });
    await newGame(page);
    await playUntil(page, 'play');
    expect(await audit(page)).toEqual([]);
  });

  test(`the dialogs have no accessibility violations (${lang})`, async ({ page }) => {
    await openGame(page, { lang, storage: PLAY_BY_HAND });
    await newGame(page);
    await playUntil(page, 'play');
    for (const [name, opener] of Object.entries(DIALOGS)) {
      await page.locator(opener).click();
      await expect(page.locator('#sheet [data-action="close"]')).toBeFocused();
      expect(await audit(page), name).toEqual([]);
      await page.keyboard.press('Escape');
      await expect(page.locator('#modal')).toBeHidden();
    }
  });

  test(`the round result has no accessibility violations (${lang})`, async ({ page }) => {
    await openGame(page, { lang });
    await newGame(page);
    await playUntil(page, 'round');
    expect(await audit(page)).toEqual([]);
  });
}

test('the tutorial and the open phrase menu have no accessibility violations', async ({ page }) => {
  await openGame(page, { tutorial: true, storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  await expect(page.locator('#coach')).toBeVisible();
  await page.locator('#chatBtn').click();
  await expect(page.locator('#chatMenu')).toBeVisible();
  expect(await audit(page)).toEqual([]);
});

test('the score is read out with its numbers, not replaced by a label', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  const name = await page.locator('#scoreBox').evaluate((button) => button.textContent ?? '');
  expect(name).toMatch(/Nous\s*0/);
  expect(name).toMatch(/Eux\s*0/);
  expect(name).toContain('ouvrir la feuille de marque');
  expect(await page.locator('#scoreBox').getAttribute('aria-label')).toBeNull();
});
