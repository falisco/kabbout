// Game state shapes shared by the engine, the AI and the clients.

import type { Card, Seat, Suit, Team } from './cards.ts';

/** FFB contrée art. 4.2.2: 80 to 160 by tens, then the capot (250 here only orders it above 160); nothing beats a capot. */
export const CAPOT = 250;
/** the lowest and the highest number of points that can be bid */
export const MIN_BID = 80;
export const MAX_BID = 160;
export const BID_VALUES = [MIN_BID, 90, 100, 110, 120, 130, 140, 150, MAX_BID, CAPOT] as const;
export type BidValue = (typeof BID_VALUES)[number];
export const isBidValue = (v: unknown): v is BidValue => (BID_VALUES as readonly unknown[]).includes(v);

/**
 * How a deal is counted (see scoring.ts).
 * ffb: the federation's "points faits + points demandés", the value bid is added to the score.
 * tunisian: as played in Tunisian cafés, the value bid is never added.
 */
export const SCORINGS = ['ffb', 'tunisian'] as const;
export type Scoring = (typeof SCORINGS)[number];
export const isScoring = (v: unknown): v is Scoring => (SCORINGS as readonly unknown[]).includes(v);

export type BidAction = { type: 'pass' } | { type: 'contre' } | { type: 'surcontre' } | { type: 'bid'; value: BidValue; suit: Suit };

/** Longest reason a robot may attach to a bid. */
export const MAX_WHY_LENGTH = 200;

/** A bid as the table heard it. */
export type PublicBid = BidAction & { seat: Seat };

/**
 * A bid as recorded in the auction. `why` is a robot's reasoning: it describes the robot's cards,
 * so it stays in the full state and is never part of a PlayerView or of a public event.
 */
export type BidRecord = PublicBid & { why?: string };

export interface BestBid {
  seat: Seat;
  value: BidValue;
  suit: Suit;
}

export interface Contract extends BestBid {
  mult: 1 | 2 | 4;
}

export interface Play {
  seat: Seat;
  card: Card;
}

export interface Trick {
  leader: Seat;
  plays: Play[];
  /** known once the four cards are played */
  winner?: Seat;
}
export type CompletedTrick = Trick & { winner: Seat };

/** holder of the trump king and queen, and how many of the two have been played */
export interface BeloteHolder {
  seat: Seat;
  played: number;
}

/** The three rows of the score sheet, each as [team 0, team 1]. */
export interface RoundRows {
  /** what the tricks bring; under the Tunisian count, also the fixed scores of a failed or contré deal */
  tricks: [number, number];
  belote: [number, number];
  /** the value bid, where the count adds it */
  contract: [number, number];
}

export interface RoundResult {
  contract: Contract;
  made: boolean;
  /** points made by the taking team / by the defence (tricks + dix de der + belote), before rounding */
  takerPoints: number;
  defencePoints: number;
  cardPoints: [number, number];
  tricksWon: [number, number];
  capotTeam: Team | null;
  rows: RoundRows;
  gained: [number, number];
}

export interface HistoryEntry {
  contract: Contract;
  made: boolean;
  gained: [number, number];
  scores: [number, number];
}

export type Phase = 'bidding' | 'playing' | 'roundEnd' | 'gameEnd';

export interface GameState {
  target: number;
  /** set when the game is created, the same for all its deals */
  scoring: Scoring;
  scores: [number, number];
  history: HistoryEntry[];
  dealer: Seat;
  round: number;
  phase: Phase;
  turn: Seat;
  hands: [Card[], Card[], Card[], Card[]];
  bids: BidRecord[];
  best: BestBid | null;
  /** 0 none, 1 contré, 2 surcontré */
  coinche: 0 | 1 | 2;
  /** after a contre: the takers still to answer (surcontre or pass) */
  contreQueue: Seat[] | null;
  passes: number;
  contract: Contract | null;
  trick: Trick | null;
  tricks: CompletedTrick[];
  belote: BeloteHolder | null;
  result: RoundResult | null;
  winner: Team | null;
}

/**
 * What a seat asks the engine to do. `next` (deal the following round, or close the game) may come
 * from any seat once a round is over. `why` is for robots run by the host, see BidRecord.
 */
export type Action = { kind: 'bid'; bid: BidAction; why?: string } | { kind: 'play'; card: Card } | { kind: 'next' };

export type GameEvent =
  | { type: 'dealt'; dealer: Seat }
  | { type: 'bid'; record: BidRecord }
  | { type: 'redeal' }
  | { type: 'contract'; contract: Contract }
  | { type: 'played'; seat: Seat; card: Card }
  | { type: 'belote'; seat: Seat; rebelote: boolean }
  | { type: 'trick'; trick: CompletedTrick }
  | { type: 'roundEnd'; result: RoundResult }
  | { type: 'gameEnd'; winner: Team };

/** An event as it may be sent to the players: a bid without its reasoning. */
export type PublicEvent = Exclude<GameEvent, { type: 'bid' }> | { type: 'bid'; record: PublicBid };

/** What one seat is allowed to know: own hand, everything public, nobody else's cards or reasoning. */
export type PlayerView = Omit<GameState, 'hands' | 'belote' | 'bids'> & {
  seat: Seat;
  bids: PublicBid[];
  hand: Card[];
  handSizes: [number, number, number, number];
  /** only once announced (first card of the pair played) */
  belote: BeloteHolder | null;
};
