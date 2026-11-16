// The bridge: the rules engine as JSON over HTTP, for the server side of Kabbout.
// Internal only (it hands out full game states), stateless, and with no dependency beyond Node.

import { createServer } from 'node:http';
import type { IncomingMessage, Server, ServerResponse } from 'node:http';
import { EngineError } from '../../engine/src/index.ts';
import { BadRequest } from './errors.ts';
import type { Body, Handler } from './handlers.ts';
import { applyHandler, legalHandler, newGameHandler, replayHandler, robotHandler, viewHandler } from './handlers.ts';
import { DealRefused } from './replay.ts';
import { isObject } from './shape.ts';

/** a game state is about 2 kB; this leaves room for the analysis calls and stops anything else */
export const MAX_BODY_BYTES = 256 * 1024;

const ROUTES = new Map<string, Handler>([
  ['/games', newGameHandler],
  ['/games/apply', applyHandler],
  ['/games/view', viewHandler],
  ['/games/legal', legalHandler],
  ['/games/robot', robotHandler],
  ['/deals/replay', replayHandler],
]);

export interface Answer {
  status: number;
  body: unknown;
}

const failure = (status: number, code: string, message: string, more: object = {}): Answer => ({
  status,
  body: { error: { code, message, ...more } },
});

const notFound = (path: string): Answer => failure(404, 'not_found', `no such call: ${path}`);

/**
 * The answer to one call. 400: the request is not one the bridge understands. 422: the engine refused it
 * (`refused`, with `at` when a recorded deal is refused at a known place). 500: a bug, logged, never described to the caller.
 */
export function dispatch(path: string, body: Body): Answer {
  const handler = ROUTES.get(path);
  if (!handler) return notFound(path);
  try {
    return { status: 200, body: handler(body) };
  } catch (e) {
    if (e instanceof BadRequest) return failure(e.status, 'bad_request', e.message);
    if (e instanceof DealRefused) return failure(422, 'refused', e.message, { at: e.at });
    if (e instanceof EngineError) return failure(422, 'refused', e.message);
    console.error(e);
    return failure(500, 'internal', 'the bridge failed');
  }
}

/** The JSON object of the request, or BadRequest. Past the size limit the rest is read and thrown away. */
async function readBody(req: IncomingMessage): Promise<Body> {
  const chunks: Buffer[] = [];
  let size = 0;
  for await (const chunk of req as AsyncIterable<Buffer>) {
    size += chunk.length;
    if (size <= MAX_BODY_BYTES) chunks.push(chunk);
  }
  if (size > MAX_BODY_BYTES) throw new BadRequest(`body: more than ${MAX_BODY_BYTES} bytes`, 413);
  let parsed: unknown;
  try {
    parsed = JSON.parse(Buffer.concat(chunks).toString('utf8'));
  } catch {
    throw new BadRequest('body: not JSON');
  }
  if (!isObject(parsed)) throw new BadRequest('body: a JSON object expected');
  return parsed;
}

async function answer(req: IncomingMessage): Promise<Answer> {
  // the path as sent, without its query: no call takes one
  const path = (req.url ?? '/').split('?', 1)[0];
  if (path === '/health') return { status: 200, body: { ok: true } };
  if (!ROUTES.has(path)) return notFound(path);
  if (req.method !== 'POST') return failure(405, 'method_not_allowed', 'POST expected');
  try {
    return dispatch(path, await readBody(req));
  } catch (e) {
    if (e instanceof BadRequest) return failure(e.status, 'bad_request', e.message);
    throw e;
  }
}

function send(res: ServerResponse, { status, body }: Answer): void {
  const text = JSON.stringify(body);
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(text),
    'cache-control': 'no-store',
  });
  res.end(text);
}

export function createBridge(): Server {
  return createServer((req, res) => {
    answer(req).then(
      (result) => send(res, result),
      (e: unknown) => {
        console.error(e);
        send(res, failure(500, 'internal', 'the bridge failed'));
      },
    );
  });
}
