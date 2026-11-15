// Measures the robots: `npm run bench:robots -- [deals] [--scoring tunisian]`.
// Matches between levels are played in duplicate (each deal twice, the sides swapped), which
// removes most of the luck of the deal; then the auction's statistics with four experts.

import type { GameState, Level, Scoring } from '../engine/src/index.ts';
import { LEVELS, aiBid, aiPlay, apply, isScoring, newGame, seededRng, team, viewFor } from '../engine/src/index.ts';

const args = process.argv.slice(2);
const asked = args.includes('--scoring') ? args[args.indexOf('--scoring') + 1] : 'ffb';
if (!isScoring(asked)) throw new Error(`unknown scoring ${asked}`);
const scoring: Scoring = asked;

function deal(seed: number, levels: Level[]): GameState | null {
  const rng = seededRng(seed);
  const cards = seededRng(seed + 7919);
  let s = newGame(rng, { scoring }).state;
  while (s.round === 1 && s.phase !== 'roundEnd') {
    const v = viewFor(s, s.turn);
    s = apply(s, s.turn, s.phase === 'bidding' ? { kind: 'bid', ...aiBid(v) } : { kind: 'play', card: aiPlay(v, cards, levels[s.turn]) }, rng).state;
  }
  return s.round === 1 ? s : null;
}
/** points team 0 took from team 1 in a played deal; 0 when everybody passed */
const diff = (s: GameState | null) => (s?.result ? s.result.gained[0] - s.result.gained[1] : 0);

const deals = Number(args.find((a, i) => /^\d+$/.test(a) && args[i - 1] !== '--scoring')) || 300;
console.log(`Duplicate matches, ${deals} deals each, ${scoring} scoring (points per deal, ± standard error):`);
for (const [a, b] of [
  ['normal', 'easy'],
  ['expert', 'normal'],
  ['expert', 'easy'],
] as [Level, Level][]) {
  const d: number[] = [];
  for (let seed = 1; seed <= deals; seed++) d.push((diff(deal(seed, [a, b, a, b])) - diff(deal(seed, [b, a, b, a]))) / 2);
  const mean = d.reduce((x, y) => x + y, 0) / deals;
  const se = Math.sqrt(d.reduce((x, y) => x + (y - mean) ** 2, 0) / (deals - 1) / deals);
  console.log(`  ${a} vs ${b}: ${mean >= 0 ? '+' : ''}${mean.toFixed(1)} ± ${se.toFixed(1)}`);
}

const count: Record<string, [number, number]> = {};
const add = (k: string, made: boolean) => {
  const c = (count[k] ??= [0, 0]);
  c[0]++;
  if (made) c[1]++;
};
let passed = 0;
for (let seed = 1; seed <= deals; seed++) {
  const s = deal(seed, [LEVELS[2], LEVELS[2], LEVELS[2], LEVELS[2]]);
  const r = s?.result;
  if (!r) {
    passed++;
    continue;
  }
  add('contracts', r.made);
  add(r.contract.mult === 1 ? '  plain' : r.contract.mult === 2 ? '  contré' : '  surcontré', r.made);
  if (r.capotTeam === team(r.contract.seat)) add('  all eight tricks', true);
}
console.log(`Auction, four experts, ${deals} deals: ${passed} passed out`);
for (const [k, [n, made]] of Object.entries(count)) console.log(`  ${k}: ${n}, made ${Math.round((100 * made) / n)} %`);
