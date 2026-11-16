// The bridge as its caller sees it, over HTTP: it refuses what the engine refuses, a seat's view never
// holds what that seat may not know, the same question always gets the same answer, and a recorded
// deal is only accepted if the engine can play it again.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import type { AddressInfo } from 'node:net';
import { after, before, test } from 'node:test';
import type { Action, Card, GameState, PlayerView, PublicEvent, RoundResult, Seat } from '../../engine/src/index.ts';
import { SEATS, legalPlays, nextSeat } from '../../engine/src/index.ts';
import type { Case } from '../contract/build.ts';
import { buildCases, recordOf, seedOf, serialize } from '../contract/build.ts';
import { seededStream } from '../src/rng.ts';
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
