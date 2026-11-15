// Shared by the tests: robots playing a move or a whole game, and a ready-made deal for the scoring tests.

import type { Action, Card, CompletedTrick, GameEvent, GameState, Level, Rng, Scoring, Seat } from '../src/index.ts';
import { aiBid, aiPlay, apply, newGame, seededRng, viewFor } from '../src/index.ts';

/** more moves than any game can last: reaching it means the game is stuck */
const MAX_MOVES = 20000;

/** What the robot whose turn it is does next. `cardRng` feeds the expert's imagined deals. */
export function robotAction(state: GameState, cardRng: Rng, level: Level = 'normal'): Action {
  const view = viewFor(state, state.turn);
  if (state.phase === 'bidding') return { kind: 'bid', ...aiBid(view) };
  if (state.phase === 'playing') return { kind: 'play', card: aiPlay(view, cardRng, level) };
  return { kind: 'next' };
}

export interface RobotGameOptions {
  scoring?: Scoring;
  level?: Level;
  /** sees the state before each move, with the move about to be made */
  beforeMove?: (state: GameState, action: Action) => void;
  /** sees every state the game goes through, with the events that led to it */
  afterMove?: (state: GameState, events: GameEvent[]) => void;
}

/** Four robots play a game from `seed` to its end. */
export function playRobotGame(seed: number, { scoring, level, beforeMove, afterMove }: RobotGameOptions = {}): GameState {
  const rng = seededRng(seed);
  let { state, events } = newGame(rng, scoring ? { scoring } : {});
  afterMove?.(state, events);
  for (let moves = 0; state.phase !== 'gameEnd'; moves++) {
    if (moves >= MAX_MOVES) throw new Error(`game ${seed} did not finish`);
    const action = robotAction(state, rng, level);
    beforeMove?.(state, action);
    ({ state, events } = apply(state, state.turn, action, rng));
    afterMove?.(state, events);
  }
  return state;
}

// A played deal for the scoring tests. Trump ♠, one trick per rank, each holding the four cards of that rank:
// J 26 · 9 14 · A 44 · 10 40 · K 16 · Q 12 · 8 0 · 7 0, plus the dix de der on the last = 162.
const RANKS_BY_TRICK = ['J', '9', 'A', 'T', 'K', 'Q', '8', '7'] as const;
/** the trick of the given index (0 to 7), won by `winner` */
export const trickOf = (index: number, winner: Seat): CompletedTrick => ({
  leader: 0,
  winner,
  plays: (['S', 'H', 'D', 'C'] as const).map((suit, seat) => ({ seat: seat as Seat, card: `${RANKS_BY_TRICK[index]}${suit}` as Card })),
});
export const tricks = (winners: Seat[]): CompletedTrick[] => winners.map((winner, index) => trickOf(index, winner));
/** the takers (seats 0 and 2) make J, 9, A, 10 = 124; the defence makes K, Q, 8, 7 + der = 38 */
export const SPLIT: Seat[] = [0, 2, 0, 2, 1, 3, 1, 3];
export const ALL_TAKERS: Seat[] = [0, 2, 0, 2, 0, 2, 0, 2];
export const ALL_DEFENCE: Seat[] = [1, 3, 1, 3, 1, 3, 1, 3];
/** who holds the king and queen of trumps, both already played */
export const BELOTE = { none: null, takers: { seat: 0 as Seat, played: 2 }, defence: { seat: 1 as Seat, played: 2 } };
