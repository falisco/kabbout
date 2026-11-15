// Who sits where and the table's rhythm.
import { SEAT_COUNT, opponentOf, partnerOf, seededRng, team } from '../../../engine/src/index.ts';
import { t } from './i18n/index.js';

/** the seat this screen plays, its partner and the two teams as it names them */
export const ME = 0;
export const PARTNER = partnerOf(ME);
export const US = team(ME);
export const THEM = opponentOf(US);
/** @returns {'us' | 'them'} the side a seat plays for, as this screen names it */
export const sideOf = (seat) => (team(seat) === US ? 'us' : 'them');

/** how many seats after this screen's player a seat is, in the order of play: 0 for the player, 2 for the partner */
export const placeOf = (seat) => (seat - ME + SEAT_COUNT) % SEAT_COUNT;
/** "Vous" / "You" for this screen's player; the three regulars of the café have their names in each language */
export const nameOf = (seat) => (seat === ME ? t('you') : t('robots')[placeOf(seat)]);
/** the same inside a sentence: "vous" / "you" */
export const who = (seat) => (seat === ME ? t('youLower') : nameOf(seat));
/** this screen's player has no portrait: the first letter of "Vous" / "You" stands for them */
export const myInitial = () => t('you')[0];
const POSITIONS = ['s', 'e', 'n', 'w'];
/** where a seat is drawn: this screen's player at the bottom, the others counter-clockwise */
export const posOf = (seat) => POSITIONS[placeOf(seat)];

/** the quick phrases, by code (their words and the answers are in i18n) */
export const PHRASES = ['hello', 'wellPlayed', 'thanks', 'sorry', 'tea', 'bye'];
export const TARGET = 1000;
export const UNDO_MS = 1500;
/** longest a robot thinks about one card (ms): past it, the expert settles for what it has explored */
export const THINK_MS = 120;
/** pauses (ms) that give the table its rhythm */
export const PACE = { deal: 900, redeal: 1700, bid: 850, play: 700, forced: 650, show: 650, collect: 950, kabbout: 3200 };
/** how long a robot takes to do anything when the tests ask for speed (ms) */
export const FAST_PACE = 5;
/** how long a message stays on screen (ms) */
export const TOAST_MS = { short: 2000, advice: 4500 };
export const BUBBLE_MS = { announcement: 1800, phrase: 2000, reply: 2200 };
/** how long a robot takes to answer a quick phrase (ms) */
export const REPLY_DELAY_MS = 900;
/** how often the partner, rather than an opponent, answers a quick phrase */
export const PARTNER_REPLY_SHARE = 0.6;

// Test hooks: ?seed=N makes deals and robot choices reproducible, ?fast makes robots instant.
// (No address under Node, where the unit tests import this module.)
const QUERY = new URLSearchParams(globalThis.location?.search ?? '');
const SEED = QUERY.has('seed') ? +QUERY.get('seed') || 0 : null;
const randomSource = (offset) => (SEED === null ? Math.random : seededRng(SEED + offset));
export const FAST = QUERY.has('fast');
/** the game's random source: deals and robots */
export const gameRng = randomSource(0);
/** separate sources for advice and for small talk, so that asking for a tip or saying hello never changes the game */
export const adviceRng = randomSource(1);
export const chatRng = randomSource(2);
