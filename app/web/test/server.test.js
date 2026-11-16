import assert from 'node:assert/strict';
import { test } from 'node:test';
import { NO_SERVER, aiNotice, loadCapabilities } from '../src/server.js';

const answer = (features, ai = { variable: 'KABBOUT_AI' }) => ({ version: 1, ai, features });
/** a fetch that answers once with the given status and body */
const answering = (status, body) => /** @type {any} */ (async () => ({ status, json: async () => body }));

test('the page asks its own origin, with a relative address', async () => {
  const asked = [];
  await loadCapabilities(/** @type {any} */ (async (url) => (asked.push(url), { status: 204 })));
  assert.deepEqual(asked, ['api/capabilities']);
});

test('a server answer gives the state of each feature and the variable to set', async () => {
  const body = answer({ coach: { state: 'off', hint: 'x' }, referee: { state: 'planned', hint: 'y' }, agent_seat: { state: 'on', hint: null } });
  assert.deepEqual(await loadCapabilities(answering(200, body)), {
    server: true,
    variable: 'KABBOUT_AI',
    features: { coach: 'off', referee: 'planned', agent_seat: 'on' },
  });
});

test('a feature in a state the page does not know is left out', async () => {
  const capabilities = await loadCapabilities(answering(200, answer({ coach: { state: 'dancing' }, referee: null, spoken_bids: { state: 'off' } })));
  assert.deepEqual(capabilities.features, { spoken_bids: 'off' });
});

test('anything else means no server, and never an error', async () => {
  const cases = {
    'a static server': answering(204, null),
    'a missing page': answering(404, 'not found'),
    'an answer that is not JSON': /** @type {any} */ (async () => ({ status: 200, json: async () => Promise.reject(new SyntaxError('no')) })),
    'no network': /** @type {any} */ (async () => Promise.reject(new TypeError('failed'))),
    'JSON of another shape': answering(200, { hello: 'world' }),
    'features without the variable': answering(200, { features: {} }),
    'features that are not a table': answering(200, answer(null)),
    nothing: answering(200, null),
  };
  for (const [name, fetchFn] of Object.entries(cases)) assert.equal(await loadCapabilities(fetchFn), NO_SERVER, name);
});

test('the menu says one thing about the AI, or nothing', () => {
  const having = (features) => ({ server: true, variable: 'KABBOUT_AI', features });
  assert.equal(aiNotice(NO_SERVER), null);
  assert.equal(aiNotice(having({})), null);
  assert.equal(aiNotice(having({ coach: 'planned', referee: 'planned' })), 'planned');
  assert.equal(aiNotice(having({ coach: 'off', referee: 'planned' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'off' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'planned' })), null);
  assert.equal(aiNotice(having({ coach: 'on' })), null);
});
