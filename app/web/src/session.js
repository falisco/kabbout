// A game as one seat lives it. The screen only ever talks to a session: it reads the PlayerView,
// sends its own actions and receives public events. This one runs the engine and the three robots
// in the page; an online session would offer the same view / send / events from a server.
// No DOM here, so it is tested under Node (app/web/test/session.test.js).

import {
  EngineError,
  aiBid,
  aiPlay,
  apply,
  cardPoints,
  isMaster,
  legalCards,
  newGame,
  partnerOf,
  playedCards,
  publicEvents,
  suitOf,
  trickWinner,
  viewFor,
} from '../../../engine/src/index.ts';

/**
 * @param {object} o
 * @param {0|1|2|3} o.seat the human's seat
 * @param {number} o.target points to win
 * @param {() => number} o.rng deals and robots
 * @param {() => number} [o.adviceRng] advice only, so that asking never changes the game
 * @param {() => ('easy'|'normal'|'expert')} [o.level] how well the robots play, asked at each card so it can change mid-game
 * @param {() => ('ffb'|'tunisian')} [o.scoring] how the game is counted, asked when a game starts and kept until its end
 * @param {() => ('fr'|'en'|'ar')} [o.lang] the language the robots give their bidding reasons in, asked at each bid
 * @param {number} [o.thinkMs] longest a robot may think about a card (the expert then imagines fewer deals); no limit if absent
 * @param {(view: object|null) => void} [o.onChange] called with the new view whenever the game moves
 */
export function createLocalSession({
  seat,
  target,
  rng,
  adviceRng = Math.random,
  level = () => 'normal',
  lang = () => 'fr',
  scoring = () => 'ffb',
  thinkMs = 0,
  onChange = () => {},
}) {
  let state = null;
  let cached = null;

  const view = () => (state ? (cached ??= viewFor(state, seat)) : null);
  /** the human's view when it is their turn in `phase`, else null */
  const humanTurnIn = (phase) => {
    const v = view();
    return v && v.phase === phase && v.turn === seat ? v : null;
  };
  function set(next) {
    state = next;
    cached = null;
    onChange(view());
  }
  /** takes a step of the game in, and returns what the players may know of it */
  function commit(step) {
    set(step.state);
    return publicEvents(step.events);
  }
  const run = (who, action) => commit(apply(state, who, action, rng));
  const robotToAct = () => !!state && state.turn !== seat && (state.phase === 'bidding' || state.phase === 'playing');

  return {
    /** what the human may see, or null before a game starts */
    view,
    /** the cards the human may play now (none when it is not their turn) */
    legalPlays() {
      const v = humanTurnIn('playing');
      return v ? legalCards(v.hand, v.trick.plays, v.contract.suit, seat) : [];
    },
    start: () => commit(newGame(rng, { target, scoring: scoring() })),
    /** The human's own action. Returns its events, or null if the rules refuse it. */
    send(action) {
      try {
        return run(seat, action);
      } catch (e) {
        if (e instanceof EngineError) return null;
        throw e;
      }
    },
    robotToAct,
    /** The robot whose turn it is bids or plays. A robot can only fail on a bug: it is not caught. */
    playRobot() {
      if (!robotToAct()) throw new Error('no robot to act');
      const who = state.turn;
      const v = viewFor(state, who);
      const bidding = state.phase === 'bidding';
      return run(who, bidding ? { kind: 'bid', ...aiBid(v, lang()) } : { kind: 'play', card: aiPlay(v, rng, level(), deadlineAfter(thinkMs)) });
    },
    /** Solo only: the robots' reasons, one per bid of the auction (undefined for the human's). */
    reasons: () => (state ? state.bids.map((b) => (b.seat === seat ? undefined : b.why)) : []),
    ...advice({ humanTurnIn, lang, adviceRng, thinkMs }),
    /** the game as it stands, to come back to with restore (undo) */
    snapshot: () => structuredClone(state),
    restore: (snap) => set(structuredClone(snap)),
  };
}

/** What a robot would do in the human's place. Asking never changes the game: advice has its own random source. */
function advice({ humanTurnIn, lang, adviceRng, thinkMs }) {
  return {
    /** The bid a robot would make, with its reason; null when the human is not to bid. */
    adviseBid() {
      const v = humanTurnIn('bidding');
      return v ? aiBid(v, lang()) : null;
    },
    /** The card a robot would play, with a reason code; null when the human is not to play. */
    advisePlay() {
      const v = humanTurnIn('playing');
      if (!v) return null;
      // the best advice there is, whatever the level the robots play at
      const card = aiPlay(v, adviceRng, 'expert', deadlineAfter(thinkMs));
      return { card, reason: playReason(v, card) };
    },
  };
}

/** A deadline `ms` from now, as the question "is it past?"; none when `ms` is 0. */
function deadlineAfter(ms) {
  if (!ms) return undefined;
  const end = performance.now() + ms;
  return () => performance.now() > end;
}

/** Why `card` is a sensible play, as a code the screen puts into words. */
function playReason(v, card) {
  const trump = v.contract.suit;
  const plays = v.trick.plays;
  if (!plays.length) return isMaster(card, v, playedCards(v)) ? 'master' : suitOf(card) === trump ? 'drawTrumps' : 'lead';
  if (trickWinner(plays, trump).seat === partnerOf(v.seat)) return cardPoints(card, trump) >= 10 ? 'feedPartner' : 'keepStrong';
  if (trickWinner([...plays, { seat: v.seat, card }], trump).seat === v.seat)
    return suitOf(card) === trump && suitOf(plays[0].card) !== trump ? 'ruff' : 'win';
  const couldWin = legalCards(v.hand, plays, trump, v.seat).some((c) => trickWinner([...plays, { seat: v.seat, card: c }], trump).seat === v.seat);
  return couldWin ? 'duck' : 'discard';
}
