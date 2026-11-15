// The robots' card play as a set of rules of thumb: draw trumps, cash master cards that cannot be
// ruffed, give points to a partner who holds the trick, win cheaply, otherwise throw the smallest
// card. How well it plays depends on what the Situation knows of the other hands.

import type { Card, Seat, Suit } from '../cards.ts';
import { SEAT_COUNT, cardPoints, cardsOfSuit, isTrump, nextSeat, partnerOf, strength, suitOf, team } from '../cards.ts';
import { legalCards, trickPoints, trickWinner } from '../rules.ts';
import { masterIn } from './memory.ts';
import type { Situation } from './memory.ts';

/** How much a card is worth keeping: its points weigh most, then its rank, and a master card is kept longest. */
const POINTS_WEIGHT = 10;
const MASTER_WEIGHT = 60;
/** an ace: the most points a card of a plain suit carries */
const ACE_POINTS = 11;
/** a trick already holding this many points is worth winning even at the risk of being beaten */
const POINTS_WORTH_A_RISK = 10;

/** `cards` ordered by `key`, lowest first; cards with the same key keep their order */
const sortedBy = (cards: readonly Card[], key: (c: Card) => number): Card[] => cards.slice().sort((a, b) => key(a) - key(b));
const lowest = (cards: readonly Card[], key: (c: Card) => number): Card => sortedBy(cards, key)[0];
const highest = (cards: readonly Card[], key: (c: Card) => number): Card => sortedBy(cards, key)[cards.length - 1];

/** The questions the rules of thumb ask about the cards, answered for one situation. */
function reading(s: Situation) {
  const { seat, hand, trump } = s;
  const partner = partnerOf(seat);
  const opponents: Seat[] = [nextSeat(seat), nextSeat(partner)];
  const points = (c: Card) => cardPoints(c, trump);
  const rank = (c: Card) => strength(c, trump);
  const trumpCard = (c: Card) => isTrump(c, trump);
  const master = (c: Card) => masterIn(c, trump, hand, s.played);
  /** cards of a suit that are in another player's hand */
  const outstanding = (suit: Suit) => cardsOfSuit(suit).filter((c) => !s.played.has(c) && !hand.includes(c));
  const outstandingTrumps = outstanding(trump);
  const mayHoldTrump = (other: Seat) => outstandingTrumps.some((c) => s.mayHold(other, c));
  const mayRuff = (other: Seat, suit: Suit) => suit !== trump && mayHoldTrump(other) && !outstanding(suit).some((c) => s.mayHold(other, c));
  /** the card that costs least to part with */
  const cheapest = (cards: readonly Card[]) => lowest(cards, (c) => points(c) * POINTS_WEIGHT + rank(c) + (master(c) ? MASTER_WEIGHT : 0));
  return { partner, opponents, points, rank, trumpCard, master, outstanding, mayHoldTrump, mayRuff, cheapest };
}
type Reading = ReturnType<typeof reading>;

export function chooseCard(s: Situation): Card {
  const legal = legalCards(s.hand, s.plays, s.trump, s.seat);
  if (legal.length === 1) return legal[0];
  const r = reading(s);
  if (!s.plays.length) return leadCard(s, r, legal);

  const lastToPlay = s.plays.length === SEAT_COUNT - 1;
  const led = suitOf(s.plays[0].card);
  /** opponents who play after this player in the trick */
  const behind = r.opponents.filter((other) => !s.plays.some((p) => p.seat === other));
  /** whether an opponent still to play may take the trick from this card */
  const mayBeBeaten = (card: Card) =>
    behind.some((other) => {
      const ruffed = !r.trumpCard(card) && suitOf(card) === led && r.mayRuff(other, led);
      return ruffed || r.outstanding(suitOf(card)).some((higher) => r.rank(higher) > r.rank(card) && s.mayHold(other, higher));
    });

  const winning = trickWinner(s.plays, s.trump);
  if (winning.seat === r.partner) return supportPartner(r, legal, lastToPlay || !mayBeBeaten(winning.card), lastToPlay);
  return tryToWin(s, r, legal, lastToPlay, mayBeBeaten);
}

/** First to play: draw trumps as the taking side, else cash a master that cannot be ruffed, else lead low. */
function leadCard(s: Situation, r: Reading, legal: Card[]): Card {
  const trumps = legal.filter(r.trumpCard);
  const plain = legal.filter((c) => !r.trumpCard(c));
  const takingSide = team(s.seat) === team(s.taker);
  if (takingSide && trumps.length && r.opponents.some(r.mayHoldTrump)) {
    const masterTrumps = trumps.filter(r.master);
    if (masterTrumps.length) return highest(masterTrumps, r.rank);
    // the taker gives up a small trump to make the big ones fall
    if (s.seat === s.taker && trumps.length >= 2) return lowest(trumps, r.rank);
  }
  const safeMasters = plain.filter((c) => r.master(c) && !r.opponents.some((other) => r.mayRuff(other, suitOf(c))));
  if (safeMasters.length) return highest(safeMasters, r.points);
  return r.cheapest(plain.length ? plain : legal);
}

/** The partner holds the trick: load it with points when it is safe, keeping the aces that still make a trick of their own. */
function supportPartner(r: Reading, legal: Card[], trickIsSafe: boolean, lastToPlay: boolean): Card {
  if (!trickIsSafe) return r.cheapest(legal);
  const plain = legal.filter((c) => !r.trumpCard(c));
  const spare = plain.filter((c) => lastToPlay || !r.master(c) || r.points(c) < ACE_POINTS);
  const pool = spare.length ? spare : plain;
  return pool.length ? highest(pool, r.points) : r.cheapest(legal);
}

/** An opponent holds the trick: take it for sure if possible, risk it when it is worth it, else throw the cheapest card. */
function tryToWin(s: Situation, r: Reading, legal: Card[], lastToPlay: boolean, mayBeBeaten: (card: Card) => boolean): Card {
  const winners = legal.filter((card) => trickWinner([...s.plays, { seat: s.seat, card }], s.trump).seat === s.seat);
  if (winners.length) {
    const sure = lastToPlay ? winners : winners.filter((c) => !mayBeBeaten(c));
    if (sure.length) {
      const plain = sure.filter((c) => !r.trumpCard(c));
      return plain.length ? highest(plain, r.points) : lowest(sure, r.rank);
    }
    // third to play, only one opponent is left to beat the card
    const oneOpponentLeft = s.plays.length === SEAT_COUNT - 2;
    if (oneOpponentLeft || trickPoints(s.plays, s.trump) >= POINTS_WORTH_A_RISK) return lowest(winners, r.rank);
  }
  const plain = legal.filter((c) => !r.trumpCard(c));
  return r.cheapest(plain.length ? plain : legal);
}
