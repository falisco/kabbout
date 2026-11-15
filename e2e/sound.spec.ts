// Sound cannot be listened to here; these tests hold what can be checked: which sounds start, when,
// and that silence means silence.

import { expect, type Page } from '@playwright/test';
import { newGame, openGame, playUntil, prefs, tapCard, test } from './helpers.ts';

/** Counts every sound started in the page: recorded samples and synthesised notes. */
async function listen(page: Page) {
  await page.addInitScript(() => {
    const w = window as unknown as { heard: { samples: number; notes: number } };
    w.heard = { samples: 0, notes: 0 };
    const count = (proto: { start: (...a: unknown[]) => void }, key: 'samples' | 'notes') => {
      const start = proto.start;
      proto.start = function (...a: unknown[]) {
        w.heard[key]++;
        return start.apply(this, a);
      };
    };
    count(AudioBufferSourceNode.prototype as never, 'samples');
    count(OscillatorNode.prototype as never, 'notes');
  });
}
const heard = (page: Page) => page.evaluate(() => (window as unknown as { heard: { samples: number; notes: number } }).heard);

test('with effects on: cards are heard, and a chime tells the player it is their turn', async ({ page }) => {
  await listen(page);
  const errors = await openGame(page, { fast: false, storage: prefs({ sound: true, undo: false, autoForced: false }) });
  await newGame(page);
  // the recorded sounds are decoded after the first click; wait for the deal's own to have been played or skipped
  await playUntil(page, 'bid');
  const atMyTurn = await heard(page);
  expect(atMyTurn.notes, 'your-turn chime: two notes of two oscillators').toBe(4);
  await page.locator('#bid [data-action="pass"]').click();
  await playUntil(page, 'play');
  const h = await heard(page);
  expect(h.samples, 'bids and cards').toBeGreaterThan(3);
  expect(h.notes, 'a second chime for the second turn').toBeGreaterThanOrEqual(8);
  expect(errors).toEqual([]);
});

test('a card that may not be played shakes and is refused, without being played', async ({ page }) => {
  await listen(page);
  await openGame(page, { storage: prefs({ sound: true, undo: false, autoForced: false }) });
  await newGame(page);
  // play until the hand holds a card the rules forbid
  for (let i = 0; i < 40; i++) {
    await playUntil(page, 'play');
    if (await page.locator('#hand.myturn .card.no').count()) break;
    await tapCard(page.locator('#hand.myturn .card.ok').first());
  }
  const no = page.locator('#hand.myturn .card.no').first();
  const before = await page.locator('#hand .card').count();
  const notes = (await heard(page)).notes;
  await tapCard(no, { force: true });
  await expect(no).toHaveClass(/nope/);
  expect(await page.locator('#hand .card').count()).toBe(before);
  expect((await heard(page)).notes).toBe(notes + 1);
});

test('with effects and music off, nothing is played', async ({ page }) => {
  await listen(page);
  await openGame(page, { storage: prefs({ sound: false, music: false, undo: false }) });
  await newGame(page);
  await playUntil(page, 'play');
  await tapCard(page.locator('#hand.myturn .card.ok').first());
  await playUntil(page, 'play');
  expect(await heard(page)).toEqual({ samples: 0, notes: 0 });
});

test('music starts when switched on and stops scheduling when switched off', async ({ page }) => {
  await page.clock.install();
  await listen(page);
  await openGame(page, { storage: prefs({ sound: false, music: false }) });
  await newGame(page);
  await page.locator('#bSettings').click();
  await page.locator('#sheet [data-setting="music"][data-value="on"]').click();
  // a chord: four pads and a bass, at least
  await expect.poll(async () => (await heard(page)).notes).toBeGreaterThanOrEqual(5);
  await page.locator('#sheet [data-setting="music"][data-value="off"]').click();
  const n = (await heard(page)).notes;
  // several chords' worth of the page's clock goes by: none is scheduled
  await page.clock.runFor(20_000);
  expect((await heard(page)).notes).toBe(n);
});
