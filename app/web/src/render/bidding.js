// The auction: the bidding panel, the history of bids and the card values table.
import { ORDER_TRUMP, SUITS, cardPoints } from '../../../../engine/src/index.ts';
import { ME, nameOf, who } from '../config.js';
import { $ } from '../dom.js';
import { coachStep, myBidTurn, view } from '../game.js';
import { esc } from '../html.js';
import { t } from '../i18n/index.js';
import { SUIT_SYMBOL, bidHTML, bidValueLabel, isRed, rankName, suitName } from '../labels.js';
import { availableBids, bidValueToShow, canContre, lastContre } from '../selectors.js';
import { overlay, prefs, selection } from '../store.js';
import { coachButtons } from './table.js';
import { keepFocus } from './transient.js';

const bidLine = (bid) => t('bidLine', bid.type, nameOf(bid.seat), bid.seat === ME, bid.type === 'bid' ? bidHTML(bid.value, bid.suit) : '');
/**
 * The auction so far: the bids, and nothing of why a robot made one. A robot's reason names cards of
 * its hand, and at the table nobody says what they hold, to their partner least of all: what a pair
 * agreed on is said by the bid itself. The list scrolls once it is long, so the keyboard can reach it.
 */
export function historyHTML() {
  if (!view || !view.bids.length) return '';
  const lines = view.bids.map((bid) => `<li>${bidLine(bid)}</li>`).join('');
  return `<ol class="hist" tabindex="0" aria-label="${esc(t('histAria'))}">${lines}</ol>`;
}
/** shows the latest bids of a history that has grown longer than its box */
function scrollHistoryToEnd(el) {
  const history = el.querySelector('.hist');
  if (history) history.scrollTop = history.scrollHeight;
}

/** What each card is worth, as trump and in a plain suit, strongest trump first. */
export function valuesTable() {
  const rows = [...ORDER_TRUMP]
    .reverse()
    .map((rank) => `<tr><td>${rankName(rank)}</td><td>${cardPoints(`${rank}S`, 'S')}</td><td>${cardPoints(`${rank}S`, 'H')}</td></tr>`)
    .join('');
  return `<div class="vt"><table class="res"><thead><tr><th>${t('colCard')}</th><th>${t('colTrump')}</th><th>${t('colPlain')}</th></tr></thead><tbody>${rows}</tbody></table></div>`;
}

const button = (action, cls, label, attrs = '') => `<button class="btn ${cls}" data-action="${action}" ${attrs}>${label}</button>`;
const passButton = () => button('pass', 'pass', t('pass'));
const panelTop = (title) =>
  `<div class="bid-top"><div class="bid-h">${title}</div>${prefs.learn ? `<button class="btn-tip" data-action="tip">${t('tip')}</button>` : ''}</div>`;

/** the tutorial's bidding steps, written at the top of the panel */
function coachInPanel() {
  const step = coachStep();
  return step === 'bid' || step === 'contre' ? `<div class="coach-in"><p>${t('coach')[step]}</p>${coachButtons()}</div>` : '';
}

/** The opponents contred: this player's side may only pass or surcontre. */
function answerPanel() {
  return `${panelTop(t('contreYou', nameOf(lastContre(view).seat)))}
    <p class="bid-sub">${t('contreSub', bidHTML(view.best.value, view.best.suit))}</p>
    ${historyHTML()}
    <div class="acts">${passButton()}${button('surcontre', 'contre', t('surcontre'))}</div>`;
}

/** The value stepper and the four suits; nothing once the highest bid has been made. */
function bidChooser(value, available) {
  if (!available.length) return '';
  const index = available.indexOf(value);
  const step = (action, label, sign, disabled) =>
    `<button class="step" data-action="${action}" aria-label="${esc(label)}" ${disabled ? 'disabled' : ''}>${sign}</button>`;
  const suits = SUITS.map((suit) => {
    const classes = `suit${isRed(suit) ? ' red' : ''}${selection.suit === suit ? ' sel' : ''}`;
    return `<button class="${classes}" data-suit="${suit}" aria-pressed="${selection.suit === suit}" title="${esc(suitName(suit))}" aria-label="${esc(suitName(suit))}">${SUIT_SYMBOL[suit]}</button>`;
  }).join('');
  return `<div class="stepper">${step('minus', t('decrease'), '−', index <= 0)}<div class="goal" aria-live="polite">${bidValueLabel(value)}</div>${step('plus', t('increase'), '+', index >= available.length - 1)}</div>
    <div class="suits">${suits}</div>`;
}

function openPanel() {
  const available = availableBids(view);
  const value = bidValueToShow(view, selection.value);
  const coach = coachInPanel();
  const best = view.best ? t('bestBid', bidHTML(view.best.value, view.best.suit), who(view.best.seat)) : t('noBidYet');
  const announce = !available.length
    ? ''
    : selection.suit
      ? button('bid', 'take', t('announce', bidHTML(value, selection.suit)))
      : button('bid', 'take', t('chooseTrump'), 'disabled');
  return `${coach}${panelTop(t('yourBid'))}
    <p class="bid-sub">${best}</p>
    ${coach ? '' : historyHTML()}
    ${bidChooser(value, available)}
    <div class="acts">${passButton()}${canContre(view, ME) ? button('contre', 'contre', t('contre')) : ''}${announce}</div>`;
}

function drawPanel() {
  const el = $('#bid');
  const shown = myBidTurn() && !overlay.modal;
  el.hidden = !shown;
  if (!shown) return;
  const answering = !!view.contreQueue;
  el.classList.toggle('answer', answering);
  el.innerHTML = answering ? answerPanel() : openPanel();
  scrollHistoryToEnd(el);
}
export function renderBid() {
  keepFocus($('#bid'), drawPanel);
}
