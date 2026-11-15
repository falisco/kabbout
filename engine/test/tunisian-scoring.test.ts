// The Tunisian way of counting, cell by cell: the value bid decides who scores and is never added.
// Whether a contract is made is the same question as under the federation's rules.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { BidValue, Contract, Seat } from '../src/index.ts';
import { EngineError, newGame, scoreRound, seededRng, viewFor } from '../src/index.ts';
import { ALL_DEFENCE, ALL_TAKERS, BELOTE, SPLIT, tricks } from './helpers.ts';

const contract = (value: BidValue, mult: 1 | 2 | 4 = 1): Contract => ({ seat: 0, value, suit: 'S', mult });
const score = (value: BidValue, mult: 1 | 2 | 4, winners: Seat[], belote: keyof typeof BELOTE = 'none') =>
  scoreRound(contract(value, mult), tricks(winners), BELOTE[belote], 'tunisian');

test('made: each side scores what it made, rounded, and the contract is not added', () => {
  const r = score(100, 1, SPLIT);
  assert.equal(r.made, true);
  assert.deepEqual(r.rows, { tricks: [120, 40], belote: [0, 0], contract: [0, 0] });
  assert.deepEqual(r.gained, [120, 40]);
  // the federation's count of the same deal adds the 100
  assert.deepEqual(scoreRound(contract(100), tricks(SPLIT), null).gained, [220, 40]);
});

test('made: each side keeps its own belote', () => {
  assert.deepEqual(score(100, 1, SPLIT, 'defence').gained, [120, 60]);
  assert.deepEqual(score(100, 1, SPLIT, 'takers').gained, [140, 40]);
});

test('failed: 160 to the defence, nothing to the takers, whatever was bid', () => {
  for (const value of [130, 140, 160] as const) {
    const r = score(value, 1, SPLIT);
    assert.equal(r.made, false);
    assert.deepEqual(r.gained, [0, 160]);
  }
});

test('failed: the belote goes to the defence, whoever held it', () => {
  // 124 + 20 = 144, short of 150
  assert.deepEqual(score(150, 1, SPLIT, 'takers').gained, [0, 180]);
  assert.deepEqual(score(130, 1, SPLIT, 'defence').gained, [0, 180]);
});

test('contré: 320 to the side that wins the deal, surcontré: 640, nothing to the other', () => {
  assert.deepEqual(score(100, 2, SPLIT).gained, [320, 0]);
  assert.deepEqual(score(100, 4, SPLIT).gained, [640, 0]);
  assert.deepEqual(score(130, 2, SPLIT).gained, [0, 320]);
  assert.deepEqual(score(130, 4, SPLIT).gained, [0, 640]);
  // and the belote with it, whoever held it
  assert.deepEqual(score(100, 2, SPLIT, 'defence').gained, [340, 0]);
  assert.deepEqual(score(150, 4, SPLIT, 'takers').gained, [0, 660]);
});

test('the eight tricks without having bid them: 250', () => {
  assert.deepEqual(score(80, 1, ALL_TAKERS).gained, [250, 0]);
  assert.deepEqual(score(80, 1, ALL_TAKERS, 'takers').gained, [270, 0]);
  // the defence takes them all: the contract fails, and 250 replaces the 160
  assert.deepEqual(score(80, 1, ALL_DEFENCE).gained, [0, 250]);
  assert.deepEqual(score(80, 1, ALL_DEFENCE, 'defence').gained, [0, 270]);
});

test('under a contre the eight tricks do not change the 320 / 640', () => {
  assert.deepEqual(score(80, 2, ALL_TAKERS).gained, [320, 0]);
  assert.deepEqual(score(80, 4, ALL_DEFENCE).gained, [0, 640]);
});

test('kabbout bid: 500 to whoever scores it, ×2 contré, ×4 surcontré', () => {
  assert.deepEqual(score(250, 1, ALL_TAKERS).gained, [500, 0]);
  assert.deepEqual(score(250, 2, ALL_TAKERS).gained, [1000, 0]);
  assert.deepEqual(score(250, 4, ALL_TAKERS).gained, [2000, 0]);
  assert.deepEqual(score(250, 1, SPLIT).gained, [0, 500]);
  assert.deepEqual(score(250, 2, SPLIT).gained, [0, 1000]);
  assert.deepEqual(score(250, 4, SPLIT, 'takers').gained, [0, 2020]);
});

test('whatever the deal: nothing in the contract row, gains are the sum of the rows, made as under the federation rules', () => {
  for (const mult of [1, 2, 4] as const)
    for (const value of [80, 120, 130, 160, 250] as const)
      for (const winners of [SPLIT, ALL_TAKERS, ALL_DEFENCE])
        for (const belote of ['none', 'takers', 'defence'] as const) {
          const r = score(value, mult, winners, belote);
          const ffb = scoreRound(contract(value, mult), tricks(winners), BELOTE[belote]);
          assert.deepEqual(r.rows.contract, [0, 0]);
          for (const i of [0, 1]) assert.equal(r.gained[i], r.rows.tricks[i] + r.rows.belote[i]);
          assert.equal(r.made, ffb.made);
          assert.deepEqual(r.cardPoints, ffb.cardPoints);
          assert.equal(r.capotTeam, ffb.capotTeam);
          assert.ok(r.gained.every((g) => g % 10 === 0));
        }
});

test('a game is counted the federation way unless it is created otherwise, and says which to its players', () => {
  assert.equal(newGame(seededRng(1)).state.scoring, 'ffb');
  const state = newGame(seededRng(1), { scoring: 'tunisian' }).state;
  assert.equal(state.scoring, 'tunisian');
  assert.equal(viewFor(state, 2).scoring, 'tunisian');
  // the way of counting changes nothing to the deal itself
  assert.deepEqual(state.hands, newGame(seededRng(1)).state.hands);
  assert.throws(() => newGame(seededRng(1), { scoring: 'other' as never }), EngineError);
});
