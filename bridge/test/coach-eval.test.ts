// The coach's evaluation set (eval/coach/) is only worth what its expected answers are worth: each one
// must still be what the engine's analysis says today. A change to the search or to the analysis that
// moves them fails here, and the set is then built again: `npm run eval:coach:build`.

import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { test } from 'node:test';
import type { EvalDeal } from '../../eval/build-coach-deals.ts';
import { DEALS, fileOf } from '../../eval/build-coach-deals.ts';
import type { Swings } from '../src/analysis.ts';
import { dispatch } from '../src/server.ts';

const DIR = new URL('../../eval/coach/', import.meta.url);
const read = (file: string): EvalDeal => JSON.parse(readFileSync(new URL(file, DIR), 'utf8')) as EvalDeal;

test('the evaluation set is fifteen deals, five in each language', () => {
  const files = readdirSync(DIR).sort();
  assert.deepEqual(
    files,
    Array.from({ length: DEALS }, (_, index) => fileOf(index)),
  );
  const langs = files.map((file) => read(file).lang);
  for (const lang of ['fr', 'en', 'ar']) assert.equal(langs.filter((l) => l === lang).length, 5, lang);
});

test('in every deal of the set, the expected mistake is the engine’s first swing, well ahead of the next', () => {
  for (const file of readdirSync(DIR)) {
    const { record, seat, expected, question } = read(file);
    assert.ok(question.length > 0);
    const { status, body } = dispatch('/analysis/swings', { record, seat });
    assert.equal(status, 200, `${file}: ${JSON.stringify(body)}`);
    const [first, second] = (body as Swings).swings;
    assert.deepEqual(first, expected, file);
    assert.ok(expected.loss >= 40, file);
    assert.ok(!second || first.loss - second.loss >= 20, file);
  }
});
