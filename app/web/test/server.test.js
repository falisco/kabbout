import assert from 'node:assert/strict';
import { test } from 'node:test';
import { BOLD, CLASSIC } from '../../../engine/src/index.ts';
import { NO_SERVER, aiNotice, askCoach, enter, interpretConventions, loadCapabilities } from '../src/server.js';

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
  assert.equal(aiNotice(having({ coach: 'planned', referee: 'planned' })), null);
  assert.equal(aiNotice(having({ coach: 'off', referee: 'planned' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'off' })), 'off');
  assert.equal(aiNotice(having({ coach: 'on', referee: 'planned' })), null);
  assert.equal(aiNotice(having({ coach: 'on' })), null);
});

/* ---------- who is playing ---------- */

const session = (player) => ({ version: 1, player });
/** a fetch that answers each request in turn, and writes down what was asked: the method, the address, and the header only the page adds */
function answeringInTurn(...answers) {
  const asked = [];
  const fetchFn = /** @type {any} */ (
    async (url, init) => {
      asked.push([init.method, url, init.headers['csrf-token'] ?? null]);
      const next = answers.shift();
      if (next instanceof Error) throw next;
      if (!next) throw new Error('asked more often than the test said');
      return { status: next[0], json: async () => (next[1] instanceof Error ? Promise.reject(next[1]) : next[1]) };
    }
  );
  return { fetchFn, asked };
}
const ASKED = ['GET', 'api/session', null];
/** coming in changes something on the server: the page shows the request is its own */
const CAME_IN = ['POST', 'api/session', 'csrf-token'];

test('a visitor the server knows plays under their name, and is not made to come in again', async () => {
  const { fetchFn, asked } = answeringInTurn([200, session({ name: 'C4821973' })]);
  assert.equal(await enter(fetchFn), 'C4821973');
  assert.deepEqual(asked, [ASKED]);
});

test('a visitor the server does not know comes in as a guest, once, and plays under the name the browser is then known by', async () => {
  const { fetchFn, asked } = answeringInTurn([200, session(null)], [201, session({ name: 'C0000007' })], [200, session({ name: 'C0000007' })]);
  assert.equal(await enter(fetchFn), 'C0000007');
  assert.deepEqual(asked, [ASKED, CAME_IN, ASKED]);
});

test('two tabs coming in at the same instant show the same name: the one the browser kept', async () => {
  // this tab was given C0000007, and the other tab's guest is the one whose cookie the browser holds
  const { fetchFn } = answeringInTurn([200, session(null)], [201, session({ name: 'C0000007' })], [200, session({ name: 'C0000042' })]);
  assert.equal(await enter(fetchFn), 'C0000042');
});

test('a guest who came in from another tab meanwhile is the same guest', async () => {
  const { fetchFn } = answeringInTurn([200, session(null)], [200, session({ name: 'C0000007' })], [200, session({ name: 'C0000007' })]);
  assert.equal(await enter(fetchFn), 'C0000007');
});

test('a browser that keeps no cookie shows no name: the server would not know it by one', async () => {
  const { fetchFn, asked } = answeringInTurn([200, session(null)], [201, session({ name: 'C0000007' })], [200, session(null)]);
  assert.equal(await enter(fetchFn), '');
  assert.equal(asked.length, 3);
});

test('with no server nobody comes in: there is no name, and nothing is posted', async () => {
  const cases = {
    'a static server': [204, null],
    'a missing page': [404, 'not found'],
    'a server error': [500, {}],
    'an answer that is not JSON': [200, new SyntaxError('no')],
    'no network': new TypeError('failed'),
    'JSON of another shape': [200, { hello: 'world' }],
    'a page served for every address': [200, '<!doctype html>'],
    nothing: [200, null],
    'a player with no name': [200, session({})],
    'a name that is no text': [200, session({ name: 7 })],
    'an empty name': [200, session({ name: '' })],
    'a name too long to be one': [200, session({ name: 'C'.repeat(33) })],
    'a player that is no player': [200, session('C4821973')],
    'a guest made by a question, which makes none': [201, session({ name: 'C4821973' })],
  };
  for (const [name, said] of Object.entries(cases)) {
    const { fetchFn, asked } = answeringInTurn(said);
    assert.equal(await enter(fetchFn), '', name);
    assert.deepEqual(asked, [ASKED], name);
  }
});

test('a visitor the server will not let in plays with no name, and it is never an error', async () => {
  const cases = {
    'refused as not coming from the page': [401, { error: { code: 'unauthorized' } }],
    'too many guests from this address': [429, { error: { code: 'too_many_requests' } }],
    'no name free': [503, { error: { code: 'no_name_free' } }],
    'the network down': new TypeError('failed'),
    'an answer that is not JSON': [201, new SyntaxError('no')],
    'nobody, again': [201, session(null)],
    'an answer of another shape': [201, { hello: 'world' }],
  };
  for (const [name, said] of Object.entries(cases)) {
    const { fetchFn, asked } = answeringInTurn([200, session(null)], said);
    assert.equal(await enter(fetchFn), '', name);
    assert.deepEqual(asked, [ASKED, CAME_IN], name);
  }
});

test('a server that stops answering once the guest is in leaves no name, and no error', async () => {
  const { fetchFn } = answeringInTurn([200, session(null)], [201, session({ name: 'C0000007' })], new TypeError('failed'));
  assert.equal(await enter(fetchFn), '');
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
  // a question spends something on the server: the page shows the request is its own
  assert.equal(init.headers['csrf-token'], 'csrf-token');
  assert.deepEqual(JSON.parse(init.body), QUESTION);
  assert.ok(init.signal instanceof AbortSignal);
});

test('the coach’s answer is its text, who wrote it, and the tricks it points at', async () => {
  const reply = await askCoach(QUESTION, coachSays({ text: 'Au pli 4…', source: 'model', tricks: [6, 4, 4], cards: ['TC'] }));
  assert.deepEqual(reply, { answer: { text: 'Au pli 4…', source: 'model', tricks: [4, 6], left: null }, reason: '' });
  const plain = await askCoach(QUESTION, coachSays({ text: 'Rien à reprocher.', source: 'summary' }));
  assert.deepEqual(plain.answer, { text: 'Rien à reprocher.', source: 'summary', tricks: [], left: null });
});

test('the answer says how many questions are left on the deal, when the server counts them', async () => {
  const left = async (value) =>
    (await askCoach(QUESTION, answering(200, { version: 1, answer: { text: 'x', source: 'model' }, left: value }))).answer.left;
  assert.equal(await left(1), 1);
  assert.equal(await left(0), 0);
  for (const value of [-1, 1.5, '2', null, {}]) assert.equal(await left(value), null);
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
    'too many questions that are not JSON': [
      /** @type {any} */ (async () => ({ status: 429, json: async () => Promise.reject(new SyntaxError('x')) })),
      'busy',
    ],
    'the questions of the deal spent': [answering(429, { error: { code: 'deal_quota', left: 0 } }), 'spent'],
    'the questions of the day spent': [answering(429, { error: { code: 'daily_quota' } }), 'resting'],
    'a limit the page does not know': [answering(429, { error: { code: 'constructor' } }), 'busy'],
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

/* ---------- the conventions with the partner ---------- */

const SENTENCE = { sentence: 'monte de 30 si t’as le valet', lang: 'fr' };

test('interpretConventions posts the sentence as the page, and reads the card as the engine reads any card', async () => {
  const asked = [];
  const reply = await interpretConventions(
    SENTENCE,
    /** @type {any} */ (
      async (url, init) => (
        asked.push([url, init]),
        { status: 200, json: async () => ({ version: 1, card: { ...BOLD }, unplaced: [' et souris ', 7, ''] }) }
      )
    ),
  );
  assert.deepEqual(reply, { card: BOLD, unplaced: [' et souris '], reason: '' });
  const [[url, init]] = asked;
  assert.equal(url, 'api/conventions/interpret');
  assert.equal(init.method, 'POST');
  assert.equal(init.headers['csrf-token'], 'csrf-token');
  assert.deepEqual(JSON.parse(init.body), SENTENCE);
});

test('interpretConventions: what is not a card is no card, whatever the server says of it', async () => {
  for (const card of [null, 'bold', { ...CLASSIC, jack: 40 }, { ...CLASSIC, contre: 10 }, { open: 70 }, [CLASSIC]]) {
    assert.deepEqual(await interpretConventions(SENTENCE, answering(200, { card, unplaced: [] })), {
      card: null,
      unplaced: [],
      reason: 'unavailable',
    });
  }
  // a card with nothing said of what was left out is a card
  assert.deepEqual(await interpretConventions(SENTENCE, answering(200, { card: CLASSIC })), { card: CLASSIC, unplaced: [], reason: '' });
  // and what was left out is a few short pieces, however many and however long the server sends
  const many = await interpretConventions(SENTENCE, answering(200, { card: CLASSIC, unplaced: Array(40).fill('x'.repeat(5000)) }));
  assert.equal(many.unplaced.length, 6);
  assert.equal(many.unplaced[0].length, 300);
});

test('interpretConventions never fails: each refusal has its reason', async () => {
  const reason = async (status, body) => (await interpretConventions(SENTENCE, answering(status, body))).reason;
  assert.equal(await reason(422, { error: { code: 'not_understood' } }), 'unclear');
  assert.equal(await reason(429, { error: { code: 'too_many_requests' } }), 'busy');
  assert.equal(await reason(429, { error: { code: 'daily_quota' } }), 'resting');
  assert.equal(await reason(503, { error: { code: 'ai_off' } }), 'unavailable');
  assert.equal(await reason(200, null), 'unavailable');
  const thrown = await interpretConventions(SENTENCE, /** @type {any} */ (async () => Promise.reject(new Error('offline'))));
  assert.deepEqual(thrown, { card: null, unplaced: [], reason: 'unavailable' });
  // a server that never answers is given up on
  const hanging = /** @type {any} */ (
    (_url, init) => new Promise((_resolve, reject) => init.signal.addEventListener('abort', () => reject(new Error('aborted'))))
  );
  assert.equal((await interpretConventions(SENTENCE, hanging, 5)).reason, 'unavailable');
});
