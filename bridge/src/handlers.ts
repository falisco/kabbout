// What each call of the bridge does. A handler receives the parsed JSON body, of unknown origin,
// and returns what is answered. It keeps nothing between two calls: the game travels in the request.
// Randomness always comes from a seed sent by the caller, so the same question gets the same answer.

import type { Action, BidAction, Conventions, GameState, Seat } from '../../engine/src/index.ts';
import {
  CLASSIC,
  readConventions,
  BID_VALUES,
  EngineError,
  SEAT_COUNT,
  SUITS,
  aiBid,
  aiPlay,
  apply,
  isCard,
  isLang,
  isLevel,
  isSeat,
  legalBid,
  legalPlays,
  newGame,
  publicEvents,
  viewFor,
} from '../../engine/src/index.ts';
import { BadRequest } from './errors.ts';
import { sorted } from './order.ts';
import { readRecord, replayDeal } from './replay.ts';
import { readSeed, seededStream } from './rng.ts';
import { isObject, unknownSeat } from './shape.ts';

export type Body = Record<string, unknown>;
export type Handler = (body: Body) => unknown;

const isList = (v: unknown, each: (item: unknown) => boolean): boolean => Array.isArray(v) && v.every(each);
const isTrick = (v: unknown): boolean => isObject(v) && isList(v.plays, isObject);
const isNullOr = (v: unknown, is: (item: unknown) => boolean): boolean => v === null || is(v);

/** every field of a game state, and nothing else; the compiler refuses this list if the engine's state gains or loses one */
const STATE_FIELDS: Record<keyof GameState, true> = {
  target: true,
  scoring: true,
  scores: true,
  history: true,
  dealer: true,
  round: true,
  phase: true,
  turn: true,
  hands: true,
  bids: true,
  best: true,
  coinche: true,
  contreQueue: true,
  passes: true,
  contract: true,
  trick: true,
  tricks: true,
  belote: true,
  result: true,
  winner: true,
};
const PHASES: Record<GameState['phase'], true> = { bidding: true, playing: true, roundEnd: true, gameEnd: true };

/**
 * A state as the bridge gave it out earlier, with only the fields a state is made of. The engine's view
 * of a seat copies every field but the hidden ones: anything the caller had stored beside them would be
 * handed to the players, so it does not get in. The rest is no defence, the caller being our own server:
 * it is what the engine needs to read a state without falling over, so that a state damaged in storage
 * is a bad request and not a failure of the bridge.
 */
function readState(v: unknown): GameState {
  const sound =
    isObject(v) &&
    typeof v.phase === 'string' &&
    Object.hasOwn(PHASES, v.phase) &&
    isSeat(v.turn) &&
    isSeat(v.dealer) &&
    Array.isArray(v.hands) &&
    v.hands.length === SEAT_COUNT &&
    v.hands.every((hand) => isList(hand, isCard)) &&
    isList(v.bids, isObject) &&
    isList(v.tricks, isTrick) &&
    isNullOr(v.trick, isTrick) &&
    isNullOr(v.contract, isObject) &&
    isNullOr(v.best, isObject) &&
    isNullOr(v.belote, isObject) &&
    isNullOr(v.contreQueue, Array.isArray) &&
    isList(v.scores, Number.isFinite) &&
    isList(v.history, isObject);
  if (!sound) throw new BadRequest('state: not a game state');
  return Object.fromEntries(Object.keys(STATE_FIELDS).map((field) => [field, v[field]])) as unknown as GameState;
}

function readSeat(v: unknown): Seat {
  if (!isSeat(v)) throw new EngineError(unknownSeat(v));
  return v;
}

/** a hand in the order of the deck, which says nothing of the order the cards were dealt in */

/** Whether the engine would deal the next round now. It is asked, not guessed: the draw is thrown away. */
function mayAskNext(state: GameState, seat: Seat): boolean {
  try {
    apply(state, seat, { kind: 'next' }, () => 0);
    return true;
  } catch (e) {
    if (e instanceof EngineError) return false;
    throw e;
  }
}

/** every bid there is, in the order of the bidding panel */
const ALL_BIDS: BidAction[] = [
  { type: 'pass' },
  ...BID_VALUES.flatMap((value) => SUITS.map((suit): BidAction => ({ type: 'bid', value, suit }))),
  { type: 'contre' },
  { type: 'surcontre' },
];

/** `seed` deals the cards; `target`, `dealer` and `scoring` are the engine's to accept or refuse. */
export const newGameHandler: Handler = (body) => {
  const rng = seededStream(readSeed(body.seed));
  const { target, dealer, scoring } = body as { target?: number; dealer?: Seat; scoring?: GameState['scoring'] };
  const step = newGame(rng, { target, dealer, scoring });
  return { state: step.state, events: publicEvents(step.events) };
};

/** One action of one seat. `seed` deals the next cards when the action ends a deal or an auction of four passes. */
export const applyHandler: Handler = (body) => {
  const state = readState(body.state);
  const rng = seededStream(readSeed(body.seed));
  // seat and action go to the engine as they came: it is the one that refuses them
  const step = apply(state, body.seat as Seat, body.action as Action, rng);
  return { state: step.state, events: publicEvents(step.events) };
};

/** What one seat may see of the game, its hand sorted. */
export const viewHandler: Handler = (body) => {
  const view = viewFor(readState(body.state), readSeat(body.seat));
  return { view: { ...view, hand: sorted(view.hand) } };
};

/** What one seat may do now: nothing when it is not its turn. */
export const legalHandler: Handler = (body) => {
  const state = readState(body.state);
  const seat = readSeat(body.seat);
  return { bids: ALL_BIDS.filter((bid) => legalBid(state, seat, bid)), cards: sorted(legalPlays(state, seat)), next: mayAskNext(state, seat) };
};

/**
 * The conventions card a call carries: the classic one when it carries none. Whose card it is, the
 * caller knows: the bridge bids by the card it is given, for the seat it is asked about.
 */
export function readCard(v: unknown): Conventions {
  if (v === undefined) return CLASSIC;
  const card = readConventions(v);
  if (!card) throw new BadRequest('conventions: a conventions card expected, every setting with a value it allows');
  return card;
}

/**
 * What a robot does for the seat whose turn it is, from that seat's view alone. The action is not
 * applied. A bid carries the robot's reason (`why`), which names its cards: it is for the state, never for a player.
 * `conventions`, when given, is the card that seat and its partner bid by.
 */
export const robotHandler: Handler = (body) => {
  const state = readState(body.state);
  const seed = readSeed(body.seed);
  if (!isLevel(body.level)) throw new BadRequest('level: easy, normal or expert expected');
  const lang = body.lang ?? 'fr';
  if (!isLang(lang)) throw new BadRequest('lang: fr, en or ar expected');
  const card = readCard(body.conventions);
  const view = viewFor(state, state.turn);
  if (state.phase === 'bidding') return { seat: state.turn, action: { kind: 'bid', ...aiBid(view, lang, card) } };
  if (state.phase === 'playing') return { seat: state.turn, action: { kind: 'play', card: aiPlay(view, seededStream(seed), body.level) } };
  throw new EngineError(`no robot to act in phase ${state.phase}`);
};

/** A recorded deal played again: its contract, its tricks with their winners, its score sheet and the hands as dealt. */
export const replayHandler: Handler = (body) => {
  const { start, end } = replayDeal(readRecord(body.record));
  return { hands: start.hands, contract: end.contract, tricks: end.tricks, result: end.result };
};

export { sorted };
