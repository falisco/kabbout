// The bridge as its caller sees it, over HTTP: it refuses what the engine refuses, a seat's view never
// holds what that seat may not know, the same question always gets the same answer, and a recorded
// deal is only accepted if the engine can play it again.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import type { AddressInfo } from 'node:net';
import { after, before, test } from 'node:test';
import type { Action, Card, Conventions, GameState, PlayerView, PublicBid, PublicEvent, RoundResult, Seat } from '../../engine/src/index.ts';
import {
  CAUTIOUS,
  CLASSIC,
  SEATS,
  aiBid,
  aiPlay,
  apply,
  legalPlays,
  newGame as dealGame,
  nextSeat,
  readConventions,
  seededRng,
  suitOf,
  team,
  viewFor,
} from '../../engine/src/index.ts';
import type { Case } from '../contract/build.ts';
import { buildCases, recordOf, seedOf, serialize } from '../contract/build.ts';
import { buildConventions, serializeConventions } from '../contract/conventions.ts';
import type { BidReview, ExpertChoice, Summary, Swings, TrickReview, WhatIf } from '../src/analysis.ts';
import { expertChoiceFrom, seatView } from '../src/analysis.ts';
import { sorted as sortedHand } from '../src/order.ts';
import { replayDeal } from '../src/replay.ts';
import { seededStream, streamOf } from '../src/rng.ts';
import { MAX_BODY_BYTES, createBridge } from '../src/server.ts';

const server = createBridge();
let base = '';
before(() =>
  new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve)).then(
    () => (base = `http://127.0.0.1:${(server.address() as AddressInfo).port}`),
  ),
);
after(() => new Promise((resolve) => server.close(resolve)));

interface Reply<T> {
  status: number;
  text: string;
  body: T;
}
interface Failure {
  error: { code: string; message: string; at?: { bid?: number; trick?: number; seat?: Seat } };
}
interface StepBody {
  state: GameState;
  events: PublicEvent[];
}
interface RobotBody {
  seat: Seat;
  action: Action;
}

async function send<T>(path: string, init: RequestInit): Promise<Reply<T>> {
  const res = await fetch(base + path, init);
  const text = await res.text();
  return { status: res.status, text, body: JSON.parse(text) as T };
}
const post = <T>(path: string, body: unknown): Promise<Reply<T>> => send<T>(path, { method: 'POST', body: JSON.stringify(body) });
/** the answer of a call that must succeed */
async function ok<T>(path: string, body: unknown): Promise<T> {
  const reply = await post<T>(path, body);
  assert.equal(reply.status, 200, `${path}: ${reply.text}`);
  return reply.body;
}
async function refused(path: string, body: unknown): Promise<Failure['error']> {
  const reply = await post<Failure>(path, body);
  assert.equal(reply.status, 422, `${path}: ${reply.text}`);
  assert.equal(reply.body.error.code, 'refused');
  return reply.body.error;
}
async function badRequest(path: string, body: unknown): Promise<void> {
  const reply = await post<Failure>(path, body);
  assert.equal(reply.status, 400, `${path}: ${reply.text}`);
  assert.equal(reply.body.error.code, 'bad_request');
}

/** a card wherever it is written in a JSON text */
const CARD_IN_JSON = /"[789TJQKA][SHDC]"/g;
const newGame = (seed: number, more: object = {}) => ok<StepBody>('/games', { seed: seedOf(seed), ...more });
const sorted = (cards: readonly string[]): string[] => [...cards].sort();

/** Robots play over HTTP until `until` holds. `watch` sees every state on the way, the first one included. */
async function play(
  start: GameState,
  until: (s: GameState) => boolean,
  watch: (s: GameState, events: PublicEvent[]) => Promise<void> | void = () => {},
) {
  let state = start;
  let seed = 1;
  await watch(state, []);
  while (!until(state)) {
    const action: Action =
      state.phase === 'roundEnd' ? { kind: 'next' } : (await ok<RobotBody>('/games/robot', { state, level: 'normal', seed: seedOf(seed++) })).action;
    const step = await ok<StepBody>('/games/apply', { state, seat: state.turn, action, seed: seedOf(seed++) });
    state = step.state;
    await watch(state, step.events);
  }
  return state;
}

test('health answers without a body', async () => {
  const reply = await send<{ ok: boolean }>('/health', {});
  assert.deepEqual([reply.status, reply.body], [200, { ok: true }]);
});

test('the same question gets the same answer, byte for byte', async () => {
  const first = await post<StepBody>('/games', { seed: seedOf(42) });
  const again = await post<StepBody>('/games', { seed: seedOf(42) });
  assert.equal(first.text, again.text);
  assert.notEqual((await post('/games', { seed: seedOf(43) })).text, first.text);

  const state = await play(first.body.state, (s) => s.phase === 'playing');
  const ask = { state, level: 'expert', seed: seedOf(9) };
  assert.equal((await post('/games/robot', ask)).text, (await post('/games/robot', ask)).text);
});

test('over a whole game, no view shows another hand, a hidden belote or a robot’s reason', async () => {
  let views = 0;
  const { state: start, events: dealt } = await newGame(3, { target: 300 });
  assert.deepEqual(dealt, [{ type: 'dealt', dealer: start.dealer }]);
  const end = await play(
    start,
    (s) => s.phase === 'gameEnd',
    async (state, events) => {
      for (const e of events) assert.ok(!(e.type === 'bid' && 'why' in e.record), 'an event carries a reason');
      const played = new Set<string>([...state.tricks, ...(state.trick ? [state.trick] : [])].flatMap((trick) => trick.plays.map((p) => p.card)));
      for (const seat of SEATS) {
        const { view } = await ok<{ view: PlayerView }>('/games/view', { state, seat });
        const text = JSON.stringify(view);
        assert.ok(!('hands' in view) && !text.includes('"why"'), `seat ${seat} is shown the hands or a reason`);
        assert.deepEqual(sorted(view.hand), sorted(state.hands[seat]));
        // wherever a card is written in the view, under any name, it is one the seat holds or one the table has seen
        const shown = new Set(text.match(CARD_IN_JSON)?.map((quoted) => quoted.slice(1, -1)));
        const allowed = new Set<string>([...state.hands[seat], ...played]);
        assert.deepEqual(
          [...shown].filter((card) => !allowed.has(card)),
          [],
          `seat ${seat} is shown cards it may not know`,
        );
        const announced = state.belote && (state.belote.played > 0 || state.belote.seat === seat);
        assert.deepEqual(view.belote, announced ? state.belote : null);
        // the cards it may play are its own, and out of turn it may do nothing
        const legal = await ok<{ bids: unknown[]; cards: Card[] }>('/games/legal', { state, seat });
        assert.deepEqual(
          legal.cards.filter((card) => !state.hands[seat].includes(card)),
          [],
          `seat ${seat} may play cards it does not hold`,
        );
        const toAct = seat === state.turn && (state.phase === 'bidding' || state.phase === 'playing');
        assert.equal(legal.bids.length + legal.cards.length > 0, toAct, `seat ${seat}, phase ${state.phase}`);
        views++;
      }
    },
  );
  assert.notEqual(end.winner, null);
  assert.ok(views > 300, `only ${views} views were checked`);
});

test('the robots’ reasons stay in the state the server keeps', async () => {
  const { state } = await newGame(42);
  const { seat, action } = await ok<RobotBody>('/games/robot', { state, level: 'normal', lang: 'en', seed: seedOf(1) });
  assert.equal(seat, state.turn);
  assert.ok(action.kind === 'bid' && action.why, 'a robot bid comes with its reason');
  const step = await ok<StepBody>('/games/apply', { state, seat, action, seed: seedOf(2) });
  assert.equal(step.state.bids[0].why, action.why);
  assert.ok(!JSON.stringify(step.events).includes('"why"'));
});

test('the bridge refuses what the engine refuses', async () => {
  const { state } = await newGame(42);
  const turn = state.turn;
  const pass: Action = { kind: 'bid', bid: { type: 'pass' } };
  const from = (seat: unknown, action: unknown) => refused('/games/apply', { state, seat, action, seed: seedOf(1) });

  for (const seat of [4, -1, '0', null, 1.5]) assert.match((await from(seat, pass)).message, /unknown seat/);
  assert.match((await from(nextSeat(turn), pass)).message, /illegal bid/);
  assert.match((await from(turn, { kind: 'bid', bid: { type: 'contre' } })).message, /illegal bid/);
  assert.match((await from(turn, { kind: 'play', card: state.hands[turn][0] })).message, /illegal card/);
  assert.match((await from(turn, { kind: 'next' })).message, /no next round/);
  for (const action of [null, 'pass', 7, {}, { kind: 'pass' }, { kind: 'bid' }, { kind: 'bid', bid: { type: 'bid', value: 85, suit: 'S' } }])
    await from(turn, action);
  await from(turn, { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'X' } });
  await from(turn, { kind: 'play', card: 'ZZ' });
  await from(turn, { kind: 'bid', bid: { type: 'pass' }, why: 'x'.repeat(201) });

  assert.match((await refused('/games/view', { state, seat: 4 })).message, /unknown seat/);
  assert.match((await refused('/games/legal', { state, seat: 'north' })).message, /unknown seat/);
  assert.match((await refused('/games', { seed: seedOf(1), scoring: 'bridge' })).message, /unknown scoring/);
  assert.match((await refused('/games', { seed: seedOf(1), target: -5 })).message, /invalid target/);
  assert.match((await refused('/games', { seed: seedOf(1), dealer: 9 })).message, /unknown dealer/);
});

test('only the known fields of an action reach the state', async () => {
  const { state } = await newGame(42);
  const action = { kind: 'bid', bid: { type: 'bid', value: 80, suit: 'H', hands: 'x' }, cheat: true };
  const step = await ok<StepBody>('/games/apply', { state, seat: state.turn, action, seed: seedOf(1) });
  assert.deepEqual(step.state.bids, [{ type: 'bid', value: 80, suit: 'H', seat: state.turn }]);
});

test('legal moves: every bid open to the seat to speak, the cards the rules allow, nothing out of turn', async () => {
  interface Legal {
    bids: { type: string }[];
    cards: string[];
    next: boolean;
  }
  const { state } = await newGame(42);
  const opening = await ok<Legal>('/games/legal', { state, seat: state.turn });
  // pass, then ten values in four suits; no contre or surcontre before a bid
  assert.equal(opening.bids.length, 41);
  assert.deepEqual([opening.cards, opening.next], [[], false]);
  assert.deepEqual(await ok<Legal>('/games/legal', { state, seat: nextSeat(state.turn) }), { bids: [], cards: [], next: false });

  const playing = await play(state, (s) => s.phase === 'playing' && s.trick?.plays.length === 2);
  const mine = await ok<Legal>('/games/legal', { state: playing, seat: playing.turn });
  assert.deepEqual({ ...mine, cards: sorted(mine.cards) }, { bids: [], cards: sorted(legalPlays(playing, playing.turn)), next: false });
  assert.ok(mine.cards.length > 0);

  const over = await play(playing, (s) => s.phase === 'roundEnd');
  assert.deepEqual(await ok<Legal>('/games/legal', { state: over, seat: 2 }), { bids: [], cards: [], next: true });
});

test('a robot only acts when a seat is to bid or to play', async () => {
  const over = await play((await newGame(42)).state, (s) => s.phase === 'roundEnd');
  assert.match((await refused('/games/robot', { state: over, level: 'normal', seed: seedOf(1) })).message, /no robot to act/);
});

test('a request the bridge does not understand is a bad request, not a refusal', async () => {
  const { state } = await newGame(42);
  // a seed of 32 bits could be found again from one hand: it is not taken
  for (const seed of [undefined, 42, '42', 'f'.repeat(31), 'F'.repeat(32), 'g'.repeat(32), 'f'.repeat(65)]) await badRequest('/games', { seed });
  for (const broken of [
    undefined,
    null,
    [],
    'state',
    {},
    { ...state, hands: [[], []] },
    { ...state, turn: 5 },
    { ...state, bids: null },
    { ...state, bids: [null] },
    { ...state, tricks: [{}] },
    { ...state, hands: [['XX'], [], [], []] },
    { ...state, trick: 3 },
    { ...state, phase: 'dancing' },
  ])
    await badRequest('/games/view', { state: broken, seat: 0 });
  await badRequest('/games/apply', { state, seat: 0, action: { kind: 'next' } });
  await badRequest('/games/robot', { state, level: 'grandmaster', seed: seedOf(1) });
  await badRequest('/games/robot', { state, level: 'easy', lang: 'de', seed: seedOf(1) });
  await badRequest('/games/robot', { state, level: 'easy' });

  for (const body of ['', 'not json', '[1]', '"x"', 'null']) {
    const reply = await send<Failure>('/games', { method: 'POST', body });
    assert.deepEqual([reply.status, reply.body.error.code], [400, 'bad_request'], body);
  }
  const large = await send<Failure>('/games', { method: 'POST', body: JSON.stringify({ seed: seedOf(1), pad: 'x'.repeat(MAX_BODY_BYTES) }) });
  assert.equal(large.status, 413);
  assert.equal((await send<Failure>('/games', {})).status, 405);
  assert.equal((await send<Failure>('/nowhere', { method: 'POST', body: '{}' })).status, 404);
  // an address that is not one is not found either, and the bridge does not fail on it
  assert.equal((await send<Failure>('//', {})).status, 404);
  assert.equal((await send<{ ok: boolean }>('/health?now', {})).status, 200);
});

test('the seed is what is drawn from: another seed, another deal and another choice of the expert', async () => {
  const over = await play((await newGame(42)).state, (s) => s.phase === 'roundEnd');
  const next = (seed: number) => ok<StepBody>('/games/apply', { state: over, seat: 0, action: { kind: 'next' }, seed: seedOf(seed) });
  assert.notDeepEqual((await next(1)).state.hands, (await next(2)).state.hands);

  // the expert imagines the hidden cards from its seed: over a deal, some card of its depends on it
  let depends = 0;
  await play(
    (await newGame(42)).state,
    (s) => s.phase === 'roundEnd',
    async (state) => {
      if (state.phase !== 'playing') return;
      const cards = new Set<string>();
      for (const seed of [1, 2, 3, 4]) {
        const { action } = await ok<RobotBody>('/games/robot', { state, level: 'expert', seed: seedOf(seed) });
        if (action.kind === 'play') cards.add(action.card);
      }
      if (cards.size > 1) depends++;
    },
  );
  assert.ok(depends > 0, 'the expert plays the same card whatever its seed');
});

test('a robot gives the reason of its bid in the language asked for', async () => {
  const { state } = await newGame(42);
  const why = async (lang: string) => {
    const { action } = await ok<RobotBody>('/games/robot', { state, level: 'normal', lang, seed: seedOf(1) });
    return action.kind === 'bid' ? action.why : undefined;
  };
  const reasons = [await why('fr'), await why('en'), await why('ar')];
  assert.ok(reasons.every(Boolean));
  assert.equal(new Set(reasons).size, 3);
});

test('what was stored beside a state never reaches a view', async () => {
  const { state } = await newGame(42);
  const stored = { ...state, secret: state.hands, tokens: ['a', 'b'] };
  const { view } = await ok<{ view: Record<string, unknown> }>('/games/view', { state: stored, seat: 0 });
  assert.ok(!('secret' in view) && !('tokens' in view));
  const step = await ok<StepBody>('/games/apply', {
    state: stored,
    seat: state.turn,
    action: { kind: 'bid', bid: { type: 'pass' } },
    seed: seedOf(1),
  });
  assert.ok(!('secret' in step.state));
});

test('the draws come from 128 bits or more, and the same seed gives the same draws', () => {
  const draws = (seed: string) => Array.from({ length: 40 }, seededStream(seed));
  assert.deepEqual(draws(seedOf(7)), draws(seedOf(7)));
  assert.notDeepEqual(draws(seedOf(7)), draws(seedOf(8)));
  assert.notDeepEqual(draws('0'.repeat(32)), draws('0'.repeat(64)));
  assert.ok(draws('ab'.repeat(32)).every((x) => x >= 0 && x < 1));
});

test('a view gives the hand in the order of the deck, not in the order it was dealt', async () => {
  const { state } = await newGame(42);
  const order = (card: Card) => 'SHDC'.indexOf(card[1]) * 8 + '789TJQKA'.indexOf(card[0]);
  for (const seat of SEATS) {
    const { view } = await ok<{ view: PlayerView }>('/games/view', { state, seat });
    assert.deepEqual(
      view.hand,
      [...state.hands[seat]].sort((a, b) => order(a) - order(b)),
    );
  }
});

test('a recorded deal is replayed to the engine’s own result, in both counts', async () => {
  interface Replayed {
    hands: string[][];
    result: RoundResult;
    tricks: GameState['tricks'];
    contract: GameState['contract'];
  }
  for (const scoring of ['ffb', 'tunisian']) {
    const { state: start } = await newGame(11, { scoring });
    const over = await play(start, (s) => s.phase === 'roundEnd');
    const dealt = await play(start, (s) => s.bids.length === 0 && s.round === over.round);
    const replayed = await ok<Replayed>('/deals/replay', { record: recordOf(over) });
    assert.deepEqual(replayed.result, over.result);
    assert.deepEqual(replayed.tricks, over.tricks);
    assert.deepEqual(replayed.contract, over.contract);
    assert.deepEqual(replayed.hands.map(sorted), dealt.hands.map(sorted));
  }
});

test('a recorded deal the rules do not allow is refused, with the place where it goes wrong', async () => {
  const over = await play((await newGame(11)).state, (s) => s.phase === 'roundEnd');
  const record = recordOf(over) as { bids: { seat: Seat; type: string }[]; tricks: { seat: Seat; card: string }[][] };
  const withTricks = (change: (tricks: typeof record.tricks) => void) => {
    const tricks = structuredClone(record.tricks);
    change(tricks);
    return refused('/deals/replay', { record: { ...record, tricks } });
  };

  const twice = await withTricks((tricks) => (tricks[4][2].card = tricks[0][0].card));
  assert.match(twice.message, /played twice/);
  assert.deepEqual(twice.at, { trick: 5, seat: record.tricks[4][2].seat });

  assert.match((await withTricks((tricks) => (tricks[2][1].card = '1S'))).message, /unknown card/);
  assert.match((await withTricks((tricks) => (tricks[2][1].seat = 6 as Seat))).message, /unknown seat/);
  // one seat plays twice in a trick, another not at all
  const uneven = await withTricks((tricks) => (tricks[7][3].seat = tricks[7][2].seat));
  assert.match(uneven.message, /does not play 8 cards/);

  // out of turn: the first two plays of a trick change places
  const outOfTurn = await withTricks((tricks) => tricks[3].reverse());
  assert.equal(outOfTurn.at?.trick, 4);

  const noContract = await refused('/deals/replay', { record: { ...record, bids: record.bids.slice(0, 1) } });
  assert.deepEqual([noContract.message, noContract.at], ['the auction does not end in a contract', { bid: 1 }]);
  // four passes: the engine would deal other cards, which are not the record's
  const passes = [1, 2, 3, 4].map((offset) => ({ type: 'pass', seat: (over.dealer + offset) % 4 }));
  const passedOut = await refused('/deals/replay', { record: { ...record, bids: passes } });
  assert.deepEqual([passedOut.message, passedOut.at], ['everybody passed: there is no deal to replay', { bid: 3 }]);
  const badBid = await refused('/deals/replay', { record: { ...record, bids: [{ seat: record.bids[0].seat, type: 'surcontre' }, ...record.bids] } });
  assert.deepEqual(badBid.at, { bid: 0 });

  for (const broken of [
    undefined,
    [],
    { ...record, scoring: 'x' },
    { ...record, dealer: 4 },
    { ...record, bids: 'pass' },
    { ...record, tricks: record.tricks.slice(1) },
  ])
    await badRequest('/deals/replay', { record: broken });
  await badRequest('/deals/replay', { record: { ...record, tricks: record.tricks.map((plays) => plays.slice(1)) } });
});

/* ---------- the analysis of a recorded deal ---------- */

type Recorded = { scoring: string; dealer: Seat; bids: object[]; tricks: { seat: Seat; card: Card }[][] };
const TRICK_NUMBERS = [1, 2, 3, 4, 5, 6, 7, 8];

/** a deal played by the normal robots, as recorded */
async function recordedDeal(seed: number, more: object = {}): Promise<{ over: GameState; record: Recorded }> {
  const over = await play((await newGame(seed, more)).state, (s) => s.phase === 'roundEnd');
  return { over, record: recordOf(over) as Recorded };
}

test('the summary and the tricks of an analysis are the engine’s own', async () => {
  for (const scoring of ['ffb', 'tunisian']) {
    const { over, record } = await recordedDeal(11, { scoring });
    const result = over.result as RoundResult;
    for (const seat of SEATS) {
      const us = team(seat);
      const them = team(nextSeat(seat));
      const summary = await ok<Summary>('/analysis/summary', { record, seat });
      assert.deepEqual(summary.contract, over.contract);
      assert.equal(summary.taking, team(result.contract.seat) === us);
      assert.equal(summary.made, result.made);
      assert.deepEqual(
        [summary.us, summary.them],
        [us, them].map((t) => ({ tricks: result.tricksWon[t], cardPoints: result.cardPoints[t], gained: result.gained[t] })),
      );
      assert.equal(summary.bids.length, over.bids.length);
      assert.doesNotMatch(JSON.stringify(summary), /"why"/);
    }
    let points = 0;
    for (const trick of TRICK_NUMBERS) {
      const review = await ok<TrickReview>('/analysis/trick', { record, seat: 0, trick });
      const played = over.tricks[trick - 1];
      assert.deepEqual([review.leader, review.plays, review.winner], [played.leader, played.plays, played.winner]);
      assert.equal(review.won, team(played.winner) === 0);
      assert.equal(review.played, played.plays.find((p) => p.seat === 0)?.card);
      points += review.points;
    }
    // the cards of a deal are worth 152; the ten of the last trick is counted apart
    assert.equal(points, 152);
  }
});

test('the same question about the same deal gets the same analysis, byte for byte', async () => {
  const { record } = await recordedDeal(12);
  const asked = { record, seat: 2 };
  const free = (await ok<Swings>('/analysis/swings', asked)).swings[0];
  for (const [path, body] of [
    ['/analysis/summary', asked],
    ['/analysis/trick', { ...asked, trick: 3 }],
    ['/analysis/expert-choice', { ...asked, trick: 2 }],
    ['/analysis/swings', asked],
    ['/analysis/what-if', { ...asked, trick: free.trick, card: free.choice }],
  ] as const) {
    const [first, second] = [await post(path, body), await post(path, body)];
    assert.equal(first.status, 200, `${path}: ${first.text}`);
    assert.equal(second.text, first.text, path);
  }
});

test('the table read at the player’s turn accounts for every card and every point, from what the player saw', async () => {
  let voids = 0;
  for (const seed of [11, 12, 13]) {
    const { record } = await recordedDeal(seed);
    const seat: Seat = 0;
    const trumpOf = (await ok<Summary>('/analysis/summary', { record, seat })).contract.suit;
    for (const trick of TRICK_NUMBERS) {
      const c: ExpertChoice = await ok('/analysis/expert-choice', { record, seat, trick });
      const r = c.reading;
      const table = c.onTable.map((p) => p.card);
      const earlier = record.tricks.slice(0, trick - 1).flat();
      const fallen: Card[] = Object.values(r.fallen).flat();
      const out: Card[] = Object.values(r.out).flat();
      assert.equal(r.trump, trumpOf);
      // the thirty-two cards: fallen, on the table, in the hand, or with the others
      assert.deepEqual([...fallen].sort(), earlier.map((p) => p.card).sort());
      assert.equal(new Set([...fallen, ...table, ...c.hand, ...out]).size, 32);
      assert.equal(fallen.length + table.length + c.hand.length + out.length, 32);
      for (const [suit, cards] of Object.entries(r.out) as [string, Card[]][]) for (const card of cards) assert.equal(suitOf(card), suit);
      assert.equal(r.trumps.fallen + r.trumps.onTable + r.trumps.inHand + r.trumps.out, 8);
      assert.equal(r.trumps.out, r.out[r.trump].length);
      assert.equal(r.points.us + r.points.them + r.points.onTable + r.points.inHands, 152);
      assert.equal(r.holding === null, table.length === 0);
      // the cards of the reading are the legal ones, and a master is beaten by nothing still out
      assert.deepEqual(r.legal.map((l) => l.card).sort(), c.cards.map((x) => x.card).sort());
      for (const l of r.legal) {
        assert.equal(l.trump, suitOf(l.card) === r.trump);
        if (table.length === 0) assert.ok(l.holds);
        if (l.master && out[0] !== undefined) {
          const top = r.masters.find((m) => m.suit === suitOf(l.card));
          assert.ok(top && (top.mine || !r.out[top.suit].includes(top.card)));
        }
      }
      for (const m of r.masters) assert.equal(m.mine, c.hand.includes(m.card));
      // a seat is void in a suit only after it failed to follow it, and holds none of it afterwards
      for (const v of r.voids) {
        assert.notEqual(v.seat, seat);
        const later = record.tricks.slice(trick).flat();
        assert.ok(!later.some((p) => p.seat === v.seat && suitOf(p.card) === v.suit), `seat ${v.seat} is void in ${v.suit}`);
        voids++;
      }
    }
  }
  assert.ok(voids > 0, 'no deal showed a void: the test would check nothing of them');
});

test('the auction is looked at turn by turn: what the seat said, what the expert says with the same cards', async () => {
  let turns = 0;
  for (const seed of [11, 12, 13]) {
    const { record } = await recordedDeal(seed);
    for (const seat of SEATS) {
      const review: BidReview = await ok('/analysis/bids', { record, seat, lang: 'en' });
      const bids = record.bids as PublicBid[];
      const said = bids.filter((b) => b.seat === seat);
      assert.equal(review.hand.length, 8);
      assert.deepEqual([...review.hand].sort(), record.tricks.flatMap((plays) => plays.filter((p) => p.seat === seat).map((p) => p.card)).sort());
      assert.deepEqual(
        review.suits.map((s) => s.suit),
        ['S', 'H', 'D', 'C'],
      );
      assert.equal(review.turns.length, said.length);
      review.turns.forEach((turn, i) => {
        const { seat: _seat, ...bid } = said[i];
        assert.equal(turn.turn, i + 1);
        assert.deepEqual(turn.bid, bid);
        // what the seat had heard: the bids before its own, and nothing after
        assert.deepEqual(turn.heard, bids.slice(0, bids.indexOf(said[i])));
        assert.equal(turn.same, JSON.stringify(turn.bid) === JSON.stringify(turn.expert));
        assert.ok(turn.why.length > 0);
        turns++;
      });
      // the reasons follow the language, the bids do not
      const french: BidReview = await ok('/analysis/bids', { record, seat, lang: 'fr' });
      assert.deepEqual(
        french.turns.map((t) => t.expert),
        review.turns.map((t) => t.expert),
      );
    }
  }
  assert.ok(turns > 12);
  const { record } = await recordedDeal(11);
  assert.equal((await post('/analysis/bids', { record, seat: 0 })).status, 400);
  assert.equal((await post('/analysis/bids', { record, seat: 0, lang: 'de' })).status, 400);
});

test('the swings are the expert’s choices trick by trick: only the cards it would not have played, the costliest first', async () => {
  let seen = 0;
  for (const seed of [11, 12, 13]) {
    const { record } = await recordedDeal(seed);
    for (const seat of [0, 1] as Seat[]) {
      const { swings, choices } = await ok<Swings>('/analysis/swings', { record, seat });
      const all: ExpertChoice[] = [];
      for (const trick of TRICK_NUMBERS) all.push(await ok<ExpertChoice>('/analysis/expert-choice', { record, seat, trick }));
      for (const c of all) {
        assert.equal(c.forced, c.cards.length === 1);
        assert.equal(c.worlds, c.forced ? 0 : 50);
        assert.equal(c.hand.length, 9 - c.trick);
        assert.ok(c.hand.includes(c.played) && c.cards.some((x) => x.card === c.choice));
        const average = (card: Card) => c.cards.find((x) => x.card === card)?.average;
        assert.equal(average(c.choice), Math.max(...c.cards.map((x) => x.average)));
        assert.equal(c.loss, (average(c.choice) ?? 0) - (average(c.played) ?? 0));
        if (c.played === c.choice) assert.equal(c.loss, 0);
        for (const x of c.cards) assert.ok(Number.isInteger(x.average));
      }
      assert.equal(choices, all.filter((c) => !c.forced).length);
      const expected = all.filter((c) => c.loss > 0).map(({ trick, played, choice, loss }) => ({ trick, played, choice, loss }));
      assert.deepEqual(
        [...swings].sort((a, b) => a.trick - b.trick).map(({ trick, played, choice, loss }) => ({ trick, played, choice, loss })),
        expected,
      );
      for (let i = 1; i < swings.length; i++) assert.ok(swings[i - 1].loss >= swings[i].loss);
      // a swing is a mistake only from a clear loss; the two costliest of those are also played out in the deal as dealt
      const clear = swings.filter((s) => s.clear);
      for (const s of swings) assert.equal(s.clear, s.loss >= 30);
      for (const [i, s] of swings.entries()) {
        if (!s.clear || clear.indexOf(s) >= 2) assert.equal(s.inThisDeal, null, `swing ${i}`);
        else {
          const { difference } = await ok<WhatIf>('/analysis/what-if', { record, seat, trick: s.trick, card: s.choice });
          assert.deepEqual(s.inThisDeal, { better: difference > 0 ? 'expert' : difference < 0 ? 'played' : 'same', by: Math.abs(difference) });
        }
      }
      seen += swings.length;
    }
  }
  assert.ok(seen > 0, 'the normal robots do not always play the expert’s card');
});

test('a card the expert would also have played is never a swing, even when it lost the trick', async () => {
  // seat 0 plays the card the analysis will name; the others are the normal robots
  let lostWithTheExpertsCard = 0;
  for (const seed of [21, 22, 23]) {
    const rng = seededRng(seed);
    let state = dealGame(rng, { dealer: 3 }).state;
    while (state.phase !== 'roundEnd') {
      const seat = state.turn;
      const view = viewFor(state, seat);
      let action: Action;
      if (state.phase === 'bidding') action = { kind: 'bid', bid: aiBid(view).bid };
      else if (seat === 0) action = { kind: 'play', card: expertChoiceFrom(seatView(state, 0), state.tricks.length + 1, view.hand[0]).choice };
      else action = { kind: 'play', card: aiPlay(view, rng, 'normal') };
      state = apply(state, seat, action, rng).state;
    }
    if (state.round !== 1) continue;
    const record = recordOf(state);
    const { swings } = await ok<Swings>('/analysis/swings', { record, seat: 0 });
    assert.deepEqual(swings, [], `deal ${seed}`);
    lostWithTheExpertsCard += state.tricks.filter((t) => team(t.winner) === 1).length;
  }
  assert.ok(lostWithTheExpertsCard > 0, 'the expert’s card lost a trick somewhere');
});

test('the expert’s choice comes from what the seat could know: other hidden hands, same answer', async () => {
  const { record } = await recordedDeal(11);
  const seat: Seat = 0;
  let compared = 0;
  for (const trick of [1, 2, 3]) {
    // the cards the seat has not seen when it plays in this trick: those the others play after it
    const mine = record.tricks[trick - 1].findIndex((p) => p.seat === seat);
    const hidden = record.tricks.flatMap((plays, t) =>
      plays.map((p, i) => ({ p, t, i })).filter(({ p, t: at, i }) => p.seat !== seat && (at > trick - 1 || (at === trick - 1 && i > mine))),
    );
    const known: ExpertChoice = await ok('/analysis/expert-choice', { record, seat, trick });
    let swapped = 0;
    for (const a of hidden) {
      for (const b of hidden) {
        if (swapped >= 2 || a.p.seat >= b.p.seat || suitOf(a.p.card) !== suitOf(b.p.card)) continue;
        const tricks = structuredClone(record.tricks);
        [tricks[a.t][a.i].card, tricks[b.t][b.i].card] = [b.p.card, a.p.card];
        const other = { ...record, tricks };
        // most exchanges change who wins a trick, and the record is then no deal at all
        if ((await post('/deals/replay', { record: other })).status !== 200) continue;
        assert.deepEqual(await ok<ExpertChoice>('/analysis/expert-choice', { record: other, seat, trick }), known);
        swapped++;
      }
    }
    compared += swapped;
  }
  assert.ok(compared >= 2, `${compared} deals with other hidden hands`);
  // and the draws do come from the view: another view, other draws
  assert.notEqual(streamOf({ hand: ['7S'] }, 'expert-choice')(), streamOf({ hand: ['8S'] }, 'expert-choice')());
  assert.notEqual(streamOf({ hand: ['7S'] }, 'expert-choice')(), streamOf({ hand: ['7S'] }, 'what-if:1')());
});

test('a record means the same whatever the order its fields are written in', async () => {
  const { record } = await recordedDeal(12);
  // as another client would write it: every object with its fields the other way round
  const reversed = (v: unknown): unknown =>
    Array.isArray(v)
      ? v.map(reversed)
      : v && typeof v === 'object'
        ? Object.fromEntries(
            Object.entries(v)
              .reverse()
              .map(([k, x]) => [k, reversed(x)]),
          )
        : v;
  const other = reversed(record);
  assert.notEqual(JSON.stringify(other), JSON.stringify(record));
  const { swings } = await ok<Swings>('/analysis/swings', { record, seat: 0 });
  assert.deepEqual(await ok<Swings>('/analysis/swings', { record: other, seat: 0 }), {
    swings,
    choices: (await ok<Swings>('/analysis/swings', { record, seat: 0 })).choices,
  });
  const asked = { seat: 0, trick: swings[0].trick, card: swings[0].choice };
  assert.deepEqual(await ok<WhatIf>('/analysis/what-if', { record: other, ...asked }), await ok<WhatIf>('/analysis/what-if', { record, ...asked }));
  assert.equal(streamOf({ a: 1, b: [{ c: 2, d: null }] }, 'x')(), streamOf({ b: [{ d: null, c: 2 }], a: 1 }, 'x')());
  assert.notEqual(streamOf({ a: 1, b: 2 }, 'x')(), streamOf({ a: 2, b: 1 }, 'x')());
});

test('a what-if finishes the deal twice with the same experts: the card played changes nothing, another card is the only difference', async () => {
  const { record } = await recordedDeal(12);
  const asked = { record, seat: 0 };
  const { swings } = await ok<Swings>('/analysis/swings', asked);
  assert.ok(swings.length > 0);
  const { trick, played, choice } = swings[0];
  const same = await ok<WhatIf>('/analysis/what-if', { ...asked, trick, card: played });
  assert.deepEqual(same.instead, same.asPlayed);
  assert.equal(same.difference, 0);
  const other = await ok<WhatIf>('/analysis/what-if', { ...asked, trick, card: choice });
  assert.deepEqual([other.played, other.card], [played, choice]);
  assert.deepEqual(other.asPlayed, same.asPlayed);
  const margin = (e: WhatIf['instead']) => e.us.gained - e.them.gained;
  assert.equal(other.difference, margin(other.instead) - margin(other.asPlayed));
  for (const end of [other.asPlayed, other.instead]) assert.equal(end.us.tricks + end.them.tricks, 8);
});

test('a replayed hand is in the order of the deck: what is played on from it cannot tell what its player did next', async () => {
  const { record } = await recordedDeal(12);
  const replay = replayDeal(record as unknown as Parameters<typeof replayDeal>[0]);
  for (const state of [replay.start, ...replay.auction.map((c) => c.before), ...replay.decisions.map((d) => d.before)]) {
    for (const hand of state.hands) assert.deepEqual(hand, sortedHand(hand));
  }
});

test('an analysis refuses what the replay refuses, and says where', async () => {
  const { record } = await recordedDeal(11);
  const tricks = structuredClone(record.tricks);
  tricks[4][2].card = tricks[0][0].card;
  const tampered = { ...record, tricks };
  const revoked = structuredClone(record.tricks);
  revoked[3].reverse();
  for (const [path, more] of [
    ['/analysis/summary', {}],
    ['/analysis/trick', { trick: 1 }],
    ['/analysis/expert-choice', { trick: 1 }],
    ['/analysis/what-if', { trick: 1, card: record.tricks[0][0].card }],
    ['/analysis/swings', {}],
  ] as const) {
    const twice = await refused(path, { record: tampered, seat: 0, ...more });
    assert.deepEqual(twice.at, { trick: 5, seat: record.tricks[4][2].seat }, path);
    assert.equal((await refused(path, { record: { ...record, tricks: revoked }, seat: 0, ...more })).at?.trick, 4, path);
    assert.match((await refused(path, { record, seat: 4, ...more })).message, /unknown seat/);
    await badRequest(path, { seat: 0, ...more });
  }
  for (const path of ['/analysis/trick', '/analysis/expert-choice', '/analysis/what-if'])
    for (const trick of [0, 9, 1.5, '1', undefined]) await badRequest(path, { record, seat: 0, trick, card: '7S' });
  await badRequest('/analysis/what-if', { record, seat: 0, trick: 1, card: '1S' });
  // a card of another seat: the player could not have played it
  const notMine = record.tricks[7].find((p) => p.seat !== 0)?.card;
  assert.match((await refused('/analysis/what-if', { record, seat: 0, trick: 1, card: notMine })).message, /illegal card/);
});

test('the contract file is what the bridge answers today', async () => {
  const recorded = readFileSync(new URL('../contract/cases.json', import.meta.url), 'utf8');
  assert.equal(recorded, serialize(buildCases()), 'bridge/contract/cases.json is out of date: npm run contract:record');
  for (const c of JSON.parse(recorded) as Case[]) {
    // a call is refused when its name says so, and only then: recording again cannot turn a refusal into an answer unnoticed
    assert.equal(c.status, c.name.startsWith('refused:') ? 422 : 200, c.name);
    const reply = await post(c.path, c.request);
    assert.deepEqual([reply.status, reply.body], [c.status, c.response], c.name);
  }
});

test('the conventions contract is what the engine reads as a card today', () => {
  const recorded = readFileSync(new URL('../contract/conventions.json', import.meta.url), 'utf8');
  const contract = buildConventions();
  assert.equal(recorded, serializeConventions(contract), 'bridge/contract/conventions.json is out of date: npm run contract:record');
  for (const card of Object.values(contract.ready)) assert.deepEqual(readConventions(JSON.parse(JSON.stringify(card))), card);
  for (const { why, card } of contract.refused) assert.equal(readConventions(card), null, why);
});

test('a call may carry the conventions card of the seat it is about, and what is not a card is a bad request', async () => {
  // a deal whose first seat to speak opens on the classic card, at 80
  const start = (await ok<{ state: GameState }>('/games', { seed: seedOf(2) })).state;
  const asked = { state: start, level: 'normal', lang: 'en', seed: seedOf(1) };
  const classic = await ok<{ action: unknown }>('/games/robot', asked);
  // the classic card, given or not, is one and the same
  assert.deepEqual(await ok('/games/robot', { ...asked, conventions: CLASSIC }), classic);
  // the same seat, on a card that opens at 90 only, bids what the engine bids by that card
  const quiet: Conventions = { ...CAUTIOUS, open: 90 };
  const byCard = await ok<{ action: unknown }>('/games/robot', { ...asked, conventions: quiet });
  assert.deepEqual(byCard.action, { kind: 'bid', ...aiBid(viewFor(start, start.turn), 'en', quiet) });
  assert.notDeepEqual(byCard.action, classic.action);
  for (const conventions of [null, 'bold', { ...CLASSIC, jack: 40 }, { ...CLASSIC, more: 1 }, []]) {
    const reply = await post<Failure>('/games/robot', { ...asked, conventions });
    assert.equal(reply.status, 400);
    assert.match(reply.body.error.message, /a conventions card expected/);
    await badRequest('/analysis/bids', { record: recordOf((await newGame(42)).state), seat: 0, lang: 'fr', conventions });
  }
});
