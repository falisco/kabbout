// The one door to the server. The game is whole without one: served as plain files or packaged for a phone,
// nothing answers here and nothing about the server shows. When the page comes from the Kabbout server,
// this is where it learns what the server adds. No DOM here, so it is tested under Node.

/** what the page knows when no server answers */
export const NO_SERVER = Object.freeze({ server: false, variable: '', features: Object.freeze({}) });
const STATES = ['planned', 'off', 'on'];

/**
 * @param {unknown} body the answer of GET api/capabilities
 * @returns {{ server: boolean, variable: string, features: Record<string, string> }}
 */
function read(body) {
  const answer = /** @type {any} */ (body);
  if (typeof answer?.features !== 'object' || answer.features === null || typeof answer.ai?.variable !== 'string') return NO_SERVER;
  /** @type {Record<string, string>} */
  const features = {};
  for (const [name, feature] of Object.entries(answer.features)) {
    if (STATES.includes(/** @type {any} */ (feature)?.state)) features[name] = /** @type {any} */ (feature).state;
  }
  return { server: true, variable: answer.ai.variable, features };
}

/**
 * Asks the server what it can do. Never fails: any answer that is not the expected one means no server.
 * @param {typeof fetch} [fetchFn] the browser's own when none is given; it only works called on the window, hence the wrapper
 */
export async function loadCapabilities(fetchFn = (url, init) => fetch(url, init)) {
  try {
    const response = await fetchFn('api/capabilities', { headers: { accept: 'application/json' } });
    return response.status === 200 ? read(await response.json()) : NO_SERVER;
  } catch {
    return NO_SERVER;
  }
}

/**
 * What the menu has to say about the AI, in one word.
 * @param {{ server: boolean, features: Record<string, string> }} capabilities
 * @returns {'off' | 'planned' | null} 'off': a feature waits for its key; 'planned': none is in this version yet;
 *   null: nothing to say (no server, or everything is on)
 */
export function aiNotice(capabilities) {
  const states = Object.values(capabilities.features);
  if (!capabilities.server || states.length === 0) return null;
  if (states.includes('off')) return 'off';
  return states.every((state) => state === 'planned') ? 'planned' : null;
}

/**
 * What the page adds to a request that changes something on the server, to show that the game's own
 * page made it: a header a page of another site cannot add. The server says what it is
 * (Kabbout\Api\Domain\FromThePage), and Symfony's same-origin check reads it.
 */
const FROM_THE_PAGE = { 'csrf-token': 'csrf-token' };

/* ---------- who is playing ---------- */

/** A longer name would not fit beside the player's seat. What a name is made of is the server's business: the page shows it as plain text. */
const NAME_MAX = 32;

/**
 * One question to api/session, and what the Kabbout server answered.
 * @param {typeof fetch} fetchFn
 * @param {'GET' | 'POST'} method
 * @returns {Promise<{ server: boolean, name: string }>} `server` is false when this is not the Kabbout
 *   server's answer; `name` is the player's, '' when the server says nobody is asking
 */
async function session(fetchFn, method) {
  const nothing = { server: false, name: '' };
  const headers = { accept: 'application/json', ...(method === 'POST' ? FROM_THE_PAGE : {}) };
  const response = await fetchFn('api/session', { method, headers });
  if (response.status !== 200 && !(method === 'POST' && response.status === 201)) return nothing;
  const answer = /** @type {any} */ (await response.json());
  if (typeof answer !== 'object' || answer === null || answer.player === undefined) return nothing;
  if (answer.player === null) return { server: true, name: '' };
  const name = answer.player.name;
  return typeof name === 'string' && name !== '' && name.length <= NAME_MAX ? { server: true, name } : nothing;
}

/**
 * The name the visitor plays under. The server is asked who is asking: it knows the visitor by a cookie
 * the page cannot read. When it says nobody, and only then, the page comes in as a new guest, and then
 * asks again: the name shown is the one the browser is known by, even if another tab came in at the
 * same instant, or if the browser keeps no cookie. Never fails: with no server, or with anything that
 * is not its answer, there is no name ('') and nothing is posted.
 * @param {typeof fetch} [fetchFn]
 * @returns {Promise<string>}
 */
export async function enter(fetchFn = (url, init) => fetch(url, init)) {
  try {
    const asked = await session(fetchFn, 'GET');
    if (!asked.server || asked.name) return asked.name;
    const entered = await session(fetchFn, 'POST');
    return entered.name ? (await session(fetchFn, 'GET')).name : '';
  } catch {
    return '';
  }
}

/* ---------- the coach ---------- */

/**
 * How long the page waits for the coach. The server gives a question about 55 seconds at the very worst
 * (its tools stop after 30, a call to the model lasts 25 at most): the page waits longer than that, so
 * an answer the server paid for is an answer the player reads.
 */
export const COACH_TIMEOUT_MS = 70_000;
const SOURCES = ['model', 'summary'];
const LAST_TRICK = 8;
/** why the coach has no answer, by the status the server gave */
const REASONS = { 422: 'refused', 429: 'busy' };
/** the server's words past a limit: the questions of this deal are spent, or those of the day for everybody */
const QUOTAS = { deal_quota: 'spent', daily_quota: 'resting' };

/**
 * @param {unknown} body the answer of POST api/coach
 * @returns {{ text: string, source: 'model' | 'summary', tricks: number[], left: number | null } | null} null when it is not an answer; `left` is the questions still open on the deal, null when the server did not say
 */
function readAnswer(body) {
  const answer = /** @type {any} */ (body)?.answer;
  if (typeof answer?.text !== 'string' || answer.text.trim() === '' || !SOURCES.includes(answer.source)) return null;
  // the tricks become buttons: only whole numbers that name a trick get through
  const tricks = Array.isArray(answer.tricks) ? answer.tricks.filter((n) => Number.isInteger(n) && n >= 1 && n <= LAST_TRICK) : [];
  const left = /** @type {any} */ (body).left;
  return {
    text: answer.text,
    source: answer.source,
    tricks: [...new Set(tricks)].sort((a, b) => a - b),
    left: Number.isInteger(left) && left >= 0 ? left : null,
  };
}

/**
 * Asks the coach about a deal. Never fails: when there is no answer, `reason` says why, 'busy' (too many
 * questions), 'spent' (no question left on this deal), 'resting' (the day's questions are spent for everybody), 'refused' (the server could not replay the deal) or 'unavailable' (anything else, a server
 * that takes too long included).
 * @param {{ record: object, seat: number, lang: string, question: string }} question
 * @param {typeof fetch} [fetchFn]
 * @param {number} [timeoutMs]
 * @returns {Promise<{ answer: { text: string, source: 'model' | 'summary', tricks: number[], left: number | null } | null, reason: string }>}
 */
export async function askCoach(question, fetchFn = (url, init) => fetch(url, init), timeoutMs = COACH_TIMEOUT_MS) {
  const waiting = new AbortController();
  const timer = setTimeout(() => waiting.abort(), timeoutMs);
  try {
    const response = await fetchFn('api/coach', {
      method: 'POST',
      headers: { accept: 'application/json', 'content-type': 'application/json', ...FROM_THE_PAGE },
      body: JSON.stringify(question),
      signal: waiting.signal,
    });
    const body = await response.json().catch(() => null);
    const answer = response.status === 200 ? readAnswer(body) : null;
    if (answer) return { answer, reason: '' };
    if (response.status === 429 && Object.hasOwn(QUOTAS, body?.error?.code)) return { answer: null, reason: QUOTAS[body.error.code] };
    return { answer: null, reason: REASONS[response.status] ?? 'unavailable' };
  } catch {
    return { answer: null, reason: 'unavailable' };
  } finally {
    clearTimeout(timer);
  }
}
