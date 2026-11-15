import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Action, GameState, PlayerView, Seat } from '../src/index.ts';
import { EngineError, aiPlay, apply, fullDeck, isCard, isMaster, legalBid, newGame, scoreRound, seededRng, shuffle, viewFor } from '../src/index.ts';
import { recall } from '../src/ai/memory.ts';

test('seededRng is deterministic and stays in [0, 1)', () => {
  const a = seededRng(99);
  const b = seededRng(99);
  for (let i = 0; i < 1000; i++) {
    const x = a();
    assert.equal(x, b());
    assert.ok(x >= 0 && x < 1);
  }
});

test('shuffle keeps every card once and is not biased toward any position', () => {
  const rng = seededRng(5);
  const firstCounts = new Map<string, number>();
  const N = 32000;
  for (let i = 0; i < N; i++) {
    const d = shuffle(fullDeck(), rng);
    if (i < 50) assert.deepEqual([...d].sort(), [...fullDeck()].sort());
    firstCounts.set(d[0], (firstCounts.get(d[0]) ?? 0) + 1);
  }
  // each card should lead ~1000 times out of 32000
  for (const c of fullDeck()) {
    const n = firstCounts.get(c) ?? 0;
    assert.ok(n > 820 && n < 1180, `${c} first ${n} times`);
  }
});

test('legalBid: surcontre needs a contre, contre needs an opposing bid', () => {
  const s = newGame(seededRng(1), { dealer: 3 }).state;
  assert.equal(legalBid(s, 0, { type: 'surcontre' }), false);
  assert.equal(legalBid(s, 0, { type: 'contre' }), false);
  assert.equal(legalBid(s, 0, { type: 'bid', value: 80, suit: 'S' }), true);
  assert.equal(legalBid(s, 0, { type: 'bid', value: 85 as 80, suit: 'S' }), false);
});

test('playing, next round or bidding in the wrong phase throws', () => {
  const s = newGame(seededRng(1), { dealer: 3 }).state;
  assert.throws(() => apply(s, 0, { kind: 'play', card: s.hands[0][0] }, seededRng(1)), EngineError);
  assert.throws(() => apply(s, 0, { kind: 'next' }, seededRng(1)), EngineError);
});

test('a card not in hand or illegal is rejected', () => {
  let s: GameState = newGame(seededRng(3), { dealer: 3 }).state;
  const rng = seededRng(4);
  const steps: [Seat, Action][] = [
    [0, { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'S' } }],
    [1, { kind: 'bid', bid: { type: 'pass' } }],
    [2, { kind: 'bid', bid: { type: 'pass' } }],
    [3, { kind: 'bid', bid: { type: 'pass' } }],
  ];
  for (const [seat, a] of steps) s = apply(s, seat, a, rng).state;
  assert.throws(() => apply(s, 0, { kind: 'play', card: s.hands[1][0] }, rng), EngineError);
});

test('a view carries the own hand only, never the hands array', () => {
  const v = viewFor(newGame(seededRng(1)).state, 2);
  assert.equal('hands' in v, false);
  assert.equal(v.hand.length, 8);
});

test('a game cannot be created with a target or a dealer that makes no sense', () => {
  const create = (opts: object) => () => newGame(seededRng(1), opts);
  for (const target of [0, -100, 1.5, NaN, Infinity, '1000']) assert.throws(create({ target }), EngineError, String(target));
  for (const dealer of [4, -1, 1.5, '0', null]) assert.throws(create({ dealer }), EngineError, String(dealer));
  assert.equal(newGame(seededRng(1), { target: 500, dealer: 2 }).state.target, 500);
});

test('only the 32 cards of the deck are cards', () => {
  assert.ok(fullDeck().every(isCard));
  for (const notACard of ['6S', 'AS ', 'as', 'SA', '10S', '', 7, null, undefined, ['A', 'S']])
    assert.equal(isCard(notACard), false, String(notACard));
});

test('a round with no trick cannot be scored', () => {
  assert.throws(() => scoreRound({ seat: 0, value: 80, suit: 'S', mult: 1 }, [], null), EngineError);
});

test('a robot asked for a card outside the play of a deal refuses instead of crashing', () => {
  const bidding: PlayerView = viewFor(newGame(seededRng(1)).state, 0);
  assert.throws(() => aiPlay(bidding, seededRng(1)), EngineError);
  assert.throws(() => recall(bidding), EngineError);
  assert.equal(isMaster(bidding.hand[0], bidding, new Set()), false, 'no card is master before there is a trump suit');
});
