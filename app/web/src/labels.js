// Names and markup of cards, suits and bids.
import { CAPOT, SUITS, fullDeck, strength } from '../../../engine/src/index.ts';
import { esc } from './html.js';
import { lang, t } from './i18n/index.js';

export const SUIT_SYMBOL = { S: '♠', H: '♥', D: '♦', C: '♣' };
export const suitName = (suit) => t('suits')[suit];
export const rankName = (rank) => t('ranks')[rank];
export const isRed = (suit) => suit === 'H' || suit === 'D';
/** a bid's value as the table says it: a number, or the capot by its name */
export const bidValueLabel = (value) => (value === CAPOT ? t('capot') : String(value));
export const suitHTML = (suit) => (SUITS.includes(suit) ? `<span class="${isRed(suit) ? 'r' : ''}">${SUIT_SYMBOL[suit]}</span>` : '');
/** kept in one piece and in this order, whatever the direction of the text around it */
export const bidHTML = (value, suit) => `<bdi>${bidValueLabel(value)} ${suitHTML(suit)}</bdi>`;
export const cardName = (card) => t('cardName', card[0], card[1]);
const CARD_IMAGES = 'cards';
const CARD_BACK = `${CARD_IMAGES}/back.webp`;
/** the court cards carry the index letters of the language (V D R, or J Q K) */
export const cardSrc = (card) => `${CARD_IMAGES}/${lang() === 'en' && 'JQK'.includes(card[0]) ? 'en/' : ''}${card}.webp`;
export function cardHTML(card, attrs = '') {
  return `<div class="card" ${attrs}><img src="${cardSrc(card)}" alt="${esc(cardName(card))}" draggable="false"></div>`;
}
/** warms the image cache so cards never flash in empty */
export function preloadCards() {
  for (const src of [...fullDeck().map(cardSrc), CARD_BACK]) new Image().src = src;
}

/** suits left to right in the hand: trump first, then alternating colours */
function suitOrder(trump) {
  const base = ['S', 'H', 'C', 'D'];
  if (!SUITS.includes(trump)) return base;
  const rest = base.filter((suit) => suit !== trump);
  const ordered = [trump];
  while (rest.length) {
    const last = ordered[ordered.length - 1];
    const otherColour = rest.findIndex((suit) => isRed(suit) !== isRed(last));
    ordered.push(rest.splice(Math.max(0, otherColour), 1)[0]);
  }
  return ordered;
}
/** @param trump the trump suit, or null while bidding */
export function sortHand(hand, trump) {
  const order = suitOrder(trump);
  return hand.slice().sort((a, b) => order.indexOf(a[1]) - order.indexOf(b[1]) || strength(b, trump) - strength(a, trump));
}
