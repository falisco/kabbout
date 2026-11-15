// The game as a pure state machine: apply(state, seat, action) → new state + events.
// Nothing here touches the DOM, timers or storage, so the same code runs in the app and on a server.

import type { Card, Seat, Suit } from './cards.ts';
import { SEATS, SEAT_COUNT, SUITS, TRICKS_PER_DEAL, fullDeck, isCard, isSeat, nextSeat, team } from './cards.ts';
import { EngineError } from './errors.ts';
import type { Rng } from './rng.ts';
import { shuffle } from './rng.ts';
import { hasBelote, isBeloteCard, legalCards, trickWinner } from './rules.ts';
import { scoreRound } from './scoring.ts';
import type {
  Action,
  BidAction,
  BidRecord,
  CompletedTrick,
  Contract,
  GameEvent,
  GameState,
  PlayerView,
  PublicBid,
  PublicEvent,
  Scoring,
} from './types.ts';
import { MAX_WHY_LENGTH, isBidValue, isScoring } from './types.ts';

export { EngineError };

/** cards given to each player in turn, three times round the table */
const DEAL_PATTERN = [3, 2, 3];
const DEFAULT_TARGET = 1000;
/** art. 4.3: the auction ends when three players pass after a bid; with no bid at all, four passes and the cards are dealt again */
const PASSES_TO_CLOSE = SEAT_COUNT - 1;
const PASSES_TO_REDEAL = SEAT_COUNT;
/** what a contre and a surcontre multiply the contract by, by the state's `coinche` */
const MULTIPLIER: Record<GameState['coinche'], Contract['mult']> = { 0: 1, 1: 2, 2: 4 };

export interface Step {
  state: GameState;
  events: GameEvent[];
}

export function newGame(rng: Rng, opts: { target?: number; dealer?: Seat; scoring?: Scoring } = {}): Step {
  if (opts.scoring !== undefined && !isScoring(opts.scoring)) throw new EngineError(`unknown scoring ${String(opts.scoring)}`);
  if (opts.target !== undefined && !(Number.isSafeInteger(opts.target) && opts.target > 0))
    throw new EngineError(`invalid target ${String(opts.target)}`);
  if (opts.dealer !== undefined && !isSeat(opts.dealer)) throw new EngineError(`unknown dealer ${String(opts.dealer)}`);
  const state: GameState = {
    target: opts.target ?? DEFAULT_TARGET,
    scoring: opts.scoring ?? 'ffb',
    scores: [0, 0],
    history: [],
    dealer: opts.dealer ?? (Math.floor(rng() * SEAT_COUNT) as Seat),
    round: 0,
    phase: 'bidding',
    turn: 0,
    hands: [[], [], [], []],
    bids: [],
    best: null,
    coinche: 0,
    contreQueue: null,
    passes: 0,
    contract: null,
    trick: null,
    tricks: [],
    belote: null,
    result: null,
    winner: null,
  };
  const events: GameEvent[] = [];
  deal(state, rng, events);
  return { state, events };
}

/** Shuffle and deal 3-2-3 starting left of the dealer; the next player bids first. */
function deal(s: GameState, rng: Rng, events: GameEvent[]): void {
  const deck = shuffle(fullDeck(), rng);
  const hands: GameState['hands'] = [[], [], [], []];
  for (const count of DEAL_PATTERN) {
    let seat = s.dealer;
    for (let given = 0; given < SEAT_COUNT; given++) {
      seat = nextSeat(seat);
      hands[seat].push(...deck.splice(0, count));
    }
  }
  s.round++;
  s.hands = hands;
  s.bids = [];
  s.best = null;
  s.coinche = 0;
  s.contreQueue = null;
  s.passes = 0;
  s.contract = null;
  s.trick = null;
  s.tricks = [];
  s.belote = null;
  s.result = null;
  s.phase = 'bidding';
  s.turn = nextSeat(s.dealer);
  events.push({ type: 'dealt', dealer: s.dealer });
}

type Unknown = Record<string, unknown>;
const isRecord = (v: unknown): v is Unknown => !!v && typeof v === 'object';
const isSuit = (v: unknown): v is Suit => (SUITS as readonly unknown[]).includes(v);

export function legalBid(s: GameState, seat: Seat, bid: BidAction): boolean {
  if (s.phase !== 'bidding' || s.turn !== seat) return false;
  // after a contre only the takers answer, and nobody may bid higher
  if (s.contreQueue) return bid.type === 'pass' || (bid.type === 'surcontre' && s.coinche === 1);
  switch (bid.type) {
    case 'pass':
      return true;
    case 'bid':
      return isBidValue(bid.value) && isSuit(bid.suit) && (!s.best || bid.value > s.best.value);
    case 'contre':
      return !!s.best && team(s.best.seat) !== team(seat) && s.coinche === 0;
    case 'surcontre':
      return false;
  }
}

export function legalPlays(s: GameState, seat: Seat): Card[] {
  if (s.phase !== 'playing' || s.turn !== seat || !s.trick || !s.contract) return [];
  return legalCards(s.hands[seat], s.trick.plays, s.contract.suit, seat);
}

/**
 * Applies one action. `seat` and `action` may come straight from a client: anything malformed,
 * out of turn or against the rules throws EngineError, and nothing but the known fields of an
 * action ever reaches the state. Never mutates `state`.
 */
export function apply(state: GameState, seat: Seat, action: Action, rng: Rng): Step {
  if (!isSeat(seat)) throw new EngineError(`unknown seat ${String(seat)}`);
  const a = readAction(action);
  const s = structuredClone(state);
  const events: GameEvent[] = [];
  if (a.kind === 'bid') bidStep(s, seat, a.bid, a.why, rng, events);
  else if (a.kind === 'play') playStep(s, seat, a.card, events);
  else nextStep(s, rng, events);
  return { state: s, events };
}

/** A clean copy of an action of unknown origin, or EngineError. */
function readAction(action: unknown): Action {
  if (!isRecord(action)) throw new EngineError('malformed action');
  if (action.kind === 'next') return { kind: 'next' };
  if (action.kind === 'play') {
    if (!isCard(action.card)) throw new EngineError('malformed card');
    return { kind: 'play', card: action.card };
  }
  if (action.kind === 'bid') {
    const bid = readBid(action.bid);
    const why = action.why === undefined ? '' : action.why;
    if (typeof why !== 'string' || why.length > MAX_WHY_LENGTH) throw new EngineError('malformed bid reason');
    return why ? { kind: 'bid', bid, why } : { kind: 'bid', bid };
  }
  throw new EngineError(`unknown action ${String(action.kind)}`);
}

function readBid(bid: unknown): BidAction {
  if (!isRecord(bid)) throw new EngineError('malformed bid');
  if (bid.type === 'pass' || bid.type === 'contre' || bid.type === 'surcontre') return { type: bid.type };
  if (bid.type === 'bid' && isBidValue(bid.value) && isSuit(bid.suit)) return { type: 'bid', value: bid.value, suit: bid.suit };
  throw new EngineError('malformed bid');
}

function bidStep(s: GameState, seat: Seat, bid: BidAction, why: string | undefined, rng: Rng, events: GameEvent[]): void {
  if (!legalBid(s, seat, bid)) throw new EngineError(`illegal bid ${JSON.stringify(bid)} by seat ${seat}`);
  const record: BidRecord = why ? { ...bid, seat, why } : { ...bid, seat };
  s.bids.push(record);
  events.push({ type: 'bid', record });

  if (s.contreQueue) return answerContre(s, s.contreQueue, bid, events);
  if (bid.type === 'pass') return passStep(s, seat, rng, events);
  if (bid.type === 'bid') {
    s.best = { seat, value: bid.value, suit: bid.suit };
    s.passes = 0;
    s.turn = nextSeat(seat);
  } else if (bid.type === 'contre' && s.best) {
    s.coinche = 1;
    // only the takers answer, in the order of play after the player who contred
    const takers = team(s.best.seat);
    s.contreQueue = [];
    for (let other = nextSeat(seat); other !== seat; other = nextSeat(other)) if (team(other) === takers) s.contreQueue.push(other);
    s.turn = s.contreQueue[0];
  }
}

/** After a contre: a surcontre closes the auction, and so does the last taker passing. */
function answerContre(s: GameState, queue: Seat[], bid: BidAction, events: GameEvent[]): void {
  if (bid.type === 'surcontre') {
    s.coinche = 2;
    return endAuction(s, events);
  }
  queue.shift();
  if (!queue.length) return endAuction(s, events);
  s.turn = queue[0];
}

function passStep(s: GameState, seat: Seat, rng: Rng, events: GameEvent[]): void {
  s.passes++;
  if (!s.best && s.passes >= PASSES_TO_REDEAL) {
    events.push({ type: 'redeal' });
    s.dealer = nextSeat(s.dealer);
    return deal(s, rng, events);
  }
  if (s.best && s.passes >= PASSES_TO_CLOSE) return endAuction(s, events);
  s.turn = nextSeat(seat);
}

function endAuction(s: GameState, events: GameEvent[]): void {
  if (!s.best) throw new EngineError('an auction cannot end without a bid');
  const trump = s.best.suit;
  s.contract = { ...s.best, mult: MULTIPLIER[s.coinche] };
  s.contreQueue = null;
  s.phase = 'playing';
  s.turn = nextSeat(s.dealer);
  s.trick = { leader: s.turn, plays: [] };
  const holder = SEATS.find((seat) => hasBelote(s.hands[seat], trump));
  s.belote = holder === undefined ? null : { seat: holder, played: 0 };
  events.push({ type: 'contract', contract: s.contract });
}

function playStep(s: GameState, seat: Seat, card: Card, events: GameEvent[]): void {
  if (!s.trick || !s.contract || !legalPlays(s, seat).includes(card)) throw new EngineError(`illegal card ${card} by seat ${seat}`);
  const trump = s.contract.suit;
  const plays = s.trick.plays;
  s.hands[seat] = s.hands[seat].filter((c) => c !== card);
  plays.push({ seat, card });
  events.push({ type: 'played', seat, card });

  if (s.belote && s.belote.seat === seat && isBeloteCard(card, trump)) {
    s.belote.played++;
    events.push({ type: 'belote', seat, rebelote: s.belote.played === 2 });
  }
  if (plays.length < SEAT_COUNT) {
    s.turn = nextSeat(seat);
    return;
  }
  const trick: CompletedTrick = { ...s.trick, winner: trickWinner(plays, trump).seat };
  s.tricks.push(trick);
  events.push({ type: 'trick', trick });
  if (s.tricks.length === TRICKS_PER_DEAL) return endRound(s, s.contract, events);
  s.trick = { leader: trick.winner, plays: [] };
  s.turn = trick.winner;
}

function endRound(s: GameState, contract: Contract, events: GameEvent[]): void {
  const r = scoreRound(contract, s.tricks, s.belote, s.scoring);
  s.result = r;
  s.trick = null;
  s.scores = [s.scores[0] + r.gained[0], s.scores[1] + r.gained[1]];
  s.history.push({ contract: r.contract, made: r.made, gained: r.gained, scores: [s.scores[0], s.scores[1]] });
  s.phase = 'roundEnd';
  const [a, b] = s.scores;
  // first team past the target wins; a tie past the target plays another round
  if ((a >= s.target || b >= s.target) && a !== b) s.winner = a > b ? 0 : 1;
  events.push({ type: 'roundEnd', result: r });
}

function nextStep(s: GameState, rng: Rng, events: GameEvent[]): void {
  if (s.phase !== 'roundEnd') throw new EngineError(`no next round from phase ${s.phase}`);
  if (s.winner !== null) {
    s.phase = 'gameEnd';
    events.push({ type: 'gameEnd', winner: s.winner });
    return;
  }
  s.dealer = nextSeat(s.dealer);
  deal(s, rng, events);
}

const publicBid = ({ why: _why, ...bid }: BidRecord): PublicBid => bid;

/**
 * The state as `seat` may see it: its own hand only, the belote holder once announced, and the
 * bids without the robots' reasoning (which names their cards).
 */
export function viewFor(state: GameState, seat: Seat): PlayerView {
  const { hands, belote, bids, ...pub } = structuredClone(state);
  return {
    ...pub,
    bids: bids.map(publicBid),
    seat,
    hand: hands[seat],
    handSizes: [hands[0].length, hands[1].length, hands[2].length, hands[3].length],
    belote: belote && (belote.played > 0 || belote.seat === seat) ? belote : null,
  };
}

/** The events of a step as they may be sent to the players. */
export function publicEvents(events: readonly GameEvent[]): PublicEvent[] {
  return events.map((e) => (e.type === 'bid' ? { type: 'bid', record: publicBid(e.record) } : structuredClone(e)));
}
