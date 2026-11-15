import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Action, Card, GameState, Seat } from '../src/index.ts';
import { EngineError, apply, fullDeck, newGame, seededRng, shuffle, viewFor } from '../src/index.ts';

const pass: Action = { kind: 'bid', bid: { type: 'pass' } };
const bid = (value: 80 | 90 | 100 | 110, suit: 'S' | 'H' | 'D' | 'C' = 'S'): Action => ({ kind: 'bid', bid: { type: 'bid', value, suit } });

function run(state: GameState, steps: [Seat, Action][], seed = 7): GameState {
  const rng = seededRng(seed);
  for (const [seat, a] of steps) state = apply(state, seat, a, rng).state;
  return state;
}

test('deal: 8 cards each, 3-2-3 from the player after the dealer, who bids first', () => {
  const { state } = newGame(seededRng(1), { dealer: 0 });
  const deck = shuffle(fullDeck(), seededRng(1));
  assert.deepEqual(state.hands[1], [...deck.slice(0, 3), ...deck.slice(12, 14), ...deck.slice(20, 23)]);
  assert.deepEqual(new Set(state.hands.flat()), new Set(fullDeck()));
  assert.ok(state.hands.every((h) => h.length === 8));
  assert.equal(state.turn, 1);
  assert.equal(state.phase, 'bidding');
});

test('four passes: redeal with the next dealer', () => {
  const s0 = newGame(seededRng(2), { dealer: 0 }).state;
  const rng = seededRng(3);
  let s = s0;
  let events: string[] = [];
  for (const seat of [1, 2, 3, 0] as Seat[]) {
    const step = apply(s, seat, pass, rng);
    s = step.state;
    events = step.events.map((e) => e.type);
  }
  assert.deepEqual(events, ['bid', 'redeal', 'dealt']);
  assert.equal(s.dealer, 1);
  assert.equal(s.turn, 2);
  assert.notDeepEqual(s.hands, s0.hands);
});

test('a bid followed by three passes sets the contract; the player after the dealer leads', () => {
  const s = run(newGame(seededRng(4), { dealer: 3 }).state, [
    [0, bid(80)],
    [1, pass],
    [2, bid(100, 'H')],
    [3, pass],
    [0, pass],
    [1, pass],
  ]);
  assert.equal(s.phase, 'playing');
  assert.deepEqual(s.contract, { seat: 2, value: 100, suit: 'H', mult: 1 });
  assert.equal(s.turn, 0);
});

test('bids must go up; acting out of turn is rejected', () => {
  const s = run(newGame(seededRng(5), { dealer: 3 }).state, [[0, bid(100)]]);
  assert.throws(() => apply(s, 1, bid(90), seededRng(1)), EngineError);
  assert.throws(() => apply(s, 2, pass, seededRng(1)), EngineError);
});

test('contre: only an opponent; then only the takers answer, nobody can overbid', () => {
  let s = run(newGame(seededRng(6), { dealer: 3 }).state, [[0, bid(80)]]);
  const partnerTurn = run(s, [[1, pass]]);
  assert.throws(() => apply(partnerTurn, 2, { kind: 'bid', bid: { type: 'contre' } }, seededRng(1)), EngineError);
  s = run(s, [[1, { kind: 'bid', bid: { type: 'contre' } }]]);
  assert.deepEqual(s.contreQueue, [2, 0]);
  assert.equal(s.turn, 2);
  assert.throws(() => apply(s, 2, bid(90), seededRng(1)), EngineError);
  const contred = run(s, [
    [2, pass],
    [0, pass],
  ]);
  assert.equal(contred.contract?.mult, 2);
  const surcontred = run(s, [[2, { kind: 'bid', bid: { type: 'surcontre' } }]]);
  assert.equal(surcontred.contract?.mult, 4);
  assert.equal(surcontred.phase, 'playing');
});

test('apply never mutates the input state', () => {
  const s = newGame(seededRng(8), { dealer: 3 }).state;
  const before = JSON.stringify(s);
  apply(s, 0, bid(80), seededRng(1));
  assert.equal(JSON.stringify(s), before);
});

test('belote is announced when the holder plays the trump king or queen', () => {
  const s0 = newGame(seededRng(9), { dealer: 3 }).state;
  const deck = fullDeck().filter((c) => c !== 'KS' && c !== 'QS');
  const hands = [['KS', 'QS', ...deck.slice(0, 6)], deck.slice(6, 14), deck.slice(14, 22), deck.slice(22, 30)] as Card[][];
  const s = run({ ...s0, hands: hands as GameState['hands'] }, [
    [0, bid(80)],
    [1, pass],
    [2, pass],
    [3, pass],
  ]);
  assert.deepEqual(s.belote, { seat: 0, played: 0 });
  // hidden from the others until announced
  assert.equal(viewFor(s, 1).belote, null);
  const step = apply(s, 0, { kind: 'play', card: 'KS' }, seededRng(1));
  assert.ok(step.events.some((e) => e.type === 'belote' && e.seat === 0 && !e.rebelote));
  assert.deepEqual(viewFor(step.state, 1).belote, { seat: 0, played: 1 });
});

test('a player view never contains another player’s cards', () => {
  const s = newGame(seededRng(10), { dealer: 0 }).state;
  for (const seat of [0, 1, 2, 3] as Seat[]) {
    const v = viewFor(s, seat);
    const text = JSON.stringify(v);
    for (const other of [0, 1, 2, 3] as Seat[]) {
      if (other === seat) continue;
      for (const c of s.hands[other]) assert.ok(!text.includes(`"${c}"`), `seat ${seat} sees ${c}`);
    }
    assert.deepEqual(v.handSizes, [8, 8, 8, 8]);
  }
});
