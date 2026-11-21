// The flow of a game on screen: the session decides, this module passes its events on,
// paces the robots and the table's pauses, and handles undo and advice.
import { READY_CONVENTIONS, isReadyConventions } from '../../../engine/src/index.ts';
import { snd } from './audio.js';
import { FAST, FAST_PACE, ME, PACE, TOAST_MS, UNDO_MS, nameOf, sideOf } from './config.js';
import { onGameEvent } from './feedback.js';
import { forcedCard, myBidTurn, myPlayTurn, session, view } from './game.js';
import { esc } from './html.js';
import { lang, t } from './i18n/index.js';
import { bidHTML, suitHTML } from './labels.js';
import { renderBid } from './render/bidding.js';
import { showLastTurn } from './render/debrief.js';
import { render } from './render/index.js';
import { renderHand } from './render/table.js';
import { say, toast } from './render/transient.js';
import { turnKey } from './selectors.js';
import { askCoach, enter, interpretConventions, loadCapabilities } from './server.js';
import { clearBubbles } from './speech.js';
import {
  agreement,
  capabilities,
  debrief,
  overlay,
  partnership,
  player,
  prefs,
  resetDebrief,
  saveConventions,
  selection,
  tableState,
  undoState,
} from './store.js';

/** the one thing the table is waiting to do by itself */
let pendingStep = null;

/**
 * Asks the server, if there is one, what it adds and who the visitor is, coming in as a guest on a
 * first visit. Each answer shows as soon as it is in: the menu's line about the AI, the player's name.
 */
export async function askServer() {
  /** keeps an answer once it is in, and draws the screen again */
  const learn = async (asking, keep) => {
    keep(await asking);
    render();
  };
  await Promise.all([learn(loadCapabilities(), (answer) => Object.assign(capabilities, answer)), learn(enter(), (name) => (player.name = name))]);
}

/**
 * Asks the coach about the deal just played: the player's question, or the one the screen proposes.
 * The record comes from the session; the answer is the server's, and the screen shows it as plain text.
 */
export async function askDebrief() {
  const record = session.record();
  if (!record || debrief.status === 'asking' || debrief.left === 0) return;
  // the screen proposes the first question; after it, an empty field asks nothing
  const question = debrief.question.trim() || (debrief.turns.length ? '' : t('debriefDefault'));
  if (!question) return;
  // a question already answered on this deal is not paid for twice: its answer is on the screen
  if (debrief.turns.some((turn) => sameQuestion(turn.question, question))) {
    debrief.question = '';
    debrief.asked++;
    render();
    return;
  }
  const asked = debrief.asked + 1;
  Object.assign(debrief, { status: 'asking', pending: question, reason: '', asked });
  say(t('debriefAsking'));
  render();
  showLastTurn();
  const reply = await askCoach({ record, seat: ME, lang: lang(), question });
  // the table moved on while the coach was thinking: this answer is about another deal
  if (asked !== debrief.asked) return;
  debrief.pending = '';
  debrief.asked++;
  if (reply.answer) {
    const { left, ...answer } = reply.answer;
    debrief.turns.push({ question, answer });
    Object.assign(debrief, { status: 'answered', question: '', left: left ?? debrief.left });
  } else {
    // the question goes back to its field, to be sent again
    Object.assign(debrief, { status: 'failed', reason: reply.reason, question, left: reply.reason === 'spent' ? 0 : debrief.left });
  }
  say(reply.answer ? reply.answer.text : t('debriefErrors')[reply.reason]);
  render();
  showLastTurn();
}
const sameQuestion = (a, b) => a.trim().toLowerCase().replace(/\s+/g, ' ') === b.trim().toLowerCase().replace(/\s+/g, ' ');
/** Shows one trick of the deal the coach spoke of. */
export function showDebriefTrick(number) {
  debrief.trick = number;
  openModal('trick');
}

export function newGame() {
  resetDebrief();
  const events = session.start();
  overlay.modal = null;
  clearUndo();
  dispatch(events);
}
function dispatch(events) {
  events.forEach(onGameEvent);
  advance();
}
/** Sends an action to the game. Returns false if the rules refused it. */
function act(action) {
  const events = session.send(action);
  if (events) dispatch(events);
  return !!events;
}
/** A human action that can be taken back for UNDO_MS; the robots wait until then. */
function humanAct(action) {
  selection.hintCard = null;
  if (prefs.undo) {
    undoState.snapshot = { game: session.snapshot(), cards: tableState.cards.slice() };
    undoState.until = Date.now() + UNDO_MS;
    clearTimeout(undoState.timer);
    undoState.timer = setTimeout(() => {
      clearUndo();
      render();
    }, UNDO_MS);
  }
  act(action);
}
export const playCard = (card) => humanAct({ kind: 'play', card });
export const makeBid = (bid) => humanAct({ kind: 'bid', bid });
export function nextRound() {
  resetDebrief();
  return act({ kind: 'next' });
}

function clearUndo() {
  undoState.snapshot = null;
  clearTimeout(undoState.timer);
}
export function undoLast() {
  if (!undoState.snapshot) return;
  clearTimeout(pendingStep);
  pendingStep = null;
  // the deal is open again: what the coach said of it no longer holds
  resetDebrief();
  session.restore(undoState.snapshot.game);
  tableState.cards = undoState.snapshot.cards;
  tableState.pause = null;
  tableState.trickWinner = null;
  tableState.roundSound = null;
  tableState.dealAnimation = false;
  clearUndo();
  clearBubbles();
  snd('tick');
  advance();
}

export function giveTip() {
  if (myBidTurn()) adviseBid();
  else if (myPlayTurn()) advisePlay();
}
function adviseBid() {
  const { bid, why } = session.adviseBid();
  const reason = why ? ` <small>(${esc(why)})</small>` : '';
  if (bid.type !== 'bid') return toast(t('tipAct', bid.type) + reason, TOAST_MS.advice);
  selection.value = bid.value;
  selection.suit = bid.suit;
  renderBid();
  toast(t('tipBid', bidHTML(bid.value, bid.suit)) + reason, TOAST_MS.advice);
}
function advisePlay() {
  const { card, reason } = session.advisePlay();
  selection.hintCard = card;
  renderHand();
  toast(t('tipCard', card[0], suitHTML(card[1]), t('tipReasons')[reason]), TOAST_MS.advice);
}

/* ---------- agreeing with the partner ---------- */

/** Opens the dialog on the card the two bid by today. */
export function openAgreement() {
  Object.assign(agreement, { card: partnership.card, sentence: '', status: 'idle', reason: '', unplaced: [], asked: agreement.asked + 1 });
  openModal('agree');
}
/** Looks at one of the ready cards. Nothing is agreed until the player says so. */
export function pickReadyCard(name) {
  if (!isReadyConventions(name)) return;
  Object.assign(agreement, { card: READY_CONVENTIONS[name], status: 'idle', reason: '', unplaced: [], asked: agreement.asked + 1 });
}
/**
 * Sends the player's sentence to the server, which answers with a card or with none. The card is then
 * read back by the page, from the card: no sentence of the server's is shown but the pieces of the
 * player's own that it could not place.
 */
export async function interpretAgreement() {
  const sentence = agreement.sentence.trim();
  if (!sentence || agreement.status === 'asking') return;
  const asked = agreement.asked + 1;
  Object.assign(agreement, { status: 'asking', reason: '', unplaced: [], asked });
  say(t('agreeAsking'));
  render();
  const reply = await interpretConventions({ sentence, lang: lang() });
  // the player went on to something else while the server was reading: this answer is no longer asked for
  if (asked !== agreement.asked || overlay.modal !== 'agree') return;
  agreement.asked++;
  if (reply.card) Object.assign(agreement, { card: reply.card, unplaced: reply.unplaced, status: 'understood', sentence: '' });
  else Object.assign(agreement, { status: 'failed', reason: reply.reason });
  say(reply.card ? t('agreeUnderstood') : t('agreeErrors')[reply.reason]);
  render();
}
/** The card looked at becomes the pair's: kept for the next visits, played by from the next game. */
export function confirmAgreement() {
  Object.assign(partnership, { card: agreement.card, agreed: true });
  saveConventions();
  openModal('menu');
  toast(t('agreeDone'), TOAST_MS.advice);
}

export function openModal(name) {
  overlay.modal = name;
  overlay.chatOpen = false;
  advance();
}
export function closeModal() {
  overlay.modal = null;
  advance();
}
export function toggleChat(open = !overlay.chatOpen) {
  overlay.chatOpen = open;
  render();
}

/** closes the kabbout screen, at its end or on a tap */
export function endKabbout() {
  if (tableState.pause !== 'kabbout') return;
  tableState.pause = null;
  advance();
}

/** Runs `step` after `ms`, or once the last human action can no longer be taken back if that is later. */
function schedule(step, ms) {
  clearTimeout(pendingStep);
  const untilFinal = undoState.snapshot ? Math.max(0, undoState.until - Date.now()) : 0;
  pendingStep = setTimeout(
    () => {
      pendingStep = null;
      if (undoState.snapshot) clearUndo();
      step();
    },
    Math.max(FAST ? FAST_PACE : ms, untilFinal),
  );
}

/** A chime when it becomes the human's turn to bid or play; once per turn, whatever redraws follow. */
function cueTurn() {
  if (!myBidTurn() && !myPlayTurn()) return;
  const turn = turnKey(view);
  if (turn !== tableState.turnCued) snd('turn');
  tableState.turnCued = turn;
}

/** What ends each pause of the table. */
const AFTER_PAUSE = {
  deal: endDealPause,
  redeal: endDealPause,
  show: startCollecting,
  collect: finishTrick,
  kabbout: endKabbout,
};
function endDealPause() {
  tableState.pause = null;
  tableState.dealAnimation = false;
  advance();
}
function startCollecting() {
  tableState.pause = 'collect';
  snd(sideOf(tableState.trickWinner) === 'us' ? 'win' : 'lose');
  advance();
}
function finishTrick() {
  const winner = tableState.trickWinner;
  const roundOver = view.phase === 'roundEnd';
  tableState.pause = null;
  tableState.trickWinner = null;
  tableState.cards = [];
  say(t('sayTrick', nameOf(winner), winner === ME));
  if (tableState.roundSound && roundOver) snd(tableState.roundSound);
  tableState.roundSound = null;
  // all eight tricks to one side: the table stops to say it
  if (roundOver && view.result.capotTeam !== null) tableState.pause = 'kabbout';
  advance();
}

/** Redraws, then schedules whatever comes next without the human: a pause, a robot, a forced card. */
export function advance() {
  clearTimeout(pendingStep);
  pendingStep = null;
  render();
  if (!view || overlay.modal) return;
  if (tableState.pause) return schedule(AFTER_PAUSE[tableState.pause], PACE[tableState.pause]);
  if (session.robotToAct()) return schedule(() => dispatch(session.playRobot()), view.phase === 'bidding' ? PACE.bid : PACE.play);
  if (prefs.autoPlayForced && forcedCard()) return schedule(playForcedCard, PACE.forced);
  cueTurn();
}
function playForcedCard() {
  const card = forcedCard();
  if (card) act({ kind: 'play', card });
}
