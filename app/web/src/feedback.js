// What the table shows and sounds like when the game moves: one reaction per kind of game event.
import { snd } from './audio.js';
import { BUBBLE_MS, ME, US, nameOf, sideOf } from './config.js';
import { t } from './i18n/index.js';
import { bidHTML, bidValueLabel } from './labels.js';
import { say, toast } from './render/transient.js';
import { bubble, clearBubbles } from './speech.js';
import { recordGame } from './storage.js';
import { selection, tableState } from './store.js';

const REACTIONS = {
  redeal() {
    tableState.redeal = true;
    toast(t('redeal'));
  },
  dealt() {
    tableState.dealAnimation = true;
    selection.value = null;
    selection.suit = null;
    clearBubbles();
    tableState.cards = [];
    tableState.trickWinner = null;
    tableState.pause = tableState.redeal ? 'redeal' : 'deal';
    tableState.redeal = false;
    snd('deal');
  },
  bid({ record }) {
    // the player's own bid needs no announcement; the others' are read out to screen readers
    if (record.seat !== ME) say(t('sayBid', nameOf(record.seat), record.type, record.type === 'bid' ? bidValueLabel(record.value) : '', record.suit));
    snd(record.type === 'contre' || record.type === 'surcontre' ? 'contre' : 'bid');
  },
  contract({ contract }) {
    tableState.cards = [];
    toast(t('contractToast', nameOf(contract.seat), contract.seat === ME, bidHTML(contract.value, contract.suit), contract.mult));
  },
  played({ seat, card }) {
    tableState.cards.push({ seat, card });
    if (seat !== ME) say(t('sayPlayed', nameOf(seat), card[0], card[1]));
    snd('card');
  },
  belote({ seat, rebelote }) {
    bubble(seat, rebelote ? t('rebelote') : t('belote'), 'belote', BUBBLE_MS.announcement);
    snd('belote');
  },
  trick({ trick }) {
    tableState.cards = trick.plays.slice();
    tableState.trickWinner = trick.winner;
    tableState.pause = 'show';
  },
  roundEnd({ result }) {
    // heard when the score sheet appears, once the last trick has been collected
    const takersAreUs = sideOf(result.contract.seat) === 'us';
    tableState.roundSound = result.made === takersAreUs ? 'roundWin' : 'roundLose';
  },
  gameEnd({ winner }) {
    recordGame(winner === US);
    snd(winner === US ? 'gameWin' : 'gameLose');
  },
};

/** @param {{ type: string }} event a public event of the game */
export function onGameEvent(event) {
  REACTIONS[event.type]?.(event);
}
