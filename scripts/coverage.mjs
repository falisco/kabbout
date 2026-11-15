// Merges the coverage of the unit tests and of the browser tests into one report, and fails under the minimum.
// Run through `npm run test:coverage`, which runs both kinds of test first.
import { readFileSync, readdirSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { CoverageReport } from 'monocart-coverage-reports';
import { MINIMUM, UNIT_DIR, coverageOptions, isMeasured } from './coverage-options.mjs';

const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
/** one number of a source map, in its variable-length base-64 form */
function vlq(value) {
  let rest = value < 0 ? (-value << 1) | 1 : value << 1;
  let text = '';
  do {
    const digit = rest & 31;
    rest >>>= 5;
    text += BASE64[rest ? digit | 32 : digit];
  } while (rest);
  return text;
}

/**
 * A source map saying that every position of `ran` is the same position in `original`.
 * Node runs a TypeScript file with its types blanked out, character for character, so V8's offsets
 * point into that blanked text; this map carries them back to the file as written, which is also
 * the text the browser bundle's own source map names. Without it the report would list the file twice.
 */
function samePositionsMap(url, original, ran) {
  let previousColumn = 0;
  const lines = ran.split('\n').map((line, index) => {
    const first = `A${vlq(0)}${vlq(index ? 1 : 0)}${vlq(-previousColumn)}`;
    previousColumn = line.length;
    return first + ',CAAC'.repeat(line.length);
  });
  return { version: 3, sources: [url], sourcesContent: [original], names: [], mappings: lines.join(';') };
}

function unitEntry(entry) {
  const original = readFileSync(fileURLToPath(entry.url), 'utf8');
  if (!entry.url.endsWith('.ts')) return { ...entry, source: original };
  const ran = stripTypeScriptTypes(original);
  return { ...entry, source: ran, sourceMap: samePositionsMap(entry.url, original, ran) };
}

function unitEntries() {
  const entries = [];
  for (const file of readdirSync(UNIT_DIR)) {
    const { result } = JSON.parse(readFileSync(join(UNIT_DIR, file), 'utf8'));
    for (const entry of result) if (entry.url.startsWith('file:') && isMeasured(entry.url)) entries.push(unitEntry(entry));
  }
  return entries;
}

const report = new CoverageReport(coverageOptions);
await report.add(unitEntries());
const results = await report.generate();
if (!results) throw new Error('no coverage was collected');

const failures = ['lines', 'branches'].map((metric) => ({ metric, pct: results.summary[metric]?.pct })).filter(({ pct }) => !(pct >= MINIMUM));
for (const { metric, pct } of failures) console.error(`Coverage of ${metric} is ${pct}%, under the ${MINIMUM}% required.`);
if (failures.length) process.exit(1);
console.log(`Coverage OK: at least ${MINIMUM}% of lines and branches.`);
