// Agreeing with the partner on what the bids mean: the ready cards, the player's own sentence when the
// server can read one, and the card read back. The read-back is written here from the card, by the
// engine's own words: the server sends a card, never a sentence the player reads.
import { CLASSIC, READY_CONVENTIONS, describeConventions, sameConventions } from '../../../../engine/src/index.ts';
import { PARTNER, nameOf } from '../config.js';
import { view } from '../game.js';
import { esc } from '../html.js';
import { lang, t } from '../i18n/index.js';
import { agreement, capabilities, partnership } from '../store.js';

/** the ready card `card` is, by its name; '' when it is one of the player's own */
const readyName = (card) => Object.keys(READY_CONVENTIONS).find((name) => sameConventions(READY_CONVENTIONS[name], card)) ?? '';
/** what a card is called on the screen */
export const cardName = (card) => (readyName(card) ? t('agreeNames')[readyName(card)] : t('agreeOwn'));

/** the way in from the menu, with the card the two bid by today */
export const agreeEntry = () =>
  `<button class="btn pass agree-entry" data-action="agreeOpen">${t('agreeOpen', nameOf(PARTNER))}</button>
    <p class="sub" id="agreeNow">${t('agreeNow', `<b>${cardName(partnership.card)}</b>`)}</p>`;

/** what the dialog shows depends on it: the dialog is drawn again when it changes, and only then, so what is being typed stays */
export const agreeState = () => `${JSON.stringify(agreement.card)}:${agreement.status}:${agreement.asked}:${capabilities.features.conventions ?? ''}`;

function readyRow() {
  const current = readyName(agreement.card);
  const buttons = Object.keys(READY_CONVENTIONS).map((name) => {
    const chosen = name === current;
    return `<button data-action="ready" data-value="${name}" aria-pressed="${chosen}" class="${chosen ? 'on' : ''}">${t('agreeNames')[name]}</button>`;
  });
  return `<div class="seg" role="group" aria-label="${esc(t('agreeReady'))}">${buttons.join('')}</div>`;
}

/** the card as the two will play it: every line the engine has for it, or the one line of the classic card */
function readBack() {
  const lines = describeConventions(agreement.card, lang());
  const items = (lines.length ? lines : [t('agreeClassic', CLASSIC)]).map((line) => `<li>${esc(line)}</li>`).join('');
  return `<p class="sec">${t('agreeCard', esc(cardName(agreement.card)))}</p><ul class="agreed" id="agreeCard">${items}</ul>`;
}

/** what became of the last sentence: being read, not understood, or understood but for some of it */
function outcome() {
  if (agreement.status === 'asking') return `<p class="sub" id="agreeWait">${t('agreeAsking')}</p>`;
  if (agreement.status === 'failed') return `<p class="note" id="agreeError">${t('agreeErrors')[agreement.reason]}</p>`;
  if (agreement.status !== 'understood') return '';
  const pieces = agreement.unplaced.map((piece) => `« ${esc(piece)} »`).join(', ');
  return pieces
    ? `<p class="note" id="agreeUnplaced">${t('agreeUnplaced', pieces)}</p>`
    : `<p class="sub" id="agreeHeard">${t('agreeUnderstood')}</p>`;
}

/** the sentence field when the server reads sentences, one line when it waits for its key, nothing with no server */
function composer() {
  const state = capabilities.features.conventions;
  if (state === 'off') return `<p class="sub" id="agreeOff">${t('agreeOff', `<code>${esc(capabilities.variable)}</code>`)}</p>`;
  if (state !== 'on' || agreement.status === 'asking') return '';
  const label = esc(t('agreeLabel'));
  return `<div class="ask"><input id="agreeQ" type="text" maxlength="300" autocomplete="off" enterkeyhint="send" aria-label="${label}" placeholder="${esc(t('agreeExample'))}" value="${esc(agreement.sentence)}">
    <button class="btn pass send" data-action="interpret" aria-disabled="${agreement.sentence.trim() === ''}" aria-label="${esc(t('agreeSend'))}" title="${esc(t('agreeSend'))}"><span aria-hidden="true">↑</span></button></div>`;
}

export function agreeDialog() {
  const back = esc(t('agreeBack'));
  const close = `<button class="icon-x" data-action="menu" aria-label="${back}" title="${back}"><span aria-hidden="true">×</span></button>`;
  const inGame = view && view.phase !== 'gameEnd';
  return `<div class="dbf">
    <div class="dbf-head"><h2>${t('agreeTitle', nameOf(PARTNER))}</h2>${close}</div>
    <div class="dbf-body">
      <p class="sub">${t('agreeIntro', nameOf(PARTNER))}</p>
      ${readyRow()}
      ${readBack()}
      ${outcome()}
    </div>
    <div class="dbf-foot">${composer()}
      <button class="btn take" data-action="agree">${t('agreeConfirm')}</button>
      ${inGame ? `<p class="sub" id="agreeNext">${t('agreeNext')}</p>` : ''}
    </div>
  </div>`;
}

/** The send button says whether there is something to send. */
export function markAgreeSendable(input) {
  input
    .closest('.ask')
    ?.querySelector('.send')
    ?.setAttribute('aria-disabled', String(input.value.trim() === ''));
}
