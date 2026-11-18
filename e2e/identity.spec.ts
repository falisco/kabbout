// Who the visitor is: a guest of the server, with a name of their own at their seat and in the menu.
// The static server of these tests has no API: nobody comes in and the game plays as it always did.
// E2E_BASE_URL points the same tests at the Kabbout server, which gives real names.

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import { PLAY_BY_HAND, newGame, openGame, playUntil, test } from './helpers.ts';

const SERVED = !!process.env.E2E_BASE_URL;
const SEAT = '#seat0 .name';
const LINE = '#playerName';
const RULES = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
/** a guest's name: the letter C and seven digits */
const GUEST = /^C\d{7}$/;
/** what the menu says before the name, and how the player's seat is labelled with no server, by language */
const WORDS = {
  fr: { line: 'Vous jouez sous le nom', you: 'Vous' },
  en: { line: 'You are playing as', you: 'You' },
  ar: { line: 'تلعب باسم', you: 'إنت' },
} as const;

/**
 * Makes the page's own origin answer api/session as the Kabbout server would.
 * @param known the name of the visitor when the server already knows them; null when it does not, and gives `given` to whoever comes in
 * @returns the methods the page asked with, in order
 */
async function serverSays(page: Page, known: string | null, given = 'C4821973'): Promise<string[]> {
  const asked: string[] = [];
  await page.route('**/api/session', (route) => {
    const method = route.request().method();
    asked.push(method);
    if (method === 'POST') return route.fulfill({ status: 201, json: { version: 1, player: { name: given } } });
    return route.fulfill({ json: { version: 1, player: known === null ? null : { name: known } } });
  });
  return asked;
}

async function audit(page: Page) {
  const report = await new AxeBuilder({ page }).withTags(RULES).analyze();
  return report.violations.map((v) => `${v.impact} ${v.id}`);
}

for (const lang of ['fr', 'en', 'ar'] as const) {
  test(`a first visit comes in as a guest, whose name is in the menu and at their seat (${lang})`, async ({ page }) => {
    const asked = await serverSays(page, null);
    const errors = await openGame(page, { lang });

    const line = page.locator(LINE);
    await expect(line).toContainText(WORDS[lang].line);
    await expect(line.locator('bdi')).toHaveText('C4821973');
    expect(await audit(page)).toEqual([]);

    await newGame(page);
    await expect(page.locator(SEAT)).toHaveText('C4821973');
    // the face of the seat keeps the first letter of "you", as with no server
    await expect(page.locator('#seat0 .avatar')).toContainText(WORDS[lang].you[0]);
    expect(await audit(page)).toEqual([]);
    // asked who they are, and came in once
    expect(asked).toEqual(['GET', 'POST']);
    expect(errors).toEqual([]);
  });
}

test('a visitor the server already knows keeps their name and is not made to come in again', async ({ page }) => {
  const asked = await serverSays(page, 'C0000042');
  await openGame(page);
  await expect(page.locator(`${LINE} bdi`)).toHaveText('C0000042');
  await newGame(page);
  await expect(page.locator(SEAT)).toHaveText('C0000042');
  expect(asked).toEqual(['GET']);
});

test('sentences about the player still say "you": only the seat carries the name', async ({ page }) => {
  await serverSays(page, 'C0000042');
  await openGame(page, { lang: 'en', storage: PLAY_BY_HAND });
  await newGame(page);
  await playUntil(page, 'play');
  await expect(page.locator('#hint')).toHaveText('Your turn');
  // on the whole table the name is written once, at the seat
  await expect(page.locator('bdi:visible', { hasText: 'C0000042' })).toHaveCount(1);
  await expect(page.locator(SEAT)).toHaveText('C0000042');
  await expect(page.locator('body')).not.toContainText(/C0000042 (leads|plays|takes|wins|bids|passes)/);
});

test('a name is shown as text, whatever the server sends', async ({ page }) => {
  const markup = '<img src=x onerror=alert(1)>';
  await serverSays(page, markup);
  const errors = await openGame(page);
  await expect(page.locator(`${LINE} bdi`)).toHaveText(markup);
  await newGame(page);
  await expect(page.locator(SEAT)).toHaveText(markup);
  await expect(page.locator('#seat0 img, #playerName img')).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('played from plain files, nobody comes in: no name, and the seat says "Vous"', async ({ page }) => {
  test.skip(SERVED, 'this run is served by the Kabbout server');
  const asked: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('/api/session')) asked.push(request.method());
  });
  const answered = page.waitForResponse('**/api/session');
  const errors = await openGame(page);
  await (await answered).finished();

  await newGame(page);
  await expect(page.locator(SEAT)).toHaveText(WORDS.fr.you);
  await expect(page.locator(LINE)).toHaveCount(0);
  // the page asked who it is, heard nothing of a server, and posted nothing
  expect(asked).toEqual(['GET']);
  expect(errors).toEqual([]);
});

test('served by Kabbout, a visitor has a name of their own, kept after a reload, in a cookie the page cannot read', async ({
  page,
  browser,
  baseURL,
}) => {
  test.skip(!SERVED, 'needs the Kabbout server: E2E_BASE_URL');
  const errors = await openGame(page);
  const name = page.locator(`${LINE} bdi`);
  await expect(name).toHaveText(GUEST);
  const mine = await name.textContent();

  await page.reload();
  await expect(name).toHaveText(mine!);
  await newGame(page);
  await expect(page.locator(SEAT)).toHaveText(mine!);

  // the secret is the browser's to send, not the page's to read
  const cookie = (await page.context().cookies(`${baseURL}/api/session`)).find((one) => one.name === 'kabbout_guest');
  expect(cookie).toMatchObject({ httpOnly: true, sameSite: 'Strict', path: '/api' });
  expect(await page.evaluate(() => document.cookie)).not.toContain('kabbout_guest');
  // and it goes to the API alone: the game's own files are asked without it
  expect(await page.context().cookies(`${baseURL}/`)).toEqual([]);

  // another browser is another visitor
  const elsewhere = await browser.newContext();
  const other = await elsewhere.newPage();
  await openGame(other);
  await expect(other.locator(`${LINE} bdi`)).toHaveText(GUEST);
  expect(await other.locator(`${LINE} bdi`).textContent()).not.toBe(mine);
  await elsewhere.close();
  expect(errors).toEqual([]);
});
