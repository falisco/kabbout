// The table: score rail, the four seats, the hand, the trick and the small controls around them.
import { SEATS, TRICKS_PER_DEAL } from '../../../../engine/src/index.ts';
import { avatarSVG } from '../avatars.js';
import { ME, PARTNER, PHRASES, TARGET, THEM, US, myInitial, nameOf, placeOf, posOf, sideOf, who } from '../config.js';
import { $ } from '../dom.js';
import { coachStep, forcedCard, myPlayTurn, session, trickOnTable, view } from '../game.js';
import { esc } from '../html.js';
import { lang, t } from '../i18n/index.js';
import { bidHTML, cardHTML, cardName, sortHand } from '../labels.js';
import { isAwaitingMove, lastBidOf } from '../selectors.js';
import { starSVG } from '../star.js';
import { overlay, prefs, selection, speech, tableState, undoState } from '../store.js';
import { restartAnimation, say } from './transient.js';

/** how far each card of the fanned hand tilts from its neighbour (degrees), and how much the fan's ends drop (share of a card's width per step²) */
const FAN_TILT = 1.6;
const FAN_DROP = 0.012;

/** What was last drawn or announced, so that it is animated and read out once, not at every redraw. */
const shown = { bubbles: {}, hint: '' };
/** After a change of language everything is said again in the new one. */
export function forgetShown() {
  shown.hint = '';
}

/** the mark a contré or surcontré contract carries: ' ×2', ' ×4', or nothing */
export const multiplierMark = (mult) => (mult > 1 ? ` ×${mult}` : '');
/** the tutorial's two answers, under its text */
export const coachButtons = () =>
  `<div class="coach-acts"><button data-answer="skip">${t('skipTuto')}</button><button class="ok" data-answer="ok">${t('gotIt')}</button></div>`;

export function renderUndo() {
  const button = $('#undoBtn');
  const offered = !!undoState.snapshot && !overlay.modal;
  // the bar under the label empties as the time to take the action back runs out
  if (offered && button.hidden)
    button.innerHTML = `<span aria-hidden="true">↶</span> ${t('undo')}<i style="--undo:${Math.max(0, undoState.until - Date.now())}ms"></i>`;
  button.hidden = !offered;
}
export function renderTipBtn() {
  $('#tipBtn').hidden = !(prefs.learn && myPlayTurn() && !overlay.modal && !undoState.snapshot && !forcedCard());
}

function contractBadge() {
  if (!view) return '';
  const contre = (label) => `<span class="x">${label}</span>`;
  if (view.contract) {
    const { value, suit, seat, mult } = view.contract;
    const multiplied = mult > 1 ? contre(mult === 2 ? t('badgeContre') : t('badgeSurcontre')) : '';
    return `<span class="badge">${bidHTML(value, suit)} <small>${who(seat)}</small>${multiplied}</span>`;
  }
  if (view.phase !== 'bidding') return '';
  const best = view.best ? bidHTML(view.best.value, view.best.suit) : '—';
  return `<span class="badge"><small>${t('bidding')}</small> ${best}${view.coinche ? contre(t('badgeContre')) : ''}</span>`;
}
export function renderTop() {
  $('#sUs').textContent = view ? view.scores[US] : 0;
  $('#sThem').textContent = view ? view.scores[THEM] : 0;
  $('#sTgt').textContent = view ? view.target : TARGET;
  $('#contract').innerHTML = contractBadge();
  $('#table').classList.toggle('nohl', !prefs.highlight);
}

export function renderCoach() {
  const el = $('#coach');
  // the bidding steps are also written inside the bidding panel; the page's styles show one of the two
  const step = coachStep();
  el.hidden = !step;
  if (el.dataset.step === (step ?? '')) return;
  el.dataset.step = step ?? '';
  if (step) el.innerHTML = `<p>${t('coach')[step]}</p>${coachButtons()}`;
}

/** What a seat says: the bubble it was given, else its last bid while the auction lasts. `key` names it, to tell a new one from a redraw. */
function bubbleOf(seat) {
  const said = speech.bubbles[seat];
  if (said) return { html: said.html, kind: said.kind, key: `said${said.id}` };
  const bid = view?.phase === 'bidding' ? lastBidOf(view, seat) : null;
  if (!bid) return null;
  const key = `bid${view.bids.lastIndexOf(bid)}`;
  if (bid.type === 'pass') return { html: t('bubPass'), kind: 'pass', key };
  if (bid.type === 'contre') return { html: t('bubContre'), kind: 'contre', key };
  if (bid.type === 'surcontre') return { html: t('bubSurcontre'), kind: 'contre', key };
  return { html: bidHTML(bid.value, bid.suit), kind: '', key };
}
function bubbleHTML(seat) {
  const said = bubbleOf(seat);
  const isNew = !!said && shown.bubbles[seat] !== said.key;
  shown.bubbles[seat] = said ? said.key : null;
  return said ? `<div class="bubble ${said.kind}${isNew ? ' pop' : ''}">${said.html}</div>` : '';
}
function chipsHTML(seat) {
  let chips = '';
  if (view?.dealer === seat) chips += `<span class="chip dealer" title="${esc(t('dealer'))}">${t('dealerLetter')}</span>`;
  if (view?.contract?.seat === seat) {
    const { value, suit, mult } = view.contract;
    chips += `<span class="chip ctr">${bidHTML(value, suit)}${multiplierMark(mult)}</span>`;
  }
  return chips;
}
export function renderSeats() {
  for (const seat of SEATS) {
    const active = !!view && !tableState.pause && view.turn === seat && isAwaitingMove(view);
    const cardsHeld = view ? view.handSizes[seat] : TRICKS_PER_DEAL;
    const fan = seat === ME ? '' : `<div class="fan">${'<i class="mini"></i>'.repeat(cardsHeld)}</div>`;
    const partnerMark = seat === PARTNER ? ` <small>${t('partner')}</small>` : '';
    const el = $('#seat' + seat);
    el.className = `seat ${posOf(seat)} team${sideOf(seat) === 'us' ? 0 : 1}${active ? ' active' : ''}`;
    el.innerHTML = `<div class="avatar">${avatarSVG(placeOf(seat)) || myInitial()}${chipsHTML(seat)}</div><div class="name">${nameOf(seat)}${partnerMark}</div>${fan}${bubbleHTML(seat)}`;
  }
}

/** The hand, held as a fan: cards tilt outward and the middle of the hand rises. */
function fanHTML(hand) {
  const middle = (hand.length - 1) / 2;
  return hand
    .map((card, index) => {
      const offset = index - middle;
      const tilt = (offset * FAN_TILT).toFixed(1);
      const drop = ((offset * offset - middle * middle) * FAN_DROP).toFixed(3);
      return cardHTML(card, `style="--i:${index};--rot:${tilt}deg;--dy:calc(var(--hw) * ${drop})" data-card="${card}"`);
    })
    .join('');
}
export function renderHand() {
  const el = $('#hand');
  const trump = view?.contract ? view.contract.suit : null;
  const hand = view ? sortHand(view.hand, trump) : [];
  // the cards are only drawn again when they change: a redraw would restart their animations
  const signature = `${hand.join(',')}|${trump ?? ''}`;
  if (el.dataset.signature !== signature) {
    el.dataset.signature = signature;
    el.innerHTML = fanHTML(hand);
    el.classList.toggle('deal', tableState.dealAnimation);
  }
  const myTurn = myPlayTurn();
  const legal = myTurn ? session.legalPlays() : [];
  el.classList.toggle('myturn', myTurn);
  el.querySelectorAll('.card').forEach((cardEl) => {
    const { card } = cardEl.dataset;
    const playable = myTurn && legal.includes(card);
    const name = cardName(card);
    cardEl.classList.toggle('ok', playable);
    cardEl.classList.toggle('no', myTurn && !playable);
    cardEl.classList.toggle('tip', myTurn && selection.hintCard === card);
    cardEl.setAttribute('role', playable ? 'button' : 'img');
    cardEl.tabIndex = playable ? 0 : -1;
    cardEl.setAttribute('aria-label', myTurn && !playable ? t('notPlayable', name) : name);
  });
}

export function renderTrick() {
  const el = $('#trick');
  const cards = tableState.cards;
  const collector = tableState.pause === 'collect' ? tableState.trickWinner : null;
  const startsOver = cards.length < el.children.length || (el.dataset.collecting && collector === null);
  if (startsOver) {
    // emptied at once: the cards of the trick just collected must not slide back
    el.style.transition = 'none';
    el.className = '';
    el.innerHTML = '';
    restartAnimation(el);
    el.style.transition = '';
    delete el.dataset.collecting;
  }
  for (const { seat, card } of cards.slice(el.children.length)) {
    const slot = document.createElement('div');
    slot.className = 'slot ' + posOf(seat);
    slot.dataset.seat = seat;
    slot.innerHTML = cardHTML(card);
    el.appendChild(slot);
  }
  if (collector !== null && !el.dataset.collecting) {
    el.dataset.collecting = '1';
    [...el.children].forEach((slot) => slot.classList.toggle('win', +slot.dataset.seat === collector));
    el.classList.add('collect-' + posOf(collector));
  }
}

export function renderHint() {
  let hint = '';
  if (myPlayTurn() && !undoState.snapshot) hint = prefs.autoPlayForced && forcedCard() ? t('forced') : t('yourTurn');
  if (hint && hint !== shown.hint) say(hint);
  shown.hint = hint;
  $('#hint').textContent = hint;
}

/** The quick phrases the player can say: the menu, and whether it is open. */
export function renderChat() {
  const menu = $('#chatMenu');
  if (menu.dataset.lang !== lang()) {
    menu.dataset.lang = lang();
    menu.innerHTML = PHRASES.map((phrase) => `<button data-phrase="${phrase}">${t('phrases')[phrase]}</button>`).join('');
  }
  menu.hidden = !overlay.chatOpen;
  $('#chatBtn').setAttribute('aria-expanded', String(overlay.chatOpen));
}

/** Every card on screen drawn again: its picture and its name depend on the language. */
export function redrawCards() {
  $('#hand').dataset.signature = '';
  [...$('#trick').children].forEach((slot, index) => {
    if (tableState.cards[index]) slot.innerHTML = cardHTML(tableState.cards[index].card);
  });
  $('#coach').dataset.step = '';
}

/**
 * The star in the middle of the table: one point lights up for each trick, in the colour of the
 * side that took it, once the trick has been collected.
 */
export function renderStar() {
  const el = $('#tstar');
  const collected = view && view.phase !== 'bidding' ? view.tricks.slice(0, view.tricks.length - (trickOnTable() ? 1 : 0)) : [];
  const points = collected.map((trick) => sideOf(trick.winner));
  const signature = `${lang()}|${points.join()}`;
  if (el.dataset.signature === signature) return;
  el.dataset.signature = signature;
  const ours = points.filter((side) => side === 'us').length;
  el.innerHTML = starSVG({ points, label: points.length ? t('tricksStar', ours, points.length - ours) : '' });
}

/** All eight tricks to one side: the whole screen says so before the score sheet. */
export function renderKabbout() {
  const el = $('#kabbout');
  const up = tableState.pause === 'kabbout' && !overlay.modal && !!view?.result;
  const opening = up && el.hidden;
  if (opening) {
    const side = view.result.capotTeam === US ? 'us' : 'them';
    const title = t(side === 'us' ? 'kabboutUs' : 'kabboutThem');
    const subtitle = t(side === 'us' ? 'kabboutUsSub' : 'kabboutThemSub');
    el.className = side;
    el.innerHTML = `${starSVG({ points: Array(TRICKS_PER_DEAL).fill(side) })}<h2>${title}</h2><p>${subtitle}</p>`;
    el.setAttribute('aria-label', `${title} ${t('close')}`);
    say(`${title} ${subtitle}`);
  }
  el.hidden = !up;
  // nothing behind it can be reached while it is up; it takes the focus so that a key closes it
  if (up) $('#app').inert = true;
  if (opening) el.focus({ preventScroll: true });
}
