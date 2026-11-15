// End-of-round scoring. What the cards are worth and whether the contract is made do not depend
// on the way of counting; what each side then writes down does:
//
// - ffb: the Fédération Française de Belote "Règles officielles de belote contrée" (27.01.2016),
//   art. 9 and 10, counting method "points faits + points demandés".
//   engine/test/ffb-contree.test.ts pins every cell of the federation's score table.
// - tunisian: as counted in Tunisian cafés, where the value bid is never added to the score.
//   engine/test/tunisian-scoring.test.ts pins its table.

import { opponentOf, team } from './cards.ts';
import type { Team } from './cards.ts';
import { trickPoints } from './rules.ts';
import type { BeloteHolder, CompletedTrick, Contract, RoundResult, RoundRows, Scoring } from './types.ts';
import { CAPOT } from './types.ts';
import { EngineError } from './errors.ts';

type Pair = [number, number];

/** art. 9.1: dix de der */
const LAST_TRICK_BONUS = 10;
/** art. 9.2: when one team takes the eight tricks the last one is worth 100, 252 in all */
const CAPOT_LAST_TRICK_BONUS = 100;
export const BELOTE_POINTS = 20;
/** art. 10.2: a failed contract gives the defence 160 + the contract */
const FAILED_BASE = 160;
/**
 * art. 10.2, table: a contré contract scores 320 + contract × 2, a surcontré one 640 + contract × 4.
 * (Note 1 lets an organiser count + 160 in both cases instead; that variant is not used.)
 */
const contreBase = (mult: 2 | 4): number => FAILED_BASE * mult;
/** art. 10.2: a capot asked for is worth 500 to whoever scores it (× 2 contré, × 4 surcontré) */
const CAPOT_SCORE = 500;
/** tunisian: the eight tricks taken without having bid them */
const UNBID_CAPOT_SCORE = 250;

/** art. 10.3: rounded to the nearest ten, 85 → 90 and 84 → 80 */
export const roundToTen = (points: number): number => Math.round(points / 10) * 10;

/** What the finished tricks hold for each team: tricks won, and card points before the last-trick bonus. */
export function tallyTricks(tricks: readonly CompletedTrick[], trump: Contract['suit']): { won: Pair; points: Pair } {
  const won: Pair = [0, 0];
  const points: Pair = [0, 0];
  for (const trick of tricks) {
    const winners = team(trick.winner);
    won[winners]++;
    points[winners] += trickPoints(trick.plays, trump);
  }
  return { won, points };
}

export function scoreRound(
  contract: Contract,
  tricks: readonly CompletedTrick[],
  belote: BeloteHolder | null,
  scoring: Scoring = 'ffb',
): RoundResult {
  const lastTrick = tricks[tricks.length - 1];
  if (!lastTrick) throw new EngineError('a round without a trick cannot be scored');
  const { won, points } = tallyTricks(tricks, contract.suit);
  // art. 7: announced by playing both cards
  const announcedBy = belote && belote.played >= 2 ? team(belote.seat) : null;
  return scoreTotals(contract, points, won, team(lastTrick.winner), announcedBy, scoring);
}

/**
 * The same result from totals: card points per team (before the last-trick bonus), tricks won,
 * the team that took the last trick and the team that announced the belote.
 */
export function scoreTotals(
  contract: Contract,
  points: readonly [number, number],
  won: readonly [number, number],
  lastTrick: Team,
  belote: Team | null,
  scoring: Scoring = 'ffb',
): RoundResult {
  const takers = team(contract.seat);
  const defence = opponentOf(takers);

  const capotTeam: Team | null = won[defence] === 0 ? takers : won[takers] === 0 ? defence : null;
  const cardPoints: Pair = [points[0], points[1]];
  cardPoints[lastTrick] += capotTeam === null ? LAST_TRICK_BONUS : CAPOT_LAST_TRICK_BONUS;

  const belotePoints: Pair = [0, 0];
  if (belote !== null) belotePoints[belote] = BELOTE_POINTS;

  // art. 10.1: tricks + dix de der + belote; made when the takers reach their bid,
  // "même si les défenseurs ont réalisé plus de points que les preneurs"
  const takerPoints = cardPoints[takers] + belotePoints[takers];
  const defencePoints = cardPoints[defence] + belotePoints[defence];
  const made = contract.value === CAPOT ? capotTeam === takers : takerPoints >= contract.value;

  const rows = sheetRows(COUNTS[scoring], { contract, cardPoints, belotePoints, made, capotTeam, takers, defence });
  const gained: Pair = [rows.tricks[0] + rows.belote[0] + rows.contract[0], rows.tricks[1] + rows.belote[1] + rows.contract[1]];

  return { contract, made, takerPoints, defencePoints, cardPoints, tricksWon: [won[0], won[1]], capotTeam, rows, gained };
}

/** A played deal, as the score sheet needs it. */
interface Outcome {
  contract: Contract;
  cardPoints: Readonly<Pair>;
  belotePoints: Readonly<Pair>;
  made: boolean;
  capotTeam: Team | null;
  takers: Team;
  defence: Team;
}

/** A way of counting: what goes in the tricks and contract rows. The belote row is the same for all. */
interface Count {
  /** contract made and not contré: each side scores for itself */
  shared(rows: RoundRows, outcome: Outcome): void;
  /** contract failed, or made while contré: `scorer` alone scores */
  oneSided(rows: RoundRows, outcome: Outcome, scorer: Team): void;
}

function sheetRows(count: Count, outcome: Outcome): RoundRows {
  const { contract, belotePoints, made, takers, defence } = outcome;
  const rows: RoundRows = { tricks: [0, 0], belote: [0, 0], contract: [0, 0] };
  if (made && contract.mult === 1) {
    // each side keeps its own belote
    rows.belote = [belotePoints[0], belotePoints[1]];
    count.shared(rows, outcome);
  } else {
    // one side scores everything, the belote included whoever held it
    const scorer = made ? takers : defence;
    rows.belote[scorer] = belotePoints[0] + belotePoints[1];
    count.oneSided(rows, outcome, scorer);
  }
  return rows;
}

const roundedCardPoints = ({ cardPoints }: Outcome): Pair => [roundToTen(cardPoints[0]), roundToTen(cardPoints[1])];

/** art. 10: what was made, plus what was bid */
const ffb: Count = {
  shared(rows, outcome) {
    const { contract, takers } = outcome;
    if (contract.value === CAPOT) rows.contract[takers] = CAPOT_SCORE;
    else {
      rows.tricks = roundedCardPoints(outcome);
      rows.contract[takers] = contract.value;
    }
  },
  oneSided(rows, { contract }, scorer) {
    const { mult } = contract;
    if (contract.value === CAPOT) rows.contract[scorer] = CAPOT_SCORE * mult;
    else {
      rows.tricks[scorer] = mult === 1 ? FAILED_BASE : contreBase(mult);
      rows.contract[scorer] = contract.value * mult;
    }
  },
};

/**
 * The value bid only decides who scores, it is never added: a made contract leaves each side what
 * it made, a failed one gives the defence 160, a contre 320 and a surcontre 640 to the side that
 * wins the deal. The eight tricks are worth 500 when they were bid, 250 otherwise. Nothing goes in
 * the contract row.
 */
const tunisian: Count = {
  shared(rows, outcome) {
    const { contract, capotTeam, takers } = outcome;
    if (contract.value === CAPOT) rows.tricks[takers] = CAPOT_SCORE;
    else if (capotTeam === takers) rows.tricks[takers] = UNBID_CAPOT_SCORE;
    else rows.tricks = roundedCardPoints(outcome);
  },
  oneSided(rows, { contract, capotTeam, defence }, scorer) {
    const { mult } = contract;
    if (contract.value === CAPOT) rows.tricks[scorer] = CAPOT_SCORE * mult;
    else if (mult !== 1) rows.tricks[scorer] = contreBase(mult);
    else rows.tricks[scorer] = capotTeam === defence ? UNBID_CAPOT_SCORE : FAILED_BASE;
  },
};

const COUNTS: Record<Scoring, Count> = { ffb, tunisian };
