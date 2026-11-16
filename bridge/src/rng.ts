// The bridge's source of randomness. The engine takes any `Rng`; its own (mulberry32) is seeded with
// 32 bits, few enough for a player to try every seed against the eight cards of a hand and so find the
// other three. Here a seed is at least 128 bits and the draws are an HMAC-SHA256 stream, which cannot be
// run backwards from what a seat sees. The same seed still gives the same draws: answers stay reproducible.

import { createHash, createHmac } from 'node:crypto';
import type { Rng } from '../../engine/src/index.ts';
import { BadRequest } from './errors.ts';

/** 128 to 256 bits, in lower-case hexadecimal */
const SEED = /^[0-9a-f]{32,64}$/;
const UINT32 = 2 ** 32;
const BYTES_PER_DRAW = 4;

export function readSeed(v: unknown): string {
  if (typeof v !== 'string' || !SEED.test(v)) throw new BadRequest('seed: 32 to 64 hexadecimal digits expected');
  return v;
}

/** Numbers in [0, 1), 32 bits each, drawn from HMAC-SHA256(seed, block number). */
export function seededStream(seed: string): Rng {
  // the seed as written is the key: decoded, HMAC would take two seeds that differ by trailing zeros for the same one
  const key = Buffer.from(seed, 'utf8');
  let block = Buffer.alloc(0);
  let offset = 0;
  let blocks = 0;
  return () => {
    if (offset >= block.length) {
      const number = Buffer.alloc(8);
      number.writeBigUInt64BE(BigInt(blocks++));
      block = createHmac('sha256', key).update(number).digest();
      offset = 0;
    }
    const draw = block.readUInt32BE(offset);
    offset += BYTES_PER_DRAW;
    return draw / UINT32;
  };
}

/** A JSON value written with the fields of every object in order: two writings of the same value give the same text. */
function canonical(value: unknown): string {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') {
    const fields = Object.entries(value)
      .filter(([, v]) => v !== undefined)
      .sort(([a], [b]) => (a < b ? -1 : 1));
    return `{${fields.map(([k, v]) => `${JSON.stringify(k)}:${canonical(v)}`).join(',')}}`;
  }
  return JSON.stringify(value) ?? 'null';
}

/**
 * The draws of an analysis: they come from what is analysed (`subject`, any JSON value) and from the
 * question asked of it (`purpose`), so the same question about the same deal always gets the same answer
 * and nobody has a seed to choose. The order in which a caller wrote the fields does not count.
 */
export function streamOf(subject: unknown, purpose: string): Rng {
  const digest = createHash('sha256').update(canonical(subject)).digest('hex');
  return seededStream(createHmac('sha256', digest).update(purpose).digest('hex'));
}
