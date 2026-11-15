// Questions about the game that several parts of the screen ask. Each one is answered from what it
// is given, with no state of its own, so the rules behind them are written (and tested) once.
import { BID_VALUES, team } from '../../../engine/src/index.ts';

/** the values that can still be bid, lowest first */
export const availableBids = (view) => BID_VALUES.filter((value) => !view.best || value > view.best.value);

/** the value the bidding panel shows: the one chosen while it can still be bid, else the lowest left (null when none is) */
export function bidValueToShow(view, chosen) {
  const available = availableBids(view);
  return available.includes(chosen) ? chosen : (available[0] ?? null);
}

/** whether `seat` may contre: the other side holds the bid and nobody contred yet */
export const canContre = (view, seat) => !!view.best && team(view.best.seat) !== team(seat) && view.coinche === 0;

const lastWhere = (bids, test) => [...bids].reverse().find(test) ?? null;
export const lastBidOf = (view, seat) => lastWhere(view.bids, (bid) => bid.seat === seat);
export const lastContre = (view) => lastWhere(view.bids, (bid) => bid.type === 'contre');

/** whether somebody is expected to bid or to play a card */
export const isAwaitingMove = (view) => view.phase === 'bidding' || view.phase === 'playing';

/** names one turn of one player, so that what is done once per turn is done once */
export const turnKey = (view) => [view.round, view.bids.length, view.tricks.length, view.trick ? view.trick.plays.length : 0].join(':');

/**
 * The tutorial bubble due now: 'bid' | 'contre' | 'play' | null.
 * @param {{ view: any, seat: number, done: Record<string, boolean>, myBidTurn: boolean, myPlayTurn: boolean }} moment
 */
export function tutorialStep({ view, seat, done, myBidTurn, myPlayTurn }) {
  if (myBidTurn && !view.contreQueue) {
    if (!done.bid) return 'bid';
    if (!done.contre && canContre(view, seat)) return 'contre';
  }
  if (myPlayTurn && !done.play) return 'play';
  return null;
}

/**
 * FFB art. 5.1: a turned trick may only be looked at once the next one is complete (its four
 * cards still on the table), never before playing one's card. Once the round is over, the last one.
 * @param {boolean} trickOnTable a complete trick is still shown on the table
 */
export function consultableTrick(view, trickOnTable) {
  const fromEnd = (back) => view.tricks[view.tricks.length - back] ?? null;
  if (view.phase === 'roundEnd' || view.phase === 'gameEnd') return fromEnd(1);
  return trickOnTable ? fromEnd(2) : null;
}
