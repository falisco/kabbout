// Redraws the whole screen from the game view and the presentation state.
import { preloadCards } from '../labels.js';
import { renderBid } from './bidding.js';
import { renderModal } from './modals.js';
import {
  forgetShown,
  redrawCards,
  renderChat,
  renderCoach,
  renderHand,
  renderHint,
  renderKabbout,
  renderSeats,
  renderStar,
  renderTipBtn,
  renderTop,
  renderTrick,
  renderUndo,
} from './table.js';
import { hideToast } from './transient.js';

export function render() {
  renderTop();
  renderStar();
  renderSeats();
  renderHand();
  renderTrick();
  renderBid();
  renderHint();
  renderModal();
  renderCoach();
  renderUndo();
  renderTipBtn();
  renderChat();
  renderKabbout();
}

/** After a change of language: also what render() leaves alone as long as the game has not moved. */
export function renderAll() {
  preloadCards();
  forgetShown();
  hideToast();
  redrawCards();
  render();
}
