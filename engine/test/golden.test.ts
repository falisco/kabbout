// Non-regression: seeded robot games must replay exactly as recorded in fixtures/golden.json,
// card for card. Any change to the deal, the rules, the scoring or the robots shows up here.
// After an intentional change: `npm run test:golden:update`, then review the diff of golden.json.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import type { GameState, Level, Scoring } from '../src/index.ts';
import { playRobotGame } from './helpers.ts';

const FIXTURE = new URL('./fixtures/golden.json', import.meta.url);

interface Reference {
  seed: number;
  scoring: Scoring;
  level: Level;
}
const seeds = (count: number, first: number) => Array.from({ length: count }, (_, i) => first + i * 37);
const REFERENCES: Reference[] = [
  ...seeds(20, 1000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'normal' })),
  ...seeds(5, 2000).map((seed): Reference => ({ seed, scoring: 'tunisian', level: 'normal' })),
  ...seeds(5, 3000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'easy' })),
  ...seeds(3, 4000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'expert' })),
];

interface Fingerprint extends Reference {
  deals: number;
  scores: [number, number];
  winner: number | null;
  /** one entry per played round: value+suit, multiplier, taker seat, made, points gained */
  rounds: string[];
  /** one entry per played round: its eight tricks, each as the cards in the order played and who took it */
  cards: string[];
  /** first deal of the game, to catch shuffle or dealing changes */
  firstHands: string[];
}

const tricksPlayed = (state: GameState) =>
  state.tricks.map((trick) => `${trick.plays.map((p) => `${p.seat}:${p.card}`).join(' ')} >${trick.winner}`).join(' | ');

function fingerprint(reference: Reference): Fingerprint {
  let firstHands: string[] = [];
  const cards: string[] = [];
  const end = playRobotGame(reference.seed, {
    scoring: reference.scoring,
    level: reference.level,
    afterMove(state, events) {
      if (!firstHands.length) firstHands = state.hands.map((h) => h.join(' '));
      if (state.phase === 'roundEnd' && events.length) cards.push(tricksPlayed(state));
    },
  });
  return {
    ...reference,
    deals: end.round,
    scores: end.scores,
    winner: end.winner,
    rounds: end.history.map(
      (h) => `${h.contract.value}${h.contract.suit} x${h.contract.mult} @${h.contract.seat} ${h.made ? 'made' : 'down'} +${h.gained.join('/')}`,
    ),
    cards,
    firstHands,
  };
}

test(`${REFERENCES.length} reference games replay exactly, card for card`, () => {
  const actual = REFERENCES.map(fingerprint);
  if (process.env.UPDATE_GOLDEN) {
    writeFileSync(FIXTURE, JSON.stringify(actual, null, 1) + '\n');
    return;
  }
  const expected: Fingerprint[] = JSON.parse(readFileSync(FIXTURE, 'utf8'));
  assert.equal(actual.length, expected.length);
  for (let i = 0; i < expected.length; i++) {
    const { seed, scoring, level } = expected[i];
    assert.deepEqual(actual[i], expected[i], `game ${seed} (${scoring}, ${level}) changed`);
  }
});
