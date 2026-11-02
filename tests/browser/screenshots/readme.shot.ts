import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The demo states come from
// the browser fixture LbReadmeShowcaseMiddleware, switched on by the lb-readme-shot cookie:
// "stock" (login-forms without this module) and "branded" (neutral demo branding). Without the
// cookie the host shows the module's defaults.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

async function variant(context: BrowserContext, baseURL: string, value: 'stock' | 'branded'): Promise<void> {
    await context.addCookies([{ name: 'lb-readme-shot', value, url: baseURL }]);
}

/** Wait until every <img> on the page has loaded and web fonts are ready. */
async function settled(page: Page): Promise<void> {
    await expect
        .poll(() => page.evaluate(() => Array.from(document.images).every((i) => i.complete && i.naturalWidth > 0)), {
            message: 'all images loaded',
        })
        .toBe(true);
    await page.evaluate(() => document.fonts.ready);
    // No focus ring on the autofocused Email field.
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.mouse.move(0, 0);
}

/**
 * The login card: from just above the header down to just below the credits/footer, centred on
 * the form. The rest of the page is empty background at 1280 x 800, which adds nothing.
 */
async function shotLogin(page: Page, file: string): Promise<void> {
    const form = (await page.locator('form').first().boundingBox())!;
    const { top, bottom } = await page.evaluate(() => {
        const els = Array.from(document.querySelectorAll<HTMLElement>('header, form, .app-credits, footer'));
        const boxes = els.map((e) => e.getBoundingClientRect()).filter((b) => b.height > 0);
        return { top: Math.min(...boxes.map((b) => b.top)), bottom: Math.max(...boxes.map((b) => b.bottom)) };
    });
    const width = 560;
    const x = Math.max(0, Math.round(form.x + form.width / 2 - width / 2));
    const y = Math.max(0, Math.floor(top - 40));
    await page.screenshot({
        path: path.join(IMAGES, file),
        clip: { x, y, width, height: Math.ceil(bottom + 40) - y },
    });
}

test('stock login-forms page, without this module', async ({ page, context, baseURL }) => {
    await variant(context, baseURL!, 'stock');
    await page.goto('/Security/login');
    // login-forms' own form header ("Log in") is back and the module's credits are gone: the
    // module's theme is not in use.
    await expect(page.locator('.login-form__header')).toBeVisible();
    await expect(page.locator('.app-credits')).toHaveCount(0);
    await settled(page);
    await shotLogin(page, 'login-stock.png');
});

test('module defaults, nothing configured', async ({ page }) => {
    await page.goto('/Security/login');
    await expect(page.locator('header.app-brand svg.bi-shield-lock')).toBeVisible();
    await settled(page);
    await shotLogin(page, 'login-defaults.png');
});

test('branded: project LoginIcon.ss, app_brand and built_by', async ({ page, context, baseURL }) => {
    await variant(context, baseURL!, 'branded');
    await page.goto('/Security/login');
    await expect(page.locator('header.app-brand svg.bi-bicycle')).toBeVisible();
    await expect(page.locator('.app-brand__text')).toHaveText('Acme Cycles');
    await settled(page);
    await shotLogin(page, 'login-branded.png');
});

test('expired login page notice', async ({ page, context, baseURL }) => {
    await variant(context, baseURL!, 'branded');
    await page.goto('/Security/login');
    // The session ends while the page stays open: only the session cookie goes, the demo
    // branding cookie stays.
    await context.clearCookies({ name: /SESSID$/ });
    const checked = page.waitForResponse((r) => /\/Security\/checktoken$/.test(r.url()));
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    expect(await (await checked).json()).toEqual({ valid: false });
    await expect(page.locator('.login-branding-expired-notice')).toBeVisible();
    await settled(page);
    await shotLogin(page, 'login-expired-notice.png');
});
