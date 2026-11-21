// The robots' auction. They only receive a PlayerView, so they cannot see anyone else's cards.
//
// Bidding follows classic contrée conventions. A hand opens in a suit where it has a trump base:
// jack and nine with a third trump, or the jack with three others, are worth 80; weaker bases
// (jack and nine alone, jack third, a long nine with the ace) are worth 60. Each trump beyond the
// base adds 10, the trump ace beside the jack 10, the belote 20, an outside ace 10 and its ten 5, a
// void with four trumps 5; a hand with no trick outside its trumps loses 10. The partner supports
// with 20 for the jack, 10 for the nine, 10 for three trumps, 20 for the belote and 10 per outside
// ace. A contre needs a well guarded nine of trumps (see evaluateContre). Each bid carries its reasons.
//
// What it takes to open and what the partner adds are the pair's conventions card (conventions.ts).
// Those figures are the classic card's, which is the one a robot bids by when it is given none.

import type { Card, Rank, Seat, Suit } from '../cards.ts';
import { ORDER_PLAIN, ORDER_TRUMP, RANKS, SUITS, partnerOf, suitOf, team } from '../cards.ts';
import { EngineError } from '../errors.ts';
import { hasBelote } from '../rules.ts';
import { BELOTE_POINTS } from '../scoring.ts';
import type { BestBid, BidAction, BidValue, PlayerView, PublicBid } from '../types.ts';
import { CAPOT, MAX_BID, MIN_BID, isBidValue } from '../types.ts';
import type { Conventions } from './conventions.ts';
import { CLASSIC, sameConventions } from './conventions.ts';
import type { Lang, Phrases } from './phrases.ts';
import { PHRASES } from './phrases.ts';

const SUIT_SYMBOL: Record<Suit, string> = { S: '♠', H: '♥', D: '♦', C: '♣' };
const REASON_SEPARATOR = ' · ';
/** one level of the auction, and half of one */
const LEVEL = 10;
const HALF_LEVEL = 5;
/** a trump base one hand can open on, and one that needs help */
const FULL_BASE = MIN_BID;
const WEAK_BASE = 60;
const roundDownToLevel = (value: number): number => Math.floor(value / LEVEL) * LEVEL;

export interface AiBid {
  bid: BidAction;
  why: string;
}

/** One reason for a bid, worded when the language is known. */
export type Reason = (phrases: Phrases) => string;
/** What a hand is worth for some purpose, and why. The value is found without wording anything. */
export interface Evaluation {
  value: number;
  reasons: Reason[];
}

export const explain = (reasons: readonly Reason[], lang: Lang): string[] => reasons.map((reason) => reason(PHRASES[lang]));

const holds = (hand: readonly Card[], rank: Rank, suit: Suit): boolean => hand.includes(`${rank}${suit}`);
const lengthIn = (hand: readonly Card[], suit: Suit): number => hand.filter((c) => suitOf(c) === suit).length;
const outsideAces = (hand: readonly Card[], trump: Suit): Suit[] => SUITS.filter((suit) => suit !== trump && holds(hand, 'A', suit));
const aceOf =
  (suit: Suit): Reason =>
  (p) =>
    `${p.ace} ${SUIT_SYMBOL[suit]}`;

/** The trump holding a hand would open on in `trump`, or null when it has none. */
function trumpBase(hand: readonly Card[], trump: Suit): Evaluation | null {
  const symbol = SUIT_SYMBOL[trump];
  const length = lengthIn(hand, trump);
  const jack = holds(hand, 'J', trump);
  const nine = holds(hand, '9', trump);
  if (jack && nine && length >= 3) return { value: FULL_BASE, reasons: [(p) => `${p.jack}-9 ${symbol}`] };
  if (jack && length >= 4) return { value: FULL_BASE, reasons: [(p) => p.jackAnd(`${p.jack} ${symbol}`, length - 1)] };
  if (jack && nine && length === 2) return { value: WEAK_BASE, reasons: [(p) => p.bare(`${p.jack}-9 ${symbol}`)] };
  if (jack && length === 3) return { value: WEAK_BASE, reasons: [(p) => p.third(`${p.jack} ${symbol}`)] };
  if (nine && length >= 5 && holds(hand, 'A', trump)) return { value: WEAK_BASE, reasons: [(p) => p.long(`9-${p.ace} ${symbol}`)] };
  return null;
}

/** What a hand is worth as the taker's with `trump` as trumps; 0 when it has no trump base. */
export function evaluateSuit(hand: readonly Card[], trump: Suit): Evaluation {
  const base = trumpBase(hand, trump);
  if (!base) return { value: 0, reasons: [] };
  let { value } = base;
  const reasons = [...base.reasons];
  const length = lengthIn(hand, trump);
  const jack = holds(hand, 'J', trump);
  const jackAndNine = jack && holds(hand, '9', trump);
  const belote = hasBelote(hand, trump);

  // the base already counts three trumps with jack and nine, four with the jack alone
  const longTrumps = length - (jackAndNine ? 3 : 4);
  if (longTrumps > 0) {
    value += longTrumps * LEVEL;
    reasons.push((p) => p.trumps(length));
  }
  if (jack && holds(hand, 'A', trump)) {
    value += LEVEL;
    reasons.push(aceOf(trump));
  }
  if (belote) {
    value += BELOTE_POINTS;
    reasons.push((p) => p.belote);
  }
  for (const suit of SUITS) {
    if (suit === trump) continue;
    if (holds(hand, 'A', suit)) {
      value += LEVEL;
      reasons.push(aceOf(suit));
      if (holds(hand, 'T', suit)) {
        value += HALF_LEVEL;
        reasons.push(() => `10 ${SUIT_SYMBOL[suit]}`);
      }
    }
    if (lengthIn(hand, suit) === 0 && length >= 4) {
      value += HALF_LEVEL;
      reasons.push((p) => p.ruff(SUIT_SYMBOL[suit]));
    }
  }
  const noOutsideTrick = !outsideAces(hand, trump).length && !belote && !(jackAndNine && length >= 4) && length < 5;
  if (noOutsideTrick) value -= LEVEL;
  return { value: roundDownToLevel(value), reasons };
}

/**
 * Adds up what a hand is worth by a card. On a card that is not the classic one each reason says what
 * it added, so that the player sees the card at work; the classic card's reasons are what they always were.
 */
function counting(card: Conventions): Evaluation & { add: (points: number, reason: Reason) => void } {
  const agreed = !sameConventions(card, CLASSIC);
  const counted: Evaluation & { add: (points: number, reason: Reason) => void } = {
    value: 0,
    reasons: [],
    add(points, reason) {
      if (!points) return;
      counted.value += points;
      counted.reasons.push(agreed ? (p) => `${reason(p)} +${points}` : reason);
    },
  };
  return counted;
}

/** What a hand adds to a partner who bid `trump` by what it holds in that suit: the jack, the nine, length, the belote. */
export function trumpSupport(hand: readonly Card[], trump: Suit, card: Conventions = CLASSIC): Evaluation {
  const symbol = SUIT_SYMBOL[trump];
  const length = lengthIn(hand, trump);
  const counted = counting(card);
  if (holds(hand, 'J', trump)) counted.add(card.jack, (p) => `${p.jack} ${symbol}`);
  if (holds(hand, '9', trump)) counted.add(card.nine, () => `9 ${symbol}`);
  if (length >= 3) counted.add(card.threeTrumps, (p) => p.trumps(length));
  if (hasBelote(hand, trump)) counted.add(card.belote, (p) => p.belote);
  return { value: counted.value, reasons: counted.reasons };
}

/** What a hand adds to a partner who bid `trump` by its aces in the other suits. */
export function aceSupport(hand: readonly Card[], trump: Suit, card: Conventions = CLASSIC): Evaluation {
  const counted = counting(card);
  for (const suit of outsideAces(hand, trump)) counted.add(card.ace, aceOf(suit));
  return { value: counted.value, reasons: counted.reasons };
}

/** All a hand adds to a partner who bid `trump`, by the pair's card: its trumps, then its aces. */
export function evaluateSupport(hand: readonly Card[], trump: Suit, card: Conventions = CLASSIC): Evaluation {
  const [trumps, aces] = [trumpSupport(hand, trump, card), aceSupport(hand, trump, card)];
  return { value: trumps.value + aces.value, reasons: [...trumps.reasons, ...aces.reasons] };
}

/**
 * Whether to contre `bid`. A contre pays when the contract fails more than about 55 % of the time
 * (under the federation's count it wins 320 + the contract when right, and loses as much and the
 * takers' own points when wrong), and the robots' contracts fail about one time in seven. Measured
 * over tens of thousands of deals, the hands that beat them often enough hold the nine of trumps
 * well guarded behind a taker who has the jack: jack and nine third, four trumps to the nine, or
 * three to the nine with two outside aces. Aces alone, a short nine or a lone jack do not.
 */
export function evaluateContre(hand: readonly Card[], bid: BestBid): { worthIt: boolean; reasons: Reason[] } {
  const trump = bid.suit;
  const symbol = SUIT_SYMBOL[trump];
  const length = lengthIn(hand, trump);
  const aces = outsideAces(hand, trump);
  const nine = holds(hand, '9', trump);
  let guard: Reason | null = null;
  if (holds(hand, 'J', trump) && nine && length >= 3) guard = (p) => `${p.jack}-9 ${symbol}`;
  else if (nine && length >= 4) guard = (p) => p.fourth(`9 ${symbol}`);
  else if (nine && length === 3 && aces.length >= 2) guard = (p) => p.third(`9 ${symbol}`);
  return guard ? { worthIt: true, reasons: [guard, ...aces.map(aceOf)] } : { worthIt: false, reasons: [] };
}

/**
 * A hand that takes the eight tricks whatever the others hold: enough top trumps to draw every
 * trump that is out, and nothing but top cards in the other suits.
 */
export function sureCapot(hand: readonly Card[], trump: Suit): boolean {
  /** how many of the suit's best cards the hand holds without a gap, from the top */
  const topCards = (suit: Suit, order: readonly Rank[]) => {
    const fromTheTop = [...order].reverse();
    const firstMissing = fromTheTop.findIndex((rank) => !holds(hand, rank, suit));
    return firstMissing < 0 ? fromTheTop.length : firstMissing;
  };
  const trumps = lengthIn(hand, trump);
  const plainSuits = SUITS.filter((suit) => suit !== trump);
  // a suit I do not hold may be led before I have the lead: one top trump goes on ruffing it
  const trumpsSpentRuffing = plainSuits.some((suit) => lengthIn(hand, suit) === 0) ? 1 : 0;
  const trumpsToDraw = Math.min(trumps, RANKS.length - trumps + trumpsSpentRuffing);
  if (topCards(trump, ORDER_TRUMP) < trumpsToDraw) return false;
  return trumps >= 4 && plainSuits.every((suit) => topCards(suit, ORDER_PLAIN) === lengthIn(hand, suit));
}

/** a surcontre needs the pair's hands to be worth this much more than the contract */
const SURCONTRE_MARGIN = 30;
/** a hand names its own suit over its partner's only when it is worth this much more than the partner's bid */
const OWN_SUIT_MARGIN = 30;
/** support worth naming: the jack of trumps, or as much in smaller cards */
const REAL_SUPPORT = 20;

type SuitBid = PublicBid & { type: 'bid' };
const suitBidsOf = (view: PlayerView, seat: Seat): SuitBid[] => view.bids.filter((b): b is SuitBid => b.seat === seat && b.type === 'bid');

/** What the taking pair's two hands are worth in the contract suit, each hand counted once. */
function pairEstimate(view: PlayerView, contract: BestBid, card: Conventions): number {
  const { hand, seat } = view;
  const mine = suitBidsOf(view, seat).filter((b) => b.suit === contract.suit);
  const partners = suitBidsOf(view, partnerOf(seat)).filter((b) => b.suit === contract.suit);
  const opener = [...mine, ...partners].sort((a, b) => view.bids.indexOf(a) - view.bids.indexOf(b))[0];
  // I named the suit first: my own evaluation, plus whatever my partner added on top of my bid
  if (opener.seat === seat) {
    const raise = partners.length ? partners[0].value - mine[0].value : 0;
    return evaluateSuit(hand, contract.suit).value + Math.max(0, raise);
  }
  // my partner did: what they announced first, plus my support
  return opener.value + evaluateSupport(hand, contract.suit, card).value;
}

/** The suit this hand would rather play in, with what it is worth there; null when it can open in none. */
function bestOwnSuit(hand: readonly Card[]): (Evaluation & { suit: Suit }) | null {
  let best: (Evaluation & { suit: Suit }) | null = null;
  for (const suit of SUITS) {
    const evaluation = evaluateSuit(hand, suit);
    if (evaluation.value > (best?.value ?? 0)) best = { ...evaluation, suit };
  }
  return best;
}

function bidValue(points: number): BidValue {
  if (!isBidValue(points)) throw new EngineError(`${points} cannot be bid`);
  return points;
}
/** the points a hand is worth, as a bid: a whole level, 160 at most */
const cappedBid = (points: number): number => Math.min(MAX_BID, roundDownToLevel(points));

/**
 * The second round, on a card that keeps the aces back: what the aces add now that the opponents have
 * bid over the pair, or null when there is no second round to bid.
 *
 * There is one only for a seat whose single bid was a raise of its partner's suit made while the pair
 * held the contract: that raise left the aces out. A seat that named the suit itself counted its aces
 * in its own hand's worth, and a raise made over the opponents kept nothing back: for those the aces
 * have been said, and are not said again. Having bid twice, a seat has had its second round.
 */
function acesKeptBack(view: PlayerView, card: Conventions): (Evaluation & { suit: Suit; from: number }) | null {
  const mine = suitBidsOf(view, view.seat);
  if (card.aces !== 'whenOverbid' || mine.length !== 1) return null;
  const [raise] = mine;
  const heardBefore = view.bids.slice(0, view.bids.indexOf(raise)).filter((b): b is SuitBid => b.type === 'bid');
  const raised = heardBefore[heardBefore.length - 1];
  if (!raised || raised.seat !== partnerOf(view.seat) || raised.suit !== raise.suit) return null;
  const aces = aceSupport(view.hand, raise.suit, card);
  const partners = suitBidsOf(view, partnerOf(view.seat)).filter((b) => b.suit === raise.suit);
  return aces.value > 0 ? { ...aces, suit: raise.suit, from: Math.max(raise.value, ...partners.map((b) => b.value)) } : null;
}

/**
 * `lang`: the language the reasons are written in. `card`: the conventions this seat and its partner
 * agreed on. It changes what it takes to open, what a raise is worth and when the aces are shown;
 * contres, surcontres' margin and a capot are no convention.
 */
export function aiBid(view: PlayerView, lang: Lang = 'fr', card: Conventions = CLASSIC): AiBid {
  const { hand, seat } = view;
  const phrases = PHRASES[lang];
  const current = view.best;
  const bid = (points: number, suit: Suit, reasons: readonly Reason[]): AiBid => ({
    bid: { type: 'bid', value: bidValue(points), suit },
    why: explain(reasons, lang).join(REASON_SEPARATOR),
  });
  const pass = (why: string): AiBid => ({ bid: { type: 'pass' }, why });

  if (!view.contreQueue && (!current || current.value < CAPOT)) {
    const suit = SUITS.find((candidate) => sureCapot(hand, candidate));
    if (suit) return bid(CAPOT, suit, [(p) => p.sureCapot]);
  }
  if (view.contreQueue && current) {
    // the opponents contred: surcontre only with a clear margin
    const worth = pairEstimate(view, current, card);
    return worth >= current.value + SURCONTRE_MARGIN ? { bid: { type: 'surcontre' }, why: phrases.pairWorth(worth) } : pass(phrases.noMargin);
  }

  const own = bestOwnSuit(hand);
  const alreadyBid = suitBidsOf(view, seat).length > 0;
  const support = (p: Phrases) => p.support;

  if (current && team(current.seat) === team(seat)) {
    // our side holds the contract: answer the partner once, with our own suit or with support
    if (current.seat === partnerOf(seat) && !alreadyBid) {
      // whether to name another suit is judged on the whole hand; what is said now may leave the aces for later
      const whole = evaluateSupport(hand, current.suit, card);
      const help = card.aces === 'atOnce' ? whole : trumpSupport(hand, current.suit, card);
      const ownSuitIsBetter = own && own.suit !== current.suit && own.value >= current.value + OWN_SUIT_MARGIN && whole.value < REAL_SUPPORT;
      if (ownSuitIsBetter) return bid(Math.min(MAX_BID, own.value), own.suit, own.reasons);
      const raised = cappedBid(current.value + help.value);
      if (raised > current.value) return bid(raised, current.suit, [support, ...help.reasons]);
    }
    return pass(phrases.ours);
  }
  if (current) {
    // the opponents hold the contract: contre it, support what the partner named, or name our own suit
    const contre = evaluateContre(hand, current);
    if (view.coinche === 0 && contre.worthIt) return { bid: { type: 'contre' }, why: explain(contre.reasons, lang).join(REASON_SEPARATOR) };
    const partnerBid = suitBidsOf(view, partnerOf(seat)).pop();
    if (partnerBid && !alreadyBid) {
      // they bid over my partner before I spoke: nothing is kept back
      const help = evaluateSupport(hand, partnerBid.suit, card);
      const raised = cappedBid(partnerBid.value + help.value);
      if (help.value >= REAL_SUPPORT && raised > current.value) return bid(raised, partnerBid.suit, [support, ...help.reasons]);
    }
    const kept = acesKeptBack(view, card);
    if (kept && cappedBid(kept.from + kept.value) > current.value) {
      return bid(cappedBid(kept.from + kept.value), kept.suit, [(p) => p.acesNow, ...kept.reasons]);
    }
    if (own && own.value > current.value && current.value < MAX_BID) return bid(Math.min(MAX_BID, own.value), own.suit, own.reasons);
    return pass(phrases.cannotRaise);
  }
  // nobody has bid: on the classic card a hand one level short still opens at 80. Measured in
  // duplicate, taking the contract on such hands wins about 7 points a deal and halves the deals
  // everybody passes.
  if (own && own.value >= card.open) return bid(Math.max(MIN_BID, Math.min(MAX_BID, own.value)), own.suit, own.reasons);
  return pass(own && own.value >= CLASSIC.open ? phrases.belowOpening(card.open) : phrases.noTrump);
}
