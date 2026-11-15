// The game's emblem: the eight-pointed star of Tunisian tilework, one point per trick of a deal.
// Pure geometry and markup, no DOM: the screen draws it (logo, tricks, kabbout) and so does
// tools/brand.mjs (icons, card back, share image).
import { esc } from './html.js';

const OUTER = 46;
/** where the two crossed squares of the star meet */
const INNER = (OUTER * Math.cos(Math.PI / 4)) / Math.cos(Math.PI / 8);
const at = (r, deg) => {
  const a = ((deg - 90) * Math.PI) / 180;
  return `${(r * Math.cos(a)).toFixed(2)},${(r * Math.sin(a)).toFixed(2)}`;
};

/** the eight points, clockwise from the top, each a triangle */
export const POINTS = Array.from({ length: 8 }, (_, k) => `${at(INNER, k * 45 - 22.5)} ${at(OUTER, k * 45)} ${at(INNER, k * 45 + 22.5)}`);
/** the octagon the points stand on */
export const CORE = Array.from({ length: 8 }, (_, k) => at(INNER, k * 45 + 22.5)).join(' ');
/** a spade that fits the core, centred on the origin */
export const SPADE =
  'M0-19c-8 9.300-16 14-16 22a8.650 8.650 0 0 0 14.650 6.250c-.530 4.800-2.260 8-4.800 10.400h12.300c-2.540-2.400-4.270-5.600-4.800-10.400A8.650 8.650 0 0 0 16 3C16-5 8-9.700 0-19z';

/**
 * @param {object} [o]
 * @param {string[]} [o.points] a class for each of the eight points ('us', 'them', 'on' or '')
 * @param {boolean} [o.spade] the spade in the middle
 * @param {string} [o.cls]
 * @param {string} [o.label] what it says to a screen reader; decorative when absent
 */
export function starSVG({ points = [], spade = true, cls = '', label = '' } = {}) {
  const a11y = label ? `role="img" aria-label="${esc(label)}"` : 'aria-hidden="true"';
  return `<svg class="star ${cls}" viewBox="-50 -50 100 100" ${a11y}>${POINTS.map((p, k) => `<polygon class="pt ${points[k] ?? ''}" points="${p}"/>`).join('')}<polygon class="core" points="${CORE}"/>${spade ? `<path class="sp" d="${SPADE}"/>` : ''}</svg>`;
}
