// The order of the deck: a hand is always handed over in it, so that the order of its cards says
// nothing of how it was dealt or of how it was played.
import type { Card } from '../../engine/src/index.ts';
import { RANKS, SUITS, rankOf, suitOf } from '../../engine/src/index.ts';

const deckOrder = (card: Card): number => SUITS.indexOf(suitOf(card)) * RANKS.length + RANKS.indexOf(rankOf(card));
export const sorted = (hand: readonly Card[]): Card[] => [...hand].sort((a, b) => deckOrder(a) - deckOrder(b));
