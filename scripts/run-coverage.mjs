// `npm run test:coverage`: runs the unit tests and the browser tests while recording which lines they run,
// then writes the merged report and fails under the minimum (see coverage.mjs). A failing test fails it too.
import { spawnSync } from 'node:child_process';
import { rmSync } from 'node:fs';
import { ROOT, UNIT_DIR, coverageOptions } from './coverage-options.mjs';

function run(command, args, env = {}) {
  const { status } = spawnSync(command, args, { cwd: ROOT, stdio: 'inherit', env: { ...process.env, ...env } });
  if (status !== 0) process.exit(status ?? 1);
}

rmSync(coverageOptions.outputDir, { recursive: true, force: true });
run('node', ['--test', 'engine/test/*.test.ts', 'bridge/test/*.test.ts', 'app/web/test/*.test.js'], { NODE_V8_COVERAGE: UNIT_DIR });
// the bundle carries its source map, so that what the browser ran is counted against the source files
run('npm', ['run', 'build:web', '--', '--sourcemap=inline']);
run('npx', ['playwright', 'test'], { COVERAGE: '1' });
run('node', ['scripts/coverage.mjs']);
