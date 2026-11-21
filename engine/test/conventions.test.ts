// The conventions card: what it lets through, how the robots bid by it, and how it is read back.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Action, Card, Conventions, GameState, Seat } from '../src/index.ts';
import {
  BOLD,
  CAUTIOUS,
  CLASSIC,
  CONVENTION_SETTINGS,
  CONVENTION_VALUES,
  READY_CONVENTIONS,
  aiBid,
  aiPlay,
  apply,
  describeConventions,
  isReadyConventions,
  legalBid,
  newGame,
  readConventions,
  seededRng,
  viewFor,
} from '../src/index.ts';
import { MAX_WHY_LENGTH } from '../src/types.ts';

const hand = (s: string) => s.split(' ') as Card[];
const rng = seededRng(1);

/** A deal where seat 2 has bid, and seat 0 (its partner) holds `mine`. Dealer 1, so seat 2 speaks first. */
function partnerOpened(mine: string, opening: 80 | 90 | 100 = 80): GameState {
  const s = newGame(seededRng(1), { dealer: 1 }).state;
  const hands = [hand(mine), s.hands[1], s.hands[2], s.hands[3]] as GameState['hands'];
  return apply({ ...s, hands }, 2, { kind: 'bid', bid: { type: 'bid', value: opening, suit: 'S' } }, rng).state;
}
const say = (s: GameState, seat: Seat, bid: Extract<Action, { kind: 'bid' }>['bid']) => apply(s, seat, { kind: 'bid', bid }, rng).state;
const PASS = { type: 'pass' } as const;

test('a card is its seven settings, each with a value it allows, and nothing else', () => {
  for (const card of Object.values(READY_CONVENTIONS)) assert.deepEqual(readConventions({ ...card }), card);
  assert.deepEqual(readConventions(JSON.parse(JSON.stringify(BOLD))), BOLD);
  for (const setting of CONVENTION_SETTINGS) {
    for (const value of CONVENTION_VALUES[setting]) assert.notEqual(readConventions({ ...CLASSIC, [setting]: value }), null, `${setting} ${value}`);
    for (const value of [5, 40, 100, -10, '10', null, 'always', 10.5, true]) {
      if ((CONVENTION_VALUES[setting] as readonly unknown[]).includes(value)) continue;
      assert.equal(readConventions({ ...CLASSIC, [setting]: value }), null, `${setting} ${String(value)}`);
    }
    const { [setting]: _left, ...short } = CLASSIC;
    assert.equal(readConventions(short), null, `without ${setting}`);
  }
  assert.equal(readConventions({ ...CLASSIC, contre: 20 }), null);
  for (const notACard of [null, undefined, 'classic', 70, [], [CLASSIC]]) assert.equal(readConventions(notACard), null);
  assert.equal(isReadyConventions('bold'), true);
  assert.equal(isReadyConventions('toString'), false);
});

test('given the classic card, a robot bids as it does given none', () => {
  for (const seed of [3, 11, 42]) {
    let { state } = newGame(seededRng(seed));
    const cards = seededRng(seed);
    for (let moves = 0; moves < 400 && state.phase !== 'gameEnd'; moves++) {
      const view = viewFor(state, state.turn);
      if (state.phase === 'bidding') assert.deepEqual(aiBid(view, 'fr', CLASSIC), aiBid(view));
      const action: Action =
        state.phase === 'bidding'
          ? { kind: 'bid', ...aiBid(view) }
          : state.phase === 'playing'
            ? { kind: 'play', card: aiPlay(view, cards, 'easy') }
            : { kind: 'next' };
      ({ state } = apply(state, state.turn, action, cards));
    }
  }
});

test('a card that adds 10 for the nine: the partner holding it raises by 10 and says so, and not without it', () => {
  const nineOnly: Conventions = { ...CLASSIC, jack: 0, threeTrumps: 0, ace: 0, belote: 0 };
  const withNine = aiBid(viewFor(say(partnerOpened('9S 8H 7H 8D 7D 8C 7C 9H'), 3, PASS), 0), 'en', nineOnly);
  assert.deepEqual(withNine.bid, { type: 'bid', value: 90, suit: 'S' });
  assert.equal(withNine.why, 'support · 9 ♠ +10');
  const without = aiBid(viewFor(say(partnerOpened('8S 8H 7H 8D 7D 8C 7C 9H'), 3, PASS), 0), 'en', nineOnly);
  assert.deepEqual(without.bid, PASS);

  // and by the amount the card says: 20 for the nine on the bold card, 10 on the classic one
  const view = viewFor(say(partnerOpened('9S 8H 7H 8D 7D 8C 7C 9H'), 3, PASS), 0);
  assert.deepEqual(aiBid(view, 'fr', BOLD).bid, { type: 'bid', value: 100, suit: 'S' });
  assert.deepEqual(aiBid(view, 'fr', CLASSIC).bid, { type: 'bid', value: 90, suit: 'S' });
  // the classic card's reasons carry no figure: they are what they always were
  assert.equal(aiBid(view, 'fr', CLASSIC).why, 'soutien · 9 ♠');
});

test('what it takes to open is the card’s', () => {
  // worth 70: opens at 80 on the classic card, not on a card that opens at 80
  const s = newGame(seededRng(1), { dealer: 3 }).state;
  const view = viewFor({ ...s, hands: [hand('JS 9S 7S KH 8D 7D 8C 7C'), s.hands[1], s.hands[2], s.hands[3]] as GameState['hands'] }, 0);
  assert.deepEqual(aiBid(view, 'en', CLASSIC).bid, { type: 'bid', value: 80, suit: 'S' });
  assert.deepEqual(aiBid(view, 'en', CAUTIOUS), { bid: PASS, why: 'short of the opening we agreed (80)' });
});

test('the second round: aces kept back are shown when the opponents bid over the pair, and never twice', () => {
  const keepsAces: Conventions = { ...CLASSIC, aces: 'whenOverbid' };
  // seat 0 holds the nine of trumps and two outside aces
  const mine = '9S 8S AH AD 7D 8C 7C 9H';
  let s = say(partnerOpened(mine), 3, PASS);

  // first round: the nine alone on this card, the nine and the aces on the classic one
  const first = aiBid(viewFor(s, 0), 'en', keepsAces);
  assert.deepEqual(first.bid, { type: 'bid', value: 90, suit: 'S' });
  assert.equal(first.why, 'support · 9 ♠ +10');
  assert.deepEqual(aiBid(viewFor(s, 0), 'en', CLASSIC).bid, { type: 'bid', value: 110, suit: 'S' });

  // nobody bids over us: the aces stay unsaid
  s = say(s, 0, first.bid);
  const quiet = say(say(say(s, 1, PASS), 2, PASS), 3, PASS);
  assert.equal(quiet.phase, 'playing');

  // the opponents bid over us: the aces are shown, once
  let over = say(say(say(s, 1, { type: 'bid', value: 100, suit: 'H' }), 2, PASS), 3, PASS);
  const second = aiBid(viewFor(over, 0), 'en', keepsAces);
  assert.deepEqual(second.bid, { type: 'bid', value: 110, suit: 'S' });
  assert.equal(second.why, 'aces kept for when they bid over us · A ♥ +10 · A ♦ +10');
  // the classic card has nothing left to say there
  assert.deepEqual(aiBid(viewFor(over, 0), 'en', CLASSIC).bid, PASS);

  over = say(say(say(say(over, 0, second.bid), 1, { type: 'bid', value: 120, suit: 'H' }), 2, PASS), 3, PASS);
  assert.deepEqual(aiBid(viewFor(over, 0), 'en', keepsAces).bid, PASS);
});

test('a raise made over the opponents holds the aces already: there is no second one', () => {
  const keepsAces: Conventions = { ...CLASSIC, aces: 'whenOverbid' };
  // seat 3 bids over seat 2 before seat 0 has spoken: seat 0 keeps nothing back
  let s = say(partnerOpened('JS 8S AH AD 7D 8C 7C 9H'), 3, { type: 'bid', value: 90, suit: 'H' });
  const first = aiBid(viewFor(s, 0), 'en', keepsAces);
  assert.deepEqual(first.bid, { type: 'bid', value: 120, suit: 'S' });
  assert.equal(first.why, 'support · J ♠ +20 · A ♥ +10 · A ♦ +10');
  s = say(say(say(say(s, 0, first.bid), 1, { type: 'bid', value: 130, suit: 'H' }), 2, PASS), 3, PASS);
  assert.deepEqual(aiBid(viewFor(s, 0), 'en', keepsAces).bid, PASS);
});

test('whatever the card, every bid a robot makes is a legal one, with a reason the engine takes', () => {
  const corners: Conventions[] = [
    ...Object.values(READY_CONVENTIONS),
    { open: 90, jack: 30, nine: 20, threeTrumps: 20, belote: 30, ace: 20, aces: 'whenOverbid' },
    { open: 70, jack: 30, nine: 20, threeTrumps: 20, belote: 30, ace: 20, aces: 'atOnce' },
    { open: 70, jack: 0, nine: 0, threeTrumps: 0, belote: 0, ace: 0, aces: 'whenOverbid' },
    { open: 90, jack: 0, nine: 0, threeTrumps: 0, belote: 0, ace: 20, aces: 'whenOverbid' },
  ];
  let bids = 0;
  for (const [index, card] of corners.entries()) {
    for (const lang of ['fr', 'en', 'ar'] as const) {
      const cards = seededRng(500 + index);
      let { state } = newGame(cards);
      for (let deals = 0; deals < 40 && state.phase !== 'gameEnd';) {
        if (state.phase === 'bidding') {
          // one pair bids by the card, the other by the classic one, as at the table
          const { bid, why } = aiBid(viewFor(state, state.turn), lang, state.turn % 2 === 0 ? card : CLASSIC);
          assert.equal(legalBid(state, state.turn, bid), true, `${JSON.stringify(bid)} by ${JSON.stringify(card)}`);
          assert.ok(why.length <= MAX_WHY_LENGTH, why);
          bids++;
          ({ state } = apply(state, state.turn, { kind: 'bid', bid, why }, cards));
        } else if (state.phase === 'playing') {
          ({ state } = apply(state, state.turn, { kind: 'play', card: aiPlay(viewFor(state, state.turn), cards, 'easy') }, cards));
        } else {
          deals++;
          ({ state } = apply(state, state.turn, { kind: 'next' }, cards));
        }
      }
    }
  }
  assert.ok(bids > 500, `${bids} bids`);
});

test('the read-back names every setting that is not the classic card’s, and nothing else', () => {
  for (const lang of ['fr', 'en', 'ar'] as const) assert.deepEqual(describeConventions(CLASSIC, lang), []);
  assert.deepEqual(describeConventions(CAUTIOUS, 'en'), [
    'We open on a hand worth at least 80.',
    'Three trumps or more: the partner does not raise for it.',
  ]);
  assert.deepEqual(describeConventions(BOLD, 'fr'), [
    'Le valet d’atout : le partenaire monte de 30.',
    'Le neuf d’atout : le partenaire monte de 20.',
    'Le partenaire garde ses as, et ne les annonce que si l’adversaire surenchérit.',
  ]);
  // one line a setting, in every language, each unlike the others
  for (const lang of ['fr', 'en', 'ar'] as const) {
    const seen = new Set<string>();
    for (const setting of CONVENTION_SETTINGS) {
      for (const value of CONVENTION_VALUES[setting]) {
        const lines = describeConventions({ ...CLASSIC, [setting]: value } as Conventions, lang);
        assert.equal(lines.length, value === CLASSIC[setting] ? 0 : 1, `${lang} ${setting} ${value}`);
        for (const line of lines) {
          assert.equal(seen.has(line), false, line);
          seen.add(line);
        }
      }
    }
  }
});
