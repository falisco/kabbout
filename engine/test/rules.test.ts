import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Card, Suit } from '../src/index.ts';
import { SUITS, cardPoints, fullDeck, legalCards, trickWinner } from '../src/index.ts';

const p = (seat: 0 | 1 | 2 | 3, card: Card) => ({ seat, card });

test('a deal is worth 152 card points whatever the trump', () => {
  for (const t of SUITS)
    assert.equal(
      fullDeck().reduce((s, c) => s + cardPoints(c, t), 0),
      152,
    );
});

test('card values follow the trump / plain tables', () => {
  const t: Suit = 'H';
  assert.deepEqual(
    ['JH', '9H', 'AH', 'TH', 'KH', 'QH', '8H', '7H'].map((c) => cardPoints(c as Card, t)),
    [20, 14, 11, 10, 4, 3, 0, 0],
  );
  assert.deepEqual(
    ['AS', 'TS', 'KS', 'QS', 'JS', '9S', '8S', '7S'].map((c) => cardPoints(c as Card, t)),
    [11, 10, 4, 3, 2, 0, 0, 0],
  );
});

test('trick winner: highest of the led suit, any trump beats it, discards never win', () => {
  assert.equal(trickWinner([p(0, 'KS'), p(1, 'AS'), p(2, 'TS'), p(3, '7S')], 'H').seat, 1);
  assert.equal(trickWinner([p(0, 'AS'), p(1, '7H'), p(2, 'TS'), p(3, 'AD')], 'H').seat, 1);
  assert.equal(trickWinner([p(0, '7S'), p(1, 'AD'), p(2, 'AC'), p(3, '8S')], 'H').seat, 3);
  // trump order: J > 9 > A > 10
  assert.equal(trickWinner([p(0, 'AH'), p(1, '9H'), p(2, 'TH'), p(3, 'JH')], 'H').seat, 3);
  assert.equal(trickWinner([p(0, 'AH'), p(1, '9H'), p(2, 'TH'), p(3, '8H')], 'H').seat, 1);
});

test('must follow the led suit', () => {
  assert.deepEqual(legalCards(['AS', '7S', 'KH', '9D'], [p(1, 'TS')], 'H', 2).sort(), ['7S', 'AS']);
});

test('trump led: must go higher when possible, else any trump', () => {
  assert.deepEqual(legalCards(['AH', '7H', 'JH'], [p(1, '9H')], 'H', 2), ['JH']);
  assert.deepEqual(legalCards(['AH', '7H'], [p(1, '9H')], 'H', 2).sort(), ['7H', 'AH']);
});

test('void, opponent master: must trump, and over-trump an opponent', () => {
  assert.deepEqual(legalCards(['7H', 'AD', 'KC'], [p(1, 'AS')], 'H', 2), ['7H']);
  assert.deepEqual(legalCards(['7H', 'JH', 'AD'], [p(0, 'AS'), p(1, '9H')], 'H', 2), ['JH']);
});

test('void and unable to over-trump: free to play anything, no forced under-trump', () => {
  assert.deepEqual(legalCards(['7H', '8H', 'AD'], [p(0, 'AS'), p(1, 'JH')], 'H', 2).sort(), ['7H', '8H', 'AD']);
});

test('void with partner master: free to play anything', () => {
  assert.deepEqual(legalCards(['7H', 'AD', 'KC'], [p(0, 'AS'), p(1, '7S')], 'H', 2).sort(), ['7H', 'AD', 'KC']);
});

test('void and no trump: free to discard', () => {
  assert.deepEqual(legalCards(['AD', 'KC'], [p(1, 'AS')], 'H', 2).sort(), ['AD', 'KC']);
});
