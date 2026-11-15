// The robots' card play: what they remember, how the levels differ, and how the expert imagines the other hands.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { Card, CompletedTrick, GameState, Level, Play, PlayerView, Seat, Suit, Trick } from '../src/index.ts';
import { LEVELS, aiPlay, apply, fullDeck, legalPlays, newGame, seededRng, trickWinner, viewFor } from '../src/index.ts';
import { recall } from '../src/ai/memory.ts';
import { MIN_SAMPLES, sampleHands, searchCard } from '../src/ai/search.ts';
import { robotAction } from './helpers.ts';

/** 'seat:card' tokens, in playing order */
const playsOf = (s: string): Play[] => s.split(' ').map((x) => ({ seat: +x[0] as Seat, card: x.slice(2) as Card }));
const unfinishedTrick = (s: string): Trick => ({ leader: playsOf(s)[0].seat, plays: playsOf(s) });
const trick = (s: string, trump: Suit): CompletedTrick => ({ ...unfinishedTrick(s), winner: trickWinner(playsOf(s), trump).seat });
/** A position as one seat sees it; only what the robots read is filled in. */
function view(o: {
  seat: Seat;
  hand: string;
  trump: Suit;
  taker: Seat;
  tricks?: string[];
  trick?: string;
  belote?: PlayerView['belote'];
}): PlayerView {
  const base = viewFor(newGame(seededRng(1)).state, o.seat);
  const tricks = (o.tricks ?? []).map((t) => trick(t, o.trump));
  const current = o.trick ? unfinishedTrick(o.trick) : { leader: o.seat, plays: [] };
  const left = (s: number) => 8 - tricks.length - (current.plays.some((p) => p.seat === s) ? 1 : 0);
  return {
    ...base,
    phase: 'playing',
    turn: o.seat,
    contract: { seat: o.taker, value: 100, suit: o.trump, mult: 1 },
    hand: o.hand.split(' ') as Card[],
    tricks,
    trick: current,
    handSizes: [left(0), left(1), left(2), left(3)],
    belote: o.belote ?? null,
  };
}
const suit = (s: Suit) => fullDeck().filter((c) => c[1] === s);
const hasAll = (set: Set<Card>, cards: Card[]) => cards.every((c) => set.has(c));

/* ---------- memory ---------- */

test('memory: a player who does not follow suit has none of it', () => {
  // seat 2 discards on its partner's ace: void in hearts, and that says nothing about its trumps
  const m = recall(view({ seat: 0, hand: 'KH 7D 8D 9D 7C 8C 9C', trump: 'S', taker: 1, tricks: ['0:AH 1:7H 2:8D 3:QH'] }));
  assert.ok(hasAll(m.cannot[2], suit('H')));
  assert.equal(m.cannot[2].has('JS'), false);
  assert.equal(m.cannot[1].size + m.cannot[3].size, 0);
});

test('memory: void, an opponent holds the trick, and no trump played: no trump left', () => {
  const m = recall(view({ seat: 0, hand: 'KH 7D TD 9D 7C 8C 9C', trump: 'S', taker: 1, tricks: ['0:AH 1:8D 2:7H 3:QH'] }));
  assert.ok(hasAll(m.cannot[1], [...suit('H'), ...suit('S')]));
});

test('memory: a ruff below an opponent’s trump shows nothing above it', () => {
  const m = recall(view({ seat: 0, hand: 'KH 7D 8D 9D 7C 8C 9C', trump: 'S', taker: 1, tricks: ['1:AH 2:9S 3:7S 0:QH'] }));
  assert.ok(m.cannot[3].has('JS'));
  assert.equal(m.cannot[3].has('AS'), false);
});

test('memory: on a trump lead, playing under the best trump shows nothing above it', () => {
  const m = recall(view({ seat: 0, hand: '9S 7D 8D 9D 7C 8C 9C', trump: 'S', taker: 0, tricks: ['0:9S 1:7S 2:8S 3:JS'] }));
  assert.ok(m.cannot[1].has('JS') && m.cannot[2].has('JS'));
  assert.equal(m.cannot[1].has('8S'), false);
  assert.equal(m.cannot[3].size, 0);
});

test('memory: the belote places the second card, a lone king or queen excludes it', () => {
  const pos = { seat: 0 as Seat, hand: '9S 7D 8D 9D 7C 8C 9C', trump: 'S' as Suit, taker: 1 as Seat, tricks: ['0:7S 1:KS 2:8S 3:JS'] };
  const announced = recall(view({ ...pos, belote: { seat: 1, played: 1 } }));
  assert.ok(announced.cannot[2].has('QS') && announced.cannot[3].has('QS'));
  assert.equal(announced.cannot[1].has('QS'), false);
  const silent = recall(view(pos));
  assert.ok(silent.cannot[1].has('QS'));
  assert.equal(silent.cannot[3].has('QS'), false);
});

test('memory: unseen cards are those neither played nor in hand; the easy level forgets finished tricks', () => {
  const v = view({ seat: 0, hand: 'KH 7D TD 9D 7C 8C 9C', trump: 'S', taker: 1, tricks: ['0:AH 1:8D 2:7H 3:QH'], trick: '3:TH' });
  const all = recall(v);
  assert.equal(all.unseen.length, 32 - 7 - 5);
  assert.ok(all.played.has('AH') && all.played.has('TH'));
  const forgetful = recall(v, false);
  assert.deepEqual([...forgetful.played], ['TH']);
  assert.equal(forgetful.cannot[1].size, 0);
});

test('memory never rules out a card a player really holds, over 40 robot games', () => {
  for (let seed = 1; seed <= 40; seed++) {
    const rng = seededRng(seed);
    let s = newGame(rng).state;
    while (s.phase !== 'gameEnd') {
      const seat = s.turn;
      if (s.phase === 'playing') {
        const m = recall(viewFor(s, seat));
        for (const o of [0, 1, 2, 3]) for (const c of s.hands[o]) assert.ok(!m.cannot[o].has(c), `seed ${seed}: seat ${o} holds ${c}`);
      }
      s = step(s, rng, 'normal');
    }
  }
});

/* ---------- levels ---------- */

const step = (s: GameState, rng: () => number, level: Level): GameState => apply(s, s.turn, robotAction(s, rng, level), rng).state;
const play = (v: PlayerView, level: Level) => aiPlay(v, seededRng(1), level);

test('normal does not lead a master ace into a suit an opponent ruffs; easy has forgotten', () => {
  // seat 3 ruffed hearts in the first trick
  const v = view({ seat: 0, hand: 'AH 7D 8D QD 7C 8C 9C', trump: 'S', taker: 1, tricks: ['2:KH 3:7S 0:8H 1:9H'] });
  assert.equal(play(v, 'easy'), 'AH');
  assert.notEqual(play(v, 'normal'), 'AH');
});

test('normal stops drawing trumps once the opponents have none; easy goes on', () => {
  // both opponents discarded on the jack of trumps
  const v = view({ seat: 0, hand: '9S AS AH 7C 8C 9C 7H', trump: 'S', taker: 0, tricks: ['0:JS 1:8H 2:7S 3:7D'] });
  assert.equal(play(v, 'normal'), 'AH');
  assert.equal(play(v, 'easy'), 'AS');
});

test('normal gives points to a partner who holds the trick only when the last opponent cannot ruff', () => {
  const pos = { seat: 0 as Seat, hand: 'TH 8H 7D 8D 7C 8C 9C', trump: 'S' as Suit, taker: 1 as Seat, trick: '2:AH 3:7H' };
  // nothing known: seat 1 has to follow, the ace holds
  assert.equal(play(view({ ...pos, tricks: ['3:KD 0:9D 1:TD 2:AD'] }), 'normal'), 'TH');
  // seat 1 already ruffed hearts: keep the ten
  const ruffed = view({ ...pos, hand: 'TH 8H 7D 8D 7C 8C 9C', tricks: ['3:KH 0:9H 1:7S 2:QH'] });
  assert.equal(play(ruffed, 'normal'), '8H');
  assert.equal(play(ruffed, 'easy'), 'TH');
});

/* ---------- the expert ---------- */

test('the worlds the expert imagines fit the hand sizes and everything observed', () => {
  let checked = 0;
  for (let seed = 1; seed <= 4; seed++) {
    const rng = seededRng(seed);
    let s = newGame(rng).state;
    while (s.phase !== 'gameEnd') {
      if (s.phase === 'playing') {
        const v = viewFor(s, s.turn);
        const m = recall(v);
        const hands = sampleHands(v, m, rng);
        assert.ok(hands, 'a world exists: the real one');
        assert.deepEqual(
          hands.map((h) => h.length),
          v.handSizes,
        );
        assert.deepEqual(hands[v.seat], v.hand);
        assert.deepEqual(hands.flat().sort(), [...v.hand, ...m.unseen].sort());
        hands.forEach((h, o) => o === v.seat || h.forEach((c) => assert.ok(!m.cannot[o].has(c))));
        checked++;
      }
      s = step(s, rng, 'normal');
    }
  }
  assert.ok(checked >= 200);
});

test('out of time, the expert settles for its first worlds instead of thinking on', () => {
  const s0 = newGame(seededRng(5)).state;
  let s = s0;
  while (s.phase !== 'playing') s = step(s, seededRng(5), 'normal');
  const v = viewFor(s, s.turn);
  const hurried = searchCard(v, recall(v), seededRng(9), 500, () => true);
  assert.equal(hurried, searchCard(v, recall(v), seededRng(9), MIN_SAMPLES));
  // asked only once the minimum is done
  let asked = 0;
  searchCard(v, recall(v), seededRng(9), MIN_SAMPLES + 3, () => (asked++, false));
  assert.equal(asked, 3);
});

test('every level plays whole games with legal cards, and the same seed gives the same game', () => {
  const run = (level: Level, seed: number) => {
    const rng = seededRng(seed);
    let s = newGame(rng).state;
    while (s.phase !== 'gameEnd') {
      if (s.phase === 'playing') {
        const card = aiPlay(viewFor(s, s.turn), seededRng(seed + s.tricks.length), level);
        assert.ok(legalPlays(s, s.turn).includes(card), `${level}: illegal card ${card}`);
      }
      s = step(s, rng, level);
    }
    return s.scores;
  };
  for (const level of LEVELS) {
    const games = level === 'expert' ? 2 : 25;
    for (let seed = 1; seed <= games; seed++) run(level, seed);
    assert.deepEqual(run(level, 3), run(level, 3));
  }
});
