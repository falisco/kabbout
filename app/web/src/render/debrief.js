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

/** the way in to the coach, when the server has one switched on */
export const debriefButton = () => (capabilities.features.coach === 'on' ? button('debrief', 'pass', t('debriefOpen')) : '');
/** one line when the server has a coach that waits for its key; nothing when there is no server */
export const debriefHint = () =>
  capabilities.features.coach === 'off' ? `<p class="sub" id="coachOff">${t('debriefOff', `<code>${esc(capabilities.variable)}</code>`)}</p>` : '';

/** what the dialog shows depends on it: the dialog is drawn again when it changes, and only then */
export const debriefState = () => `${debrief.status}:${debrief.asked}`;

function outcome() {
  if (debrief.status === 'asking') return `<p class="sub" id="debriefWait">${t('debriefAsking')}</p>`;
  if (debrief.status === 'failed') return `<p class="note" id="debriefError">${t('debriefErrors')[debrief.reason]}</p>`;
  if (debrief.status !== 'answered') return '';
  const { text, source, tricks } = debrief.answer;
  const links = tricks.map((number) => button('trick', 'pass', t('debriefSeeTrick', number), String(number))).join('');
  return `<p class="answer" id="debriefAnswer">${esc(text)}</p>
    ${source === 'summary' ? `<p class="sub" id="debriefPlain">${t('debriefPlain')}</p>` : ''}
    ${links ? `<div class="chips">${links}</div>` : ''}`;
}

/**
 * The coach as a conversation: its name and the way out at the top, the answer in the middle, the
 * question at the bottom. Only the middle scrolls, so on a phone the way out and the question never
 * leave the screen however long the answer is.
 */
export function debriefDialog() {
  const asking = debrief.status === 'asking';
  const label = esc(t('debriefLabel'));
  const question = `<div class="ask"><input id="debriefQ" type="text" maxlength="300" autocomplete="off" enterkeyhint="send" aria-label="${label}" value="${esc(debrief.question || t('debriefDefault'))}">
    <button class="btn take send" data-action="ask" aria-label="${esc(t('debriefAsk'))}" title="${esc(t('debriefAsk'))}"><span aria-hidden="true">↑</span></button></div>`;
  const close = `<button class="icon-x" data-action="close" aria-label="${esc(t('debriefBack'))}" title="${esc(t('debriefBack'))}"><span aria-hidden="true">×</span></button>`;
  return `<div class="dbf">
    <div class="dbf-head"><h2>${t('debriefTitle')}</h2>${close}</div>
    <div class="dbf-body">${debrief.status === 'idle' || debrief.status === 'failed' ? `<p class="sub">${t('debriefIntro')}</p>` : ''}${outcome()}</div>
    ${asking ? '' : question}
  </div>`;
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
