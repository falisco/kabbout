// The flow of a game on screen: the session decides, this module passes its events on,
// paces the robots and the table's pauses, and handles undo and advice.
import { snd } from './audio.js';
import { FAST, FAST_PACE, ME, PACE, TOAST_MS, UNDO_MS, nameOf, sideOf } from './config.js';
import { onGameEvent } from './feedback.js';
import { forcedCard, myBidTurn, myPlayTurn, session, view } from './game.js';
import { esc } from './html.js';
import { t } from './i18n/index.js';
import { bidHTML, suitHTML } from './labels.js';
import { renderBid } from './render/bidding.js';
import { render } from './render/index.js';
import { renderHand } from './render/table.js';
import { say, toast } from './render/transient.js';
import { turnKey } from './selectors.js';
import { clearBubbles } from './speech.js';
import { overlay, prefs, selection, tableState, undoState } from './store.js';

/** the one thing the table is waiting to do by itself */
let pendingStep = null;

export function newGame() {
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
export const nextRound = () => act({ kind: 'next' });

function clearUndo() {
  undoState.snapshot = null;
  clearTimeout(undoState.timer);
}
export function undoLast() {
  if (!undoState.snapshot) return;
  clearTimeout(pendingStep);
  pendingStep = null;
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
