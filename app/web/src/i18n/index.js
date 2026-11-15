// The language of the screen: which one, its texts, and the page's fixed texts.
// No DOM needed to import this module, so the dictionaries are tested under Node.
import { LANGS, isLang } from '../../../../engine/src/index.ts';
import ar from './ar.js';
import en from './en.js';
import fr from './fr.js';

export { LANGS, isLang };
export const DICT = { fr, en, ar };
/** how each language names itself in the switch */
export const LANG_NAMES = { fr: 'Français', en: 'English', ar: 'تونسي' };
/** what the page declares: Tunisian Arabic is written right to left */
const HTML_LANG = { fr: 'fr', en: 'en', ar: 'ar-TN' };

/** @type {'fr' | 'en' | 'ar'} */
let current = 'fr';
export const lang = () => current;

/**
 * The text for `key` in the current language. Texts that take values are functions: pass the
 * values after the key. Tables (ranks, suits, levels…) are returned whole.
 * @returns {any}
 */
export function t(key, ...args) {
  const text = DICT[current][key];
  return typeof text === 'function' ? text(...args) : text;
}

/** the first of `wanted` (BCP 47 tags, best first) that the game speaks, or English */
export function pickLang(wanted) {
  for (const tag of wanted) {
    const code = String(tag).toLowerCase().split('-')[0];
    if (isLang(code)) return code;
  }
  return 'en';
}

/** `?lang=` (tests, links), then the player's own choice, then the browser's languages. */
export function detectLang(chosen) {
  const asked = new URLSearchParams(location.search).get('lang');
  if (isLang(asked)) return asked;
  if (isLang(chosen)) return chosen;
  return pickLang(navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language]);
}

/**
 * The texts written in index.html: `data-i18n` (content), `data-i18n-title` (tooltip and label, for
 * buttons that show only an icon), `data-i18n-tooltip` (tooltip alone, for buttons whose content
 * already names them).
 */
function applyStatic() {
  document.documentElement.lang = HTML_LANG[current];
  document.title = t('docTitle');
  document.querySelector('meta[name="description"]')?.setAttribute('content', t('docDesc'));
  /** @type {NodeListOf<HTMLElement>} */
  const marked = document.querySelectorAll('[data-i18n],[data-i18n-title],[data-i18n-tooltip]');
  marked.forEach((el) => {
    const { i18n, i18nTitle, i18nTooltip } = el.dataset;
    if (i18n) el.textContent = t(i18n);
    if (i18nTooltip) el.title = t(i18nTooltip);
    if (i18nTitle) {
      el.title = t(i18nTitle);
      el.setAttribute('aria-label', t(i18nTitle));
    }
  });
}

/** Switches the screen to `code`, or to English when the game does not speak it. */
export function setLang(code) {
  current = isLang(code) ? code : 'en';
  if (typeof document !== 'undefined') applyStatic();
}
