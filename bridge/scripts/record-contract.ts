// `npm run contract:record`: writes bridge/contract/cases.json and conventions.json again, after an
// intended change of the bridge or of what a conventions card is.
import { writeFileSync } from 'node:fs';
import { buildCases, serialize } from '../contract/build.ts';
import { buildConventions, serializeConventions } from '../contract/conventions.ts';

const cases = buildCases();
writeFileSync(new URL('../contract/cases.json', import.meta.url), serialize(cases));
writeFileSync(new URL('../contract/conventions.json', import.meta.url), serializeConventions(buildConventions()));
console.log(`${cases.length} calls recorded, and the conventions card.`);
