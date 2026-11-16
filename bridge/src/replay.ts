// A recorded deal played again through the engine. The record only holds what the table saw: the
// count, the dealer, the auction and the eight tricks. The hands are rebuilt from the tricks, then
// every bid and every card goes through `apply`, so a record the rules do not allow is refused.

import type { Card, GameState, Play, PublicBid, Scoring, Seat } from '../../engine/src/index.ts';
import { EngineError, SEATS, SEAT_COUNT, TRICKS_PER_DEAL, apply, isCard, isScoring, isSeat, newGame, seededRng } from '../../engine/src/index.ts';
import { BadRequest } from './errors.ts';
import { isObject, unknownSeat } from './shape.ts';

export interface DealRecord {
  scoring: Scoring;
  dealer: Seat;
  /** the auction as the table heard it, in order */
  bids: PublicBid[];
  /** the eight tricks, each as its four plays in the order they were made */
  tricks: Play[][];
}

/** Where a record stops being a legal deal: the bid of that index (from 0), or that trick (from 1) and seat. */
export type Fault = { bid: number } | { trick: number; seat?: Seat };

/** The engine refused the record at `at`. */
export class DealRefused extends EngineError {
  at: Fault;
  constructor(message: string, at: Fault) {
    super(message);
    this.at = at;
  }
}

/** an auction cannot be longer than this: nine raises, a contre, a surcontre and the passes between them */
const MAX_BIDS = 64;

/** The record with the shape checked. What the bids and the cards are is for the engine to judge. */
export function readRecord(v: unknown): DealRecord {
  if (!isObject(v)) throw new BadRequest('record: not a deal record');
  if (!isScoring(v.scoring)) throw new BadRequest('record.scoring: unknown count');
  if (!isSeat(v.dealer)) throw new BadRequest('record.dealer: unknown seat');
  if (!Array.isArray(v.bids) || v.bids.length > MAX_BIDS || !v.bids.every(isObject)) throw new BadRequest('record.bids: not an auction');
  const tricks: unknown = v.tricks;
  if (!Array.isArray(tricks) || tricks.length !== TRICKS_PER_DEAL) throw new BadRequest(`record.tricks: ${TRICKS_PER_DEAL} tricks expected`);
  for (const plays of tricks) {
    if (!Array.isArray(plays) || plays.length !== SEAT_COUNT || !plays.every(isObject)) throw new BadRequest('record.tricks: four plays to a trick');
  }
  return { scoring: v.scoring, dealer: v.dealer, bids: v.bids as unknown as PublicBid[], tricks: tricks as Play[][] };
}

/** Each seat's eight cards: the ones it played. */
function handsOf(tricks: Play[][]): GameState['hands'] {
  const hands: GameState['hands'] = [[], [], [], []];
  const seen = new Set<Card>();
  tricks.forEach((plays, index) => {
    for (const { seat, card } of plays) {
      const at = { trick: index + 1 };
      if (!isSeat(seat)) throw new DealRefused(unknownSeat(seat), at);
      if (!isCard(card)) throw new DealRefused(`unknown card ${String(card)}`, { ...at, seat });
      if (seen.has(card)) throw new DealRefused(`card ${card} is played twice`, { ...at, seat });
      seen.add(card);
      hands[seat].push(card);
    }
  });
  const short = SEATS.find((seat) => hands[seat].length !== TRICKS_PER_DEAL);
  if (short !== undefined) throw new DealRefused(`seat ${short} does not play ${TRICKS_PER_DEAL} cards`, { trick: TRICKS_PER_DEAL, seat: short });
  return hands;
}

export interface Replay {
  /** the deal before the first bid, with the hands rebuilt */
  start: GameState;
  /** the deal once scored (phase `roundEnd`) */
  end: GameState;
}

/** Plays the record through the engine, or throws DealRefused where the engine stops it. */
export function replayDeal(record: DealRecord): Replay {
  // a new game is only the engine's way to an empty table: its cards are replaced by the record's
  const rng = seededRng(0);
  const start = newGame(rng, { dealer: record.dealer, scoring: record.scoring }).state;
  start.hands = handsOf(record.tricks);

  let state = start;
  record.bids.forEach((bid, index) => {
    const { seat, ...action } = bid;
    try {
      const step = apply(state, seat, { kind: 'bid', bid: action }, rng);
      // four passes: the engine deals again, and those cards are not the record's
      if (step.events.some((e) => e.type === 'redeal')) throw new EngineError('everybody passed: there is no deal to replay');
      state = step.state;
    } catch (e) {
      throw e instanceof EngineError ? new DealRefused(e.message, { bid: index }) : e;
    }
  });
  if (state.phase !== 'playing') throw new DealRefused('the auction does not end in a contract', { bid: record.bids.length });

  record.tricks.forEach((plays, index) => {
    for (const { seat, card } of plays) {
      try {
        state = apply(state, seat, { kind: 'play', card }, rng).state;
      } catch (e) {
        throw e instanceof EngineError ? new DealRefused(e.message, { trick: index + 1, seat }) : e;
      }
    }
  });
  return { start, end: state };
}
