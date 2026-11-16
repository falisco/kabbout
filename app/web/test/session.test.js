// The local session is the only thing the screen knows of the game: these tests hold it to
// what an online session will have to offer too (a view, own actions, public events).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fullDeck, legalCards, seededRng } from '../../../engine/src/index.ts';
import { createLocalSession } from '../src/session.js';

const make = (seed = 7, extra = {}) => createLocalSession({ seat: 0, target: 1000, rng: seededRng(seed), adviceRng: seededRng(seed + 1), ...extra });

/** Lets the robots act until the human has to (or the game ends). Returns every event seen. */
function robots(s) {
  const events = [];
  while (s.robotToAct()) events.push(...s.playRobot());
  return events;
}
/** The human passes every bid and plays the first legal card until `stop(view)`. */
function playUntil(s, stop) {
  for (let guard = 0; guard < 5000; guard++) {
    robots(s);
    const v = s.view();
    if (stop(v)) return v;
    if (v.phase === 'bidding') assert.ok(s.send({ kind: 'bid', bid: { type: 'pass' } }));
    else if (v.phase === 'playing') assert.ok(s.send({ kind: 'play', card: s.legalPlays()[0] }));
    else assert.ok(s.send({ kind: 'next' }));
  }
  throw new Error('never reached');
}
const myCardTurn = (v) => v.phase === 'playing' && v.turn === 0;

test('before a game there is no view, nothing to play, no advice', () => {
  const s = make();
  assert.equal(s.view(), null);
  assert.deepEqual(s.legalPlays(), []);
  assert.equal(s.adviseBid(), null);
  assert.equal(s.advisePlay(), null);
  assert.equal(s.robotToAct(), false);
});

test('start deals and reports it; the view is the human’s alone', () => {
  const s = make();
  const events = s.start();
  assert.deepEqual(
    events.map((e) => e.type),
    ['dealt'],
  );
  const v = s.view();
  assert.equal(v.seat, 0);
  assert.equal(v.hand.length, 8);
  assert.deepEqual(v.handSizes, [8, 8, 8, 8]);
  assert.ok(!('hands' in v));
});

test('whatever the session hands out over a whole game, it never shows a robot’s cards or reasons', () => {
  const s = make(3);
  const seen = [s.start()];
  const check = () => {
    const v = s.view();
    const text = JSON.stringify(v);
    assert.ok(!text.includes('"why"') && !('hands' in v));
    // the human's cards plus the cards on the table are all the view may name
    const known = new Set([...v.hand, ...v.tricks.flatMap((t) => t.plays.map((p) => p.card)), ...(v.trick?.plays ?? []).map((p) => p.card)]);
    for (const c of fullDeck()) if (!known.has(c)) assert.ok(!text.includes(`"${c}"`), `view names ${c}`);
  };
  for (let guard = 0; guard < 5000 && s.view().phase !== 'gameEnd'; guard++) {
    check();
    if (s.robotToAct()) seen.push(s.playRobot());
    else {
      const v = s.view();
      seen.push(
        s.send(
          v.phase === 'bidding'
            ? { kind: 'bid', bid: { type: 'pass' } }
            : v.phase === 'playing'
              ? { kind: 'play', card: s.legalPlays()[0] }
              : { kind: 'next' },
        ),
      );
    }
  }
  assert.equal(s.view().phase, 'gameEnd');
  assert.ok(!JSON.stringify(seen).includes('"why"'));
});

test('the view is refreshed after every move, and onChange is told', () => {
  const views = [];
  const s = make(7, { onChange: (v) => views.push(v) });
  s.start();
  assert.equal(views.at(-1), s.view());
  const before = s.view();
  assert.equal(s.view(), before, 'same object while nothing moves');
  robots(s);
  playUntil(s, (v) => v.turn === 0);
  assert.notEqual(s.view(), before);
  assert.equal(views.at(-1), s.view());
});

test('legalPlays: the engine’s rule on the human’s hand, and nothing when it is not their turn', () => {
  const s = make();
  s.start();
  assert.deepEqual(s.legalPlays(), [], 'bidding');
  const v = playUntil(s, myCardTurn);
  assert.deepEqual(s.legalPlays(), legalCards(v.hand, v.trick.plays, v.contract.suit, 0));
  assert.ok(s.legalPlays().length > 0);
});

test('send: a legal action returns its events, an illegal one returns null and changes nothing', () => {
  const s = make();
  s.start();
  const v = playUntil(s, myCardTurn);
  const illegal = fullDeck().find((c) => !v.hand.includes(c));
  assert.equal(s.send({ kind: 'play', card: illegal }), null);
  assert.equal(s.send({ kind: 'next' }), null);
  assert.equal(s.send({ kind: 'nonsense' }), null);
  assert.equal(s.view(), v);
  const card = s.legalPlays()[0];
  const events = s.send({ kind: 'play', card });
  assert.deepEqual(events[0], { type: 'played', seat: 0, card });
  assert.ok(!s.view().hand.includes(card));
});

test('playRobot acts for the seat to play, and refuses when it is the human’s turn', () => {
  const s = make();
  s.start();
  playUntil(s, (v) => v.turn === 0);
  assert.equal(s.robotToAct(), false);
  assert.throws(() => s.playRobot(), /no robot to act/);
});

test('level: the robots play at the level asked for at each card, so it can change mid-game', () => {
  const run = (level) => {
    const s = make(11, { level });
    s.start();
    playUntil(s, (v) => v.phase === 'roundEnd');
    return s.view().tricks.map((t) => t.plays.map((p) => p.card).join(' '));
  };
  const asked = [];
  const normal = run(() => (asked.push(1), 'normal'));
  assert.equal(asked.length, 24, 'asked once for each card of the three robots');
  assert.deepEqual(
    run(() => 'normal'),
    normal,
  );
  assert.deepEqual(run(undefined), normal, 'normal is the default');
  assert.notDeepEqual(
    run(() => 'easy'),
    normal,
  );
  assert.notDeepEqual(
    run(() => 'expert'),
    normal,
  );
});

test('reasons: one entry per bid, the robots’ only (solo), kept out of the view', () => {
  const s = make();
  s.start();
  const v = playUntil(s, (x) => x.bids.length >= 4);
  const reasons = s.reasons();
  assert.equal(reasons.length, v.bids.length);
  v.bids.forEach((b, i) => {
    if (b.seat === 0) assert.equal(reasons[i], undefined);
    else assert.equal(typeof reasons[i], 'string');
  });
});

test('advice never changes the game: same deals and same robot moves whether asked or not', () => {
  const play = (ask) => {
    const s = make(11);
    s.start();
    for (let i = 0; i < 40; i++) {
      robots(s);
      const v = s.view();
      if (v.phase === 'gameEnd') break;
      if (ask && v.phase === 'bidding') for (let k = 0; k < 3; k++) assert.ok(s.adviseBid());
      if (ask && v.phase === 'playing') for (let k = 0; k < 3; k++) assert.ok(s.advisePlay());
      if (v.phase === 'bidding') s.send({ kind: 'bid', bid: { type: 'pass' } });
      else if (v.phase === 'playing') s.send({ kind: 'play', card: s.legalPlays()[0] });
      else s.send({ kind: 'next' });
    }
    return s.snapshot();
  };
  assert.deepEqual(play(true), play(false));
});

test('advice: a legal bid with its reason while bidding, a legal card with a reason code while playing', () => {
  const s = make();
  s.start();
  playUntil(s, (v) => v.turn === 0);
  const bid = s.adviseBid();
  assert.ok(['pass', 'bid', 'contre', 'surcontre'].includes(bid.bid.type));
  assert.equal(typeof bid.why, 'string');
  assert.equal(s.advisePlay(), null, 'no card to advise while bidding');
  playUntil(s, myCardTurn);
  assert.equal(s.adviseBid(), null, 'no bid to advise while playing');
  const tip = s.advisePlay();
  assert.ok(s.legalPlays().includes(tip.card));
  assert.ok(['master', 'lead', 'feedPartner', 'keepStrong', 'ruff', 'win', 'discard'].includes(tip.reason));
  // while a robot is to play there is nothing to advise
  s.send({ kind: 'play', card: tip.card });
  if (s.robotToAct()) assert.equal(s.advisePlay(), null);
});

test('snapshot and restore: an action can be taken back exactly', () => {
  const s = make();
  s.start();
  const before = playUntil(s, myCardTurn);
  const snap = s.snapshot();
  s.send({ kind: 'play', card: s.legalPlays()[0] });
  robots(s);
  assert.notDeepEqual(s.view(), before);
  s.restore(snap);
  assert.deepEqual(s.view(), before);
  // the snapshot is a copy: playing on does not alter it
  s.send({ kind: 'play', card: s.legalPlays()[0] });
  s.restore(snap);
  assert.deepEqual(s.view(), before);
});

test('record: nothing until a deal is over, then the deal as the table saw it, and nothing else', () => {
  const s = make();
  assert.equal(s.record(), null);
  s.start();
  assert.equal(s.record(), null);
  playUntil(s, myCardTurn);
  assert.equal(s.record(), null);
  const over = playUntil(s, (v) => v.phase === 'roundEnd');
  const record = s.record();
  assert.deepEqual(Object.keys(record), ['scoring', 'dealer', 'bids', 'tricks']);
  assert.deepEqual([record.scoring, record.dealer], [over.scoring, over.dealer]);
  // the auction as heard: who said what, never why
  assert.deepEqual(record.bids, over.bids);
  assert.ok(record.bids.length >= 4 && record.bids.every((bid) => !('why' in bid)));
  // eight tricks of four plays, the thirty-two cards once each, in the order they fell
  assert.deepEqual(
    record.tricks,
    over.tricks.map((trick) => trick.plays),
  );
  assert.deepEqual(
    record.tricks.flat().map((p) => Object.keys(p)),
    Array(32).fill(['seat', 'card']),
  );
  assert.deepEqual(record.tricks.flatMap((plays) => plays.map((p) => p.card)).sort(), [...fullDeck()].sort());
  // a copy: what is done to it does not reach the game
  record.tricks[0][0].card = 'XX';
  assert.notEqual(s.record().tricks[0][0].card, 'XX');
  // the next deal has no record yet
  s.send({ kind: 'next' });
  assert.equal(s.record(), null);
});
