// Trick rules: who wins a trick, which cards a player may play, and what a trick is worth.

import type { Card, Seat, Suit } from './cards.ts';
import { cardPoints, isTrump, partnerOf, strength, suitOf } from './cards.ts';
import type { Play } from './types.ts';

export function trickWinner(plays: readonly Play[], trump: Suit): Play {
  let best = plays[0];
  for (const p of plays.slice(1)) {
    if (isTrump(p.card, trump) && !isTrump(best.card, trump)) {
      best = p;
      continue;
    }
    if (suitOf(p.card) === suitOf(best.card) && strength(p.card, trump) > strength(best.card, trump)) best = p;
  }
  return best;
}

/** card points lying in a trick (the bonus of the last trick is counted with the score) */
export const trickPoints = (plays: readonly Play[], trump: Suit): number => plays.reduce((sum, p) => sum + cardPoints(p.card, trump), 0);

/** strength of the best trump among `plays`, or -1 when none was played */
export const highestTrump = (plays: readonly Play[], trump: Suit): number =>
  Math.max(-1, ...plays.filter((p) => isTrump(p.card, trump)).map((p) => strength(p.card, trump)));

/** the king and queen of trumps in one hand: the belote */
export const hasBelote = (hand: readonly Card[], trump: Suit): boolean => hand.includes(`K${trump}`) && hand.includes(`Q${trump}`);
/** one of the two cards of the belote */
export const isBeloteCard = (card: Card, trump: Suit): boolean => isTrump(card, trump) && (card[0] === 'K' || card[0] === 'Q');

/**
 * FFB contrée art. 5.2. Follow suit; when trump is led, go higher if possible (1). Void in the
 * led suit and partner not master: trump (2.1), over-trumping an opponent who already did; if
 * that is impossible any card may be played, there is no duty to under-trump (2.2). Partner
 * master: any card (2.3).
 */
export function legalCards(hand: readonly Card[], plays: readonly Play[], trump: Suit, seat: Seat): Card[] {
  if (!plays.length) return [...hand];
  const led = suitOf(plays[0].card);
  const follow = hand.filter((c) => suitOf(c) === led);
  const topTrump = highestTrump(plays, trump);
  if (led === trump) {
    if (!follow.length) return [...hand];
    const higher = follow.filter((c) => strength(c, trump) > topTrump);
    return higher.length ? higher : follow;
  }
  if (follow.length) return follow;
  const trumps = hand.filter((c) => isTrump(c, trump));
  if (!trumps.length) return [...hand];
  if (trickWinner(plays, trump).seat === partnerOf(seat)) return [...hand];
  const over = trumps.filter((c) => strength(c, trump) > topTrump);
  return over.length ? over : [...hand];
}
