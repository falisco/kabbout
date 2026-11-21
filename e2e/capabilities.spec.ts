// What the page says about the AI, from what the server answers on api/capabilities.
// The static server of these tests has no API; E2E_BASE_URL points the same tests at the Kabbout server.

import { expect, type Page } from '@playwright/test';
import { audit, openGame, test } from './helpers.ts';

const SERVED = !!process.env.E2E_BASE_URL;
const FEATURES = ['coach', 'conventions', 'referee', 'agent_seat'];
const NOTICE = '#aiNotice';
/** a word of the notice, by language */
const WORDS = {
  fr: { off: 'L’IA dort' },
  en: { off: 'The AI is asleep' },
  ar: { off: 'راقد' },
} as const;

/** opens the game and waits until the page has read what its origin answers about the server */
async function openAndAsk(page: Page, options: Parameters<typeof openGame>[1] = {}) {
  const answered = page.waitForResponse('**/api/capabilities');
  const errors = await openGame(page, options);
  await (await answered).finished();
  await expect(page.locator('#sheet [data-action="new"]')).toBeVisible();
  return errors;
}

/** makes the page's own origin answer as the Kabbout server would, every feature in the given state */
async function serverAnswers(page: Page, state: 'planned' | 'off' | 'on') {
  const features = Object.fromEntries(FEATURES.map((name) => [name, { state, hint: null }]));
  await page.route('**/api/capabilities', (route) =>
    route.fulfill({ json: { version: 1, ai: { configured: state === 'on', platform: null, model: null, variable: 'ANTHROPIC_API_KEY' }, features } }),
  );
}

test('played from plain files, the menu says nothing about the AI', async ({ page }) => {
  test.skip(SERVED, 'this run is served by the Kabbout server');
  const errors = await openAndAsk(page);
  await expect(page.locator(NOTICE)).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('served by Kabbout with no key, the menu says the AI is asleep', async ({ page }) => {
  test.skip(!SERVED, 'needs the Kabbout server: E2E_BASE_URL');
  const errors = await openGame(page, { lang: 'en' });
  // the coach is built and waits for its key
  await expect(page.locator(NOTICE)).toContainText(WORDS.en.off);
  await expect(page.locator(`${NOTICE} code`)).toHaveText('ANTHROPIC_API_KEY');
  expect(errors).toEqual([]);
});

for (const lang of ['fr', 'en', 'ar'] as const) {
  test(`a server whose features are off gets one accessible line in the menu (${lang})`, async ({ page }) => {
    await serverAnswers(page, 'off');
    const errors = await openGame(page, { lang });
    const notice = page.locator(NOTICE);
    await expect(notice).toContainText(WORDS[lang].off);
    await expect(notice.locator('code')).toHaveText('ANTHROPIC_API_KEY');
    expect(await audit(page)).toEqual([]);
    expect(errors).toEqual([]);
  });
}

test('what is only planned is promised nowhere: a server with nothing built yet gets no line', async ({ page }) => {
  await serverAnswers(page, 'planned');
  await openAndAsk(page);
  await expect(page.locator(NOTICE)).toHaveCount(0);
});

test('a server with everything on has nothing left to explain', async ({ page }) => {
  await serverAnswers(page, 'on');
  await openAndAsk(page);
  await expect(page.locator(NOTICE)).toHaveCount(0);
});
