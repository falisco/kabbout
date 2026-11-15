// The three dictionaries must answer the same questions: same keys, same shapes, same parameters.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { DICT, detectLang, pickLang, setLang, t } from '../src/i18n/index.js';

const shape = (v) =>
  typeof v === 'function' ? `function/${v.length}` : v && typeof v === 'object' ? `table/${Object.keys(v).sort().join(',')}` : typeof v;

test('every language has the same keys as French, of the same kind', () => {
  for (const [l, d] of Object.entries(DICT)) {
    assert.deepEqual(Object.keys(d).sort(), Object.keys(DICT.fr).sort(), l);
    for (const k of Object.keys(DICT.fr)) assert.equal(shape(d[k]), shape(DICT.fr[k]), `${l}.${k}`);
  }
  assert.deepEqual(Object.keys(DICT), ['fr', 'en', 'ar']);
});

test('the answers to the quick phrases cover every phrase and every robot', () => {
  for (const d of Object.values(DICT)) for (const p of Object.keys(d.phrases)) assert.deepEqual(Object.keys(d.replies[p]), ['1', '2', '3'], p);
});

test('the language follows the browser, English when it speaks neither', () => {
  assert.equal(pickLang(['fr-TN', 'en']), 'fr');
  assert.equal(pickLang(['ar-TN', 'en-GB', 'fr']), 'ar');
  assert.equal(pickLang(['es-ES', 'en-GB', 'fr']), 'en');
  assert.equal(pickLang(['de-DE']), 'en');
  assert.equal(pickLang([]), 'en');
});

test('texts are given in the current language, with their values', () => {
  setLang('fr');
  assert.equal(t('cardName', 'Q', 'H'), 'Dame de cœur');
  assert.equal(t('dealsPlayed', 1), '1 donne jouée');
  assert.equal(t('dealsPlayed', 3), '3 donnes jouées');
  setLang('en');
  assert.equal(t('cardName', 'Q', 'H'), 'Queen of hearts');
  assert.equal(t('dealsPlayed', 1), '1 deal played');
  assert.equal(t('levels').easy, 'Easy');
  setLang('ar');
  assert.equal(t('cardName', 'Q', 'H'), 'الدامة كور');
  assert.equal(t('dealsPlayed', 2), 'زوز جريات تلعبو');
  assert.equal(t('robots')[2], 'سليم');
});

test('the language is the one in the address, else the player’s choice, else the browser’s', () => {
  const browser = (search, languages, language = languages[0]) => {
    globalThis.location = /** @type {any} */ ({ search });
    Object.defineProperty(globalThis, 'navigator', { value: { languages, language }, configurable: true });
  };
  browser('?lang=ar', ['fr-FR']);
  assert.equal(detectLang('en'), 'ar');
  browser('?lang=klingon', ['fr-FR']);
  assert.equal(detectLang('en'), 'en', 'an unknown language in the address is ignored');
  browser('', ['fr-FR', 'en']);
  assert.equal(detectLang(null), 'fr');
  assert.equal(detectLang('nonsense'), 'fr', 'so is an unknown saved choice');
  browser('', [], 'ar-TN');
  assert.equal(detectLang(null), 'ar', 'a browser that lists no languages still names one');
});
