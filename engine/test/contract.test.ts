// The contract between the engine and its clients (the app today, a server tomorrow):
// what a player is allowed to receive, and what the engine accepts from outside.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Action, GameEvent, GameState, Seat } from '../src/index.ts';
import { EngineError, MAX_WHY_LENGTH, apply, newGame, publicEvents, seededRng, viewFor } from '../src/index.ts';
import { playRobotGame } from './helpers.ts';

const pass: Action = { kind: 'bid', bid: { type: 'pass' } };
const rng = () => seededRng(1);
const fresh = (seed = 3): GameState => newGame(seededRng(seed), { dealer: 3 }).state;
const refused = (s: GameState, seat: unknown, action: unknown) => assert.throws(() => apply(s, seat as Seat, action as Action, rng()), EngineError);

/** Robots play a whole game; `visit` sees every state on the way and the events that led to it. */
const playGame = (seed: number, visit: (s: GameState, events: GameEvent[]) => void): GameState => playRobotGame(seed, { afterMove: visit });

/* ---------- what a player may receive ---------- */

test('a view never carries a robot’s reasoning, which names its cards', () => {
  const s = apply(fresh(), 0, { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'S' }, why: 'V-9 ♠ · As ♥' }, rng()).state;
  assert.equal(s.bids[0].why, 'V-9 ♠ · As ♥');
  for (const seat of [0, 1, 2, 3] as Seat[]) {
    const v = viewFor(s, seat);
    assert.deepEqual(v.bids, [{ type: 'bid', value: 80, suit: 'S', seat: 0 }]);
    assert.ok(!JSON.stringify(v).includes('why'));
  }
});

test('public events carry the bids without their reasoning, and everything else unchanged', () => {
  const step = apply(fresh(), 0, { kind: 'bid', bid: { type: 'pass' }, why: 'pas d’atout solide' }, rng());
  assert.deepEqual(publicEvents(step.events), [{ type: 'bid', record: { type: 'pass', seat: 0 } }]);
  const dealt = newGame(seededRng(1), { dealer: 2 }).events;
  assert.deepEqual(publicEvents(dealt), dealt);
});

test('over whole games, no view or public event shows another hand, a hidden belote or a reason', () => {
  for (const seed of [1, 2, 3]) {
    playGame(seed, (s, events) => {
      assert.ok(!JSON.stringify(publicEvents(events)).includes('why'));
      for (const seat of [0, 1, 2, 3] as Seat[]) {
        const v = viewFor(s, seat);
        const text = JSON.stringify(v);
        assert.ok(!text.includes('"why"') && !('hands' in v));
        for (const other of [0, 1, 2, 3] as Seat[]) {
          if (other === seat) continue;
          // every card of another hand that the view mentions must be public: none are, they are still held
          for (const c of s.hands[other]) assert.ok(!text.includes(`"${c}"`), `seat ${seat} sees ${c} of seat ${other}`);
        }
        if (s.belote && s.belote.played === 0 && s.belote.seat !== seat) assert.equal(v.belote, null);
        assert.deepEqual(v.hand, s.hands[seat]);
      }
    });
  }
});

/* ---------- what the engine accepts ---------- */

test('apply refuses a seat that is not 0, 1, 2 or 3', () => {
  for (const seat of [-1, 4, 1.5, '0', null, undefined, NaN]) refused(fresh(), seat, pass);
});

test('apply refuses malformed actions with EngineError, never with a crash', () => {
  const s = fresh();
  const bad: unknown[] = [
    null,
    undefined,
    'pass',
    42,
    [],
    {},
    { kind: 'bid' },
    { kind: 'bid', bid: null },
    { kind: 'bid', bid: 'pass' },
    { kind: 'bid', bid: {} },
    { kind: 'bid', bid: { type: 'shout' } },
    { kind: 'bid', bid: { type: 'bid' } },
    { kind: 'bid', bid: { type: 'bid', value: '80', suit: 'S' } },
    { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'spades' } },
    { kind: 'bid', bid: { type: 'pass' }, why: 42 },
    { kind: 'bid', bid: { type: 'pass' }, why: 'x'.repeat(MAX_WHY_LENGTH + 1) },
    { kind: 'play' },
    { kind: 'play', card: 'ZZ' },
    { kind: 'play', card: { toString: () => 'AS' } },
    { kind: 'cheat' },
  ];
  for (const a of bad) refused(s, 0, a);
});

test('an unknown action never passes for "next"', () => {
  const end = playGame(5, () => undefined);
  const roundEnd: GameState = { ...end, phase: 'roundEnd', winner: null };
  refused(roundEnd, 0, { kind: 'cheat' });
  refused(roundEnd, 0, {});
  assert.equal(apply(roundEnd, 0, { kind: 'next' }, rng()).state.phase, 'bidding');
});

test('"next" may come from any seat, but only once a round is over', () => {
  const end = playGame(5, () => undefined);
  const roundEnd: GameState = { ...end, phase: 'roundEnd', winner: null };
  for (const seat of [0, 1, 2, 3] as Seat[]) assert.equal(apply(roundEnd, seat, { kind: 'next' }, rng()).state.round, roundEnd.round + 1);
  refused(fresh(), 0, { kind: 'next' });
  // not from someone who is not at the table
  for (const seat of [-1, 4, '0', null]) refused(roundEnd, seat, { kind: 'next' });
});

test('only the known fields of an action reach the state', () => {
  const smuggled = { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'S', seat: 2, html: '<img>' }, extra: '<script>' };
  const step = apply(fresh(), 0, smuggled as unknown as Action, rng());
  assert.deepEqual(step.state.bids, [{ type: 'bid', value: 80, suit: 'S', seat: 0 }]);
  assert.deepEqual(step.events, [{ type: 'bid', record: { type: 'bid', value: 80, suit: 'S', seat: 0 } }]);
  const passed = apply(fresh(), 0, { kind: 'bid', bid: { type: 'pass', value: 80, suit: 'S' } } as unknown as Action, rng());
  assert.deepEqual(passed.state.bids, [{ type: 'pass', seat: 0 }]);
});
