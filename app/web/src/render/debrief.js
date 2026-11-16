// The coach of the server on the end-of-deal screens: the way in, the question and its answer, and
// the trick an answer points at. The answer is the server's text: it is shown escaped, never as markup,
// and the buttons to the tricks are built here from numbers.
import { nameOf } from '../config.js';
import { view } from '../game.js';
import { esc } from '../html.js';
import { t } from '../i18n/index.js';
import { cardHTML } from '../labels.js';
import { capabilities, debrief } from '../store.js';

const button = (action, cls, label, value = '') =>
  `<button class="btn ${cls}" data-action="${action}"${value === '' ? '' : ` data-value="${value}"`}>${label}</button>`;

/** the way in to the coach, when the server has one switched on; once it has answered, the way back to what it said */
export const debriefButton = () =>
  capabilities.features.coach === 'on' ? button('debrief', 'pass', t(debrief.turns.length ? 'debriefReopen' : 'debriefOpen')) : '';
/** one line when the server has a coach that waits for its key; nothing when there is no server */
export const debriefHint = () =>
  capabilities.features.coach === 'off' ? `<p class="sub" id="coachOff">${t('debriefOff', `<code>${esc(capabilities.variable)}</code>`)}</p>` : '';

/** what the dialog shows depends on it: the dialog is drawn again when it changes, and only then */
export const debriefState = () => `${debrief.status}:${debrief.asked}`;

const asked = (question) => `<p class="asked">${esc(question)}</p>`;

/** a question and its answer; the last answer carries the id the screen readers and the tests look for */
function turn({ question, answer }, last) {
  const links = answer.tricks.map((number) => button('trick', 'pass', t('debriefSeeTrick', number), String(number))).join('');
  return `${asked(question)}<p class="answer"${last ? ' id="debriefAnswer"' : ''}>${esc(answer.text)}</p>
    ${answer.source === 'summary' ? `<p class="sub"${last ? ' id="debriefPlain"' : ''}>${t('debriefPlain')}</p>` : ''}
    ${links ? `<div class="chips">${links}</div>` : ''}`;
}

/** the conversation so far, then what is happening now */
function conversation() {
  const turns = debrief.turns.map((one, i) => turn(one, i === debrief.turns.length - 1)).join('');
  if (debrief.status === 'asking') return `${turns}${asked(debrief.pending)}<p class="sub" id="debriefWait">${t('debriefAsking')}</p>`;
  if (debrief.status === 'failed') return `${turns}<p class="note" id="debriefError">${t('debriefErrors')[debrief.reason]}</p>`;
  return turns || `<p class="sub">${t('debriefIntro')}</p>`;
}

/** the question field, or the word that this deal has had its questions */
function composer() {
  if (debrief.status === 'asking') return '';
  if (debrief.left === 0) return `<p class="sub" id="debriefSpent">${t('debriefSpent')}</p>`;
  const first = debrief.turns.length === 0;
  const value = debrief.question || (first ? t('debriefDefault') : '');
  const label = esc(t('debriefLabel'));
  const left = debrief.left === null ? '' : `<p class="sub" id="debriefLeft">${t('debriefLeft', debrief.left)}</p>`;
  return `<div class="ask"><input id="debriefQ" type="text" maxlength="300" autocomplete="off" enterkeyhint="send" aria-label="${label}" placeholder="${esc(t('debriefMore'))}" value="${esc(value)}">
    <button class="btn take send" data-action="ask" aria-disabled="${value.trim() === ''}" aria-label="${esc(t('debriefAsk'))}" title="${esc(t('debriefAsk'))}"><span aria-hidden="true">↑</span></button></div>${left}`;
}

/**
 * The coach as a conversation: its name and the way out at the top, the questions and their answers in
 * the middle, the next question at the bottom. Only the middle scrolls, so on a phone the way out and
 * the question never leave the screen however long an answer is.
 */
export function debriefDialog() {
  const close = `<button class="icon-x" data-action="close" aria-label="${esc(t('debriefBack'))}" title="${esc(t('debriefBack'))}"><span aria-hidden="true">×</span></button>`;
  return `<div class="dbf">
    <div class="dbf-head"><h2>${t('debriefTitle')}</h2>${close}</div>
    <div class="dbf-body">${conversation()}</div>
    <div class="dbf-foot">${composer()}</div>
  </div>`;
}

/** Brings the last question to the top of the conversation: its answer is read from its first line. */
export function showLastTurn() {
  const questions = document.querySelectorAll('#sheet .dbf-body .asked');
  questions[questions.length - 1]?.scrollIntoView({ block: 'start' });
}
/** The send button says whether there is something to send. */
export function markSendable(input) {
  input
    .closest('.ask')
    ?.querySelector('.send')
    ?.setAttribute('aria-disabled', String(debrief.turns.length > 0 && input.value.trim() === ''));
}

/** One trick of the deal just played, as the answer named it. The deal is over: every trick may be looked at. */
export function debriefTrickDialog() {
  const trick = view?.tricks[debrief.trick - 1];
  const back = button('debrief', 'take', t('debriefBackCoach'));
  if (!trick) return `<h2>${t('debriefTitle')}</h2>${back}`;
  const cards = trick.plays
    .map(
      (p) =>
        `<figure class="${p.seat === trick.winner ? 'w' : ''}">${cardHTML(p.card, `data-card="${p.card}"`)}<figcaption>${nameOf(p.seat)}</figcaption></figure>`,
    )
    .join('');
  return `<h2>${t('debriefTrickTitle', debrief.trick)}</h2><div class="lt">${cards}</div><p class="sub">${t('wonBy', nameOf(trick.winner))}</p>${back}`;
}
