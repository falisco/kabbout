// Robots play complete games against each other; every action must be legal and every round balanced.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { GameState, Scoring } from '../src/index.ts';
import { legalBid, legalPlays } from '../src/index.ts';
import { playRobotGame } from './helpers.ts';

function playGame(seed: number, scoring: Scoring = 'ffb'): { state: GameState; rounds: number } {
  let rounds = 0;
  const state = playRobotGame(seed, {
    scoring,
    beforeMove(before, action) {
      if (action.kind === 'bid') assert.ok(legalBid(before, before.turn, action.bid), `illegal robot bid ${JSON.stringify(action.bid)}`);
      else if (action.kind === 'play') assert.ok(legalPlays(before, before.turn).includes(action.card), `illegal robot card ${action.card}`);
      else {
        rounds++;
        assertBalancedRound(before);
      }
    },
  });
  return { state, rounds };
}

function assertBalancedRound(state: GameState): void {
  const r = state.result!;
  assert.equal(state.tricks.length, 8);
  assert.ok(state.hands.every((h) => h.length === 0));
  const total = r.cardPoints[0] + r.cardPoints[1];
  assert.ok(r.capotTeam === null ? total === 162 : total === 252, `card points ${total}`);
  assert.ok(
    r.gained.every((g) => g % 10 === 0),
    `unrounded score ${r.gained}`,
  );
  assert.ok(r.gained[0] === 0 || r.gained[1] === 0 || (r.made && r.contract.mult === 1), 'both sides score only on a made, uncontred contract');
}

test('120 robot games finish with legal actions and balanced rounds', () => {
  let rounds = 0;
  for (let seed = 1; seed <= 120; seed++) {
    const { state, rounds: r } = playGame(seed);
    rounds += r;
    const [a, b] = state.scores;
    assert.ok(Math.max(a, b) >= state.target && a !== b);
    assert.equal(state.winner, a > b ? 0 : 1);
    assert.deepEqual(state.history.at(-1)!.scores, state.scores);
  }
  assert.ok(rounds > 500);
});

test('40 robot games counted the Tunisian way finish too, and no contract value is ever added', () => {
  for (let seed = 1; seed <= 40; seed++) {
    const { state } = playGame(seed, 'tunisian');
    const [a, b] = state.scores;
    assert.ok(Math.max(a, b) >= state.target && a !== b);
    assert.equal(state.scoring, 'tunisian');
    // the most a deal can bring: a surcontré kabbout, 2000, plus the belote
    let before = [0, 0];
    for (const h of state.history) {
      assert.deepEqual([h.scores[0] - before[0], h.scores[1] - before[1]], h.gained);
      assert.ok(Math.max(...h.gained) <= 2020);
      before = h.scores;
    }
  }
});

test('same seed, same game', () => {
  assert.deepEqual(playGame(42).state.scores, playGame(42).state.scores);
});
