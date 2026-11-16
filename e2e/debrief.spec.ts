// The coach of the server on the end-of-deal screen: the way in, the question, the answer and the trick
// it points at. The static server of these tests has no API: the page's own origin is made to answer
// as the Kabbout server does, so what is checked here is the screen, not the coach.

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, type Route } from '@playwright/test';
import { newGame, openGame, playUntil, test } from './helpers.ts';

const SERVED = !!process.env.E2E_BASE_URL;
const WORDS = {
  fr: {
    open: 'Demander au coach',
    question: 'Qu’est-ce que j’ai mal joué ?',
    trick: 'Voir le pli 4',
    title: 'Pli 4',
    off: 'Le coach dort',
    busy: 'Trop de questions',
  },
  en: {
    open: 'Ask the coach',
    question: 'What did I do wrong?',
    trick: 'See trick 4',
    title: 'Trick 4',
    off: 'The coach is asleep',
    busy: 'Too many questions',
  },
  ar: { open: 'اسأل الكوتش', question: 'شنوّة لعبت غالط؟', trick: 'شوف البلي 4', title: 'البلي 4', off: 'الكوتش راقد', busy: 'برشا أسئلة' },
} as const;
type Lang = keyof typeof WORDS;

const ENTRY = '#sheet [data-action="debrief"]';
const QUESTION = '#debriefQ';
const ANSWER = '#debriefAnswer';
const NEXT = '#sheet [data-action="next"]';

interface Asked {
  record: { scoring: string; dealer: number; bids: unknown[]; tricks: { seat: number; card: string }[][] };
  seat: number;
  lang: string;
  question: string;
}

/** the page's origin answers about the coach as the Kabbout server would */
async function serverWithCoach(page: Page, state: 'on' | 'off') {
  const planned = { state: 'planned', hint: null };
  const features = { coach: { state, hint: null }, spoken_bids: planned, referee: planned, agent_seat: planned };
  await page.route('**/api/capabilities', (route) =>
    route.fulfill({ json: { version: 1, ai: { configured: state === 'on', platform: null, model: null, variable: 'KABBOUT_AI' }, features } }),
  );
}

/** the coach answers each question with what `reply` gives; the questions asked are returned as they come */
async function coachAnswers(page: Page, reply: (asked: Asked, route: Route) => Promise<void> | void): Promise<Asked[]> {
  const questions: Asked[] = [];
  await page.route('**/api/coach', async (route) => {
    const asked = route.request().postDataJSON() as Asked;
    questions.push(asked);
    await reply(asked, route);
  });
  return questions;
}
const saying =
  (text: string, more: object = {}) =>
  (_asked: Asked, route: Route) =>
    route.fulfill({ json: { version: 1, answer: { text, source: 'model', tricks: [4], cards: [], ...more } } });

/** a deal played to its result, on a server whose coach is in the given state */
async function dealPlayed(page: Page, state: 'on' | 'off', lang: Lang = 'fr'): Promise<string[]> {
  await serverWithCoach(page, state);
  const answered = page.waitForResponse('**/api/capabilities');
  const errors = await openGame(page, { lang });
  await (await answered).finished();
  await newGame(page);
  await playUntil(page, 'round');
  return errors;
}

async function axe(page: Page): Promise<string[]> {
  const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return report.violations.map((v) => `${v.impact} ${v.id}`);
}

for (const lang of ['fr', 'en', 'ar'] as const) {
  test(`after a deal, the coach is asked by keyboard and its answer opens the trick it names (${lang})`, async ({ page }) => {
    const words = WORDS[lang];
    const questions = await coachAnswers(page, saying(`${words.title}: 10 ♣.`));
    const errors = await dealPlayed(page, 'on', lang);

    // the result keeps its own button first; the coach is the next stop
    await expect(page.locator(NEXT)).toBeFocused();
    await expect(page.locator(ENTRY)).toHaveText(words.open);
    await page.keyboard.press('Tab');
    await expect(page.locator(ENTRY)).toBeFocused();
    await page.keyboard.press('Enter');

    // the question the screen proposes is ready to be sent as it is
    await expect(page.locator(QUESTION)).toHaveValue(words.question);
    await expect(page.locator(ANSWER)).toHaveCount(0);
    expect(await axe(page)).toEqual([]);
    await page.locator(QUESTION).press('Enter');

    await expect(page.locator(ANSWER)).toHaveText(`${words.title}: 10 ♣.`);
    // said to screen readers too, after the word that the coach is looking
    await expect(page.locator('#sr')).toContainText(`${words.title}: 10 ♣.`);
    expect(questions).toHaveLength(1);
    expect(questions[0]).toMatchObject({ seat: 0, lang, question: words.question });
    expect(questions[0].record.tricks.map((plays) => plays.length)).toEqual(Array(8).fill(4));
    expect(new Set(questions[0].record.tricks.flat().map((p) => p.card)).size).toBe(32);
    expect(await axe(page)).toEqual([]);

    // the trick the answer names, then back to the answer with Escape
    const trick = page.locator('#sheet [data-action="trick"]');
    await expect(trick).toHaveText(words.trick);
    await trick.click();
    await expect(page.locator('#sheet h2')).toHaveText(words.title);
    await expect(page.locator('#sheet .lt .card')).toHaveCount(4);
    await expect(page.locator('#sheet .lt figure.w')).toHaveCount(1);
    const fourth = questions[0].record.tricks[3].map((p) => p.card);
    expect(await page.locator('#sheet .lt .card').evaluateAll((cards) => cards.map((c) => (c as HTMLElement).dataset.card))).toEqual(fourth);
    expect(await axe(page)).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(page.locator(ANSWER)).toBeVisible();

    // and back to the result, which has not moved
    await page.keyboard.press('Escape');
    await expect(page.locator(NEXT)).toBeVisible();
    expect(errors).toEqual([]);
  });

  test(`without a key, the end of a deal says in one line how to wake the coach (${lang})`, async ({ page }) => {
    const errors = await dealPlayed(page, 'off', lang);
    const hint = page.locator('#coachOff');
    await expect(hint).toContainText(WORDS[lang].off);
    await expect(hint.locator('code')).toHaveText('KABBOUT_AI');
    await expect(page.locator(ENTRY)).toHaveCount(0);
    expect(await axe(page)).toEqual([]);
    expect(errors).toEqual([]);
  });
}

test('played from plain files, the end of a deal says nothing of a coach', async ({ page }) => {
  test.skip(SERVED, 'this run is served by the Kabbout server');
  const errors = await openGame(page);
  await newGame(page);
  await playUntil(page, 'round');
  await expect(page.locator(ENTRY)).toHaveCount(0);
  await expect(page.locator('#coachOff')).toHaveCount(0);
  expect(errors).toEqual([]);
});

test('what the coach says is shown as text, whatever it holds', async ({ page }) => {
  const text = '<img src=x onerror="document.title=\'pwned\'"><b>pli 4</b> & "voilà"';
  await coachAnswers(page, saying(text, { tricks: [4, '<script>', 99, 2] }));
  const errors = await dealPlayed(page, 'on');
  const title = await page.title();
  await page.locator(ENTRY).click();
  await page.locator('#sheet [data-action="ask"]').click();

  await expect(page.locator(ANSWER)).toHaveText(text);
  await expect(page.locator(`${ANSWER} *`)).toHaveCount(0);
  expect(await page.title()).toBe(title);
  // only the numbers that name a trick became buttons
  await expect(page.locator('#sheet [data-action="trick"]')).toHaveText(['Voir le pli 2', 'Voir le pli 4']);
  expect(errors).toEqual([]);
});

test('the player’s own question is the one sent, and an answer without the model says so', async ({ page }) => {
  const questions = await coachAnswers(page, saying('Au pli 4, vous avez joué le 10 de trèfle.', { source: 'summary' }));
  await dealPlayed(page, 'on');
  await page.locator(ENTRY).click();
  await expect(page.locator('#debriefPlain')).toHaveCount(0);
  await page.locator(QUESTION).fill('Fallait-il couper au pli 4 ?');
  await page.locator('#sheet [data-action="ask"]').click();

  await expect(page.locator(ANSWER)).toBeVisible();
  await expect(page.locator('#debriefPlain')).toBeVisible();
  expect(questions[0].question).toBe('Fallait-il couper au pli 4 ?');
  // the question stays in its field, to be asked again or changed
  await expect(page.locator(QUESTION)).toHaveValue('Fallait-il couper au pli 4 ?');
});

test('a coach that cannot answer says why, and can be asked again', async ({ page }) => {
  let status = 429;
  const questions = await coachAnswers(page, (asked, route) =>
    status === 200 ? saying('Au pli 4.')(asked, route) : route.fulfill({ status, json: { error: { code: 'x', message: 'x' } } }),
  );
  await dealPlayed(page, 'on');
  await page.locator(ENTRY).click();
  const ask = page.locator('#sheet [data-action="ask"]');

  await ask.click();
  await expect(page.locator('#debriefError')).toContainText(WORDS.fr.busy);
  expect(await axe(page)).toEqual([]);
  status = 422;
  await ask.click();
  await expect(page.locator('#debriefError')).toContainText('rejouer cette donne');
  status = 500;
  await ask.click();
  await expect(page.locator('#debriefError')).toContainText('ne répond pas');
  status = 200;
  await ask.click();
  await expect(page.locator(ANSWER)).toHaveText('Au pli 4.');
  await expect(page.locator('#debriefError')).toHaveCount(0);
  expect(questions).toHaveLength(4);
});

test('an answer belongs to its deal: the next deal starts with none, even if one arrives late', async ({ page }) => {
  let release: () => void = () => {};
  const held = new Promise<void>((resolve) => (release = resolve));
  let slow = false;
  await coachAnswers(page, async (asked, route) => {
    if (slow) await held;
    await saying('Au pli 4.')(asked, route);
  });
  await dealPlayed(page, 'on');

  // an answer, then the next deal: nothing of it is left
  await page.locator(ENTRY).click();
  await page.locator('#sheet [data-action="ask"]').click();
  await expect(page.locator(ANSWER)).toBeVisible();
  await page.locator('#sheet [data-action="close"]').click();
  await page.locator(NEXT).click();
  await playUntil(page, 'round');
  await page.locator(ENTRY).click();
  await expect(page.locator(ANSWER)).toHaveCount(0);

  // a question still in the air when the table moves on: its answer is about a deal that is gone
  slow = true;
  await page.locator('#sheet [data-action="ask"]').click();
  await expect(page.locator('#debriefWait')).toBeVisible();
  await expect(page.locator('#sheet [data-action="ask"]')).toHaveCount(0);
  await page.locator('#sheet [data-action="close"]').click();
  await page.locator(NEXT).click();
  const answered = page.waitForResponse('**/api/coach');
  release();
  await (await answered).finished();
  await playUntil(page, 'round');
  await page.locator(ENTRY).click();
  await expect(page.locator(QUESTION)).toBeVisible();
  await expect(page.locator(ANSWER)).toHaveCount(0);
  await expect(page.locator('#debriefWait')).toHaveCount(0);
});
