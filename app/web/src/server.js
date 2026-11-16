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
