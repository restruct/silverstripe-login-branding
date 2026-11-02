import { defineConfig, devices } from '@playwright/test';

// README screenshots, NOT tests: writes docs/images/*.png from a Silverstripe 6 host with the
// browser fixtures loaded. Kept apart from ../playwright.config.ts on purpose: the browser tests
// (and CI) run that config, whose testDir is ../specs, and must never rewrite docs/images/.
// How to run: README.md next to this file.

// The same BROWSER_TARGET_URLS the runner passes, but only ONE target is used: two targets would
// overwrite each other's images. ss6 when present, else the first one.
const targets = (process.env.BROWSER_TARGET_URLS ?? '')
    .split(',')
    .map((pair) => pair.trim())
    .filter(Boolean)
    .map((pair) => {
        const [name, url] = pair.split('=');
        return { name, url };
    });
const target = targets.find((t) => t.name === 'ss6') ?? targets[0];

if (!target) {
    throw new Error(
        'BROWSER_TARGET_URLS is empty. Run through tools/browser/run.sh (see screenshots/README.md), ' +
            'or set it by hand, e.g. BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8890"',
    );
}

export default defineConfig({
    testDir: '.',
    testMatch: /.*\.shot\.ts/,
    outputDir: '../test-results',
    retries: 0,
    workers: 1,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list']],
    use: {
        ...devices['Desktop Chrome'],
        // 1x, light mode: the PNGs are the size a README shows them at. The login pages are seen
        // by visitors, so no saved login is needed (and no login setup project).
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 1,
        colorScheme: 'light',
        storageState: { cookies: [], origins: [] },
        baseURL: target.url,
        screenshot: 'only-on-failure',
    },
});
