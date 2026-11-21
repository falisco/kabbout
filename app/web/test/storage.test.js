// What the game keeps between visits: everything read back is of unknown shape, and a browser may refuse to store at all.
import { beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { KEYS, loadJSON, loadObj, loadStats, migrateStorage, recordGame, removeKey, saveJSON } from '../src/storage.js';
import { BOLD, CLASSIC } from '../../../engine/src/index.ts';
import {
  coachAnswer,
  loadConventions,
  loadPrefs,
  markTutorialStep,
  partnership,
  prefs,
  resetTutorial,
  saveConventions,
  savePrefs,
  tutorial,
} from '../src/store.js';

/** a browser's localStorage, reduced to what the game uses */
function fakeStorage(entries = {}) {
  const items = new Map(Object.entries(entries));
  return {
    getItem: (key) => (items.has(key) ? items.get(key) : null),
    setItem: (key, value) => void items.set(key, String(value)),
    removeItem: (key) => void items.delete(key),
    keys: () => [...items.keys()].sort(),
  };
}
/** @param {object} storage what stands in for the browser's storage during a test */
const useStorage = (storage) => {
  globalThis.localStorage = /** @type {any} */ (storage);
  return storage;
};
const refusing = () => {
  throw new Error('storage is disabled');
};
const DEFAULTS = structuredClone(prefs);

/** back to a first visit: default settings, tutorial not started */
function resetSettings() {
  Object.assign(prefs, DEFAULTS);
  Object.assign(tutorial, { done: {}, off: false });
  Object.assign(partnership, { card: CLASSIC, agreed: false });
}

beforeEach(() => {
  useStorage(fakeStorage());
  resetSettings();
});

test('what is saved is read back; anything unreadable reads as nothing', () => {
  saveJSON('k', { a: 1 });
  assert.deepEqual(loadJSON('k'), { a: 1 });
  assert.equal(loadJSON('missing'), null);
  localStorage.setItem('broken', '{not json');
  assert.equal(loadJSON('broken'), null);
  localStorage.setItem('list', '[1,2]');
  assert.equal(loadObj('list'), null, 'a list is not a settings object');
  removeKey('k');
  assert.equal(loadJSON('k'), null);
});

test('a browser that refuses storage never stops the game', () => {
  useStorage({ getItem: refusing, setItem: refusing, removeItem: refusing });
  saveJSON('k', 1);
  removeKey('k');
  migrateStorage();
  recordGame(true);
  assert.equal(loadJSON('k'), null);
  assert.deepEqual(loadStats(), { played: 0, won: 0 });
});

test('games are counted, and a tampered count starts again from zero', () => {
  recordGame(true);
  recordGame(false);
  assert.deepEqual(loadStats(), { played: 2, won: 1 });
  for (const tampered of [
    { played: -1, won: 0 },
    { played: 1.5, won: 0 },
    { played: 1, won: 2 },
    { played: '3', won: 1 },
    { played: Number.MAX_VALUE, won: 0 },
    { won: 1 },
  ]) {
    saveJSON(KEYS.stats, tampered);
    assert.deepEqual(loadStats(), { played: 0, won: 0 }, JSON.stringify(tampered));
  }
  saveJSON(KEYS.stats, { played: 4, won: 2, __proto__: { hacked: true }, extra: '<b>' });
  assert.deepEqual(loadStats(), { played: 4, won: 2 }, 'only the two counts are kept');
});

test('what was stored under the game’s former name is brought over once, without replacing newer data', () => {
  const stored = useStorage(
    fakeStorage({
      'contree.stats': '{"played":3,"won":1}',
      'contree.prefs': '{"music":true}',
      'kabbout.prefs': '{"music":false}',
    }),
  );
  migrateStorage();
  assert.deepEqual(stored.keys(), ['kabbout.prefs', 'kabbout.stats']);
  assert.deepEqual(loadStats(), { played: 3, won: 1 });
  assert.equal(loadJSON(KEYS.prefs).music, false);
});

test('settings keep the names they were first stored under, so that an update loses none', () => {
  Object.assign(prefs, { highlight: false, showReasons: true, autoPlayForced: false });
  savePrefs();
  const stored = loadJSON(KEYS.prefs);
  assert.deepEqual([stored.hl, stored.why, stored.autoForced], [false, true, false]);
  resetSettings();
  loadPrefs();
  assert.deepEqual([prefs.highlight, prefs.showReasons, prefs.autoPlayForced], [false, true, false]);
});

test('settings survive a visit; unknown values keep the defaults', () => {
  Object.assign(prefs, { music: true, sound: false, level: 'expert', scoring: 'tunisian', lang: 'ar' });
  savePrefs();
  resetSettings();
  loadPrefs();
  assert.deepEqual([prefs.music, prefs.sound, prefs.level, prefs.scoring, prefs.lang], [true, false, 'expert', 'tunisian', 'ar']);

  saveJSON(KEYS.prefs, { level: 'godlike', scoring: 7, lang: '<script>', sound: 'yes', undo: false, why: 1 });
  resetSettings();
  loadPrefs();
  assert.deepEqual([prefs.level, prefs.scoring, prefs.lang], ['normal', 'ffb', null]);
  assert.deepEqual([prefs.sound, prefs.undo, prefs.showReasons], [true, false, true], 'switches are read as on or off, nothing else');
});

test('with no settings saved, the sound switch of the former version is still honoured', () => {
  localStorage.setItem('contree.sound', 'false');
  loadPrefs();
  assert.equal(prefs.sound, false);
});

test('the language is saved only once the player has chosen one', () => {
  savePrefs();
  assert.ok(!('lang' in loadJSON(KEYS.prefs)));
});

test('the tutorial remembers the steps seen, as plain yes or no, and can be skipped or started again', () => {
  coachAnswer('ok', 'bid');
  assert.deepEqual(loadJSON(KEYS.tutorial), { done: { bid: true }, off: false });
  coachAnswer('skip');
  assert.equal(loadJSON(KEYS.tutorial).off, true);

  saveJSON(KEYS.tutorial, { done: { bid: '<img>', play: 0 }, off: 'yes' });
  loadPrefs();
  assert.deepEqual(tutorial, { done: { bid: true, play: false }, off: true });
  saveJSON(KEYS.tutorial, { done: [1], off: true });
  resetSettings();
  loadPrefs();
  assert.deepEqual(tutorial, { done: {}, off: false }, 'a malformed record is ignored');

  resetTutorial();
  assert.deepEqual(loadJSON(KEYS.tutorial), { done: {}, off: false });
  markTutorialStep('round');
  assert.deepEqual(loadJSON(KEYS.tutorial), { done: { round: true }, off: false });
});

test('the card agreed with the partner is kept; what is stored and is not a card is no agreement', () => {
  loadConventions();
  assert.deepEqual(partnership, { card: CLASSIC, agreed: false });

  Object.assign(partnership, { card: BOLD, agreed: true });
  saveConventions();
  Object.assign(partnership, { card: CLASSIC, agreed: false });
  loadConventions();
  assert.deepEqual(partnership, { card: BOLD, agreed: true });

  // agreeing on the classic card is an agreement too
  Object.assign(partnership, { card: CLASSIC, agreed: true });
  saveConventions();
  Object.assign(partnership, { card: CLASSIC, agreed: false });
  loadConventions();
  assert.equal(partnership.agreed, true);

  for (const stored of ['"bold"', '{"open":70}', JSON.stringify({ ...BOLD, jack: 500 }), JSON.stringify({ ...BOLD, more: 1 }), '[', 'null']) {
    useStorage(fakeStorage({ [KEYS.conventions]: stored }));
    Object.assign(partnership, { card: CLASSIC, agreed: false });
    loadConventions();
    assert.deepEqual(partnership, { card: CLASSIC, agreed: false }, stored);
  }
  useStorage({ getItem: refusing, setItem: refusing, removeItem: refusing });
  saveConventions();
  loadConventions();
  assert.equal(partnership.agreed, false);
});
