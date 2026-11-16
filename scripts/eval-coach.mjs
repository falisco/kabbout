// `npm run eval:coach`: asks the coach about the fifteen deals of eval/coach/ and prints how often it
// names the expected mistake, with the model and the date. It runs in the server's own image, with the
// AI settings of .env (KABBOUT_AI): the coach measured is the one players get. Each deal is one or two
// calls to the model, on your key.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const { status } = spawnSync(
  'docker',
  ['compose', 'run', '--rm', '--build', '-v', `${ROOT}eval/coach:/eval/coach:ro`, 'app', 'php', 'bin/console', 'kabbout:coach:eval', '/eval/coach'],
  { cwd: ROOT, stdio: 'inherit' },
);
process.exit(status ?? 1);
