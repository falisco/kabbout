// The contract between the bridge and the PHP side, as a list of calls with their answers.
// `node bridge/scripts/record-contract.ts` writes it to cases.json; the bridge's tests check that the
// file is what the bridge answers today, and the PHP tests run the same calls through their adapter.
// Only calls the typed PHP adapter can make are listed: malformed requests are the bridge tests' business.

import type { Action, Conventions, GameState, Seat } from '../../engine/src/index.ts';
import { BOLD, CAUTIOUS, CLASSIC, nextSeat } from '../../engine/src/index.ts';
import type { Body } from '../src/handlers.ts';
import { dispatch } from '../src/server.ts';

export interface Case {
  name: string;
  path: string;
  request: Body;
  status: number;
  response: unknown;
}

interface StepAnswer {
  state: GameState;
}
interface RobotAnswer {
  seat: Seat;
  action: Action;
}

/** a seed for tests and for the contract: a small number, written as the bridge reads seeds */
export const seedOf = (n: number): string => n.toString(16).padStart(32, '0');

/** the answer of a call that must succeed */
function ask<T>(path: string, request: Body): T {
  const { status, body } = dispatch(path, request);
  if (status !== 200) throw new Error(`${path} answered ${status}: ${JSON.stringify(body)}`);
  return body as T;
}

/** Robots play from `state` until `until` holds; every call takes its seed from `seeds`. */
function advance(state: GameState, until: (s: GameState) => boolean, seeds: () => string): GameState {
  let s = state;
  while (!until(s)) {
    const { seat, action } = ask<RobotAnswer>('/games/robot', { state: s, level: 'normal', lang: 'fr', seed: seeds() });
    s = ask<StepAnswer>('/games/apply', { state: s, seat, action, seed: seeds() }).state;
  }
  return s;
}

/** the deal just played, as a client would record it */
export function recordOf(s: GameState): Body {
  return {
    scoring: s.scoring,
    dealer: s.dealer,
    bids: s.bids.map(({ why: _why, ...bid }) => bid),
    tricks: s.tricks.map((trick) => trick.plays),
  };
}

type Add = (name: string, path: string, request: Body) => unknown;

/** the deal of `over` replayed as recorded, then with two faults a record may carry */
function addReplays(add: Add, over: GameState): void {
  const record = recordOf(over);
  add('a recorded deal replayed', '/deals/replay', { record });
  const tricks = structuredClone(record.tricks) as { seat: Seat; card: string }[][];
  // the second card of the first trick and the same seat's card in the last trick change places
  const second = tricks[0][1];
  const later = tricks[7].find((p) => p.seat === second.seat);
  if (!later) throw new Error('a seat plays in every trick');
  [second.card, later.card] = [later.card, second.card];
  add('refused: a recorded deal with two cards swapped', '/deals/replay', { record: { ...record, tricks } });
  add('refused: a recorded deal whose auction is four passes', '/deals/replay', {
    record: { ...record, bids: [1, 2, 3, 0].map((offset) => ({ type: 'pass', seat: (over.dealer + offset) % 4 })) },
  });
}

interface ChoiceAnswer {
  trick: number;
  played: string;
  forced: boolean;
  cards: { card: string }[];
}

/** the deal of `over` analysed for seat 0: every question the coach may ask, and two it may not */
function addAnalysis(add: Add, over: GameState): void {
  const record = recordOf(over);
  const asked = { record, seat: 0 };
  add('a recorded deal summed up for a seat', '/analysis/summary', asked);
  add('one trick of a recorded deal', '/analysis/trick', { ...asked, trick: 1 });
  let free: ChoiceAnswer | undefined;
  for (let trick = 1; trick <= 8; trick++) {
    const choice = add(`what the expert would have played in trick ${trick}`, '/analysis/expert-choice', { ...asked, trick }) as ChoiceAnswer;
    if (!choice.forced) free ??= choice;
  }
  if (!free) throw new Error('a seat has a choice somewhere in a deal');
  const other = free.cards.find((c) => c.card !== free.played);
  if (!other) throw new Error('a choice is between two cards at least');
  add('the deal finished from another card', '/analysis/what-if', { ...asked, trick: free.trick, card: other.card });
  add('the deal finished from the card really played', '/analysis/what-if', { ...asked, trick: free.trick, card: free.played });
  add('the decisions of a seat, the costliest first', '/analysis/swings', asked);
  add('the decisions of the other team', '/analysis/swings', { record, seat: 1 });
  add('the auction of a seat next to the expert’s', '/analysis/bids', { ...asked, lang: 'fr' });
  add('the same auction with its reasons in Tunisian Arabic', '/analysis/bids', { ...asked, lang: 'ar' });
  // the card another seat played in the last trick: the player never held it
  const notHeld = over.tricks[7].plays.find((p) => p.seat !== 0);
  if (!notHeld) throw new Error('four seats play in a trick');
  add('refused: a what-if with a card the seat did not hold', '/analysis/what-if', { ...asked, trick: 1, card: notHeld.card });
  const tricks = structuredClone(record.tricks) as { seat: Seat; card: string }[][];
  tricks[3].reverse();
  add('refused: an analysis of a deal played out of turn', '/analysis/swings', { record: { ...record, tricks }, seat: 0 });
}

/**
 * Calls that carry a conventions card. They are recorded last, so that the calls recorded before the
 * card existed stay where they were, line for line.
 */
function addConventions(add: Add, over: GameState): void {
  // a deal whose first seat to speak opens at 80 on the classic card: on a card that opens at 90 only, it passes
  const opens = (dispatch('/games', { seed: seedOf(2) }).body as StepAnswer).state;
  const asked = { state: opens, level: 'normal', lang: 'en', seed: seedOf(1) };
  const quiet: Conventions = { ...CAUTIOUS, open: 90 };
  add('a robot given no card opens', '/games/robot', asked);
  add('a robot given the classic card bids as one given none', '/games/robot', { ...asked, conventions: CLASSIC });
  add('a robot bids by the card its pair agreed on, and passes', '/games/robot', { ...asked, conventions: quiet });
  add('the auction of a seat next to an expert who bids by the pair’s card', '/analysis/bids', {
    record: recordOf(over),
    seat: 0,
    lang: 'fr',
    conventions: BOLD,
  });
}

/** what is asked about a deal once it is over; the calls that carry a card come last */
function addAfterTheDeal(add: Add, over: GameState): void {
  addReplays(add, over);
  addAnalysis(add, over);
  addConventions(add, over);
}

export function buildCases(): Case[] {
  const cases: Case[] = [];
  const add: Add = (name, path, request) => {
    const { status, body } = dispatch(path, request);
    cases.push({ name, path, request, status, response: body });
    return body;
  };
  let seed = 1000;
  const seeds = () => seedOf(seed++);

  const start = (add('a new game, federation count', '/games', { seed: seedOf(42), scoring: 'ffb' }) as StepAnswer).state;
  add('a new game, Tunisian count, dealer and target chosen', '/games', { seed: seedOf(7), scoring: 'tunisian', target: 500, dealer: 2 });
  for (const seat of [0, 1, 2, 3]) add(`the view of seat ${seat} during the auction`, '/games/view', { state: start, seat });
  add('what the seat to speak may bid', '/games/legal', { state: start, seat: start.turn });
  add('what a seat may do out of turn: nothing', '/games/legal', { state: start, seat: nextSeat(start.turn) });
  const bid = add('a robot bids, with its reason in English', '/games/robot', {
    state: start,
    level: 'normal',
    lang: 'en',
    seed: seedOf(1),
  }) as RobotAnswer;
  add('the robot bid is applied', '/games/apply', { state: start, seat: bid.seat, action: bid.action, seed: seedOf(2) });
  add('refused: a bid out of turn', '/games/apply', {
    state: start,
    seat: nextSeat(start.turn),
    action: { kind: 'bid', bid: { type: 'pass' } },
    seed: seedOf(3),
  });
  add('refused: a surcontre with nothing to answer', '/games/apply', {
    state: start,
    seat: start.turn,
    action: { kind: 'bid', bid: { type: 'surcontre' } },
    seed: seedOf(4),
  });
  add('refused: a card during the auction', '/games/apply', {
    state: start,
    seat: start.turn,
    action: { kind: 'play', card: start.hands[start.turn][0] },
    seed: seedOf(5),
  });
  add('refused: the next deal before this one is over', '/games/apply', { state: start, seat: 0, action: { kind: 'next' }, seed: seedOf(6) });

  // the second trick, so that a trick has been won and one is on the table
  const playing = advance(start, (s) => s.phase === 'playing' && s.tricks.length === 1 && s.trick?.plays.length === 1, seeds);
  for (const seat of [0, 1, 2, 3]) add(`the view of seat ${seat} during the play`, '/games/view', { state: playing, seat });
  add('the cards the seat to play may play', '/games/legal', { state: playing, seat: playing.turn });
  const play = add('the expert robot plays a card', '/games/robot', { state: playing, level: 'expert', lang: 'fr', seed: seedOf(5) }) as RobotAnswer;
  add('the robot card is applied', '/games/apply', { state: playing, seat: play.seat, action: play.action, seed: seedOf(8) });
  const notMine = playing.hands[nextSeat(playing.turn)][0];
  add('refused: a card the seat does not hold', '/games/apply', {
    state: playing,
    seat: playing.turn,
    action: { kind: 'play', card: notMine },
    seed: seedOf(9),
  });

  const over = advance(playing, (s) => s.phase === 'roundEnd', seeds);
  add('what a seat may do once the deal is scored', '/games/legal', { state: over, seat: 0 });
  add('the next deal', '/games/apply', { state: over, seat: 0, action: { kind: 'next' }, seed: seedOf(10) });
  add('refused: a robot asked to act once the deal is scored', '/games/robot', { state: over, level: 'easy', lang: 'ar', seed: seedOf(11) });

  addAfterTheDeal(add, over);
  return cases;
}

/** the file's text: one call to a line, so that a change shows as the lines it touches */
export const serialize = (cases: Case[]): string => `[\n${cases.map((c) => JSON.stringify(c)).join(',\n')}\n]\n`;
