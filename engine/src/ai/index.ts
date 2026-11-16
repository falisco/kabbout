// Robot players. They only receive a PlayerView, so they cannot see anyone else's cards.
// All levels bid alike (a partner must be able to rely on the conventions); they differ in how
// they play the cards.

import type { Card } from '../cards.ts';
import type { Rng } from '../rng.ts';
import type { PlayerView } from '../types.ts';
import { recall, situation } from './memory.ts';
import { chooseCard } from './policy.ts';
import { bestOf, cardAverages, searchCard } from './search.ts';

export { aiBid, evaluateContre, evaluateSuit, evaluateSupport, explain } from './bidding.ts';
export type { AiBid, Evaluation, Reason } from './bidding.ts';
export { LANGS, isLang } from './phrases.ts';
export type { Lang } from './phrases.ts';
export { isMaster, playedCards, recall } from './memory.ts';
export type { Memory } from './memory.ts';

/**
 * easy: plays by rules of thumb and forgets the tricks already played.
 * normal: same rules, remembering every card played and what it reveals of the other hands.
 * expert: also plays the rest of the deal out in its head before choosing (see search.ts).
 */
export const LEVELS = ['easy', 'normal', 'expert'] as const;
export type Level = (typeof LEVELS)[number];
export const isLevel = (v: unknown): v is Level => (LEVELS as readonly unknown[]).includes(v);

/** deals the expert imagines for each card it plays */
const EXPERT_SAMPLES = 50;

/** what each level does before choosing a card */
const SKILLS: Record<Level, { remembersTricks: boolean; imaginesDeals: boolean }> = {
  easy: { remembersTricks: false, imaginesDeals: false },
  normal: { remembersTricks: true, imaginesDeals: false },
  expert: { remembersTricks: true, imaginesDeals: true },
};

/** `outOfTime` only matters to the expert: see searchCard. */
export function aiPlay(view: PlayerView, rng: Rng, level: Level = 'normal', outOfTime?: () => boolean): Card {
  const skill = SKILLS[level];
  const memory = recall(view, skill.remembersTricks);
  return skill.imaginesDeals ? searchCard(view, memory, rng, EXPERT_SAMPLES, outOfTime) : chooseCard(situation(view, memory));
}

/** What the expert thinks of one legal card: its average over the deals imagined, in score-sheet points for the player's team minus the other's. */
export interface CardAverage {
  card: Card;
  average: number;
}

export interface ExpertAverages {
  /** the card `aiPlay` returns at the expert level from the same view and the same rng */
  choice: Card;
  /** every legal card, the expert's choice included, in the order the expert considered them */
  cards: CardAverage[];
  /** deals imagined; 0 when one card only was legal */
  worlds: number;
}

/** The expert's choice with what it rests on. Draws from `rng` exactly what `aiPlay(view, rng, 'expert')` draws. */
export function expertAverages(view: PlayerView, rng: Rng): ExpertAverages {
  const found = cardAverages(view, recall(view, SKILLS.expert.remembersTricks), rng, EXPERT_SAMPLES);
  const cards = found.candidates.map((card, i) => ({ card, average: found.worlds > 0 ? found.totals[i] / found.worlds : 0 }));
  return { choice: found.candidates[bestOf(found)], cards, worlds: found.worlds };
}
