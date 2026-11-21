// The one game on this screen, and the questions the screen keeps asking about it.
import { ME, TARGET, THINK_MS, adviceRng, gameRng } from './config.js';
import { lang } from './i18n/index.js';
import { tutorialStep } from './selectors.js';
import { createLocalSession } from './session.js';
import { overlay, partnership, prefs, tableState, tutorial } from './store.js';

/**
 * The table as this screen's player may see it (a PlayerView), or null before a game starts.
 * Kept up to date by the session; modules read it, only the session changes the game.
 */
export let view = null;

export const session = createLocalSession({
  seat: ME,
  target: TARGET,
  rng: gameRng,
  adviceRng,
  level: () => prefs.level,
  lang,
  scoring: () => prefs.scoring,
  conventions: () => partnership.card,
  thinkMs: THINK_MS,
  onChange: (latest) => {
    view = latest;
  },
});

const myTurnIn = (phase) => !!view && view.phase === phase && view.turn === ME && !tableState.pause;
export const myBidTurn = () => myTurnIn('bidding');
export const myPlayTurn = () => myTurnIn('playing');
/** a complete trick is still shown on the table, before or while it is collected */
export const trickOnTable = () => tableState.pause === 'show' || tableState.pause === 'collect';

/** the only card the rules allow, when there is exactly one */
export function forcedCard() {
  if (!myPlayTurn()) return null;
  const legal = session.legalPlays();
  return legal.length === 1 ? legal[0] : null;
}

/** the tutorial bubble due now: 'bid' | 'contre' | 'play' | null */
export function coachStep() {
  if (!view || tutorial.off || overlay.modal) return null;
  return tutorialStep({ view, seat: ME, done: tutorial.done, myBidTurn: myBidTurn(), myPlayTurn: myPlayTurn() });
}
