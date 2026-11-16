// Builds the coach's evaluation set: `npm run eval:coach:build` writes eval/coach/01.json to 15.json.
// Each deal is played by the expert robots, except for one card of seat 0: in one trick it plays the
// card the expert likes least instead of the one it would have played. That is the mistake the coach is
// expected to name. A deal is kept only when the engine's own analysis puts that card first among the
// swings, well ahead of any other: the expected answer is the engine's, not an opinion.
// Everything comes from seeds, so the same command always writes the same files.

import { mkdirSync, writeFileSync } from 'node:fs';
import type { Card, GameState, Scoring, Seat } from '../engine/src/index.ts';
import { aiBid, aiPlay, apply, newGame, seededRng, viewFor } from '../engine/src/index.ts';
import { recordOf } from '../bridge/contract/build.ts';
import type { Swings } from '../bridge/src/analysis.ts';
import { expertChoiceFrom, seatView } from '../bridge/src/analysis.ts';
import { dispatch } from '../bridge/src/server.ts';

export const DEALS = 15;
/** the player of the page */
const PLAYER: Seat = 0;
/** what the planted card must cost on average to count as a clear mistake, in score-sheet points */
const MIN_LOSS = 40;
/** how far ahead of the next swing it must be */
const MIN_LEAD = 20;
const LANGS = ['fr', 'en', 'ar'] as const;
const QUESTIONS = { fr: 'Qu’est-ce que j’ai mal joué ?', en: 'What did I do wrong?', ar: 'شنوّة لعبت غالط؟' };

export interface EvalDeal {
  name: string;
  lang: (typeof LANGS)[number];
  seat: Seat;
  question: string;
  /** the mistake the coach should name: the trick, the card played, and what the engine says of it */
  expected: { trick: number; played: Card; choice: Card; loss: number };
  record: ReturnType<typeof recordOf>;
}

/** One deal from `seed`, the mistake planted in `trick`; null when the seed gives no clear one there. */
function deal(seed: number, trick: number, scoring: Scoring): { state: GameState; planted: Card } | null {
  const rng = seededRng(seed);
  const robots = seededRng(seed + 7919);
  let state = newGame(rng, { scoring, dealer: (seed % 4) as Seat }).state;
  let planted: Card | null = null;
  while (state.round === 1 && state.phase !== 'roundEnd') {
    const seat = state.turn;
    const view = viewFor(state, seat);
    if (state.phase === 'bidding') {
      state = apply(state, seat, { kind: 'bid', bid: aiBid(view).bid }, rng).state;
      continue;
    }
    let card = aiPlay(view, robots, 'expert');
    if (seat === PLAYER) {
      const number = state.tricks.length + 1;
      // the player plays what the analysis will call the expert's card, so that only the planted card is a swing
      const found = expertChoiceFrom(seatView(state, PLAYER), number, view.hand[0]);
      card = found.choice;
      if (number === trick) {
        const worst = found.cards.reduce((a, b) => (b.average < a.average ? b : a));
        const best = found.cards.find((c) => c.card === found.choice);
        if (!best || best.average - worst.average < MIN_LOSS) return null;
        card = planted = worst.card;
      }
    }
    state = apply(state, seat, { kind: 'play', card }, rng).state;
  }
  return state.round === 1 && planted ? { state, planted } : null;
}

/** The set: the first seeds that give a clear mistake, the trick, the count and the language going round. */
export function buildDeals(): EvalDeal[] {
  const deals: EvalDeal[] = [];
  for (let seed = 1; deals.length < DEALS; seed++) {
    const slot = deals.length;
    const trick = (slot % 6) + 1;
    const scoring: Scoring = slot % 5 === 4 ? 'tunisian' : 'ffb';
    const played = deal(seed, trick, scoring);
    if (!played) continue;
    const record = recordOf(played.state);
    const { status, body } = dispatch('/analysis/swings', { record, seat: PLAYER });
    if (status !== 200) throw new Error(`deal ${seed} is not replayed: ${JSON.stringify(body)}`);
    const [first, second] = (body as Swings).swings;
    if (!first || first.trick !== trick || first.played !== played.planted || (second && first.loss - second.loss < MIN_LEAD)) continue;
    const lang = LANGS[slot % LANGS.length];
    deals.push({
      name: `deal ${seed}, ${scoring}: ${first.played} for ${first.choice} in trick ${trick}`,
      lang,
      seat: PLAYER,
      question: QUESTIONS[lang],
      expected: { trick: first.trick, played: first.played, choice: first.choice, loss: first.loss },
      record,
    });
  }
  return deals;
}

export const fileOf = (index: number): string => `${String(index + 1).padStart(2, '0')}.json`;
export const serialize = (one: EvalDeal): string => `${JSON.stringify(one, null, 1)}\n`;

if (import.meta.main) {
  const dir = new URL('./coach/', import.meta.url);
  mkdirSync(dir, { recursive: true });
  const deals = buildDeals();
  deals.forEach((one, index) => writeFileSync(new URL(fileOf(index), dir), serialize(one)));
  for (const one of deals) console.log(`${one.lang}  ${one.name}  (loss ${one.expected.loss})`);
  console.log(`${deals.length} deals written to eval/coach/.`);
}
