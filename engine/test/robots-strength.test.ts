// The three levels must stay in order. Each match is played in duplicate (every deal twice, the
// sides swapped) on fixed seeds, so the result is the same on every run; when these tests
// were written normal led easy by about 10 points a deal and expert led normal by more than 30.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { GameState, Level } from '../src/index.ts';
import { aiBid, aiPlay, apply, newGame, seededRng, viewFor } from '../src/index.ts';

/** One deal with `levels[seat]`; points won by team 0 minus team 1 (0 if everyone passes). */
function deal(seed: number, levels: Level[]): number {
  const rng = seededRng(seed);
  const cards = seededRng(seed + 7919);
  let s: GameState = newGame(rng).state;
  while (s.round === 1 && s.phase !== 'roundEnd') {
    const v = viewFor(s, s.turn);
    s = apply(s, s.turn, s.phase === 'bidding' ? { kind: 'bid', ...aiBid(v) } : { kind: 'play', card: aiPlay(v, cards, levels[s.turn]) }, rng).state;
  }
  return s.round === 1 ? s.result!.gained[0] - s.result!.gained[1] : 0;
}
/** Average points per deal that pairs of `a` take from pairs of `b`. */
function lead(a: Level, b: Level, deals: number): number {
  let sum = 0;
  for (let seed = 1; seed <= deals; seed++) sum += deal(seed, [a, b, a, b]) - deal(seed, [b, a, b, a]);
  return sum / (2 * deals);
}

test('normal (remembers the cards) beats easy (forgets them)', () => {
  assert.ok(lead('normal', 'easy', 1500) > 5);
});

test('expert (plays the deal out in its head) beats normal', () => {
  assert.ok(lead('expert', 'normal', 120) > 10);
});
