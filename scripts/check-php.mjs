// `npm run check:php`: the checks of the PHP side (server/), with the bridge running for the tests that ask it.
// Coding standard, static analysis at the strictest level, then the tests; a skipped test fails the run.
// PHP 8.4 is used from this machine when it is there with a coverage driver (pcov or Xdebug); otherwise from an image
// built from server/docker/checks.Dockerfile. The run fails under 80% of the lines of server/src run by the tests.
import { spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { once } from 'node:events';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const DOCKERFILE = 'server/docker/checks.Dockerfile';
/** tagged with its Dockerfile, so that a change to the file builds a new image */
const IMAGE = `kabbout-php-checks:${createHash('sha256')
  .update(readFileSync(`${ROOT}${DOCKERFILE}`))
  .digest('hex')
  .slice(0, 12)}`;
/** lowest share of the lines of server/src the tests must run, in percent: the same bar as the JavaScript side */
const MINIMUM = 80;
const CLOVER = 'var/coverage/clover.xml';

const local =
  spawnSync('php', ['-r', 'exit(PHP_VERSION_ID >= 80401 && (extension_loaded("pcov") || extension_loaded("xdebug")) ? 0 : 1);']).status === 0;
const uid = `${process.getuid()}:${process.getgid()}`;
/** runs a PHP tool of server/vendor/bin; in the container, the repository is mounted where it is on this machine */
function php(args, env = {}) {
  const [command, argv] = local
    ? ['php', args]
    : [
        'docker',
        [
          'run',
          '--rm',
          '--network',
          'host',
          '-u',
          uid,
          '-v',
          `${ROOT}:${ROOT}`,
          '-w',
          `${ROOT}server`,
          ...Object.entries(env).flatMap(([k, v]) => ['-e', `${k}=${v}`]),
          IMAGE,
          'php',
          ...args,
        ],
      ];
  const { status } = spawnSync(command, argv, { cwd: `${ROOT}server`, stdio: 'inherit', env: { ...process.env, ...env } });
  return status === 0;
}

/** the dependencies of server/, installed on the first run and whenever the lock file has moved */
function installed() {
  if (
    existsSync(`${ROOT}server/vendor/autoload.php`) &&
    statSync(`${ROOT}server/vendor/autoload.php`).mtimeMs >= statSync(`${ROOT}server/composer.lock`).mtimeMs
  )
    return true;
  const install = ['install', '--no-interaction', '--no-progress'];
  const [command, argv] =
    spawnSync('composer', ['--version']).status === 0 && local
      ? ['composer', install]
      : [
          'docker',
          [
            'run',
            '--rm',
            '-u',
            uid,
            '-e',
            'COMPOSER_HOME=/tmp/composer',
            '-v',
            `${ROOT}server:/app`,
            '-w',
            '/app',
            'composer:2',
            'composer',
            ...install,
          ],
        ];
  return spawnSync(command, argv, { cwd: `${ROOT}server`, stdio: 'inherit' }).status === 0;
}

/** Starts the bridge on a free port and gives its address once it listens; fails if it stops before. */
async function startBridge() {
  const child = spawn('node', ['bridge/src/main.ts'], { cwd: ROOT, env: { ...process.env, BRIDGE_PORT: '0' }, stdio: ['ignore', 'pipe', 'inherit'] });
  const listening = once(child.stdout, 'data').then(([line]) => /http:\/\/\S+/.exec(String(line))?.[0]);
  const stopped = once(child, 'exit').then(([code]) => {
    throw new Error(`the bridge stopped before listening (exit ${code})`);
  });
  const url = await Promise.race([listening, stopped]);
  if (!url) throw new Error('the bridge did not say where it listens');
  return { child, url };
}

/** the image of the checks, built once: PHP 8.4 and pcov */
const imageReady = () =>
  local ||
  spawnSync('docker', ['image', 'inspect', IMAGE], { stdio: 'ignore' }).status === 0 ||
  spawnSync('docker', ['build', '-t', IMAGE, '-f', DOCKERFILE, 'server/docker'], { cwd: ROOT, stdio: 'inherit' }).status === 0;

/** The share of the statements of server/src the tests ran, read from the report PHPUnit wrote. */
function coverageIsEnough() {
  const metrics = /<metrics files="\d+"[^>]*\bstatements="(\d+)" coveredstatements="(\d+)"/.exec(readFileSync(`${ROOT}server/${CLOVER}`, 'utf8'));
  if (!metrics) throw new Error('no coverage total in the report');
  const pct = (100 * Number(metrics[2])) / Number(metrics[1]);
  const enough = pct >= MINIMUM;
  console[enough ? 'log' : 'error'](
    `PHP coverage: ${pct.toFixed(2)}% of ${metrics[1]} lines, ${enough ? 'at least' : 'under'} the ${MINIMUM}% required.`,
  );
  return enough;
}

/** each check in turn, stopping at the first that fails */
const checks = (bridgeUrl) =>
  php(['vendor/bin/php-cs-fixer', 'check', '--diff']) &&
  php(['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G']) &&
  php(['vendor/bin/phpunit', '--fail-on-skipped', '--coverage-text', '--coverage-clover', CLOVER], {
    BRIDGE_URL: bridgeUrl,
    XDEBUG_MODE: 'coverage',
  }) &&
  coverageIsEnough();

if (!imageReady() || !installed()) process.exit(1);
const bridge = await startBridge();
try {
  process.exitCode = checks(bridge.url) ? 0 : 1;
} finally {
  bridge.child.kill();
}
