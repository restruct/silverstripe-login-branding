import { test, expect } from './support';

// The header and credits as a project configures them (app_brand, include_icon, built_by,
// powered_by). The options are switched on for this spec's requests only, by a cookie the fixture
// middleware reads (tests/browser/fixtures/LbBrowserVariantMiddleware.php), so the default-state
// specs in branding.spec.ts are unaffected.

test.beforeEach(async ({ context, baseURL }) => {
    await context.addCookies([{ name: 'lb-browser-variant', value: 'configured', url: baseURL! }]);
});

test('configured options: brand text (raw HTML), no icon, own credits without powered_by', async ({ page }) => {
    const response = await page.goto('/Security/login');
    expect(response?.status()).toBe(200);

    const brand = page.locator('header.app-brand');
    // include_icon: false - no icon link at all.
    await expect(brand.locator('a.login-icon')).toHaveCount(0);
    // app_brand is printed RAW, so its markup is real markup.
    await expect(brand.locator('h1.app-brand__text')).toHaveText('Acme Intranet');
    await expect(brand.locator('h1.app-brand__text em')).toHaveText('Intranet');

    // built_by as given; powered_by empty, so no Silverstripe line and no line break.
    const credits = page.locator('.app-credits');
    await expect(credits.locator('a')).toHaveText(['Example Studio']);
    await expect(credits.locator('a')).toHaveAttribute('href', 'https://example.org/');
    await expect(credits).not.toContainText('Powered by');
    await expect(credits.locator('br')).toHaveCount(0);
    await expect(credits.locator('code')).toHaveCount(0);
});
