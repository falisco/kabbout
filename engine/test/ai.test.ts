import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { BidRecord, Card, GameState, Seat } from '../src/index.ts';
import { aiBid, aiPlay, apply, newGame, seededRng, viewFor } from '../src/index.ts';
import { evaluateContre, evaluateSuit, evaluateSupport, explain, sureCapot } from '../src/ai/bidding.ts';

const hand = (s: string) => s.split(' ') as Card[];

test('evaluateSuit: V-9 third + an outside ace opens at 90', () => {
  const e = evaluateSuit(hand('JS 9S 7S AH 8D 7D 8C 7C'), 'S');
  assert.equal(e.value, 90);
  assert.deepEqual(explain(e.reasons, 'fr'), ['V-9 ♠', 'As ♥']);
});

test('the reasons come in English when asked, and the bid does not change', () => {
  const h = hand('JS 9S 7S AH 8D 7D 8C 7C');
  assert.deepEqual(explain(evaluateSuit(h, 'S').reasons, 'en'), ['J-9 ♠', 'A ♥']);
  assert.deepEqual(explain(evaluateSupport(h, 'S').reasons, 'en'), ['J ♠', '9 ♠', '3 trumps', 'A ♥']);
  const view = viewFor(stateWith([h, ...FILLER]), 0);
  const fr = aiBid(view);
  const en = aiBid(view, 'en');
  assert.deepEqual(en.bid, fr.bid);
  assert.equal(fr.why, 'V-9 ♠ · As ♥');
  assert.equal(en.why, 'J-9 ♠ · A ♥');
  const weak = viewFor(stateWith([hand('JS 8S 7S KH 8D 7D 8C 7C'), ...FILLER]), 0);
  assert.equal(aiBid(weak, 'en').why, 'no solid trump suit');
});

test('evaluateSuit: no outside trick costs a level', () => {
  assert.equal(evaluateSuit(hand('JS 9S 7S KH 8D 7D 8C 7C'), 'S').value, 70);
});

test('aiBid opens at 80 on a hand one level short, and passes below', () => {
  const open = (h: string) => aiBid(viewFor(stateWith([hand(h), ...FILLER]), 0)).bid;
  assert.deepEqual(open('JS 9S 7S KH 8D 7D 8C 7C'), { type: 'bid', value: 80, suit: 'S' });
  // jack third and nothing beside: 50
  assert.equal(evaluateSuit(hand('JS 8S 7S KH 8D 7D 8C 7C'), 'S').value, 50);
  assert.deepEqual(open('JS 8S 7S KH 8D 7D 8C 7C'), { type: 'pass' });
});

test('evaluateSuit: belote and length count, without any ace', () => {
  const e = evaluateSuit(hand('JS 9S KS QS 8H 7D 8C 7C'), 'S');
  assert.equal(e.value, 110);
  assert.ok(explain(e.reasons, 'fr').includes('belote'));
});

test('evaluateSuit: no jack and no long 9-A means no opening in that suit', () => {
  assert.equal(evaluateSuit(hand('AS TS KS QS 8H 7D 8C 7C'), 'S').value, 0);
});

test('evaluateSupport: +20 jack, +10 nine, +10 with three trumps, +10 per outside ace', () => {
  assert.deepEqual(evaluateSupport(hand('JS 9S 7S AH AD 8C 7C 8H'), 'S').value, 60);
  assert.equal(evaluateSupport(hand('7S AH 8D 7D 8C 7C 8H 9H'), 'S').value, 10);
});

test('evaluateContre: contre on a well guarded nine of trumps, not on aces or a short stop', () => {
  const at = (value: 80 | 110 | 160) => ({ seat: 1 as Seat, value, suit: 'S' as const });
  // four trumps to the nine
  assert.equal(evaluateContre(hand('9S 8S 7S QS 8D 7D 8C 7C'), at(110)).worthIt, true);
  // three to the nine: two outside aces are needed
  assert.equal(evaluateContre(hand('9S 8S 7S AH AD 7D 8C 7C'), at(110)).worthIt, true);
  assert.equal(evaluateContre(hand('9S 8S 7S AH 8D 7D 8C 7C'), at(110)).worthIt, false);
  assert.equal(evaluateContre(hand('9S AS 7S AH 8D 7D 8C 7C'), at(110)).worthIt, false);
  // jack and nine third
  assert.equal(evaluateContre(hand('JS 9S 7S 8H 8D 7D 8C 7C'), at(80)).worthIt, true);
  // what used to contre and lost two times out of three: a guarded jack, or three aces
  assert.equal(evaluateContre(hand('JS 7S AH 8D 7D 8C 7C 8H'), at(110)).worthIt, false);
  assert.equal(evaluateContre(hand('7S AH AD AC 7D 8C 7C 8H'), at(160)).worthIt, false);
  // the reasons name the stop first
  assert.deepEqual(explain(evaluateContre(hand('9S 8S 7S AH AD 7D 8C 7C'), at(110)).reasons, 'fr'), ['9 ♠ troisième', 'As ♥', 'As ♦']);
});

test('sureCapot: enough top trumps to draw the others, and only top cards beside', () => {
  assert.equal(sureCapot(hand('JS 9S AS TS AH TH AD AC'), 'S'), true);
  assert.equal(sureCapot(hand('JS 9S AS 8S 7S AH AD AC'), 'S'), true);
  // four trumps are out and only two rounds are sure
  assert.equal(sureCapot(hand('JS 9S 8S 7S AH AD AC TC'), 'S'), false);
  // a loser beside
  assert.equal(sureCapot(hand('JS 9S AS TS KS AH TH 7D'), 'S'), false);
  // a suit I do not hold may be led first: the ruff costs a top trump
  assert.equal(sureCapot(hand('JS 9S AS 8S 7S AH TH KH'), 'S'), false);
  assert.equal(sureCapot(hand('JS 9S AS TS 7S AH TH KH'), 'S'), true);
});

function stateWith(hands: Card[][], dealer: Seat = 3): GameState {
  const s = newGame(seededRng(1), { dealer }).state;
  return { ...s, hands: hands as GameState['hands'] };
}
const H0 = hand('JS 9S 7S AH 8D 7D 8C 7C');
const FILLER = [hand('8S QH 7H 9D TD 9C TC KD'), hand('TS KS AS JH 9H TH AD JD'), hand('QS QD KC QC AC JC 8H KH')];

test('aiBid opens with its evaluation and explains it', () => {
  const r = aiBid(viewFor(stateWith([H0, ...FILLER]), 0));
  assert.deepEqual(r.bid, { type: 'bid', value: 90, suit: 'S' });
  assert.equal(r.why, 'V-9 ♠ · As ♥');
});

test('aiBid supports its partner once, with the support conventions', () => {
  let s = stateWith([hand('8S QH 7H 9D TD 9C TC KD'), FILLER[0], H0, FILLER[2]], 1);
  // dealer 1 → seat 2 bids first
  s = apply(s, 2, { kind: 'bid', bid: { type: 'bid', value: 90, suit: 'S' } }, seededRng(1)).state;
  s = apply(s, 3, { kind: 'bid', bid: { type: 'pass' } }, seededRng(1)).state;
  const view = viewFor({ ...s, hands: [hand('AH AD 9S 7S 8C 7C 8H 9H'), ...s.hands.slice(1)] as GameState['hands'] }, 0);
  const r = aiBid(view);
  assert.deepEqual(r.bid, { type: 'bid', value: 120, suit: 'S' });
  assert.match(r.why, /^soutien · 9 ♠/);
});

test('aiBid never bids at or below the current best, and nothing after a capot', () => {
  const s = stateWith([H0, ...FILLER]);
  const bids: BidRecord[] = [{ seat: 3, type: 'bid', value: 250, suit: 'H' }];
  const v = viewFor({ ...s, bids, best: { seat: 3, value: 250, suit: 'H' } }, 0);
  assert.notEqual(aiBid(v).bid.type, 'bid');
});

test('aiBid announces a capot it is sure of, even over an opposing bid', () => {
  const sure = hand('JS 9S AS TS AH TH AD AC');
  const s = stateWith([sure, ...FILLER]);
  assert.deepEqual(aiBid(viewFor(s, 0)).bid, { type: 'bid', value: 250, suit: 'S' });
  const over = {
    ...s,
    bids: [{ seat: 3, type: 'bid', value: 160, suit: 'H' }] as BidRecord[],
    best: { seat: 3 as Seat, value: 160 as const, suit: 'H' as const },
  };
  assert.deepEqual(aiBid(viewFor(over, 0)).bid, { type: 'bid', value: 250, suit: 'S' });
});

/** seat 0 opens `open` ♠, seat 2 raises to `raise` ♠, seat 3 contres: seat 0 is asked first */
function contred(h0: Card[], open: 80 | 110, raise: 110 | 130): GameState {
  const r = seededRng(1);
  let s = stateWith([h0, ...FILLER]);
  s = apply(s, 0, { kind: 'bid', bid: { type: 'bid', value: open, suit: 'S' } }, r).state;
  s = apply(s, 1, { kind: 'bid', bid: { type: 'pass' } }, r).state;
  s = apply(s, 2, { kind: 'bid', bid: { type: 'bid', value: raise, suit: 'S' } }, r).state;
  return apply(s, 3, { kind: 'bid', bid: { type: 'contre' } }, r).state;
}

test('aiBid does not surcontre by counting its own hand twice', () => {
  // opened 80 on a hand worth 90; the raise to 110 already counts on that hand
  const s = contred(H0, 80, 110);
  assert.deepEqual(s.contreQueue, [0, 2]);
  assert.deepEqual(aiBid(viewFor(s, 0)).bid, { type: 'pass' });
});

test('aiBid surcontres when the two hands are worth well over the contract', () => {
  // a hand worth 140 that only opened 80: with the partner's raise of 30, 170 for a contract of 110
  const strong = hand('JS 9S AS KS QS AH 8D 7C');
  assert.equal(evaluateSuit(strong, 'S').value, 140);
  const r = aiBid(viewFor(contred(strong, 80, 110), 0));
  assert.deepEqual(r.bid, { type: 'surcontre' });
  assert.equal(r.why, 'jeu estimé à 170 à deux');
  // the supporter: partner announced 110, my jack and an ace add 30, not enough over 130
  const sup = contred(strong, 110, 130);
  const asked = {
    ...sup,
    turn: 2 as Seat,
    contreQueue: [2 as Seat],
    hands: [sup.hands[0], sup.hands[1], hand('TS 7S AD 8H 7H 9D 9C TC'), sup.hands[3]] as GameState['hands'],
  };
  assert.deepEqual(aiBid(viewFor(asked, 2)).bid, { type: 'pass' });
});

test('aiPlay: last to play with partner master → loads points', () => {
  let s = stateWith([hand('AD 7D 8C 7C 9C TC KC QC'), FILLER[0], FILLER[1], FILLER[2]]);
  s = {
    ...s,
    phase: 'playing',
    contract: { seat: 2, value: 80, suit: 'S', mult: 1 },
    turn: 0,
    trick: {
      leader: 1,
      plays: [
        { seat: 1, card: '8H' },
        { seat: 2, card: 'JH' },
        { seat: 3, card: '9H' },
      ],
    },
  };
  // void in hearts, partner (seat 2) is master and the trick is complete after me: play the ace
  assert.equal(aiPlay(viewFor(s, 0), seededRng(1)), 'AD');
});

test('aiPlay: the taker leads its master trump to draw trumps', () => {
  let s = stateWith([hand('JS 9S 7S AH 8D 7D 8C 7C'), ...FILLER]);
  s = { ...s, phase: 'playing', contract: { seat: 0, value: 90, suit: 'S', mult: 1 }, turn: 0, trick: { leader: 0, plays: [] } };
  assert.equal(aiPlay(viewFor(s, 0), seededRng(1)), 'JS');
});
