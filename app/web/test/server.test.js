import assert from 'node:assert/strict';
import { test } from 'node:test';
import { NO_SERVER, aiNotice, askCoach, loadCapabilities } from '../src/server.js';

const answer = (features, ai = { variable: 'ANTHROPIC_API_KEY' }) => ({ version: 1, ai, features });
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
    variable: 'ANTHROPIC_API_KEY',
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
  const having = (features) => ({ server: true, variable: 'ANTHROPIC_API_KEY', features });
  assert.equal(aiNotice(NO_SERVER), null);
  assert.equal(aiNotice(having({})), null);
  assert.equal(aiNotice(having({ coach: 'planned', referee: 'planned' })), 'planned');
  assert.equal(aiNotice(having({ coach: 'off', referee: 'planned' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'off' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'planned' })), null);
  assert.equal(aiNotice(having({ coach: 'on' })), null);
});

/* ---------- the coach ---------- */

const QUESTION = { record: { scoring: 'ffb', dealer: 0, bids: [], tricks: [] }, seat: 0, lang: 'fr', question: 'Qu’ai-je mal joué ?' };
const coachSays = (said) => answering(200, { version: 1, answer: said });

test('a question to the coach is posted to the page’s own origin, as JSON', async () => {
  const asked = [];
  await askCoach(QUESTION, /** @type {any} */ (async (url, init) => (asked.push([url, init]), { status: 503 })));
  const [[url, init]] = asked;
  assert.equal(url, 'api/coach');
  assert.equal(init.method, 'POST');
  assert.equal(init.headers['content-type'], 'application/json');
  assert.deepEqual(JSON.parse(init.body), QUESTION);
  assert.ok(init.signal instanceof AbortSignal);
});

test('the coach’s answer is its text, who wrote it, and the tricks it points at', async () => {
  const reply = await askCoach(QUESTION, coachSays({ text: 'Au pli 4…', source: 'model', tricks: [6, 4, 4], cards: ['TC'] }));
  assert.deepEqual(reply, { answer: { text: 'Au pli 4…', source: 'model', tricks: [4, 6] }, reason: '' });
  const plain = await askCoach(QUESTION, coachSays({ text: 'Rien à reprocher.', source: 'summary' }));
  assert.deepEqual(plain.answer, { text: 'Rien à reprocher.', source: 'summary', tricks: [] });
});

test('only numbers that name a trick become tricks to show', async () => {
  const tricks = [0, 9, 2.5, '3', null, { n: 1 }, 8, 1];
  const reply = await askCoach(QUESTION, coachSays({ text: 'x', source: 'model', tricks }));
  assert.deepEqual(reply.answer.tricks, [1, 8]);
});

test('no answer always comes with its reason, and never as an error', async () => {
  const failing = /** @type {any} */ (async () => Promise.reject(new TypeError('network')));
  const cases = {
    'too many questions': [answering(429, {}), 'busy'],
    'a deal the server refused': [answering(422, { error: { code: 'refused' } }), 'refused'],
    'the AI off': [answering(503, { error: { code: 'ai_off' } }), 'unavailable'],
    'a server error': [answering(500, {}), 'unavailable'],
    'no server': [answering(404, {}), 'unavailable'],
    'the network down': [failing, 'unavailable'],
    'an answer that is not JSON': [
      /** @type {any} */ (async () => ({ status: 200, json: async () => Promise.reject(new SyntaxError('x')) })),
      'unavailable',
    ],
    'an empty text': [coachSays({ text: '  ', source: 'model', tricks: [] }), 'unavailable'],
    'a text that is no text': [coachSays({ text: { html: '<b>' }, source: 'model' }), 'unavailable'],
    'an unknown author': [coachSays({ text: 'x', source: 'someone' }), 'unavailable'],
    'no answer at all': [answering(200, null), 'unavailable'],
  };
  for (const [name, [fetchFn, reason]] of Object.entries(cases)) assert.deepEqual(await askCoach(QUESTION, fetchFn), { answer: null, reason }, name);
});

test('a coach that takes too long is given up on', async () => {
  const never = /** @type {any} */ (
    (_url, init) => new Promise((_resolve, reject) => init.signal.addEventListener('abort', () => reject(new Error('aborted'))))
  );
  assert.deepEqual(await askCoach(QUESTION, never, 5), { answer: null, reason: 'unavailable' });
});
