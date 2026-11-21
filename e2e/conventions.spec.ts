// Agreeing with the partner before a game: the ready cards, the player's own sentence when the server
// reads one, the card read back, and the partner's reasons once the two have agreed. The static server
// of these tests has no API: the page's own origin is made to answer as the Kabbout server does, so what
// is checked here is the screen, not the model.

import { expect, type Page } from '@playwright/test';
import { audit, newGame, nextMoment, openGame, test } from './helpers.ts';

const BOLD = { open: 70, jack: 30, nine: 20, threeTrumps: 10, belote: 20, ace: 10, aces: 'whenOverbid' };
const WORDS = {
  fr: {
    bold: 'Audacieuse',
    own: 'La vôtre',
    jack: 'Le valet d’atout : le partenaire monte de 30.',
    classic: 'conventions classiques',
    off: 'avec vos mots',
  },
  en: {
    bold: 'Bold',
    own: 'Your own',
    jack: 'The jack of trumps: the partner raises by 30.',
    classic: 'classic conventions',
    off: 'in your own words',
  },
  ar: { bold: 'جريئة', own: 'متاعك', jack: 'الفالي متاع الأتو: الشريك يزيد 30.', classic: 'القواعد الكلاسيك', off: 'بكلامك' },
} as const;
type Lang = keyof typeof WORDS;

const ENTRY = '#sheet [data-action="agreeOpen"]';
const CONFIRM = '#sheet [data-action="agree"]';
const FIELD = '#agreeQ';
const CARD = '#agreeCard li';
const ready = (name: string) => `#sheet [data-setting="ready"][data-value="${name}"]`;
const stored = (page: Page) => page.evaluate(() => JSON.parse(localStorage.getItem('kabbout.conventions') ?? 'null') as unknown);

/** the page's origin answers about the conventions as the Kabbout server would */
async function serverReading(page: Page, state: 'on' | 'off', lang: Lang = 'fr'): Promise<string[]> {
  const features = { coach: { state, hint: null }, conventions: { state, hint: null } };
  await page.route('**/api/capabilities', (route) =>
    route.fulfill({ json: { version: 1, ai: { configured: state === 'on', platform: null, model: null, variable: 'ANTHROPIC_API_KEY' }, features } }),
  );
  const answered = page.waitForResponse('**/api/capabilities');
  const errors = await openGame(page, { lang });
  await (await answered).finished();
  return errors;
}

/** Presses `key` until `target` has the focus, as a player with a keyboard does. */
async function reach(page: Page, target: string, key: 'Tab' | 'Shift+Tab' = 'Tab') {
  for (let presses = 0; presses < 30; presses++) {
    if (await page.locator(target).evaluate((element) => element === document.activeElement)) return;
    await page.keyboard.press(key);
  }
  await expect(page.locator(target)).toBeFocused();
}

for (const lang of ['fr', 'en', 'ar'] as const) {
  test(`with no server and the keyboard alone, the two agree on a ready card, which is read back and kept (${lang})`, async ({ page }) => {
    const words = WORDS[lang];
    const errors = await openGame(page, { lang });
    expect(await stored(page)).toBeNull();

    await reach(page, ENTRY);
    await page.keyboard.press('Enter');
    // the classic card is the one looked at first, and it says what it is
    await expect(page.locator(ready('classic'))).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator(CARD)).toHaveCount(1);
    await expect(page.locator(CARD)).toContainText(words.classic);
    // nothing reads a sentence here: no field, and no word about a key
    await expect(page.locator(FIELD)).toHaveCount(0);
    await expect(page.locator('#agreeOff')).toHaveCount(0);
    expect(await audit(page)).toEqual([]);

    await reach(page, ready('bold'), 'Shift+Tab');
    await page.keyboard.press('Enter');
    await expect(page.locator(ready('bold'))).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator(CARD)).toHaveCount(3);
    await expect(page.locator(CARD).first()).toHaveText(words.jack);
    expect(await audit(page)).toEqual([]);
    // looking at a card agrees on nothing
    expect(await stored(page)).toBeNull();

    await reach(page, CONFIRM);
    await page.keyboard.press('Enter');
    await expect(page.locator('#agreeNow')).toContainText(words.bold);
    expect(await stored(page)).toEqual(BOLD);

    // and it is still the pair's card at the next visit
    await page.reload();
    await expect(page.locator('#agreeNow')).toContainText(words.bold);
    expect(errors).toEqual([]);
  });

  test(`a sentence becomes a card, read back from the card, with what had no place on it (${lang})`, async ({ page }) => {
    const words = WORDS[lang];
    const asked: unknown[] = [];
    await page.route('**/api/conventions/interpret', async (route) => {
      asked.push(route.request().postDataJSON());
      await route.fulfill({ json: { version: 1, card: { ...BOLD, nine: 10 }, unplaced: ['et souris <b>quand</b> tu as le dix'] } });
    });
    const errors = await serverReading(page, 'on', lang);
    await page.locator(ENTRY).click();

    await reach(page, FIELD, 'Shift+Tab');
    await page.keyboard.type('monte de 30 avec le valet');
    await page.keyboard.press('Enter');

    await expect(page.locator(CARD)).toHaveCount(2);
    await expect(page.locator(CARD).first()).toHaveText(words.jack);
    // it is no ready card: it is the player's own
    await expect(page.locator('#sheet [data-setting="ready"][aria-pressed="true"]')).toHaveCount(0);
    await expect(page.locator('#sheet .sec')).toContainText(words.own);
    // the player's own words come back as text, never as markup
    await expect(page.locator('#agreeUnplaced')).toContainText('et souris <b>quand</b> tu as le dix');
    await expect(page.locator('#agreeUnplaced b')).toHaveCount(0);
    expect(asked).toEqual([{ sentence: 'monte de 30 avec le valet', lang }]);
    // the way to agree is where the keyboard is
    await expect(page.locator(CONFIRM)).toBeFocused();
    expect(await audit(page)).toEqual([]);

    await page.keyboard.press('Enter');
    await expect(page.locator('#agreeNow')).toContainText(words.own);
    expect(await stored(page)).toEqual({ ...BOLD, nine: 10 });
    expect(errors).toEqual([]);
  });

  test(`without a key, the ready cards are there and one line says how to use one’s own words (${lang})`, async ({ page }) => {
    const errors = await serverReading(page, 'off', lang);
    await page.locator(ENTRY).click();
    await expect(page.locator('#agreeOff')).toContainText(WORDS[lang].off);
    await expect(page.locator('#agreeOff code')).toHaveText('ANTHROPIC_API_KEY');
    await expect(page.locator(FIELD)).toHaveCount(0);
    await page.locator(ready('cautious')).click();
    await expect(page.locator(CARD)).toHaveCount(2);
    expect(await audit(page)).toEqual([]);
    expect(errors).toEqual([]);
  });
}

test('a sentence that describes no card is said so, and stays in its field to be put another way', async ({ page }) => {
  await page.route('**/api/conventions/interpret', (route) =>
    route.fulfill({ status: 422, json: { version: 1, error: { code: 'not_understood', message: 'x' } } }),
  );
  // the server's refusal is logged by the browser as a failed request: that one line is expected
  const errors = await serverReading(page, 'on');
  await page.locator(ENTRY).click();
  await page.locator(FIELD).fill('il fait beau');
  await page.locator('#sheet [data-action="interpret"]').click();
  await expect(page.locator('#agreeError')).toContainText('pas reconnu de convention');
  await expect(page.locator(FIELD)).toHaveValue('il fait beau');
  // the card looked at has not moved
  await expect(page.locator(ready('classic'))).toHaveAttribute('aria-pressed', 'true');
  expect(await audit(page)).toEqual([]);
  expect(errors.filter((line) => !line.includes('422'))).toEqual([]);
});

test('Escape leaves the dialog for the menu, and nothing is agreed', async ({ page }) => {
  await openGame(page);
  await page.locator(ENTRY).click();
  await page.locator(ready('bold')).click();
  await page.keyboard.press('Escape');
  await expect(page.locator(ENTRY)).toBeVisible();
  expect(await stored(page)).toBeNull();
});

test('once the two have agreed, the partner gives its reasons beside its bids, and the opponents do not', async ({ page }) => {
  const partner = 'Selim';
  await openGame(page, { storage: { 'kabbout.conventions': JSON.stringify(BOLD) } });
  await expect(page.locator('#agreeNow')).toContainText('Audacieuse');
  await newGame(page);
  const reasons = page.locator('#bid .hist li:has(.why)');
  for (let turns = 0; turns < 8 && (await reasons.count()) === 0; turns++) {
    expect(await nextMoment(page)).toBe('bid');
    if ((await reasons.count()) === 0) await page.locator('#bid [data-action="pass"]').click();
  }
  expect(await reasons.count()).toBeGreaterThan(0);
  for (const line of await reasons.allTextContents()) expect(line).toContain(partner);
});

test('with nothing agreed, no robot gives a reason unless the player asked for them', async ({ page }) => {
  await openGame(page);
  await newGame(page);
  expect(await nextMoment(page)).toBe('bid');
  await page.locator('#bid [data-action="pass"]').click();
  await nextMoment(page);
  await expect(page.locator('#bid .hist .why')).toHaveCount(0);
});
