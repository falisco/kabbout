// A recorded deal looked at again, for the coach. Every call replays the record first, so a deal the
// rules do not allow is refused here as it is by `/deals/replay`. The player is the seat the questions
// are asked for. What the expert would have played is worked out from that seat's view alone; the
// what-if is the only call that plays on with the real hands, the deal being over.

import type {
  Card,
  CardAverage,
  Contract,
  GameState,
  Play,
  PlayerView,
  PublicBid,
  RoundResult,
  Scoring,
  Seat,
  Team,
} from '../../engine/src/index.ts';
import {
  EngineError,
  TRICKS_PER_DEAL,
  aiPlay,
  apply,
  expertAverages,
  isCard,
  isSeat,
  legalPlays,
  opponentOf,
  team,
  trickPoints,
  viewFor,
} from '../../engine/src/index.ts';
import { BadRequest } from './errors.ts';
import type { Body, Handler } from './handlers.ts';
import { sorted } from './handlers.ts';
import type { DealRecord, Decision, Replay } from './replay.ts';
import type { Reading } from './reading.ts';
import { readingOf } from './reading.ts';
import { readRecord, replayDeal } from './replay.ts';
import { streamOf } from './rng.ts';
import { unknownSeat } from './shape.ts';

interface Asked {
  record: DealRecord;
  replay: Replay;
  seat: Seat;
}

function read(body: Body): Asked {
  const record = readRecord(body.record);
  if (!isSeat(body.seat)) throw new EngineError(unknownSeat(body.seat));
  return { record, replay: replayDeal(record), seat: body.seat };
}

function readTrick(v: unknown): number {
  if (!Number.isInteger(v) || (v as number) < 1 || (v as number) > TRICKS_PER_DEAL) throw new BadRequest(`trick: 1 to ${TRICKS_PER_DEAL} expected`);
  return v as number;
}

/** the state the engine reached: a replayed deal has a contract and a result */
function scored(end: GameState): { contract: Contract; result: RoundResult } {
  if (!end.contract || !end.result) throw new Error('a replayed deal is scored');
  return { contract: end.contract, result: end.result };
}

/** what one team made of a deal */
interface Side {
  tricks: number;
  /** card points, with the ten of the last trick */
  cardPoints: number;
  /** what goes on the score sheet */
  gained: number;
}
const sideOf = (result: RoundResult, t: Team): Side => ({ tricks: result.tricksWon[t], cardPoints: result.cardPoints[t], gained: result.gained[t] });

export interface Summary {
  seat: Seat;
  scoring: Scoring;
  dealer: Seat;
  bids: PublicBid[];
  contract: Contract;
  /** whether the player's team took the contract */
  taking: boolean;
  made: boolean;
  /** the team holding king and queen of trumps, as the player's team (`us`) or the other (`them`) */
  belote: 'us' | 'them' | null;
  us: Side;
  them: Side;
}

/** The deal in a few lines: the auction, the contract, what each team made. */
export const summaryHandler: Handler = (body): Summary => {
  const { record, replay, seat } = read(body);
  const { contract, result } = scored(replay.end);
  const us = team(seat);
  const holder = replay.end.belote;
  return {
    seat,
    scoring: record.scoring,
    dealer: record.dealer,
    bids: replay.end.bids.map(({ why: _why, ...bid }) => bid),
    contract,
    taking: team(contract.seat) === us,
    made: result.made,
    belote: holder ? (team(holder.seat) === us ? 'us' : 'them') : null,
    us: sideOf(result, us),
    them: sideOf(result, opponentOf(us)),
  };
};

export interface TrickReview {
  trick: number;
  leader: Seat;
  plays: Play[];
  winner: Seat;
  /** the card points of the four cards; the ten of the last trick is not in it */
  points: number;
  /** whether the player's team won it */
  won: boolean;
  /** the card the player put in it */
  played: Card;
}

/** One trick as it was played. */
export const trickHandler: Handler = (body): TrickReview => {
  const { replay, seat } = read(body);
  const number = readTrick(body.trick);
  const trick = replay.end.tricks[number - 1];
  const { contract } = scored(replay.end);
  const mine = trick.plays.find((p) => p.seat === seat);
  if (!mine) throw new Error('a seat plays in every trick');
  return {
    trick: number,
    leader: trick.leader,
    plays: trick.plays,
    winner: trick.winner,
    points: trickPoints(trick.plays, contract.suit),
    won: team(trick.winner) === team(seat),
    played: mine.card,
  };
};

export interface ExpertChoice {
  trick: number;
  /** the player's cards at that moment */
  hand: Card[];
  /** the cards already in the trick when the player played */
  onTable: Play[];
  played: Card;
  /** what the expert plays there */
  choice: Card;
  /** one card only was legal: there was nothing to choose */
  forced: boolean;
  /** deals the expert imagined; 0 when forced */
  worlds: number;
  /** every legal card with its average over those deals: score-sheet points for the player's team minus the other team's */
  cards: CardAverage[];
  /** the average of the expert's card minus the average of the card played; 0 when they are the same card */
  loss: number;
  /** the table as the player could read it then: what explains the choice */
  reading: Reading;
}

/** the player's own card in a trick, with the deal as it stood before it */
function decisionOf(replay: Replay, seat: Seat, trick: number): Decision {
  const found = replay.decisions.find((d) => d.trick === trick && d.seat === seat);
  if (!found) throw new Error('a seat plays in every trick');
  return found;
}

/**
 * What the expert would have played, from what the seat could know: its view, and draws that come from
 * that view too. Two deals that look the same from the seat get the same answer, whatever the hidden hands.
 */
export function expertChoiceFrom(view: PlayerView, trick: number, played: Card): ExpertChoice {
  const found = expertAverages(view, streamOf(view, 'expert-choice'));
  const cards = found.cards.map(({ card, average }) => ({ card, average: Math.round(average) }));
  const averageOf = (card: Card): number => cards.find((c) => c.card === card)?.average ?? 0;
  return {
    trick,
    hand: view.hand,
    onTable: view.trick?.plays ?? [],
    played,
    choice: found.choice,
    forced: cards.length === 1,
    worlds: found.worlds,
    cards,
    loss: averageOf(found.choice) - averageOf(played),
    reading: readingOf(view),
  };
}

/**
 * What a seat sees of a deal, its hand in the order of the deck. In a replayed deal the hands are in
 * the order the cards were played: left so, the expert would be told what the player did next.
 */
export function seatView(state: GameState, seat: Seat): PlayerView {
  const view = viewFor(state, seat);
  return { ...view, hand: sorted(view.hand) };
}

const expertChoice = (replay: Replay, seat: Seat, trick: number): ExpertChoice => {
  const decision = decisionOf(replay, seat, trick);
  return expertChoiceFrom(seatView(decision.before, seat), trick, decision.card);
};

/** What the expert would have played at the player's turn in one trick. */
export const expertChoiceHandler: Handler = (body): ExpertChoice => {
  const { replay, seat } = read(body);
  return expertChoice(replay, seat, readTrick(body.trick));
};

export interface Swing {
  trick: number;
  played: Card;
  choice: Card;
  loss: number;
}

export interface Swings {
  /** the player's decisions the expert would have made otherwise, the costliest first */
  swings: Swing[];
  /** tricks where the player had more than one legal card */
  choices: number;
}

/**
 * The player's decisions ranked by what they cost against the expert's card, on average over the deals
 * the expert imagines: a loss in expectation, not in the deal as it fell. A card the expert would also
 * have played is not in the list, whatever became of it.
 */
export const swingsHandler: Handler = (body): Swings => {
  const { replay, seat } = read(body);
  const all = Array.from({ length: TRICKS_PER_DEAL }, (_, i) => expertChoice(replay, seat, i + 1));
  const swings = all
    .filter((c) => c.loss > 0)
    .map(({ trick, played, choice, loss }) => ({ trick, played, choice, loss }))
    .sort((a, b) => b.loss - a.loss || a.trick - b.trick);
  return { swings, choices: all.filter((c) => !c.forced).length };
};

/** how a deal ends, seen from the player's team */
interface Ending {
  made: boolean;
  us: Side;
  them: Side;
}

export interface WhatIf {
  trick: number;
  played: Card;
  card: Card;
  /** the deal finished by four experts from the card really played */
  asPlayed: Ending;
  /** the same from `card` */
  instead: Ending;
  /** what `card` changes for the player's team on the score sheet, the other team's change taken off */
  difference: number;
}

/** Four experts finish the deal from `state`. */
function finish(state: GameState, record: DealRecord, purpose: string, seat: Seat): Ending {
  const rng = streamOf(record, purpose);
  let s = state;
  while (s.phase === 'playing') s = apply(s, s.turn, { kind: 'play', card: aiPlay(viewFor(s, s.turn), rng, 'expert') }, rng).state;
  const { result } = scored(s);
  const us = team(seat);
  return { made: result.made, us: sideOf(result, us), them: sideOf(result, opponentOf(us)) };
}

/**
 * How the deal ends if the player plays `card` in that trick, and how it ends from the card really
 * played. Both lines are finished by the same four experts on the same draws, so the difference comes
 * from the card and not from what was played after it.
 */
export const whatIfHandler: Handler = (body): WhatIf => {
  const { record, replay, seat } = read(body);
  const trick = readTrick(body.trick);
  if (!isCard(body.card)) throw new BadRequest('card: a card expected');
  const card = body.card;
  const { before, card: played } = decisionOf(replay, seat, trick);
  if (!legalPlays(before, seat).includes(card)) throw new EngineError(`illegal card ${card} by seat ${seat}`);
  const purpose = `what-if:${trick}`;
  const line = (c: Card): Ending => finish(apply(before, seat, { kind: 'play', card: c }, () => 0).state, record, purpose, seat);
  const asPlayed = line(played);
  const instead = line(card);
  const margin = (e: Ending): number => e.us.gained - e.them.gained;
  return { trick, played, card, asPlayed, instead, difference: margin(instead) - margin(asPlayed) };
};
