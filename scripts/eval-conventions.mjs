// `npm run eval:conventions`: reads the sentences of eval/conventions/sentences.json as a player's would
// be and prints how often the card that comes back is the expected one, overall and for each way of
// writing, with the model and the date. It runs in the server's own image, with the AI settings of .env
// (ANTHROPIC_API_KEY, KABBOUT_AI_MODEL): what is measured is what players get. Each sentence is one
// call to the model, on your key.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const { status } = spawnSync(
  'docker',
  [
    'compose',
    'run',
    '--rm',
    '--build',
    '-v',
    `${ROOT}eval/conventions:/eval/conventions:ro`,
    'app',
    'php',
    'bin/console',
    'kabbout:conventions:eval',
    '/eval/conventions/sentences.json',
  ],
  { cwd: ROOT, stdio: 'inherit' },
);
process.exit(status ?? 1);
