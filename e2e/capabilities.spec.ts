// What the page says about the AI, from what the server answers on api/capabilities.
// The static server of these tests has no API; E2E_BASE_URL points the same tests at the Kabbout server.

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import { openGame, test } from './helpers.ts';

const SERVED = !!process.env.E2E_BASE_URL;
const FEATURES = ['coach', 'spoken_bids', 'referee', 'agent_seat'];
const NOTICE = '#aiNotice';
/** the words that tell the two notices apart, by language */
const WORDS = {
  fr: { planned: 'En chemin', off: 'L’IA dort' },
  en: { planned: 'On its way', off: 'The AI is asleep' },
  ar: { planned: 'في الثنية', off: 'راقد' },
} as const;

/** makes the page's own origin answer as the Kabbout server would, every feature in the given state */
async function serverAnswers(page: Page, state: 'planned' | 'off' | 'on') {
  const features = Object.fromEntries(FEATURES.map((name) => [name, { state, hint: null }]));
  await page.route('**/api/capabilities', (route) =>
    route.fulfill({ json: { version: 1, ai: { configured: state === 'on', platform: null, model: null, variable: 'KABBOUT_AI' }, features } }),
  );
}

test('played from plain files, the menu says nothing about the AI', async ({ page }) => {
  test.skip(SERVED, 'this run is served by the Kabbout server');
  const errors = await openGame(page);
  await expect(page.locator('#sheet [data-action="new"]')).toBeVisible();
  await expect(page.locator(NOTICE)).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('served by Kabbout with no key, the menu says the AI is on its way', async ({ page }) => {
  test.skip(!SERVED, 'needs the Kabbout server: E2E_BASE_URL');
  const errors = await openGame(page, { lang: 'en' });
  await expect(page.locator(NOTICE)).toContainText(WORDS.en.planned);
  await expect(page.locator(`${NOTICE} code`)).toHaveText('KABBOUT_AI');
  expect(errors).toEqual([]);
});

for (const lang of ['fr', 'en', 'ar'] as const) {
  for (const state of ['planned', 'off'] as const) {
    test(`a server whose features are ${state} gets one accessible line in the menu (${lang})`, async ({ page }) => {
      await serverAnswers(page, state);
      const errors = await openGame(page, { lang });
      const notice = page.locator(NOTICE);
      await expect(notice).toContainText(WORDS[lang][state]);
      await expect(notice.locator('code')).toHaveText('KABBOUT_AI');
      const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
      expect(report.violations.map((v) => `${v.impact} ${v.id}`)).toEqual([]);
      expect(errors).toEqual([]);
    });
  }
}

test('a server with everything on has nothing left to explain', async ({ page }) => {
  await serverAnswers(page, 'on');
  await openGame(page);
  await expect(page.locator('#sheet [data-action="new"]')).toBeVisible();
  await expect(page.locator(NOTICE)).toHaveCount(0);
});
