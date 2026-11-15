// What the coverage report measures, shared by the browser tests (which feed it) and scripts/coverage.mjs (which writes it).
import { fileURLToPath } from 'node:url';

export const ROOT = fileURLToPath(new URL('..', import.meta.url));
/** where the unit tests leave V8's raw coverage */
export const UNIT_DIR = 'coverage/.v8-unit';
/** lowest share of lines and of branches the tests must run, in percent */
export const MINIMUM = 80;

/** the dictionaries are data: counting them would lift the figure without testing anything */
const DICTIONARIES = /\/i18n\/(fr|en|ar)\.js$/;
/**
 * The name a source file has in the report, or null when it is not one of the game's sources.
 * The unit tests know a file by its full path; the bundle's source map knows it relative to app/web.
 */
function reportPath(path) {
  const fromBundle = /^src\//.test(path) ? `app/web/${path}` : path;
  const found = /(engine\/src|app\/web\/src)\/.*$/.exec(fromBundle.replaceAll('\\', '/'));
  return found && !DICTIONARIES.test(found[0]) ? found[0] : null;
}
export const isMeasured = (path) => reportPath(path) !== null;

export const coverageOptions = {
  name: 'Kabbout coverage (unit + browser tests)',
  outputDir: 'coverage',
  baseDir: ROOT,
  reports: ['v8', 'console-details'],
  // the unit tests load the sources themselves; the browser loads the bundle, mapped back to them
  entryFilter: (entry) => isMeasured(entry.url) || /\/app\.js$/.test(entry.url),
  sourceFilter: isMeasured,
  // both kinds of test must name a file the same way for their results to add up
  sourcePath: (path) => reportPath(path) ?? path,
  all: { dir: ['engine/src', 'app/web/src'], filter: (path) => isMeasured(path) && path.endsWith('.js') },
};
