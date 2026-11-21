// Non-regression of the auction: every bid the robots make in the reference games, and the reason
// given with it, must stay what fixtures/golden-bids.json recorded. golden.json holds the contracts
// and the cards; a change to a bid that leads to the same contract, or to a reason, shows only here.
// After an intentional change: `npm run test:golden:update`, then review the diff.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import type { Level, Scoring } from '../src/index.ts';
import { playRobotGame } from './helpers.ts';

const FIXTURE = new URL('./fixtures/golden-bids.json', import.meta.url);

interface Reference {
  seed: number;
  scoring: Scoring;
  level: Level;
}
/** the games of golden.test.ts */
const seeds = (count: number, first: number) => Array.from({ length: count }, (_, i) => first + i * 37);
const REFERENCES: Reference[] = [
  ...seeds(20, 1000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'normal' })),
  ...seeds(5, 2000).map((seed): Reference => ({ seed, scoring: 'tunisian', level: 'normal' })),
  ...seeds(5, 3000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'easy' })),
  ...seeds(3, 4000).map((seed): Reference => ({ seed, scoring: 'ffb', level: 'expert' })),
];

interface Auction extends Reference {
  bids: number;
  /** the first deal's auction in full, so that a change can be read and not only seen */
  firstDeal: string[];
  /** every bid of the game with its reason, as one hash */
  all: string;
}

function auction(reference: Reference): Auction {
  const said: string[] = [];
  let firstDeal: string[] | null = null;
  playRobotGame(reference.seed, {
    scoring: reference.scoring,
    level: reference.level,
    beforeMove(state, action) {
      if (action.kind === 'bid') {
        const { bid } = action;
        said.push(`${state.turn}:${bid.type === 'bid' ? `${bid.value}${bid.suit}` : bid.type} (${action.why ?? ''})`);
      } else if (firstDeal === null) firstDeal = [...said];
    },
  });
  return { ...reference, bids: said.length, firstDeal: firstDeal ?? said, all: createHash('sha256').update(said.join('\n')).digest('hex') };
}

test(`the auctions of the ${REFERENCES.length} reference games stay as recorded, bid for bid and reason for reason`, () => {
  const actual = REFERENCES.map(auction);
  if (process.env.UPDATE_GOLDEN) {
    writeFileSync(FIXTURE, JSON.stringify(actual, null, 1) + '\n');
    return;
  }
  const expected: Auction[] = JSON.parse(readFileSync(FIXTURE, 'utf8'));
  assert.equal(actual.length, expected.length);
  for (let i = 0; i < expected.length; i++) {
    const { seed, scoring, level } = expected[i];
    assert.deepEqual(actual[i], expected[i], `the auction of game ${seed} (${scoring}, ${level}) changed`);
  }
});
