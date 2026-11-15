// What the players say out loud: a bubble beside a seat, for a moment.
import { BUBBLE_MS } from './config.js';
import { renderSeats } from './render/table.js';
import { speech } from './store.js';

export function bubble(seat, html, kind = '', ms = BUBBLE_MS.announcement) {
  const id = ++speech.sequence;
  speech.bubbles[seat] = { html, kind, id };
  renderSeats();
  setTimeout(() => {
    // a newer bubble at the same seat stays
    if (speech.bubbles[seat]?.id !== id) return;
    delete speech.bubbles[seat];
    renderSeats();
  }, ms);
}

export function clearBubbles() {
  speech.bubbles = {};
}
