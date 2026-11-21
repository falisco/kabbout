// Agreeing with the partner before a game: the ready cards, the player's own sentence when the server
// reads one, the card read back, and what is not shown: a robot's reasons, the partner's included. The static server
// of these tests has no API: the page's own origin is made to answer as the Kabbout server does, so what
// is checked here is the screen, not the model.

import { expect, type Page } from '@playwright/test';
import { audit, newGame, nextMoment, openGame, playUntil, test } from './helpers.ts';

const SERVED = !!process.env.E2E_BASE_URL;
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
const ready = (name: string) => `#sheet [data-action="ready"][data-value="${name}"]`;
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
  test(`with the keyboard alone and no model, the two agree on a ready card, which is read back and kept (${lang})`, async ({ page }) => {
    const words = WORDS[lang];
    const errors = await openGame(page, { lang });
    expect(await stored(page)).toBeNull();

    await reach(page, ENTRY);
    await page.keyboard.press('Enter');
    // the classic card is the one looked at first, and it says what it is
    await expect(page.locator(ready('classic'))).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator(CARD)).toHaveCount(1);
    await expect(page.locator(CARD)).toContainText(words.classic);
    // played from plain files nothing reads a sentence: no field, and no word about a key. From the Kabbout
    // server there is one or the other, which the tests below look at with a server of their own
    if (!SERVED) {
      await expect(page.locator(FIELD)).toHaveCount(0);
      await expect(page.locator('#agreeOff')).toHaveCount(0);
    }
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
    await expect(page.locator('#sheet [data-action="ready"][aria-pressed="true"]')).toHaveCount(0);
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

for (const [agreed, storage] of [
  ['a card', { 'kabbout.conventions': JSON.stringify(BOLD) }],
  ['nothing', {}],
  // what an earlier version stored when its setting "robots' reasoning" was on: there is no such setting any more
  [
    'a card, in a browser that had the reasons switched on',
    { 'kabbout.conventions': JSON.stringify(BOLD), 'kabbout.prefs': JSON.stringify({ why: true }) },
  ],
] as const) {
  test(`no robot ever says what it holds, the partner no more than the others (the two agreed on ${agreed})`, async ({ page }) => {
    await openGame(page, { storage });
    await newGame(page);
    let heard = 0;
    for (let turns = 0; turns < 4 && (await nextMoment(page)) === 'bid'; turns++) {
      // the bids are there, and nothing beside them: a reason names cards, and nobody tells their partner their cards
      heard += await page.locator('#bid .hist li').count();
      await expect(page.locator('#bid .hist small')).toHaveCount(0);
      expect(await page.locator('#bid .hist li').allTextContents()).not.toContainEqual(expect.stringMatching(/soutien|atout| [♠♥♦♣] ·|\+\d0/));
      await page.locator('#bid [data-action="pass"]').click();
    }
    expect(heard).toBeGreaterThan(0);
  });
}

for (const [agreed, card] of [
  ['a card of their own', BOLD],
  ['the classic card', { ...BOLD, jack: 20, nine: 10, aces: 'atOnce' }],
  ['nothing', null],
] as const) {
  test(`the coach is asked with the card of the game, and with none when that is the classic one (the two agreed on ${agreed})`, async ({ page }) => {
    const asked: { conventions?: unknown }[] = [];
    await page.route('**/api/coach', async (route) => {
      asked.push(route.request().postDataJSON() as { conventions?: unknown });
      await route.fulfill({ json: { version: 1, answer: { text: 'Rien à redire.', source: 'model', tricks: [], cards: [] }, left: 1 } });
    });
    const features = { coach: { state: 'on', hint: null }, conventions: { state: 'on', hint: null } };
    await page.route('**/api/capabilities', (route) => route.fulfill({ json: { version: 1, ai: { variable: 'ANTHROPIC_API_KEY' }, features } }));
    const answered = page.waitForResponse('**/api/capabilities');
    await openGame(page, { storage: card ? { 'kabbout.conventions': JSON.stringify(card) } : {} });
    await (await answered).finished();

    await newGame(page);
    await playUntil(page, 'round');
    await page.locator('#sheet [data-action="debrief"]').click();
    await page.locator('#debriefQ').press('Enter');
    await expect(page.locator('#debriefAnswer')).toHaveText('Rien à redire.');

    expect(asked).toHaveLength(1);
    // the expert the coach compares the bids with bids by the pair's card; the classic one is the server's own default
    if (card === BOLD) expect(asked[0].conventions).toEqual(BOLD);
    else expect(asked[0]).not.toHaveProperty('conventions');
  });
}
