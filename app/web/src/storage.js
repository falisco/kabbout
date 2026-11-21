// Local storage: everything read back is treated as data of unknown shape.

// A browser may refuse storage (private mode, quota): the game then plays on without remembering.

export function saveJSON(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {}
}
export function loadJSON(key) {
  try {
    return JSON.parse(localStorage.getItem(key) || 'null');
  } catch {
    return null;
  }
}
export function removeKey(key) {
  try {
    localStorage.removeItem(key);
  } catch {}
}
export const isObj = (value) => !!value && typeof value === 'object' && !Array.isArray(value);
export function loadObj(key) {
  const value = loadJSON(key);
  return isObj(value) ? value : null;
}

export const KEYS = { stats: 'kabbout.stats', prefs: 'kabbout.prefs', tutorial: 'kabbout.tuto', conventions: 'kabbout.conventions' };
/** what the game stored under its former name */
const OLD = { stats: 'contree.stats', prefs: 'contree.prefs', tutorial: 'contree.tuto' };
export const OLD_KEYS = { save: 'contree.save', sound: 'contree.sound' };

/** Brings over, once, what was stored before the game was called Kabbout. */
export function migrateStorage() {
  try {
    for (const name of Object.keys(OLD)) {
      const stored = localStorage.getItem(OLD[name]);
      if (stored === null) continue;
      if (localStorage.getItem(KEYS[name]) === null) localStorage.setItem(KEYS[name], stored);
      localStorage.removeItem(OLD[name]);
    }
  } catch {}
}

const isCount = (value) => Number.isSafeInteger(value) && value >= 0;
/** games played and won; a record that cannot be true counts as none */
export function loadStats() {
  const stored = loadObj(KEYS.stats);
  const plausible = stored && isCount(stored.played) && isCount(stored.won) && stored.won <= stored.played;
  return plausible ? { played: stored.played, won: stored.won } : { played: 0, won: 0 };
}
export function recordGame(won) {
  const stats = loadStats();
  saveJSON(KEYS.stats, { played: stats.played + 1, won: stats.won + (won ? 1 : 0) });
}
