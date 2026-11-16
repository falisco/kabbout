// Lint rules: what Prettier and the type checker do not see. Names that hide one another, loose
// comparisons, things declared and never used, and functions too long to read in one go.
import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';

const MAX_FUNCTION_LINES = 60;

export default tseslint.config(
  {
    ignores: [
      'node_modules/',
      'coverage/',
      'playwright-report/',
      'test-results/',
      'app/web/app.js',
      'app/web/sounds.js',
      'server/vendor/',
      'server/var/',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
    rules: {
      eqeqeq: 'error',
      'prefer-const': 'error',
      'no-shadow': 'off',
      '@typescript-eslint/no-shadow': 'error',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-non-null-assertion': 'error',
      'max-lines-per-function': ['error', { max: MAX_FUNCTION_LINES, skipBlankLines: true, skipComments: true }],
      // storage, audio and clipboard may be refused by the browser: the game then simply goes without
      'no-empty': ['error', { allowEmptyCatch: true }],
    },
  },
  {
    // a test reads as one story per case, and asserts on values it knows are there
    files: ['**/*.test.ts', '**/*.test.js', 'e2e/**'],
    rules: { 'max-lines-per-function': 'off', '@typescript-eslint/no-non-null-assertion': 'off' },
  },
);
