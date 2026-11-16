// The table as a player reads it at their turn: the cards that have fallen, those still out, who has
// shown what, the points made so far, and what each legal card is worth there. It is the reasoning a
// player holds in their head, written down; the coach explains a card with it. Built from the seat's
// view only, as the expert's choice is: two deals that look the same from the seat read the same.

import type { Card, Play, PlayerView, Seat, Suit } from '../../engine/src/index.ts';
import {
  SEATS,
  SUITS,
  cardPoints,
  cardsOfSuit,
  isTrump,
  legalCards,
  recall,
  strength,
  suitOf,
  team,
  trickPoints,
  trickWinner,
} from '../../engine/src/index.ts';

/** the card points of a deal's thirty-two cards; the ten of the last trick comes on top */
const DECK_POINTS = 152;

type BySuit = Record<Suit, Card[]>;

export interface LegalCard {
  card: Card;
  trump: boolean;
  /** no card of its suit beats it, neither one still out nor one already on the table */
  master: boolean;
  /** played now, it holds the trick over the cards already on the table */
  holds: boolean;
  points: number;
}

export interface Reading {
  trump: Suit;
  /** whether the player's team took the contract */
  taking: boolean;
  /** the cards played in the tricks before this one, by suit, the strongest first */
  fallen: BySuit;
  /** the cards the player has not seen: the three others hold them. By suit, the strongest first */
  out: BySuit;
  /** the trumps: fallen in earlier tricks, on the table, in the player's hand, and held by the others */
  trumps: { fallen: number; onTable: number; inHand: number; out: number };
  /** per suit, the strongest card that has not been played, and whether the player holds it */
  masters: { suit: Suit; card: Card; mine: boolean }[];
  /** a seat that has shown it holds no card of a suit that is still out */
  voids: { seat: Seat; suit: Suit }[];
  /** the seat holding the trick when the player plays; null when the player leads */
  holding: Seat | null;
  /** card points: won by each team in earlier tricks, on the table, and still in the four hands */
  points: { us: number; them: number; onTable: number; inHands: number };
  /** what each card the player may play is, there */
  legal: LegalCard[];
}

export function readingOf(view: PlayerView): Reading {
  if (!view.contract || !view.trick) throw new Error('a table is read while cards are played');
  const trump = view.contract.suit;
  const seat = view.seat;
  const plays: Play[] = view.trick.plays;
  const memory = recall(view);
  const strongestFirst = (cards: Card[]): Card[] => [...cards].sort((a, b) => strength(b, trump) - strength(a, trump));
  const bySuit = (cards: readonly Card[]): BySuit =>
    Object.fromEntries(SUITS.map((suit) => [suit, strongestFirst(cards.filter((c) => suitOf(c) === suit))])) as BySuit;

  const before = view.tricks.flatMap((t) => t.plays.map((p) => p.card));
  const onTable = plays.map((p) => p.card);
  const out = bySuit(memory.unseen);
  const trumpsIn = (cards: readonly Card[]): number => cards.filter((c) => isTrump(c, trump)).length;

  const masters = SUITS.flatMap((suit) => {
    const [card] = strongestFirst(cardsOfSuit(suit).filter((c) => !memory.played.has(c)));
    return card ? [{ suit, card, mine: view.hand.includes(card) }] : [];
  });
  const voids = SEATS.filter((s) => s !== seat).flatMap((s) =>
    SUITS.filter((suit) => out[suit].length > 0 && cardsOfSuit(suit).every((c) => memory.cannot[s].has(c))).map((suit) => ({ seat: s, suit })),
  );

  const won = [0, 0];
  for (const t of view.tricks) if (t.winner !== undefined) won[team(t.winner)] += trickPoints(t.plays, trump);
  const us = team(seat);
  const tablePoints = trickPoints(plays, trump);

  const legal = legalCards(view.hand, plays, trump, seat).map((card) => ({
    card,
    trump: isTrump(card, trump),
    master: ![...memory.unseen, ...onTable].some((o) => suitOf(o) === suitOf(card) && strength(o, trump) > strength(card, trump)),
    holds: trickWinner([...plays, { seat, card }], trump).seat === seat,
    points: cardPoints(card, trump),
  }));

  return {
    trump,
    taking: team(view.contract.seat) === us,
    fallen: bySuit(before),
    out,
    trumps: { fallen: trumpsIn(before), onTable: trumpsIn(onTable), inHand: trumpsIn(view.hand), out: out[trump].length },
    masters,
    voids,
    holding: plays.length > 0 ? trickWinner(plays, trump).seat : null,
    points: { us: won[us], them: won[1 - us], onTable: tablePoints, inHands: DECK_POINTS - won[0] - won[1] - tablePoints },
    legal,
  };
}
