// Cards, seats and card values for belote contrée (32-card deck, 4 suits; the optional Sans Atout /
// Tout Atout variant of FFB art. 11 is not played).

export type Suit = 'S' | 'H' | 'D' | 'C';
export type Rank = '7' | '8' | '9' | 'T' | 'J' | 'Q' | 'K' | 'A';
export type Card = `${Rank}${Suit}`;
export type Seat = 0 | 1 | 2 | 3;
export type Team = 0 | 1;

export const SUITS: readonly Suit[] = ['S', 'H', 'D', 'C'];
export const RANKS: readonly Rank[] = ['7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];
export const SEATS: readonly Seat[] = [0, 1, 2, 3];
export const SEAT_COUNT = SEATS.length;
/** each player holds eight cards, so a deal is eight tricks */
export const TRICKS_PER_DEAL = RANKS.length;

const POINTS_PLAIN: Record<Rank, number> = { A: 11, T: 10, K: 4, Q: 3, J: 2, 9: 0, 8: 0, 7: 0 };
const POINTS_TRUMP: Record<Rank, number> = { J: 20, 9: 14, A: 11, T: 10, K: 4, Q: 3, 8: 0, 7: 0 };
/** the ranks of a plain suit and of the trump suit, weakest first */
export const ORDER_PLAIN: readonly Rank[] = ['7', '8', '9', 'J', 'Q', 'K', 'T', 'A'];
export const ORDER_TRUMP: readonly Rank[] = ['7', '8', 'Q', 'K', 'T', 'A', '9', 'J'];

export const isSeat = (v: unknown): v is Seat => (SEATS as readonly unknown[]).includes(v);
export const rankOf = (c: Card): Rank => c[0] as Rank;
export const suitOf = (c: Card): Suit => c[1] as Suit;
export const team = (s: Seat): Team => (s % 2) as Team;
export const opponentOf = (t: Team): Team => (t === 0 ? 1 : 0);
export const partnerOf = (s: Seat): Seat => ((s + 2) % SEAT_COUNT) as Seat;
export const nextSeat = (s: Seat): Seat => ((s + 1) % SEAT_COUNT) as Seat;
export const isTrump = (c: Card, trump: Suit): boolean => suitOf(c) === trump;

export function cardPoints(c: Card, trump: Suit): number {
  return isTrump(c, trump) ? POINTS_TRUMP[rankOf(c)] : POINTS_PLAIN[rankOf(c)];
}

/** Rank within the card's own suit; only comparable between cards of the same suit. */
export function strength(c: Card, trump: Suit): number {
  return (isTrump(c, trump) ? ORDER_TRUMP : ORDER_PLAIN).indexOf(rankOf(c));
}

/** the eight cards of a suit, in the order of RANKS */
export const cardsOfSuit = (suit: Suit): Card[] => RANKS.map((rank): Card => `${rank}${suit}`);

export function fullDeck(): Card[] {
  return SUITS.flatMap(cardsOfSuit);
}

const DECK: ReadonlySet<unknown> = new Set(fullDeck());
export const isCard = (v: unknown): v is Card => DECK.has(v);
