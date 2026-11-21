// The dialogs: menu, round result, end of game, last trick, score sheet, rules, settings, card values,
// and the coach's two (render/debrief.js).
import { LEVELS, SCORINGS, SEATS, TRICKS_PER_DEAL } from '../../../../engine/src/index.ts';
import { PARTNER, TARGET, THEM, US, nameOf, sideOf, who } from '../config.js';
import { $ } from '../dom.js';
import { trickOnTable, view } from '../game.js';
import { esc } from '../html.js';
import { LANGS, LANG_NAMES, lang, t } from '../i18n/index.js';
import { bidHTML, cardHTML } from '../labels.js';
import { consultableTrick } from '../selectors.js';
import { aiNotice } from '../server.js';
import { loadStats } from '../storage.js';
import { starSVG } from '../star.js';
import { capabilities, overlay, player, prefs, tableState, tutorial } from '../store.js';
import { agreeDialog, agreeEntry, agreeState } from './agree.js';
import { historyHTML, valuesTable } from './bidding.js';
import { debriefButton, debriefDialog, debriefHint, debriefState, debriefTrickDialog } from './debrief.js';
import { multiplierMark } from './table.js';
import { keepFocus } from './transient.js';

const OPPONENTS = SEATS.filter((seat) => sideOf(seat) === 'them');
/** dialogs whose content changes while they are open; the others are drawn once */
const REDRAWN_WHILE_OPEN = ['menu', 'round', 'settings'];
/** what had the focus before a dialog opened, to give it back when the dialog closes */
let focusBeforeDialog = null;

const actionButton = (action, cls, label) => `<button class="btn ${cls}" data-action="${action}">${label}</button>`;
const closeButton = () => actionButton('close', 'take', t('close'));
const allPoints = (cls) => Array(TRICKS_PER_DEAL).fill(cls);
const scoreColumns = (firstColumn = '') => `<thead><tr>${firstColumn}<th class="us">${t('us')}</th><th class="them">${t('them')}</th></tr></thead>`;

/**
 * One setting as a row of buttons, the current choice pressed.
 * @param {string} setting the name input.js knows the setting by
 * @param {{ value: string, label: string, lang?: string }[]} options `lang` when the label is written in another language than the screen's
 * @param {string} [cls] the width of the row, by its style class
 */
function segmented(setting, label, options, current, cls = '') {
  const buttons = options.map((option) => {
    const chosen = option.value === current;
    const langAttr = option.lang ? ` lang="${option.lang}"` : '';
    return `<button data-setting="${setting}" data-value="${option.value}"${langAttr} aria-pressed="${chosen}" class="${chosen ? 'on' : ''}">${option.label}</button>`;
  });
  return `<div class="setrow"><span>${label}</span><div class="seg ${cls}" role="group" aria-label="${esc(label)}">${buttons.join('')}</div></div>`;
}
const switchRow = (setting, label) =>
  segmented(
    setting,
    label,
    [
      { value: 'off', label: t('off') },
      { value: 'on', label: t('on') },
    ],
    prefs[setting] ? 'on' : 'off',
  );

/** the robots' level; it only changes how they play the cards, and applies from the next card */
const levelRow = () =>
  segmented(
    'level',
    t('robotLevel'),
    LEVELS.map((level) => ({ value: level, label: t('levels')[level] })),
    prefs.level,
    'lv',
  );
/** the language of the screen; each one is named in its own words */
const langRow = () =>
  segmented(
    'lang',
    t('language'),
    LANGS.map((code) => ({ value: code, label: LANG_NAMES[code], lang: code })),
    lang(),
    'lv',
  );
/**
 * How games are counted: with the value of the contract added, or without. It is fixed when a game
 * starts, so while one is under way the choice is for the next.
 */
function scoringRow() {
  const forNextGame = view && view.phase !== 'gameEnd' && view.scoring !== prefs.scoring;
  const row = segmented(
    'scoring',
    t('scoringLabel'),
    SCORINGS.map((scoring) => ({ value: scoring, label: t('scorings')[scoring] })),
    prefs.scoring,
    'sc',
  );
  return row + (forNextGame ? `<p class="sub">${t('scoringNext')}</p>` : '');
}
/** the way the game on the table is counted (the choice of the menu before any game) */
const scoringNow = () => (view ? view.scoring : prefs.scoring);

/** one line about the AI when the page comes from a server that has something to say about it */
function aiLine() {
  const notice = aiNotice(capabilities);
  const variable = `<code>${esc(capabilities.variable)}</code>`;
  return notice ? `<p class="sub" id="aiNotice">${t(notice === 'off' ? 'aiOff' : 'aiPlanned', variable)}</p>` : '';
}

/** one line saying under which name the visitor plays, when the page comes from a server that gave them one */
function playerLine() {
  return player.name ? `<p class="sub" id="playerName">${t('playingAs', `<b><bdi>${esc(player.name)}</bdi></b>`)}</p>` : '';
}

function menuDialog() {
  const stats = loadStats();
  const inGame = view && view.phase !== 'gameEnd';
  return `<h1 class="logo">${starSVG({ points: allPoints('on') })}<span class="ar" lang="ar" dir="rtl">كبّوط</span>Kabbout<small>${t('kicker')}</small></h1>
    <div class="frieze" aria-hidden="true">${starSVG({ spade: false, points: allPoints('on') }).repeat(5)}</div>
    <p class="tagline">${t('tagline')}</p>
    <p class="sub">${t('menuSub', nameOf(PARTNER), nameOf(OPPONENTS[0]), nameOf(OPPONENTS[1]), TARGET)}</p>
    <div class="menu-acts">
      ${inGame ? actionButton('close', 'take', t('resume')) : ''}
      ${actionButton('new', inGame ? 'pass' : 'take', t('newGame'))}
    </div>
    ${levelRow()}${scoringRow()}${langRow()}
    ${agreeEntry()}
    ${playerLine()}${aiLine()}
    ${stats.played ? `<p class="stats">${t('stats', stats.won, stats.played)}</p>` : ''}`;
}

function roundDialog() {
  const result = view.result;
  const { contract, capotTeam, tricksWon } = result;
  const takersAreUs = sideOf(contract.seat) === 'us';
  const status = t('roundTitle', result.made, takersAreUs);
  // all eight tricks to one side: the kabbout is the headline, the contract a detail
  const kabbout = capotTeam !== null;
  const title = !kabbout ? status : capotTeam === US ? t('kabboutUs') : t('kabboutThem');
  const goodForUs = kabbout ? capotTeam === US : result.made === takersAreUs;
  const cell = (points) => `<td class="${points ? '' : 'zero'}">${points ? '+' + points : '0'}</td>`;
  const row = (label, pair) => `<tr><td>${label}</td>${cell(pair[US])}${cell(pair[THEM])}</tr>`;
  // the Tunisian count never adds the contract: its row would always be empty
  const contractRow = view.scoring === 'tunisian' ? '' : row(t('rowContract', contract.mult), result.rows.contract);
  const star = starSVG({
    spade: false,
    points: view.tricks.map((trick) => sideOf(trick.winner)),
    label: t('tricksStar', tricksWon[US], tricksWon[THEM]),
  });
  const firstTimeNote = !tutorial.off && !tutorial.done.round ? `<p class="note">${t('roundNote')[view.scoring]}</p>` : '';
  return `<h2 class="${goodForUs ? '' : 'bad'}">${title}</h2>
    <div class="rstar">${star}</div>
    ${kabbout ? `<div class="tagrow"><span class="tag">${status}</span></div>` : ''}
    <p class="sub">${t('roundSub', bidHTML(contract.value, contract.suit), contract.mult, who(contract.seat), takersAreUs, result.takerPoints, result.defencePoints)}</p>
    <table class="res">${scoreColumns('<th></th>')}<tbody>
    ${row(t('rowTricks'), result.rows.tricks)}${row(t('rowBelote'), result.rows.belote)}${contractRow}
    <tr class="tot"><td>${t('roundTotal')}</td><td>${result.gained[US]}</td><td>${result.gained[THEM]}</td></tr>
    <tr class="cum"><td>${t('score')}</td><td style="color:var(--us)">${view.scores[US]}</td><td style="color:var(--them)">${view.scores[THEM]}</td></tr>
    </tbody></table>
    <p class="sub">${t('tricksWon', tricksWon[US], tricksWon[THEM], view.target)}</p>
    ${firstTimeNote}
    <div class="menu-acts">${actionButton('next', 'take', view.winner !== null ? t('seeResult') : t('nextDeal'))}${debriefButton()}</div>
    ${debriefHint()}`;
}

function endDialog() {
  const won = view.winner === US;
  return `<h1 class="logo" style="font-size:clamp(40px,11vw,56px)">${won ? t('win') : t('lose')}</h1>
    <p class="sub">${won ? t('winSub', nameOf(PARTNER)) : t('loseSub', nameOf(OPPONENTS[0]), nameOf(OPPONENTS[1]))}</p>
    <table class="res">${scoreColumns('<th></th>')}<tbody>
    <tr class="tot"><td>${t('finalScore')}</td><td>${view.scores[US]}</td><td>${view.scores[THEM]}</td></tr></tbody></table>
    <p class="sub">${t('dealsPlayed', view.history.length)}</p>
    <div class="menu-acts">${actionButton('new', 'take', t('replay'))}${debriefButton()}${actionButton('menu', 'pass', t('menu'))}</div>
    ${debriefHint()}`;
}

function lastTrickDialog() {
  const trick = view ? consultableTrick(view, trickOnTable()) : null;
  const cards = (plays) =>
    plays
      .map((p) => `<figure class="${p.seat === trick.winner ? 'w' : ''}">${cardHTML(p.card)}<figcaption>${nameOf(p.seat)}</figcaption></figure>`)
      .join('');
  const shown = trick
    ? `<div class="lt">${cards(trick.plays)}</div><p class="sub">${t('wonBy', nameOf(trick.winner))}</p>`
    : `<p class="sub">${t('lastTrickRule')}</p>`;
  const bids = view?.bids.length ? `<p class="sec">${t('dealBids')}</p>${historyHTML()}` : '';
  return `<h2>${t('lastTrick')}</h2>${shown}${bids}${closeButton()}`;
}

function scoreSheetDialog() {
  const deals = view ? view.history : [];
  const muted = (text) => `<small style="color:var(--muted)">${text}</small>`;
  const lines = deals.map(({ contract, made, scores, gained }, index) => {
    const taker = muted(`${who(contract.seat)}${made ? '' : ` · ${t('sheetDown')}`}`);
    return `<tr><td>${index + 1}</td><td>${bidHTML(contract.value, contract.suit)}${multiplierMark(contract.mult)} ${taker}</td><td>${scores[US]} ${muted('+' + gained[US])}</td><td>${scores[THEM]} ${muted('+' + gained[THEM])}</td></tr>`;
  });
  const table = `<div class="vt"><table class="res sheet-tbl">${scoreColumns(`<th>#</th><th>${t('colContract')}</th>`)}<tbody>${lines.join('')}</tbody></table></div>`;
  return `<h2>${t('scoreTitle')}</h2>${deals.length ? table : `<p class="sub">${t('noDeals')}</p>`}
    <p class="sub">${t('scoringLine', t('scorings')[scoringNow()])}</p>
    ${closeButton()}`;
}

const rulesDialog = () =>
  `<h2>${t('rulesTitle')}</h2><div class="rules">${t('rulesBody', TARGET, valuesTable(), scoringNow())}</div>${closeButton()}`;

function settingsDialog() {
  const switches = [
    ['music', 'setMusic'],
    ['sound', 'setSound'],
    ['highlight', 'setHl'],
    ['showReasons', 'setWhy'],
    ['autoPlayForced', 'setAutoForced'],
    ['undo', 'setUndo'],
    ['learn', 'setLearn'],
  ];
  return `<h2>${t('settings')}</h2>
    ${langRow()}${levelRow()}${switches.map(([setting, label]) => switchRow(setting, t(label))).join('')}
    <div class="links">${actionButton('rules', 'pass', t('rules'))}${actionButton('values', 'pass', t('cardValues'))}${actionButton('tutorial', 'pass', t('reviewTuto'))}${actionButton('menu', 'pass', t('leaveTable'))}</div>
    ${closeButton()}
    <p class="sub">${t('credits')}</p>`;
}

const valuesDialog = () => `<h2>${t('cardValues')}</h2>${valuesTable()}<p class="sub">${t('valuesSub')}</p>${closeButton()}`;

const DIALOGS = {
  menu: menuDialog,
  round: roundDialog,
  end: endDialog,
  last: lastTrickDialog,
  sheet: scoreSheetDialog,
  rules: rulesDialog,
  settings: settingsDialog,
  values: valuesDialog,
  debrief: debriefDialog,
  trick: debriefTrickDialog,
  agree: agreeDialog,
};
/** dialogs drawn again when what they show has changed, and left alone otherwise: what the player is typing stays */
const DIALOG_STATES = { debrief: debriefState, agree: agreeState };

/** The dialog to show: the one the player opened, else the result of the round or of the game once the table has settled. */
function dialogDue() {
  if (overlay.modal) return overlay.modal;
  if (!view) return null;
  if (view.phase === 'roundEnd' && !tableState.pause) return 'round';
  return view.phase === 'gameEnd' ? 'end' : null;
}

export function renderModal() {
  const name = dialogDue();
  const box = $('#modal');
  const sheet = $('#sheet');
  const opening = box.hidden && !!name;
  const closing = !box.hidden && !name;
  if (opening) focusBeforeDialog = document.activeElement;
  box.hidden = !name;
  $('#app').inert = !!name;
  if (!name) {
    sheet.dataset.modal = '';
    if (closing && focusBeforeDialog && document.contains(focusBeforeDialog)) focusBeforeDialog.focus();
    return;
  }
  const state = DIALOG_STATES[name]?.() ?? '';
  const changed = sheet.dataset.modal !== name || sheet.dataset.state !== state;
  if (!changed && !REDRAWN_WHILE_OPEN.includes(name)) return;
  keepFocus(sheet, () => {
    sheet.innerHTML = DIALOGS[name]();
  });
  sheet.dataset.modal = name;
  sheet.dataset.state = state;
  const title = sheet.querySelector('h1,h2');
  if (title) {
    title.id = 'dlgTitle';
    sheet.setAttribute('aria-labelledby', 'dlgTitle');
  }
  if (opening || changed) (sheet.querySelector('.btn.take') || sheet.querySelector('button'))?.focus({ preventScroll: true });
}
