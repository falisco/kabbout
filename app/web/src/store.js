// Presentation state: what is on screen besides the game itself, and the player's settings.
// Only flow.js, input.js and speech.js change it; the render modules read it.
import { CLASSIC, isLang, isLevel, isScoring, readConventions } from '../../../engine/src/index.ts';
import { NO_SERVER } from './server.js';
import { KEYS, OLD_KEYS, isObj, loadJSON, loadObj, saveJSON } from './storage.js';

/** The player's settings, kept between visits. */
export const prefs = {
  sound: true,
  music: false,
  /** playable cards stand out, the others are dimmed */
  highlight: true,
  /** a card the rules leave no choice about is played without waiting for a tap */
  autoPlayForced: true,
  undo: true,
  /** the advice button is offered */
  learn: false,
  /** @type {'easy' | 'normal' | 'expert'} how well the robots play the cards */
  level: 'normal',
  /** @type {'ffb' | 'tunisian'} how the next game will be counted: with the contract added, or without */
  scoring: 'ffb',
  /** @type {'fr' | 'en' | 'ar' | null} the language the player chose; null follows the browser */
  lang: null,
};

/**
 * What the player and their partner agreed their bids mean. `card` is the classic one until they agree
 * on another; `agreed` says they did agree, on whichever card.
 * A game is played to its end by the card it started with, whatever is agreed meanwhile: the session
 * holds that one.
 */
export const partnership = { card: CLASSIC, agreed: false };
export function saveConventions() {
  saveJSON(KEYS.conventions, partnership.card);
}
/** A card that is not one (a setting the game no longer has, a value it does not allow) is no agreement. */
export function loadConventions() {
  const card = readConventions(loadJSON(KEYS.conventions));
  if (card) Object.assign(partnership, { card, agreed: true });
}

/**
 * The dialog where the two agree: the card being looked at, the sentence being typed, and what became of
 * the last sentence sent. `asked` counts the sentences, so that a late answer is told from the current one.
 */
export const agreement = {
  card: CLASSIC,
  sentence: '',
  /** @type {'idle' | 'asking' | 'understood' | 'failed'} */
  status: 'idle',
  /** why there is no card, when status is 'failed' */
  reason: '',
  /** the parts of the sentence the card has no place for */
  unplaced: [],
  asked: 0,
};

/** The tutorial: the steps already seen, and whether the player switched it off. */
export const tutorial = { done: {}, off: false };

/** What the player is composing or was advised. */
export const selection = {
  /** value and suit chosen in the bidding panel, before announcing */
  value: null,
  suit: null,
  /** the card the advice pointed at */
  hintCard: null,
};

/** The table between two moves of the game. */
export const tableState = {
  /** cards shown in the middle: the current trick, or the finished one until it is collected */
  cards: [],
  /** winner of the finished trick still on the table */
  trickWinner: null,
  /** a pause the table is in: 'deal' | 'redeal' | 'show' | 'collect' | 'kabbout' | null */
  pause: null,
  /** everybody passed: the deal about to be shown is a new one */
  redeal: false,
  /** the hand about to be drawn has just been dealt */
  dealAnimation: false,
  /** sound of the round result, played when its score sheet appears */
  roundSound: null,
  /** the turn the 'your turn' chime was last played for */
  turnCued: '',
};

/** What covers the table. */
export const overlay = {
  /** open dialog: 'menu' | 'last' | 'rules' | 'sheet' | 'settings' | 'values' | 'debrief' | 'trick' | null */
  modal: null,
  chatOpen: false,
};

/**
 * The coach's word on the deal just played: asked once the deal is over, forgotten when the table moves on.
 * (The `tutorial` above is the guide of the first deals; this is the AI coach of the server.)
 */
export const debrief = {
  /** 'idle' | 'asking' | 'answered' | 'failed' */
  status: 'idle',
  /** what the player is typing; empty at first for the question the screen proposes */
  question: '',
  /** the question the coach is looking at, while it does */
  pending: '',
  /** the conversation about this deal, a question and its answer at a time */
  turns: /** @type {{ question: string, answer: { text: string, source: string, tricks: number[] } }[]} */ ([]),
  /** questions still open on this deal, as the server counts them; null until it has said */
  left: /** @type {number | null} */ (null),
  /** why there is no answer: 'busy' | 'spent' | 'resting' | 'refused' | 'unavailable' */
  reason: '',
  /** the trick being looked at, from 1 */
  trick: 0,
  /** counts the questions and the deals: an answer that comes back to another count is thrown away */
  asked: 0,
};
export function resetDebrief() {
  Object.assign(debrief, { status: 'idle', question: '', pending: '', turns: [], left: null, reason: '', trick: 0, asked: debrief.asked + 1 });
}

/** What the server adds to the game, as server.js read it; nothing until a server answers. */
export const capabilities = /** @type {{ server: boolean, variable: string, features: Record<string, string> }} */ ({ ...NO_SERVER });

/** Who the visitor is for the server: the name it gave them; none ('') until a server answers, and with no server. */
export const player = { name: '' };

/** The last human action, while it can still be taken back. */
export const undoState = {
  /** what the action replaced: the game and the cards on the table */
  snapshot: null,
  /** when the action becomes final (ms since the epoch) */
  until: 0,
  timer: null,
};

/** What the players say: one bubble per seat, each numbered so that a newer one is never removed by an older timer. */
export const speech = { bubbles: {}, sequence: 0 };

/** the on/off settings, and the name each is stored under */
const STORED_SWITCHES = {
  music: 'music',
  sound: 'sound',
  highlight: 'hl',
  autoPlayForced: 'autoForced',
  undo: 'undo',
  learn: 'learn',
};
export const SWITCHES = Object.keys(STORED_SWITCHES);
/** settings that are on unless switched off */
const ON_BY_DEFAULT = ['sound', 'highlight', 'autoPlayForced', 'undo'];

export function savePrefs() {
  saveJSON(KEYS.prefs, {
    ...Object.fromEntries(SWITCHES.map((name) => [STORED_SWITCHES[name], prefs[name]])),
    level: prefs.level,
    scoring: prefs.scoring,
    ...(prefs.lang ? { lang: prefs.lang } : {}),
  });
}
export function loadPrefs() {
  const stored = loadObj(KEYS.prefs);
  if (stored) {
    for (const name of SWITCHES) {
      const value = stored[STORED_SWITCHES[name]];
      prefs[name] = ON_BY_DEFAULT.includes(name) ? value !== false : !!value;
    }
    if (isLevel(stored.level)) prefs.level = stored.level;
    if (isLang(stored.lang)) prefs.lang = stored.lang;
    if (isScoring(stored.scoring)) prefs.scoring = stored.scoring;
  } else if (loadJSON(OLD_KEYS.sound) === false) prefs.sound = false;
  const seen = loadObj(KEYS.tutorial);
  if (seen && isObj(seen.done)) {
    tutorial.done = Object.fromEntries(Object.keys(seen.done).map((step) => [step, !!seen.done[step]]));
    tutorial.off = !!seen.off;
  }
}

export function saveTutorial() {
  saveJSON(KEYS.tutorial, tutorial);
}
export function resetTutorial() {
  tutorial.done = {};
  tutorial.off = false;
  saveTutorial();
}
/** The player has seen a tutorial step. */
export function markTutorialStep(step) {
  if (tutorial.done[step]) return;
  tutorial.done[step] = true;
  saveTutorial();
}
/** the player answers a tutorial bubble: 'ok' marks that step as seen, 'skip' ends the tutorial */
export function coachAnswer(answer, step) {
  if (answer === 'ok' && step) tutorial.done[step] = true;
  if (answer === 'skip') tutorial.off = true;
  saveTutorial();
}
