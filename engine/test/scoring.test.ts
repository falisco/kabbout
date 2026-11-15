// The shape of a round result. The scoring rules themselves are pinned, article by article, in
// ffb-contree.test.ts.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Contract, Seat } from '../src/index.ts';
import { scoreRound } from '../src/index.ts';
import { SPLIT, tricks } from './helpers.ts';

test('a result details the tricks, the points really made and the three rows of the score sheet', () => {
  const contract: Contract = { seat: 0, value: 100, suit: 'S', mult: 1 };
  const r = scoreRound(contract, tricks(SPLIT), { seat: 1, played: 2 });
  assert.deepEqual(r.contract, contract);
  assert.equal(r.made, true);
  assert.deepEqual(r.tricksWon, [4, 4]);
  assert.deepEqual(r.cardPoints, [124, 38]);
  assert.equal(r.takerPoints, 124);
  assert.equal(r.defencePoints, 58);
  assert.equal(r.capotTeam, null);
  assert.deepEqual(r.rows, { tricks: [120, 40], belote: [0, 20], contract: [100, 0] });
  assert.deepEqual(r.gained, [220, 60]);
});

test('each gain is the sum of its rows', () => {
  for (const mult of [1, 2, 4] as const)
    for (const value of [80, 150, 250] as const)
      for (const winners of [SPLIT, [0, 2, 0, 2, 0, 2, 0, 2] as Seat[]]) {
        const r = scoreRound({ seat: 0, value, suit: 'S', mult }, tricks(winners), { seat: 0, played: 2 });
        for (const i of [0, 1]) assert.equal(r.gained[i], r.rows.tricks[i] + r.rows.belote[i] + r.rows.contract[i]);
      }
});

test('a belote that was not fully played does not score', () => {
  const r = scoreRound({ seat: 0, value: 100, suit: 'S', mult: 1 }, tricks(SPLIT), { seat: 0, played: 1 });
  assert.deepEqual(r.rows.belote, [0, 0]);
});
