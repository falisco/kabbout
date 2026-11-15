// The expert's card play. It deals the unseen cards at random in ways that fit everything it has
// observed, plays each of its legal cards to the end of the deal in every such world (all four
// seats then following the rules of thumb of policy.ts), and keeps the card that scores best on
// average under the real scoring.

import type { Card, Seat, Team } from '../cards.ts';
import { SEATS, SEAT_COUNT, TRICKS_PER_DEAL, nextSeat, opponentOf, team } from '../cards.ts';
import type { Rng } from '../rng.ts';
import { shuffle } from '../rng.ts';
import { legalCards, trickPoints, trickWinner } from '../rules.ts';
import { scoreTotals, tallyTricks } from '../scoring.ts';
import type { Contract, Play, PlayerView } from '../types.ts';
import { evaluateSuit } from './bidding.ts';
import { cardPlay, situation, tricksSoFar } from './memory.ts';
import type { Memory, Situation } from './memory.ts';
import { chooseCard } from './policy.ts';

type Hands = Card[][];

/**
 * How many times a draw is started again. Cards are placed one by one, so a draw can run into a
 * dead end (a card left that only full hands may hold); this is rare, and a new shuffle gets out of it.
 */
const SAMPLING_ATTEMPTS = 30;

/** One way the unseen cards may lie, or null if none was found. */
export function sampleHands(view: PlayerView, m: Memory, rng: Rng): Hands | null {
  const others = SEATS.filter((s) => s !== view.seat);
  for (let attempt = 0; attempt < SAMPLING_ATTEMPTS; attempt++) {
    const hands: Hands = [[], [], [], []];
    // the cards with the fewest possible holders are placed first
    const cards = shuffle(m.unseen, rng)
      .map((c) => ({ c, who: others.filter((s) => !m.cannot[s].has(c)) }))
      .sort((a, b) => a.who.length - b.who.length);
    let ok = true;
    for (const { c, who } of cards) {
      const room = who.map((s) => view.handSizes[s] - hands[s].length);
      const total = room.reduce((a, b) => a + b, 0);
      if (total <= 0) {
        ok = false;
        break;
      }
      // a holder is drawn in proportion to the room left in its hand, so that every hand fills up evenly
      let pick = rng() * total;
      let i = 0;
      while (i < who.length - 1 && pick >= room[i]) pick -= room[i++];
      // rounding can leave the pick on a full hand: take the first one with room
      if (room[i] <= 0) i = room.findIndex((r) => r > 0);
      hands[who[i]].push(c);
    }
    if (ok) {
      hands[view.seat] = [...view.hand];
      return hands;
    }
  }
  return null;
}

/** The team holding trump king and queen in this world; the pair always ends up played, so it scores. */
function beloteTeam(view: PlayerView, hands: Hands, trump: Contract['suit']): Team | null {
  const holder = (card: Card): Seat | undefined => {
    for (const trick of tricksSoFar(view)) for (const p of trick.plays) if (p.card === card) return p.seat;
    return SEATS.find((seat) => hands[seat].includes(card));
  };
  const kingHolder = holder(`K${trump}`);
  return kingHolder !== undefined && kingHolder === holder(`Q${trump}`) ? team(kingHolder) : null;
}

/**
 * Whether a world fits the auction: whoever first named the trump suit had a hand to open in it.
 * (The robots' own opening test is used; a human may open on less, so this only steers the draw.)
 */
function fitsAuction(view: PlayerView, hands: Hands, trump: Contract['suit']): boolean {
  const opener = view.bids.find((b) => b.type === 'bid' && b.suit === trump)?.seat;
  if (opener === undefined || opener === view.seat) return true;
  const dealt = [...hands[opener]];
  for (const trick of tricksSoFar(view)) for (const p of trick.plays) if (p.seat === opener) dealt.push(p.card);
  return evaluateSuit(dealt, trump).value > 0;
}
/** how many draws may be thrown away, per world kept, for not fitting the auction */
const REDRAWS = 4;

/** worlds always played out, even when the caller's time is up */
export const MIN_SAMPLES = 12;

/**
 * `outOfTime`, when given, is asked before each world past the first MIN_SAMPLES: a slow device
 * then thinks less instead of freezing. Without it the choice depends on the seed alone.
 */
export function searchCard(view: PlayerView, m: Memory, rng: Rng, samples: number, outOfTime?: () => boolean): Card {
  const { contract, trick } = cardPlay(view);
  const trump = contract.suit;
  const us = team(view.seat);
  const first = chooseCard(situation(view, m));
  const legal = legalCards(view.hand, trick.plays, trump, view.seat);
  if (legal.length === 1) return first;
  // the rule-of-thumb card comes first: it is kept unless another one does strictly better
  const candidates = [first, ...legal.filter((c) => c !== first)];
  const totals = candidates.map(() => 0);
  const soFar = tallyTricks(view.tricks, trump);

  for (let n = 0; n < samples; n++) {
    if (n >= MIN_SAMPLES && outOfTime?.()) break;
    let world = sampleHands(view, m, rng);
    for (let redraw = 0; redraw < REDRAWS && world && !fitsAuction(view, world, trump); redraw++) world = sampleHands(view, m, rng);
    // no world fits what was observed: the totals so far decide, the rule-of-thumb card if there are none
    if (!world) break;
    const belote = beloteTeam(view, world, trump);
    candidates.forEach((card, i) => {
      const end = playOut(view, contract, world, m.played, soFar, trick.plays, card);
      const gained = scoreTotals(contract, end.points, end.won, end.lastTrick, belote, view.scoring).gained;
      totals[i] += gained[us] - gained[opponentOf(us)];
    });
  }
  let best = 0;
  for (let i = 1; i < candidates.length; i++) if (totals[i] > totals[best]) best = i;
  return candidates[best];
}

type Pair = [number, number];

/**
 * Plays the deal to its end in one world: this player plays `card`, then all four seats follow the
 * rules of thumb, each knowing the others' cards. Returns the totals the score is made from.
 */
function playOut(
  view: PlayerView,
  contract: Contract,
  world: Hands,
  alreadyPlayed: ReadonlySet<Card>,
  soFar: { won: Pair; points: Pair },
  onTable: readonly Play[],
  card: Card,
): { points: Pair; won: Pair; lastTrick: Team } {
  const trump = contract.suit;
  const hands = world.map((hand) => hand.slice());
  const played = new Set(alreadyPlayed);
  const points: Pair = [soFar.points[0], soFar.points[1]];
  const won: Pair = [soFar.won[0], soFar.won[1]];
  let plays: Play[] = onTable.slice();
  let turn: Seat = view.seat;
  let forced: Card | null = card;
  let lastTrick: Team = team(view.seat);
  const seen: Situation = { seat: turn, hand: [], plays, trump, taker: contract.seat, played, mayHold: (seat, c) => hands[seat].includes(c) };
  for (let done = view.tricks.length; done < TRICKS_PER_DEAL;) {
    seen.seat = turn;
    seen.hand = hands[turn];
    seen.plays = plays;
    const chosen: Card = forced ?? chooseCard(seen);
    forced = null;
    hands[turn].splice(hands[turn].indexOf(chosen), 1);
    played.add(chosen);
    plays.push({ seat: turn, card: chosen });
    if (plays.length < SEAT_COUNT) {
      turn = nextSeat(turn);
      continue;
    }
    turn = trickWinner(plays, trump).seat;
    lastTrick = team(turn);
    won[lastTrick]++;
    points[lastTrick] += trickPoints(plays, trump);
    plays = [];
    done++;
  }
  return { points, won, lastTrick };
}
