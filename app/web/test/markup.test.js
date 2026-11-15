// Markup built from text: escaping, the names and order of cards, the star and the robots' faces.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { avatarSVG } from '../src/avatars.js';
import { esc } from '../src/html.js';
import { setLang } from '../src/i18n/index.js';
import { bidHTML, bidValueLabel, cardHTML, cardSrc, sortHand, suitHTML } from '../src/labels.js';
import { POINTS, starSVG } from '../src/star.js';

test('escaped text can break out of neither markup nor a quoted attribute', () => {
  const escaped = esc(`<img src=x onerror="alert('x')">&`);
  assert.doesNotMatch(escaped, /[<>"']/);
  assert.equal(esc('a & b'), 'a &#38; b');
  assert.equal(esc(42), '42');
});

test('a bid is named by its value and suit, a capot by the name the table gives it', () => {
  setLang('en');
  assert.equal(bidValueLabel(120), '120');
  assert.equal(bidValueLabel(250), 'Kabbout');
  assert.equal(bidHTML(90, 'H'), '<bdi>90 <span class="r">♥</span></bdi>');
  assert.equal(suitHTML('S'), '<span class="">♠</span>');
  assert.equal(suitHTML('X'), '', 'an unknown suit draws nothing');
});

test('court cards carry the index letters of the language', () => {
  setLang('fr');
  assert.equal(cardSrc('KH'), 'cards/KH.webp');
  setLang('en');
  assert.equal(cardSrc('KH'), 'cards/en/KH.webp');
  assert.equal(cardSrc('9H'), 'cards/9H.webp');
  assert.equal(
    cardHTML('AS', 'data-card="AS"'),
    '<div class="card" data-card="AS"><img src="cards/AS.webp" alt="Ace of spades" draggable="false"></div>',
  );
});

test('a hand is sorted trump first, colours alternating, strongest card first in each suit', () => {
  const hand = ['7S', 'AH', 'JS', 'TC', '9S', 'KD', 'AS', '7C'];
  assert.deepEqual(sortHand(hand, 'S'), ['JS', '9S', 'AS', '7S', 'AH', 'TC', '7C', 'KD']);
  assert.deepEqual(sortHand(hand, 'D'), ['KD', 'AS', 'JS', '9S', '7S', 'AH', 'TC', '7C']);
  assert.deepEqual(sortHand(hand, null), ['AS', 'JS', '9S', '7S', 'AH', 'TC', '7C', 'KD'], 'no trump while bidding');
  assert.deepEqual(hand[0], '7S', 'the hand given is left as it was');
});

test('the star has eight points, each with the class asked for, and a label only when it says something', () => {
  assert.equal(POINTS.length, 8);
  const lit = starSVG({ points: ['us', 'them'], label: 'Tricks: "1" & 1' });
  assert.equal(lit.match(/<polygon class="pt/g).length, 8);
  assert.match(lit, /class="pt us"/);
  assert.match(lit, /class="pt them"/);
  assert.match(lit, /role="img" aria-label="Tricks: &#34;1&#34; &#38; 1"/);
  const plain = starSVG({ spade: false });
  assert.match(plain, /aria-hidden="true"/);
  assert.doesNotMatch(plain, /class="sp"/);
});

test('each robot has a face, this screen’s player has none', () => {
  for (const place of [1, 2, 3]) assert.match(avatarSVG(place), /^<svg class="face"/);
  assert.notEqual(avatarSVG(1), avatarSVG(2));
  assert.equal(avatarSVG(0), '');
  assert.equal(avatarSVG(4), '');
});
