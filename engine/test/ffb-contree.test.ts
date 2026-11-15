// Conformity with the Fédération Française de Belote "Règles officielles de belote contrée",
// version of 27.01.2016:
// https://www.ffbelote.org/wp-content/uploads/2016/01/regles-officielles-de-la-Belote-Contree-27-01-2016.pdf
//
// One block per article, named after it. These tests are the rulebook: if one fails, the engine no
// longer plays the federation's game. Do not adapt an expectation here to make a change pass unless
// the rule itself is being changed on purpose, and then quote the new rule next to it.
//
// Choices the rulebook leaves to the organiser:
//   - counting method (art. 10.2): "points faits + points demandés";
//   - contré / surcontré (art. 10.2): the table's 320 (640) + contract × 2 (× 4), not the note 1 variant;
//   - Sans Atout / Tout Atout (art. 11): not played.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Action, BidAction, BidValue, Card, Contract, GameState, Seat, Suit } from '../src/index.ts';
import {
  BID_VALUES,
  EngineError,
  SUITS,
  apply,
  cardPoints,
  fullDeck,
  legalBid,
  legalCards,
  legalPlays,
  newGame,
  scoreRound,
  seededRng,
  strength,
  trickWinner,
} from '../src/index.ts';
import { roundToTen } from '../src/scoring.ts';
import { ALL_DEFENCE, ALL_TAKERS, BELOTE, SPLIT, trickOf, tricks } from './helpers.ts';

/* ---------- helpers ---------- */

const rng = () => seededRng(1);
const pass: Action = { kind: 'bid', bid: { type: 'pass' } };
const contre: Action = { kind: 'bid', bid: { type: 'contre' } };
const surcontre: Action = { kind: 'bid', bid: { type: 'surcontre' } };
const bid = (value: number, suit: Suit = 'S'): Action => ({ kind: 'bid', bid: { type: 'bid', value: value as BidValue, suit } });
const p = (seat: Seat, card: Card) => ({ seat, card });

/** A fresh deal with seat 3 dealing, so that seat 0 speaks first. */
const deal = (seed = 11): GameState => newGame(seededRng(seed), { dealer: 3 }).state;
function run(state: GameState, steps: [Seat, Action][]): GameState {
  const r = rng();
  for (const [seat, a] of steps) state = apply(state, seat, a, r).state;
  return state;
}
const refused = (state: GameState, seat: Seat, a: Action) => assert.throws(() => apply(state, seat, a, rng()), EngineError);

const contract = (value: number, mult: 1 | 2 | 4 = 1): Contract => ({ seat: 0, value: value as BidValue, suit: 'S', mult });
type Holder = keyof typeof BELOTE;
const gained = (value: number, mult: 1 | 2 | 4, winners: Seat[], belote: Holder = 'none') =>
  scoreRound(contract(value, mult), tricks(winners), BELOTE[belote]).gained;

/**
 * A deal one card from its end: seven tricks won as given, and a last trick (the four 7s, ♠ trump)
 * that seat 0 wins as soon as seat 3 plays its last card.
 */
function beforeLastCard(scores: [number, number], target: number, value: number, winners: Seat[]): GameState {
  const last = trickOf(7, 0);
  return {
    ...deal(),
    target,
    scores,
    phase: 'playing',
    contract: contract(value),
    best: { seat: 0, value: value as BidValue, suit: 'S' },
    hands: [[], [], [], ['7C']],
    tricks: tricks(winners.slice(0, 7)),
    trick: { leader: 0, plays: last.plays.slice(0, 3) },
    turn: 3,
  };
}
const finish = (s: GameState) => apply(s, 3, { kind: 'play', card: '7C' }, rng()).state;

/* ---------- art. 2 and 3: the deck and the deal ---------- */

test('art. 2 — 32 cards: 7, 8, 9, 10, valet, dame, roi, as in four suits; two teams facing each other', () => {
  assert.equal(fullDeck().length, 32);
  assert.equal(new Set(fullDeck()).size, 32);
  const s = deal();
  assert.deepEqual([...s.hands.flat()].sort(), [...fullDeck()].sort());
});

test('art. 3.2 — the deck is shuffled before every deal', () => {
  const r = seededRng(5);
  const first = newGame(r, { dealer: 0 }).state;
  let s = first;
  for (const seat of [1, 2, 3, 0] as Seat[]) s = apply(s, seat, pass, r).state;
  assert.notDeepEqual(s.hands, first.hands);
});

test('art. 3.4 — 8 cards each, dealt 3-2-3 starting with the player on the dealer’s right', () => {
  const s = deal();
  assert.ok(s.hands.every((h) => h.length === 8));
  assert.equal(s.dealer, 3);
  // seat numbers grow counter-clockwise: the dealer's right-hand neighbour is the next seat
  assert.equal(s.turn, 0);
});

/* ---------- art. 4: the auction ---------- */

test('art. 4.2 — the player on the dealer’s right speaks first, then each in turn', () => {
  const s = deal();
  assert.equal(s.turn, 0);
  refused(s, 1, bid(80));
  assert.equal(run(s, [[0, pass]]).turn, 1);
});

test('art. 4.2.1 — a player who passed may bid later', () => {
  const s = run(deal(), [
    [0, pass],
    [1, bid(80)],
    [2, pass],
    [3, pass],
  ]);
  assert.ok(legalBid(s, 0, { type: 'bid', value: 90, suit: 'H' }));
});

test('art. 4.2.1 — four passes: no points, the player on the dealer’s right deals next', () => {
  const s = run(deal(), [
    [0, pass],
    [1, pass],
    [2, pass],
    [3, pass],
  ]);
  assert.deepEqual(s.scores, [0, 0]);
  assert.equal(s.history.length, 0);
  assert.equal(s.dealer, 0);
  assert.equal(s.phase, 'bidding');
  assert.equal(s.turn, 1);
});

test('art. 4.2.2 — a bid is a multiple of 10 from 80 to 160, in one of the four suits', () => {
  const s = deal();
  for (const v of [80, 90, 100, 110, 120, 130, 140, 150, 160]) assert.ok(legalBid(s, 0, { type: 'bid', value: v as BidValue, suit: 'H' }), `${v}`);
  for (const v of [0, 70, 85, 170, 200, 500]) assert.ok(!legalBid(s, 0, { type: 'bid', value: v as BidValue, suit: 'H' }), `${v}`);
  assert.ok(!legalBid(s, 0, { type: 'bid', value: 80, suit: 'X' as Suit }));
});

test('art. 4.2.2 — a bid must be higher than the previous one', () => {
  const s = run(deal(), [[0, bid(100)]]);
  refused(s, 1, bid(90));
  refused(s, 1, bid(100, 'H'));
  assert.ok(legalBid(s, 1, { type: 'bid', value: 110, suit: 'H' }));
});

test('art. 4.2.2 — a capot may be asked at any time, and no bid is higher than a capot', () => {
  assert.equal(BID_VALUES.at(-1), 250);
  assert.ok(legalBid(deal(), 0, { type: 'bid', value: 250, suit: 'S' }));
  const over160 = run(deal(), [[0, bid(160)]]);
  assert.ok(legalBid(over160, 1, { type: 'bid', value: 250, suit: 'H' }));
  // "dès lors l'équipe adverse ne peut plus que passer ou contrer"
  const capot = run(deal(), [[0, bid(250)]]);
  for (const v of BID_VALUES) for (const suit of SUITS) assert.ok(!legalBid(capot, 1, { type: 'bid', value: v, suit }));
  assert.ok(legalBid(capot, 1, { type: 'pass' }));
  assert.ok(legalBid(capot, 1, { type: 'contre' }));
});

test('art. 4.2.2 — the auction ends when it comes back to the last bidder, who cannot outbid himself', () => {
  const s = run(deal(), [
    [0, bid(80)],
    [1, pass],
    [2, bid(100, 'H')],
    [3, pass],
    [0, pass],
    [1, pass],
  ]);
  assert.equal(s.phase, 'playing');
  assert.deepEqual(s.contract, { seat: 2, value: 100, suit: 'H', mult: 1 });
});

test('art. 4.2.3 — only an opposing bid can be contré, and only at one’s turn', () => {
  const s = run(deal(), [[0, bid(80)]]);
  assert.ok(legalBid(s, 1, { type: 'contre' }));
  refused(s, 3, contre); // "on ne contre pas à la volée"
  refused(run(s, [[1, pass]]), 2, contre); // not one's partner
  refused(deal(), 0, contre); // nothing to contre
});

test('art. 4.2.3 — a contre freezes the contract: only the contred team speaks, to pass or surcontre', () => {
  const s = run(deal(), [
    [0, bid(80)],
    [1, contre],
  ]);
  // the contrer's partner (seat 3) is skipped
  assert.deepEqual(s.contreQueue, [2, 0]);
  assert.equal(s.turn, 2);
  refused(s, 3, pass);
  for (const b of [{ type: 'bid', value: 90, suit: 'H' }, { type: 'contre' }] as BidAction[]) assert.ok(!legalBid(s, 2, b));
  const afterPass = run(s, [[2, pass]]);
  assert.equal(afterPass.turn, 0);
  assert.ok(!legalBid(afterPass, 0, { type: 'bid', value: 90, suit: 'H' }));
  const played = run(afterPass, [[0, pass]]);
  assert.equal(played.phase, 'playing');
  assert.deepEqual(played.contract, { seat: 0, value: 80, suit: 'S', mult: 2 });
});

test('art. 4.2.4 — a surcontre answers a contre and ends the auction at once', () => {
  const contred = run(deal(), [
    [0, bid(80)],
    [1, contre],
  ]);
  const s = run(contred, [[2, surcontre]]);
  assert.equal(s.phase, 'playing');
  assert.deepEqual(s.contract, { seat: 0, value: 80, suit: 'S', mult: 4 });
  // never without a contre
  refused(run(deal(), [[0, bid(80)]]), 1, surcontre);
  refused(
    run(deal(), [
      [0, bid(80)],
      [1, pass],
    ]),
    2,
    surcontre,
  );
});

/* ---------- art. 5: the play ---------- */

test('art. 5.1 — the player on the dealer’s right leads; play goes counter-clockwise; the winner leads next', () => {
  let s = run(deal(), [
    [0, bid(80)],
    [1, pass],
    [2, pass],
    [3, pass],
  ]);
  assert.equal(s.turn, 0);
  const trump = s.contract!.suit;
  const order: Seat[] = [];
  const r = rng();
  for (let i = 0; i < 4; i++) {
    order.push(s.turn);
    s = apply(s, s.turn, { kind: 'play', card: legalPlays(s, s.turn)[0] }, r).state;
  }
  assert.deepEqual(order, [0, 1, 2, 3]);
  const done = s.tricks[0];
  assert.equal(done.winner, trickWinner(done.plays, trump).seat);
  assert.equal(s.turn, done.winner);
});

test('art. 5.1 — the trick goes to the highest trump, or failing that the highest card of the suit led', () => {
  assert.equal(trickWinner([p(0, 'KS'), p(1, 'AS'), p(2, 'TS'), p(3, '7S')], 'H').seat, 1);
  assert.equal(trickWinner([p(0, 'AS'), p(1, '7H'), p(2, 'TS'), p(3, 'AD')], 'H').seat, 1);
  assert.equal(trickWinner([p(0, 'AS'), p(1, '7H'), p(2, '8H'), p(3, 'AD')], 'H').seat, 2);
  // a discard never wins
  assert.equal(trickWinner([p(0, '7S'), p(1, 'AD'), p(2, 'AC'), p(3, '8S')], 'H').seat, 3);
});

test('art. 5.2 rule 1 — follow the suit led', () => {
  assert.deepEqual(legalCards(['AS', '7S', 'KH', '9D'], [p(1, 'TS')], 'H', 2).sort(), ['7S', 'AS']);
});

test('art. 5.2 rule 1 — trump led: go higher than the highest trump played when possible, even over one’s partner', () => {
  assert.deepEqual(legalCards(['AH', '7H', 'JH', 'AS'], [p(1, '9H')], 'H', 2), ['JH']);
  assert.deepEqual(legalCards(['AH', '7H', 'JH', 'AS'], [p(0, '9H'), p(1, '8H')], 'H', 2), ['JH']);
  // unable to go higher: any trump, but still a trump
  assert.deepEqual(legalCards(['AH', '7H', 'AS'], [p(1, '9H')], 'H', 2).sort(), ['7H', 'AH']);
  // no trump at all: anything
  assert.deepEqual(legalCards(['AS', 'KD'], [p(1, '9H')], 'H', 2).sort(), ['AS', 'KD']);
});

test('art. 5.2 rule 2.1 — void, partner not master or yet to play: must trump; without a trump, discard anything', () => {
  assert.deepEqual(legalCards(['7H', '8H', 'AD', 'KC'], [p(1, 'AS')], 'H', 2).sort(), ['7H', '8H']);
  // partner played but an opponent holds the trick
  assert.deepEqual(legalCards(['7H', 'AD', 'KC'], [p(0, 'KS'), p(1, 'AS')], 'H', 2), ['7H']);
  assert.deepEqual(legalCards(['AD', 'KC'], [p(1, 'AS')], 'H', 2).sort(), ['AD', 'KC']);
});

test('art. 5.2 rule 2.2 — an opponent has trumped and holds the trick: must over-trump', () => {
  assert.deepEqual(legalCards(['7H', 'JH', 'AD'], [p(0, 'AS'), p(1, '9H')], 'H', 2), ['JH']);
  // partner trumped first, the opponent went over: still over-trump if possible
  assert.deepEqual(legalCards(['7H', 'JH', 'AD'], [p(3, 'AS'), p(0, '8H'), p(1, '9H')], 'H', 2), ['JH']);
});

test('art. 5.2 rule 2.2 — unable to over-trump: any card, there is no duty to under-trump', () => {
  assert.deepEqual(legalCards(['7H', '8H', 'AD'], [p(0, 'AS'), p(1, 'JH')], 'H', 2).sort(), ['7H', '8H', 'AD']);
  assert.deepEqual(legalCards(['7H', 'AD', 'KC'], [p(3, 'AS'), p(0, '8H'), p(1, '9H')], 'H', 2).sort(), ['7H', 'AD', 'KC']);
});

test('art. 5.2 rule 2.3 — partner holds the trick: no duty to trump, any card, a lower trump included', () => {
  // partner master in the suit led
  assert.deepEqual(legalCards(['7H', 'AD', 'KC'], [p(0, 'AS'), p(1, '7S')], 'H', 2).sort(), ['7H', 'AD', 'KC']);
  // partner trumped and holds
  assert.deepEqual(legalCards(['7H', 'JH', 'AD'], [p(3, 'AS'), p(0, '9H'), p(1, '7S')], 'H', 2).sort(), ['7H', 'AD', 'JH']);
});

/* ---------- art. 6: order and value of the cards ---------- */

test('art. 6 — trump: valet 20, neuf 14, as 11, dix 10, roi 4, dame 3, huit 0, sept 0, in that order', () => {
  const order = ['JH', '9H', 'AH', 'TH', 'KH', 'QH', '8H', '7H'] as Card[];
  assert.deepEqual(
    order.map((c) => cardPoints(c, 'H')),
    [20, 14, 11, 10, 4, 3, 0, 0],
  );
  assert.deepEqual(
    [...order].sort((a, b) => strength(b, 'H') - strength(a, 'H')),
    order,
  );
});

test('art. 6 — plain suit: as 11, dix 10, roi 4, dame 3, valet 2, neuf 0, huit 0, sept 0, in that order', () => {
  const order = ['AS', 'TS', 'KS', 'QS', 'JS', '9S', '8S', '7S'] as Card[];
  assert.deepEqual(
    order.map((c) => cardPoints(c, 'H')),
    [11, 10, 4, 3, 2, 0, 0, 0],
  );
  assert.deepEqual(
    [...order].sort((a, b) => strength(b, 'H') - strength(a, 'H')),
    order,
  );
});

/* ---------- art. 7 and 8: belote, no other announcement ---------- */

test('art. 7 — king and queen of trump in one hand: belote on the first, rebelote on the second, 20 points', () => {
  const rest = fullDeck().filter((c) => c !== 'KS' && c !== 'QS');
  const hands = [['KS', 'QS', ...rest.slice(0, 6)], rest.slice(6, 14), rest.slice(14, 22), rest.slice(22, 30)] as GameState['hands'];
  const s = run({ ...deal(), hands }, [
    [0, bid(80)],
    [1, pass],
    [2, pass],
    [3, pass],
  ]);
  assert.deepEqual(s.belote, { seat: 0, played: 0 });
  const first = apply(s, 0, { kind: 'play', card: 'QS' }, rng());
  assert.deepEqual(
    first.events.filter((e) => e.type === 'belote'),
    [{ type: 'belote', seat: 0, rebelote: false }],
  );
  assert.deepEqual(scoreRound(contract(80), tricks(SPLIT), BELOTE.takers).rows.belote, [20, 0]);
  // split between two players, there is no belote
  const split = [['KS', ...rest.slice(0, 7)], ['QS', ...rest.slice(7, 14)], rest.slice(14, 22), rest.slice(22, 30)] as GameState['hands'];
  const none = run({ ...deal(), hands: split }, [
    [0, bid(80)],
    [1, pass],
    [2, pass],
    [3, pass],
  ]);
  assert.equal(none.belote, null);
});

test('art. 7 — when the contract fails, the takers lose their belote', () => {
  const r = scoreRound(contract(150), tricks(SPLIT), BELOTE.takers);
  assert.equal(r.made, false);
  assert.equal(r.gained[0], 0);
  assert.deepEqual(r.rows.belote, [0, 20]);
});

/* ---------- art. 9: dix de der and capot ---------- */

test('art. 9.1 — the last trick adds 10: a deal is worth 162 points whatever the trump', () => {
  for (const t of SUITS)
    assert.equal(
      fullDeck().reduce((s, c) => s + cardPoints(c, t), 0),
      152,
    );
  const r = scoreRound(contract(80), tricks(SPLIT), null);
  assert.deepEqual(r.cardPoints, [124, 38]);
});

test('art. 9.2 — eight tricks for one team: the last trick is worth 100, 252 in all', () => {
  assert.deepEqual(scoreRound(contract(80), tricks(ALL_TAKERS), null).cardPoints, [252, 0]);
  assert.deepEqual(scoreRound(contract(80), tricks(ALL_DEFENCE), null).cardPoints, [0, 252]);
});

/* ---------- art. 10.1: is the contract made? ---------- */

test('art. 10.1.1 — made when the takers reach or exceed their bid', () => {
  // A + 10 + K = 100 exactly
  const exactly100: Seat[] = [1, 3, 0, 2, 0, 3, 1, 3];
  assert.equal(scoreRound(contract(100), tricks(exactly100), null).takerPoints, 100);
  assert.equal(scoreRound(contract(100), tricks(exactly100), null).made, true);
  assert.equal(scoreRound(contract(110), tricks(exactly100), null).made, false);
});

test('art. 10.1 — the belote counts toward the bid', () => {
  // A + 10 = 84, 104 with the belote
  const w: Seat[] = [1, 3, 0, 2, 1, 3, 1, 3];
  assert.equal(scoreRound(contract(100), tricks(w), null).made, false);
  const r = scoreRound(contract(100), tricks(w), BELOTE.takers);
  assert.equal(r.takerPoints, 104);
  assert.equal(r.made, true);
});

test('art. 10.1.1 — made "même si les défenseurs ont réalisé plus de points que les preneurs"', () => {
  // takers 84; defence 78 + belote 20 = 98
  const r = scoreRound(contract(80), tricks([1, 3, 0, 2, 1, 3, 1, 3]), BELOTE.defence);
  assert.equal(r.takerPoints, 84);
  assert.equal(r.defencePoints, 98);
  assert.equal(r.made, true);
  assert.deepEqual(r.gained, [80 + 80, 80 + 20]);
});

test('art. 10.1 — a capot asked for is made with the eight tricks, and only then', () => {
  assert.equal(scoreRound(contract(250), tricks(ALL_TAKERS), null).made, true);
  // seven tricks out of eight: failed, however many points
  assert.equal(scoreRound(contract(250), tricks([0, 2, 0, 2, 0, 2, 0, 1]), null).made, false);
  // the partner's tricks count: the capot belongs to the team
  assert.equal(scoreRound(contract(250), tricks([2, 2, 2, 2, 2, 2, 2, 2]), null).made, true);
});

/* ---------- art. 10.2: the federation's score table, "points faits + demandés" ---------- */
// [takers, defence]. Takers make 124 and the defence 38 in SPLIT, rounded to 120 and 40.

test('art. 10.2 — contract made, standard: points + contract + own belote / points + own belote', () => {
  assert.deepEqual(gained(100, 1, SPLIT), [120 + 100, 40]);
  assert.deepEqual(gained(100, 1, SPLIT, 'takers'), [120 + 100 + 20, 40]);
  assert.deepEqual(gained(100, 1, SPLIT, 'defence'), [120 + 100, 40 + 20]);
});

test('art. 10.2 — contract made, contré (surcontré): 320 (640) + contract × 2 (× 4) + the belote of either side / 0', () => {
  assert.deepEqual(gained(100, 2, SPLIT), [320 + 200, 0]);
  assert.deepEqual(gained(100, 2, SPLIT, 'takers'), [320 + 200 + 20, 0]);
  assert.deepEqual(gained(100, 2, SPLIT, 'defence'), [320 + 200 + 20, 0]);
  assert.deepEqual(gained(100, 4, SPLIT), [640 + 400, 0]);
  assert.deepEqual(gained(100, 4, SPLIT, 'takers'), [640 + 400 + 20, 0]);
  assert.deepEqual(gained(100, 4, SPLIT, 'defence'), [640 + 400 + 20, 0]);
});

test('art. 10.2 — capot made, standard: 500 + own belote / 0 + own belote', () => {
  assert.deepEqual(gained(250, 1, ALL_TAKERS), [500, 0]);
  assert.deepEqual(gained(250, 1, ALL_TAKERS, 'takers'), [520, 0]);
  assert.deepEqual(gained(250, 1, ALL_TAKERS, 'defence'), [500, 20]);
});

test('art. 10.2 — capot made, contré (surcontré): 1000 (2000) + the belote of either side / 0', () => {
  assert.deepEqual(gained(250, 2, ALL_TAKERS), [1000, 0]);
  assert.deepEqual(gained(250, 2, ALL_TAKERS, 'takers'), [1020, 0]);
  assert.deepEqual(gained(250, 2, ALL_TAKERS, 'defence'), [1020, 0]);
  assert.deepEqual(gained(250, 4, ALL_TAKERS), [2000, 0]);
  assert.deepEqual(gained(250, 4, ALL_TAKERS, 'takers'), [2020, 0]);
  assert.deepEqual(gained(250, 4, ALL_TAKERS, 'defence'), [2020, 0]);
});

test('art. 10.2 — contract failed, standard: 0 / 160 + contract + the belote of either side', () => {
  assert.deepEqual(gained(150, 1, SPLIT), [0, 160 + 150]);
  assert.deepEqual(gained(150, 1, SPLIT, 'takers'), [0, 160 + 150 + 20]);
  assert.deepEqual(gained(150, 1, SPLIT, 'defence'), [0, 160 + 150 + 20]);
});

test('art. 10.2 — contract failed, contré (surcontré): 0 / 320 (640) + contract × 2 (× 4) + the belote of either side', () => {
  assert.deepEqual(gained(150, 2, SPLIT), [0, 320 + 300]);
  assert.deepEqual(gained(150, 2, SPLIT, 'takers'), [0, 320 + 300 + 20]);
  assert.deepEqual(gained(150, 2, SPLIT, 'defence'), [0, 320 + 300 + 20]);
  assert.deepEqual(gained(150, 4, SPLIT), [0, 640 + 600]);
  assert.deepEqual(gained(150, 4, SPLIT, 'takers'), [0, 640 + 600 + 20]);
  assert.deepEqual(gained(150, 4, SPLIT, 'defence'), [0, 640 + 600 + 20]);
});

test('art. 10.2 — capot failed, standard: 0 / 500 + the belote of either side', () => {
  assert.deepEqual(gained(250, 1, SPLIT), [0, 500]);
  assert.deepEqual(gained(250, 1, SPLIT, 'takers'), [0, 520]);
  assert.deepEqual(gained(250, 1, SPLIT, 'defence'), [0, 520]);
});

test('art. 10.2 — capot failed, contré (surcontré): 0 / 1000 (2000) + the belote of either side', () => {
  assert.deepEqual(gained(250, 2, SPLIT), [0, 1000]);
  assert.deepEqual(gained(250, 2, SPLIT, 'takers'), [0, 1020]);
  assert.deepEqual(gained(250, 2, SPLIT, 'defence'), [0, 1020]);
  assert.deepEqual(gained(250, 4, SPLIT), [0, 2000]);
  assert.deepEqual(gained(250, 4, SPLIT, 'takers'), [0, 2020]);
  assert.deepEqual(gained(250, 4, SPLIT, 'defence'), [0, 2020]);
});

test('art. 10.2 and 9.2 — eight tricks without asking for the capot: the 252 points made, plus the contract', () => {
  assert.deepEqual(gained(100, 1, ALL_TAKERS), [250 + 100, 0]);
  // contré: the table gives no bonus for the tricks
  assert.deepEqual(gained(100, 2, ALL_TAKERS), [320 + 200, 0]);
  // the defence takes everything: an ordinary failed contract
  assert.deepEqual(gained(100, 1, ALL_DEFENCE), [0, 160 + 100]);
});

test('art. 10.2 — the table holds whichever team takes', () => {
  const taker1: Contract = { seat: 1, value: 100, suit: 'S', mult: 1 };
  // seats 1 and 3 now make the 124
  const w = SPLIT.map((s) => ((s + 1) % 4) as Seat);
  assert.deepEqual(scoreRound(taker1, tricks(w), null).gained, [40, 120 + 100]);
  assert.deepEqual(scoreRound({ ...taker1, value: 150 }, tricks(w), { seat: 3, played: 2 }).gained, [160 + 150 + 20, 0]);
});

/* ---------- art. 10.3: rounding ---------- */

test('art. 10.3 — points are rounded to the nearest ten: 85 gives 90, 84 gives 80', () => {
  assert.equal(roundToTen(85), 90);
  assert.equal(roundToTen(84), 80);
  assert.equal(roundToTen(162), 160);
  assert.equal(roundToTen(252), 250);
  assert.equal(roundToTen(0), 0);
});

test('art. 10.3 — every score of a deal is a multiple of ten; the bid is checked on the points really made', () => {
  // takers 126 (J, A, 10, K), defence 36
  const w: Seat[] = [0, 3, 0, 2, 0, 3, 1, 3];
  const r = scoreRound(contract(130), tricks(w), null);
  assert.equal(r.takerPoints, 126);
  // 126 rounds to 130 but does not make a 130 contract
  assert.equal(r.made, false);
  const made = scoreRound(contract(120), tricks(w), null);
  assert.deepEqual(made.rows.tricks, [130, 40]);
  assert.deepEqual(made.gained, [130 + 120, 40]);
  for (const mult of [1, 2, 4] as const)
    for (const v of BID_VALUES)
      for (const winners of [SPLIT, w, ALL_TAKERS, ALL_DEFENCE])
        for (const g of gained(v, mult, winners, 'takers')) assert.equal(g % 10, 0, `${v} x${mult}`);
});

/* ---------- art. 10.4: end of the game ---------- */

test('art. 10.4 — the first team to reach or exceed the target wins', () => {
  // takers make 124 + 10 = 134, rounded 130, + 100 = 230
  const reached = finish(beforeLastCard([770, 0], 1000, 100, SPLIT));
  assert.deepEqual(reached.scores, [1000, 30]);
  assert.equal(reached.winner, 0);
  const next = apply(reached, 0, { kind: 'next' }, rng()).state;
  assert.equal(next.phase, 'gameEnd');
  const short = finish(beforeLastCard([760, 0], 1000, 100, SPLIT));
  assert.deepEqual(short.scores, [990, 30]);
  assert.equal(short.winner, null);
  assert.equal(apply(short, 0, { kind: 'next' }, rng()).state.phase, 'bidding');
});

test('art. 10.4 — both teams past the target on the same deal: the one with more points wins', () => {
  const takersAhead = finish(beforeLastCard([800, 990], 1000, 100, SPLIT));
  assert.deepEqual(takersAhead.scores, [1030, 1020]);
  assert.equal(takersAhead.winner, 0);
  // the takers made their contract, yet the defence is further past the target
  const defenceAhead = finish(beforeLastCard([780, 990], 1000, 100, SPLIT));
  assert.deepEqual(defenceAhead.scores, [1010, 1020]);
  assert.equal(defenceAhead.winner, 1);
});

test('art. 10.4 — a tie past the target: another deal is played until it is broken', () => {
  const tie = finish(beforeLastCard([790, 990], 1000, 100, SPLIT));
  assert.deepEqual(tie.scores, [1020, 1020]);
  assert.equal(tie.winner, null);
  const next = apply(tie, 0, { kind: 'next' }, rng()).state;
  assert.equal(next.phase, 'bidding');
  assert.equal(next.dealer, 0);
});

test('art. 10.4 — "les points sont arrondis en fin de partie et permettent de gagner"', () => {
  // the federation's example 2, here with 106 points made for a 100 contract: 1790 + 110 + 100 = 2000
  // (J, 9, A, Q and the last trick with its dix de der)
  const w: Seat[] = [0, 2, 0, 3, 1, 2, 1, 0];
  const s = finish(beforeLastCard([1790, 0], 2000, 100, w));
  assert.equal(s.result!.takerPoints, 106);
  assert.deepEqual(s.scores, [2000, 60]);
  assert.equal(s.winner, 0);
});
