// The questions the screen asks about a game, answered from a view alone.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { apply, newGame, seededRng, viewFor } from '../../../engine/src/index.ts';
import {
  availableBids,
  bidValueToShow,
  canContre,
  consultableTrick,
  isAwaitingMove,
  lastBidOf,
  lastContre,
  turnKey,
  tutorialStep,
} from '../src/selectors.js';

const pass = { kind: 'bid', bid: { type: 'pass' } };
const bid = (value, suit = 'S') => ({ kind: 'bid', bid: { type: 'bid', value, suit } });
/** a deal where seat 0 speaks first, after the given bids, as seat 0 sees it */
function auction(...actions) {
  const rng = seededRng(5);
  let state = newGame(rng, { dealer: 3 }).state;
  for (const action of actions) state = apply(state, state.turn, action, rng).state;
  return viewFor(state, 0);
}

test('only the values above the best bid can still be bid, and the panel never shows one that cannot', () => {
  assert.deepEqual(availableBids(auction()), [80, 90, 100, 110, 120, 130, 140, 150, 160, 250]);
  const after130 = auction(pass, bid(130));
  assert.deepEqual(availableBids(after130), [140, 150, 160, 250]);
  assert.equal(bidValueToShow(after130, 150), 150);
  assert.equal(bidValueToShow(after130, 100), 140, 'a value chosen earlier that was overtaken gives way to the lowest left');
  assert.equal(bidValueToShow(after130, null), 140);
  const afterCapot = auction(pass, bid(250));
  assert.deepEqual(availableBids(afterCapot), []);
  assert.equal(bidValueToShow(afterCapot, 160), null, 'nothing beats a capot');
});

test('a contre is for the side that does not hold the bid, once', () => {
  assert.equal(canContre(auction(), 0), false, 'nothing to contre yet');
  const theirs = auction(pass, bid(80));
  assert.equal(canContre(theirs, 0), true);
  assert.equal(canContre(theirs, 3), false, 'their partner cannot');
  const contred = auction(pass, bid(80), { kind: 'bid', bid: { type: 'contre' } });
  assert.equal(canContre(contred, 0), false);
  assert.equal(lastContre(contred).seat, 2);
  assert.equal(lastContre(theirs), null);
});

test('the last thing a seat said is found, whatever was said since', () => {
  const view = auction(bid(80), pass, bid(100, 'H'), pass, pass);
  assert.deepEqual(lastBidOf(view, 0), { type: 'pass', seat: 0 });
  assert.deepEqual(lastBidOf(view, 2), { type: 'bid', value: 100, suit: 'H', seat: 2 });
  assert.equal(lastBidOf(auction(), 1), null);
});

test('each turn has its own key, and a move is awaited only while bidding or playing', () => {
  const first = auction();
  const second = auction(pass);
  assert.notEqual(turnKey(first), turnKey(second));
  assert.equal(turnKey(first), turnKey(auction()));
  assert.equal(isAwaitingMove(first), true);
  assert.equal(isAwaitingMove({ ...first, phase: 'roundEnd' }), false);
});

test('the tutorial explains bidding, then the contre when it becomes possible, then card play, each once', () => {
  const open = auction();
  const theirs = auction(pass, bid(80), pass, pass);
  const step = (view, done, turn = { myBidTurn: true, myPlayTurn: false }) => tutorialStep({ view, seat: 0, done, ...turn });
  assert.equal(step(open, {}), 'bid');
  assert.equal(step(open, { bid: true }), null, 'no contre to explain before the other side bids');
  assert.equal(step(theirs, { bid: true }), 'contre');
  assert.equal(step(theirs, { bid: true, contre: true }), null);
  assert.equal(step({ ...theirs, contreQueue: [0] }, {}), null, 'answering a contre is explained by its own panel');
  const playing = { myBidTurn: false, myPlayTurn: true };
  assert.equal(step(open, {}, playing), 'play');
  assert.equal(step(open, { play: true }, playing), null);
  assert.equal(step(open, {}, { myBidTurn: false, myPlayTurn: false }), null);
});

test('FFB art. 5.1: the previous trick may be looked at only while the next one lies complete, or once the round is over', () => {
  const trick = (winner) => ({ leader: 0, plays: [], winner });
  const playing = { ...auction(), phase: 'playing', tricks: [trick(0), trick(1), trick(2)] };
  assert.equal(consultableTrick(playing, false), null, 'not before playing one’s card');
  assert.equal(consultableTrick(playing, true).winner, 1, 'the one before the trick on the table');
  assert.equal(consultableTrick({ ...playing, tricks: [trick(0)] }, true), null, 'the first trick has none before it');
  assert.equal(consultableTrick({ ...playing, phase: 'roundEnd' }, false).winner, 2, 'the last one, after the round');
  assert.equal(consultableTrick({ ...playing, phase: 'gameEnd', tricks: [] }, false), null);
});
