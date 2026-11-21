// The conventions card: what two partners agreed their bids mean. The robots bid by one, and the
// classic card holds the values they have always used, so a robot given no card bids as before.
//
// A card is a closed set of settings, each with a short list of values it may take. It says what a
// hand must be worth to open, what the partner adds for each thing it holds in the suit named, and
// when it shows its outside aces: with its first raise, or only once the opponents have bid over
// the pair (a second round). It says nothing of the play of the cards.

import type { Lang } from './phrases.ts';
import { PHRASES } from './phrases.ts';

/** the values each setting may take; the first of each list is not special, CLASSIC says what the default is */
export const CONVENTION_VALUES = {
  /** what the best suit of a hand must be worth for it to open the auction (it opens at 80 at least) */
  open: [70, 80, 90],
  /** what the partner adds for the jack of the suit named */
  jack: [0, 10, 20, 30],
  /** for the nine */
  nine: [0, 10, 20],
  /** for holding three trumps or more */
  threeTrumps: [0, 10, 20],
  /** for the king and queen of trumps */
  belote: [0, 10, 20, 30],
  /** for each ace outside the trump suit */
  ace: [0, 10, 20],
  /** when the outside aces are shown: with the first raise, or only when the opponents bid over the pair */
  aces: ['atOnce', 'whenOverbid'],
} as const;

export type ConventionSetting = keyof typeof CONVENTION_VALUES;
export type Conventions = { readonly [S in ConventionSetting]: (typeof CONVENTION_VALUES)[S][number] };
export const CONVENTION_SETTINGS = Object.keys(CONVENTION_VALUES) as ConventionSetting[];

/** How the robots have always bid. */
export const CLASSIC: Conventions = { open: 70, jack: 20, nine: 10, threeTrumps: 10, belote: 20, ace: 10, aces: 'atOnce' };
/** Opens on a full hand only, and does not raise on length alone. */
export const CAUTIOUS: Conventions = { open: 80, jack: 20, nine: 10, threeTrumps: 0, belote: 20, ace: 10, aces: 'atOnce' };
/** Raises more on the honours, and keeps its aces for when the opponents come in. */
export const BOLD: Conventions = { open: 70, jack: 30, nine: 20, threeTrumps: 10, belote: 20, ace: 10, aces: 'whenOverbid' };

export const READY_CONVENTIONS = { classic: CLASSIC, cautious: CAUTIOUS, bold: BOLD } as const;
export type ReadyConventions = keyof typeof READY_CONVENTIONS;
export const isReadyConventions = (v: unknown): v is ReadyConventions => typeof v === 'string' && Object.hasOwn(READY_CONVENTIONS, v);

export const sameConventions = (a: Conventions, b: Conventions): boolean => CONVENTION_SETTINGS.every((setting) => a[setting] === b[setting]);

/** The card `value` holds, or null when it is not one: a setting missing, one too many, a value the card does not allow. */
export function readConventions(value: unknown): Conventions | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
  const given = value as Record<string, unknown>;
  if (Object.keys(given).length !== CONVENTION_SETTINGS.length) return null;
  const allowed = (setting: ConventionSetting) => (CONVENTION_VALUES[setting] as readonly unknown[]).includes(given[setting]);
  return CONVENTION_SETTINGS.every(allowed)
    ? (Object.fromEntries(CONVENTION_SETTINGS.map((setting) => [setting, given[setting]])) as Conventions)
    : null;
}

/**
 * The card read back: one line for each setting that is not the classic card's, and nothing else.
 * The classic card reads back as no line at all. Written from the card alone, so it cannot promise
 * what the card does not hold.
 */
export function describeConventions(card: Conventions, lang: Lang): string[] {
  const said = PHRASES[lang].conventions;
  const lines: string[] = [];
  if (card.open !== CLASSIC.open) lines.push(said.open(card.open));
  for (const setting of ['jack', 'nine', 'threeTrumps', 'belote', 'ace'] as const) {
    if (card[setting] !== CLASSIC[setting]) lines.push(card[setting] ? said.adds(said[setting], card[setting]) : said.addsNothing(said[setting]));
  }
  if (card.aces !== CLASSIC.aces) lines.push(said[card.aces]);
  return lines;
}
