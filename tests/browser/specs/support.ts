import { test as base, expect, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the login-branding specs.
//
// No fixtures: the module brands the login-forms theme as soon as it is installed, so the specs
// check its defaults (shield-lock icon, no app_brand, the built_by hint, the powered_by line, the
// expired-page notice on). They run as a fresh VISITOR (no saved admin session), which is who sees
// these pages.

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point, page load included. "Failed to load
 * resource" (any 4xx/5xx asset or request) arrives as a console error too, so a missing module
 * script or stylesheet, or a failing token check, is caught here as well.
 */
export const test = base.extend<{ consoleGuard: void }>({
    // A visitor: no cookies, no saved login.
    storageState: { cookies: [], origins: [] },
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** The expired-notice script's own minimum gap between two checks (MIN_GAP_MS in the script). */
export const MIN_GAP_MS = 2000;

/** The page's security token: the SecurityID in the login form. */
export async function formToken(page: Page): Promise<string> {
    return page.locator('form input[name="SecurityID"]').first().inputValue();
}

/**
 * Make the page think its tab came back to the foreground: the script checks the token on window
 * focus (and on visibilitychange / pageshow, which a headless tab does not produce by itself).
 */
export async function regainFocus(page: Page): Promise<void> {
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
}

/** Resolves with the next token-check request (POST Security/checktoken). */
export function nextTokenCheck(page: Page): Promise<Request> {
    return page.waitForRequest((r) => r.method() === 'POST' && /\/Security\/checktoken$/.test(r.url()));
}

/** Collect every token-check request from now on; returns a getter. */
export function watchTokenChecks(page: Page): () => number {
    let n = 0;
    page.on('request', (r) => {
        if (/\/Security\/checktoken$/.test(r.url())) {
            n++;
        }
    });
    return () => n;
}

/** Assert a token check: AJAX POST of the page's token, answered 200 no-store JSON; returns the answer. */
export async function expectTokenCheck(request: Request, token: string): Promise<{ valid: boolean }> {
    expect(['xhr', 'fetch'], 'the check is an AJAX request').toContain(request.resourceType());
    expect(new URLSearchParams(request.postData() ?? '').get('SecurityID'), 'the page token is posted').toBe(token);
    const response = (await request.response())!;
    expect(response.status(), 'checktoken status').toBe(200);
    expect(response.headers()['cache-control'], 'checktoken Cache-Control').toBe('no-store');
    expect(response.headers()['content-type']).toMatch(/^application\/json/);
    return response.json();
}

/** The notice the script inserts above the form. */
export function notice(page: Page) {
    return page.locator('.login-branding-expired-notice');
}
