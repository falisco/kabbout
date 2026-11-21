// The conventions card as the two sides of the bridge must both read it. The engine says what a card
// is (engine/src/ai/conventions.ts); the PHP application refuses a card before it ever reaches the
// bridge (Kabbout\Game\Domain\ConventionCard) and so holds the same lists. This file is what keeps the
// two from drifting: it is written from the engine, checked against it by the bridge's tests, and
// replayed by the PHP tests.

import { CLASSIC, CONVENTION_VALUES, READY_CONVENTIONS } from '../../engine/src/index.ts';

export interface ConventionsContract {
  /** each setting with the values it may take */
  values: typeof CONVENTION_VALUES;
  /** the cards ready to pick, by name */
  ready: typeof READY_CONVENTIONS;
  /** what is not a card, each with why */
  refused: { why: string; card: unknown }[];
}

export function buildConventions(): ConventionsContract {
  const { aces: _aces, ...short } = CLASSIC;
  return {
    values: CONVENTION_VALUES,
    ready: READY_CONVENTIONS,
    refused: [
      { why: 'a value between two the card allows', card: { ...CLASSIC, jack: 15 } },
      { why: 'a value beyond the last', card: { ...CLASSIC, jack: 40 } },
      { why: 'a negative value', card: { ...CLASSIC, ace: -10 } },
      { why: 'an opening below the first', card: { ...CLASSIC, open: 60 } },
      { why: 'a number written as text', card: { ...CLASSIC, nine: '10' } },
      { why: 'a number that is not whole', card: { ...CLASSIC, nine: 10.5 } },
      { why: 'a value that is no value', card: { ...CLASSIC, belote: null } },
      { why: 'a way to show aces the card does not know', card: { ...CLASSIC, aces: 'never' } },
      { why: 'a setting missing', card: short },
      { why: 'a setting the card does not have', card: { ...CLASSIC, contre: 20 } },
      { why: 'the name of a ready card', card: 'classic' },
      { why: 'a list', card: [CLASSIC] },
      { why: 'nothing', card: null },
    ],
  };
}

export const serializeConventions = (contract: ConventionsContract): string => JSON.stringify(contract, null, 1) + '\n';
