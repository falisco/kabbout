// What the player does: clicks, taps and keys, turned into game actions and screen changes.
import { SEATS, isLang, isLevel, isScoring } from '../../../engine/src/index.ts';
import { snd, unlockAudio, updateMusic } from './audio.js';
import { $ } from './dom.js';
import { BUBBLE_MS, ME, PARTNER, PARTNER_REPLY_SHARE, REPLY_DELAY_MS, chatRng, placeOf, sideOf } from './config.js';
import {
  askDebrief,
  closeModal,
  confirmAgreement,
  endKabbout,
  giveTip,
  interpretAgreement,
  makeBid,
  newGame,
  nextRound,
  openAgreement,
  openModal,
  pickReadyCard,
  playCard,
  showDebriefTrick,
  toggleChat,
  undoLast,
} from './flow.js';
import { coachStep, myBidTurn, view } from './game.js';
import { setLang, t } from './i18n/index.js';
import { renderBid } from './render/bidding.js';
import { markAgreeSendable } from './render/agree.js';
import { markSendable } from './render/debrief.js';
import { render, renderAll } from './render/index.js';
import { availableBids, bidValueToShow } from './selectors.js';
import { bubble, clearBubbles } from './speech.js';
import {
  SWITCHES,
  agreement,
  coachAnswer,
  debrief,
  markTutorialStep,
  overlay,
  prefs,
  resetTutorial,
  savePrefs,
  selection,
  tableState,
} from './store.js';

/** dialogs that close on Escape or a click outside */
const CLOSABLE = ['last', 'rules', 'sheet', 'settings', 'values', 'debrief', 'trick'];
const buttonOf = (event) => event.target.closest('button');

function playFromHand(target) {
  if (!$('#hand').classList.contains('myturn')) return false;
  const forbidden = target?.closest('.card.no');
  if (forbidden) {
    // a card the rules forbid: it shakes its head
    snd('refused');
    forbidden.classList.remove('nope');
    // reading the width makes the browser take the removal in, so that the animation starts again
    void forbidden.offsetWidth;
    forbidden.classList.add('nope');
    return false;
  }
  const playable = target?.closest('.card.ok');
  if (!playable) return false;
  playCard(playable.dataset.card);
  return true;
}

/** Moves the value in the bidding panel one step down (-1) or up (+1) among the bids still possible. */
function stepBidValue(step) {
  const available = availableBids(view);
  const next = available[available.indexOf(bidValueToShow(view, selection.value)) + step];
  if (next === undefined) return;
  selection.value = next;
  snd('tick');
  renderBid();
}

const BID_ACTIONS = {
  tip: giveTip,
  minus: () => stepBidValue(-1),
  plus: () => stepBidValue(+1),
  bid: () => selection.suit && makeBid({ type: 'bid', value: bidValueToShow(view, selection.value), suit: selection.suit }),
  pass: () => makeBid({ type: 'pass' }),
  contre: () => makeBid({ type: 'contre' }),
  surcontre: () => makeBid({ type: 'surcontre' }),
};

function onBidClick(event) {
  const button = buttonOf(event);
  if (!button || !myBidTurn()) return;
  const { answer, suit, action } = button.dataset;
  if (answer) {
    coachAnswer(answer, coachStep());
    return render();
  }
  if (suit) {
    selection.suit = suit;
    snd('tick');
    return renderBid();
  }
  BID_ACTIONS[action]?.();
}

/** What choosing a value of each setting does, beyond remembering it. */
const SETTINGS = {
  level: (value) => isLevel(value) && (prefs.level = value),
  scoring: (value) => isScoring(value) && (prefs.scoring = value),
  /** one of the ready cards, looked at in the dialog where the player and their partner agree */
  ready: (value) => pickReadyCard(value),
  lang(value) {
    if (!isLang(value)) return;
    prefs.lang = value;
    setLang(value);
    // what was said in the other language is not said again
    clearBubbles();
    renderAll();
  },
  music(value) {
    prefs.music = value === 'on';
    unlockAudio();
    updateMusic();
  },
  sound(value) {
    prefs.sound = value === 'on';
    if (!prefs.sound) return;
    unlockAudio();
    snd('tick');
  },
};
function changeSetting(name, value) {
  if (SETTINGS[name]) SETTINGS[name](value);
  else if (SWITCHES.includes(name)) prefs[name] = value === 'on';
  else return;
  savePrefs();
  render();
}

const SHEET_ACTIONS = {
  rules: () => openModal('rules'),
  values: () => openModal('values'),
  menu: () => openModal('menu'),
  close: () => closeModal(),
  debrief: () => openModal('debrief'),
  ask: () => askDebrief(),
  agreeOpen: () => openAgreement(),
  interpret: () => interpretAgreement(),
  agree: () => confirmAgreement(),
  trick: (number) => showDebriefTrick(Number(number)),
  tutorial() {
    resetTutorial();
    closeModal();
  },
  new() {
    unlockAudio();
    newGame();
  },
  next() {
    // the first score sheet explains itself once
    markTutorialStep('round');
    nextRound();
  },
};

function onSheetClick(event) {
  const button = buttonOf(event);
  if (!button) return;
  const { setting, value, action } = button.dataset;
  if (setting) changeSetting(setting, value);
  else SHEET_ACTIONS[action]?.(value);
}
/** the question to the coach is kept as it is typed, and Enter sends it */
function onSheetInput(event) {
  if (event.target.id === 'debriefQ') {
    debrief.question = event.target.value;
    markSendable(event.target);
  } else if (event.target.id === 'agreeQ') {
    agreement.sentence = event.target.value;
    markAgreeSendable(event.target);
  }
}
/** the fields of the dialogs, and what Enter does in each */
const SENT_BY_ENTER = { debriefQ: () => askDebrief(), agreeQ: () => interpretAgreement() };
function onSheetKeyDown(event) {
  if (event.key !== 'Enter' || !Object.hasOwn(SENT_BY_ENTER, event.target.id)) return;
  event.preventDefault();
  SENT_BY_ENTER[event.target.id]();
}

function onChatPick(event) {
  const phrase = buttonOf(event)?.dataset.phrase;
  if (!phrase) return;
  toggleChat(false);
  if (!t('phrases')[phrase]) return;
  bubble(ME, t('phrases')[phrase], '', BUBBLE_MS.phrase);
  // one of the three others answers in their own way, the partner more often
  const opponents = SEATS.filter((seat) => sideOf(seat) === 'them');
  const answerer = chatRng() < PARTNER_REPLY_SHARE ? PARTNER : opponents[chatRng() < 0.5 ? 0 : 1];
  setTimeout(() => bubble(answerer, t('replies')[phrase][placeOf(answerer)], '', BUBBLE_MS.reply), REPLY_DELAY_MS);
}

function onKeyDown(event) {
  const { key } = event;
  if (tableState.pause === 'kabbout' && !overlay.modal && (key === 'Escape' || key === 'Enter' || key === ' ')) {
    event.preventDefault();
    return endKabbout();
  }
  if (key !== 'Escape') return;
  // from a trick the coach showed, back to what the coach said
  if (overlay.modal === 'trick') openModal('debrief');
  else if (overlay.modal === 'agree') openModal('menu');
  else if (CLOSABLE.includes(overlay.modal)) closeModal();
  else if (overlay.chatOpen) {
    toggleChat(false);
    $('#chatBtn').focus();
  }
}

export function initInput() {
  $('#hand').addEventListener('click', (event) => playFromHand(event.target));
  $('#hand').addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && playFromHand(event.target)) event.preventDefault();
  });
  $('#undoBtn').addEventListener('click', undoLast);
  $('#kabbout').addEventListener('click', endKabbout);
  $('#tipBtn').addEventListener('click', giveTip);
  $('#bid').addEventListener('click', onBidClick);
  $('#sheet').addEventListener('click', onSheetClick);
  $('#sheet').addEventListener('input', onSheetInput);
  $('#sheet').addEventListener('keydown', onSheetKeyDown);

  const openers = { '#bLast': 'last', '#bRules': 'rules', '#scoreBox': 'sheet', '#bMenu': 'menu', '#bSettings': 'settings', '#valBtn': 'values' };
  for (const [selector, modal] of Object.entries(openers)) $(selector).addEventListener('click', () => openModal(modal));

  $('#coach').addEventListener('click', (event) => {
    const answer = buttonOf(event)?.dataset.answer;
    if (!answer) return;
    coachAnswer(answer, $('#coach').dataset.step);
    render();
  });
  $('#modal').addEventListener('click', (event) => {
    if (event.target.id === 'modal' && CLOSABLE.includes(overlay.modal)) closeModal();
  });
  document.addEventListener('keydown', onKeyDown);

  $('#chatBtn').addEventListener('click', () => {
    toggleChat();
    if (overlay.chatOpen) $('#chatMenu button')?.focus();
  });
  $('#chatMenu').addEventListener('click', onChatPick);
  // a click anywhere else puts the phrases away
  document.addEventListener('click', (event) => {
    const target = /** @type {Element} */ (event.target);
    if (overlay.chatOpen && !target.closest('#chatMenu, #chatBtn')) toggleChat(false);
  });
}
