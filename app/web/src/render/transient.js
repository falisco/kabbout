// Things that appear and go: the toast, screen-reader announcements, and the focus across a redraw.
import { TOAST_MS } from '../config.js';
import { $ } from '../dom.js';

/** how many announcements the live region keeps; older ones have been read already */
const ANNOUNCEMENTS_KEPT = 4;
let toastTimer = null;

/** Makes the browser take in the styles just changed, so that an animation or transition set next starts from them. */
export function restartAnimation(el) {
  void el.offsetWidth;
}

export function toast(html, ms = TOAST_MS.short) {
  const el = $('#toast');
  el.innerHTML = html;
  el.hidden = false;
  el.style.animation = 'none';
  restartAnimation(el);
  el.style.animation = '';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    el.hidden = true;
  }, ms);
}
export function hideToast() {
  $('#toast').hidden = true;
}

/** the data attributes that tell one button of a panel from another */
const FOCUS_KEYS = ['action', 'suit', 'setting', 'value', 'answer'];
const focusKey = (button) => FOCUS_KEYS.map((key) => button.dataset[key] ?? '').join('|');
/** Runs `redraw` (which rebuilds `el`) and puts the focus back on the same control. */
export function keepFocus(el, redraw) {
  const focused = document.activeElement;
  const key = focused && focused !== document.body && el.contains(focused) ? focusKey(focused) : null;
  redraw();
  if (key === null) return;
  const same = [...el.querySelectorAll('button')].find((button) => focusKey(button) === key && !button.disabled);
  same?.focus();
}

/** Announces `text` to screen readers. */
export function say(text) {
  const region = $('#sr');
  const line = document.createElement('p');
  line.textContent = text;
  region.appendChild(line);
  while (region.children.length > ANNOUNCEMENTS_KEPT) region.firstChild.remove();
}
