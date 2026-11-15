// What a player can work out from the cards played so far: which cards are gone, and which cards
// each other player has shown they cannot hold (a suit they did not follow, a trump they did not
// beat, the second card of a belote). Built from a PlayerView only.

import type { Card, Seat, Suit } from '../cards.ts';
import { SEATS, cardsOfSuit, fullDeck, isTrump, partnerOf, rankOf, strength, suitOf } from '../cards.ts';
import { EngineError } from '../errors.ts';
import { highestTrump, isBeloteCard, trickWinner } from '../rules.ts';
import type { Contract, Play, PlayerView, Trick } from '../types.ts';

export interface Memory {
  /** cards on the table or in a finished trick */
  played: Set<Card>;
  /** cards held by the three other players, whoever has them */
  unseen: Card[];
  /** per seat, cards that seat has shown it does not hold */
  cannot: Set<Card>[];
}

/** What a player needs to choose a card: its own hand, the trick, and what it knows of the others. */
export interface Situation {
  seat: Seat;
  hand: readonly Card[];
  plays: readonly Play[];
  trump: Suit;
  taker: Seat;
  played: ReadonlySet<Card>;
  /** whether another seat may still hold a card, as far as this player knows */
  mayHold(seat: Seat, card: Card): boolean;
}

/** The contract of a deal being played and the trick on the table; the robots only choose cards then. */
export function cardPlay(view: PlayerView): { contract: Contract; trick: Trick } {
  if (!view.contract || !view.trick) throw new EngineError(`no card to play in phase ${view.phase}`);
  return { contract: view.contract, trick: view.trick };
}

/** every trick of the deal so far, the one on the table last */
export const tricksSoFar = (view: PlayerView): Trick[] => (view.trick ? [...view.tricks, view.trick] : [...view.tricks]);

/** Cards already played this round (finished tricks + the current one). */
export function playedCards(view: PlayerView): Set<Card> {
  return new Set(tricksSoFar(view).flatMap((trick) => trick.plays.map((p) => p.card)));
}

/**
 * Everything the cards played so far tell this player. With `wholeRound` false the player only
 * looks at the trick on the table and has forgotten the earlier ones.
 */
export function recall(view: PlayerView, wholeRound = true): Memory {
  if (!view.contract) throw new EngineError(`nothing to recall in phase ${view.phase}`);
  const trump = view.contract.suit;
  const tricks = wholeRound ? tricksSoFar(view) : view.trick ? [view.trick] : [];
  const played = new Set<Card>();
  const cannot = SEATS.map(() => new Set<Card>());
  const exclude = (seat: Seat, cards: readonly Card[]) => cards.forEach((card) => cannot[seat].add(card));

  for (const trick of tricks) {
    trick.plays.forEach((play, index) => {
      played.add(play.card);
      if (isBeloteCard(play.card, trump)) excludeOtherBeloteCard(view, play, trump, exclude);
      if (index === 0) return;
      const before = trick.plays.slice(0, index);
      const led = suitOf(before[0].card);
      const followed = suitOf(play.card) === led;
      if (!followed) exclude(play.seat, cardsOfSuit(led));
      // FFB art. 5.2: on a trump lead, or void while an opponent holds the trick, a player must
      // beat the best trump if they can. Not doing so shows they have nothing above it.
      const top = highestTrump(before, trump);
      const own = isTrump(play.card, trump) ? strength(play.card, trump) : -1;
      const mustBeat = led === trump ? followed : !followed && trickWinner(before, trump).seat !== partnerOf(play.seat);
      if (mustBeat && own <= top) {
        exclude(
          play.seat,
          cardsOfSuit(trump).filter((card) => strength(card, trump) > top),
        );
      }
    });
  }
  const unseen = fullDeck().filter((c) => !played.has(c) && !view.hand.includes(c));
  return { played, unseen, cannot };
}

/**
 * A king or queen of trumps was played. The belote is announced with the first of the two: if this
 * player announced it, they hold the other card and nobody else does; if they did not, they do not
 * hold it.
 */
function excludeOtherBeloteCard(view: PlayerView, play: Play, trump: Suit, exclude: (seat: Seat, cards: readonly Card[]) => void): void {
  const other: Card = `${rankOf(play.card) === 'K' ? 'Q' : 'K'}${trump}`;
  const announced = view.belote?.seat === play.seat;
  for (const seat of SEATS) {
    const isPlayer = seat === play.seat;
    if (announced !== isPlayer) exclude(seat, [other]);
  }
}

export function situation(view: PlayerView, m: Memory): Situation {
  const { contract, trick } = cardPlay(view);
  return {
    seat: view.seat,
    hand: view.hand,
    plays: trick.plays,
    trump: contract.suit,
    taker: contract.seat,
    played: m.played,
    mayHold: (seat, card) => seat !== view.seat && !m.played.has(card) && !view.hand.includes(card) && !m.cannot[seat].has(card),
  };
}

/** Whether no card left outside this player's hand and the table beats this one in its suit. */
export function isMaster(c: Card, view: PlayerView, played: ReadonlySet<Card>): boolean {
  return !!view.contract && masterIn(c, view.contract.suit, view.hand, played);
}

export function masterIn(c: Card, trump: Suit, hand: readonly Card[], played: ReadonlySet<Card>): boolean {
  const mine = strength(c, trump);
  for (const o of cardsOfSuit(suitOf(c))) {
    if (o !== c && !played.has(o) && !hand.includes(o) && strength(o, trump) > mine) return false;
  }
  return true;
}
