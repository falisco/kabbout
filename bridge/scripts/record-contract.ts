// `npm run contract:record`: writes bridge/contract/cases.json again, after an intended change of the bridge.
import { writeFileSync } from 'node:fs';
import { buildCases, serialize } from '../contract/build.ts';

const cases = buildCases();
writeFileSync(new URL('../contract/cases.json', import.meta.url), serialize(cases));
console.log(`${cases.length} calls recorded.`);
