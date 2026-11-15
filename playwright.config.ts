import { defineConfig, devices } from '@playwright/test';

const PORT = 4173;
// local runs reuse the installed Chrome; CI installs Playwright's Chromium
const chrome = process.env.CI ? {} : { channel: 'chrome' };
const phone = (width: number, height: number) => ({ ...chrome, viewport: { width, height }, isMobile: true, hasTouch: true });

export default defineConfig({
  testDir: 'e2e',
  timeout: 60_000,
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    // CSS animations off: clicks never wait for a card in flight
    reducedMotion: 'reduce',
    // the game follows the browser's language: French here, English in i18n.spec.ts
    locale: 'fr-FR',
    trace: 'retain-on-failure',
  },
  webServer: {
    command: `node scripts/serve.mjs app/web ${PORT}`,
    url: `http://127.0.0.1:${PORT}`,
    reuseExistingServer: !process.env.CI,
  },
  projects: [
    { name: 'desktop', use: { ...chrome, viewport: { width: 1280, height: 800 } } },
    // one phone runs everything: touch, a narrow screen and the dialogs as sheets
    { name: 'phone', use: phone(375, 667) },
    // the other sizes only check that everything fits
    { name: 'phone-small', use: phone(360, 640), testMatch: /responsive/ },
    { name: 'phone-narrow', use: phone(320, 568), testMatch: /responsive/ },
    { name: 'phone-landscape', use: phone(812, 375), testMatch: /responsive/ },
    { name: 'phone-landscape-small', use: phone(667, 375), testMatch: /responsive/ },
    // Safari's engine, the one every browser on an iPhone runs on: layout, sound and whole games
    { name: 'iphone', use: devices['iPhone 13'], testMatch: /responsive|sound|game/ },
  ],
});
