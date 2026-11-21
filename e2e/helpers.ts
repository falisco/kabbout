import AxeBuilder from '@axe-core/playwright';
import { test as base, errors as playwrightErrors, expect, type Locator, type Page } from '@playwright/test';
import { CoverageReport } from 'monocart-coverage-reports';
import { coverageOptions } from '../scripts/coverage-options.mjs';

/**
 * The `test` every spec uses. Under `npm run test:coverage` it also records which lines of the game
 * each test ran (Chromium only: the other browsers cannot tell) and hands them to the coverage report.
 */
export const test = base.extend<{ coverage: void }>({
  coverage: [
    async ({ page, browserName }, use) => {
      const recording = !!process.env.COVERAGE && browserName === 'chromium';
      if (recording) await page.coverage.startJSCoverage({ resetOnNavigation: false });
      await use();
      if (recording) await new CoverageReport(coverageOptions).add(await page.coverage.stopJSCoverage());
    },
    { auto: true },
  ],
});

/**
 * The accessibility audit of what is on screen (axe-core; WCAG 2.0, 2.1 and 2.2, levels A and AA).
 * @returns one line per rule broken, none when the screen is clean: what it weighs, its name, how often and where first
 */
export async function audit(page: Page): Promise<string[]> {
  const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return report.violations.map((v) => `${v.impact} ${v.id}: ${v.nodes.length} × ${v.nodes[0]?.target.join(' ')}`);
}

export interface OpenOptions {
  seed?: number;
  /** robots answer instantly */
  fast?: boolean;
  /** localStorage entries set before the first load only (reloads keep what the game saved) */
  storage?: Record<string, string>;
  tutorial?: boolean;
  /** forces the language; without it the game follows the browser (French in these tests) */
  lang?: 'fr' | 'en' | 'ar';
}

/** Opens the game and records page errors / console errors in the returned array. */
export async function openGame(page: Page, opts: OpenOptions = {}): Promise<string[]> {
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => {
    if (m.type() === 'error') errors.push(m.text());
  });
  const storage: Record<string, string> = { ...opts.storage };
  if (!opts.tutorial) storage['kabbout.tuto'] ??= JSON.stringify({ done: {}, off: true });
  await page.addInitScript((entries) => {
    if (sessionStorage.getItem('e2e-init')) return;
    sessionStorage.setItem('e2e-init', '1');
    for (const [k, v] of Object.entries(entries)) localStorage.setItem(k, v);
  }, storage);
  const qs = new URLSearchParams();
  qs.set('seed', String(opts.seed ?? 7));
  if (opts.fast ?? true) qs.set('fast', '');
  if (opts.lang) qs.set('lang', opts.lang);
  await page.goto('/?' + qs.toString());
  return errors;
}

/** settings as a first visit would store them, with the changes a test needs */
export const prefs = (changes: Record<string, unknown>) => ({ 'kabbout.prefs': JSON.stringify(changes) });
/** no time to take a move back, and no card played for the player: every move is the test's own */
export const PLAY_BY_HAND = prefs({ undo: false, autoForced: false });

/** The rows of the score table on screen: label, us, them. */
export const sheetRows = (page: Page): Promise<string[][]> =>
  page.locator('#sheet table.res tbody tr').evaluateAll((trs) => trs.map((tr) => [...tr.querySelectorAll('td')].map((td) => td.textContent!.trim())));
/** the number in a cell of the score table: '+120' is 120 */
export const points = (cell: string): number => Number(cell.replace('+', ''));

export async function newGame(page: Page): Promise<void> {
  await page.locator('#sheet [data-action="new"]').click();
  await expect(page.locator('#hand .card')).toHaveCount(8);
}

type Moment = 'bid' | 'play' | 'round' | 'end' | 'kabbout';

/** Waits until the human has something to do (or the game ends) and says what. */
export async function nextMoment(page: Page, timeout = 15_000): Promise<Moment> {
  const handle = await page.waitForFunction(
    () => {
      const q = (s: string) => document.querySelector(s);
      // the sheet keeps its last content while hidden: only trust it when the dialog is open
      if (q('#kabbout:not([hidden])')) return 'kabbout';
      if (q('#modal:not([hidden]) [data-action="next"]')) return 'round';
      if (q('#modal:not([hidden]) #sheet[data-modal="end"] [data-action="new"]')) return 'end';
      if (q('#bid:not([hidden]) [data-action="pass"]')) return 'bid';
      if (q('#hand.myturn .card.ok')) return 'play';
      return null;
    },
    undefined,
    { timeout },
  );
  return (await handle.jsonValue()) as Moment;
}

/**
 * Where a card is tapped, as a share of its box: near its top-left corner, where its rank and suit
 * are printed. In a hand held as a fan each card lies under the next, so the middle of its box
 * shows the neighbour; and a tilted card does not reach the very corner of its box.
 */
export const CARD_INDEX: [number, number] = [0.12, 0.12];
/** Taps a card of the hand the way a player does. `force` taps even a card the rules forbid. */
export async function tapCard(card: Locator, options: { force?: boolean; timeout?: number } = {}): Promise<void> {
  const box = await card.boundingBox({ timeout: options.timeout });
  // a card that has just left the hand has no box: the click then waits for it and times out, as for any missing control
  const position = box ? { x: box.width * CARD_INDEX[0], y: box.height * CARD_INDEX[1] } : undefined;
  await card.click({ position, ...options });
}

const CONTROL_FOR: Record<Exclude<Moment, 'end'>, string> = {
  bid: '#bid [data-action="pass"]',
  play: '#hand.myturn .card.ok',
  round: '#modal:not([hidden]) [data-action="next"]',
  kabbout: '#kabbout',
};

/** Plays (passing every bid, first legal card) until `stop` is reached. */
export async function playUntil(page: Page, stop: Moment, maxSteps = 2000): Promise<void> {
  for (let i = 0; i < maxSteps; i++) {
    const moment = await nextMoment(page);
    if (moment === stop) return;
    if (moment === 'end') throw new Error(`game ended before reaching "${stop}"`);
    try {
      const control = page.locator(CONTROL_FOR[moment]).first();
      if (moment === 'play') await tapCard(control, { timeout: 3000 });
      else await control.click({ timeout: 3000 });
    } catch (error) {
      // the game may move on by itself (forced card, robot) between seeing a control and clicking it:
      // the control is then gone, which is not a failure. Anything else is.
      if (!(error instanceof playwrightErrors.TimeoutError)) throw error;
    }
  }
  throw new Error(`"${stop}" not reached`);
}

export async function handCards(page: Page): Promise<string[]> {
  return page.locator('#hand .card').evaluateAll((cards) => cards.map((card) => (card as HTMLElement).dataset.card ?? ''));
}
