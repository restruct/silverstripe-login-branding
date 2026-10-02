import { test, expect } from './support';

// The login-forms theme, de-branded: the module puts its own login-forms theme first
// (themes/login-forms: AppHeader.ss and SilverStripeLogo.ss), which renders an icon (and the
// optional app_brand) above the form and the built_by / powered_by credits below it.

for (const [name, path] of [
    ['login', '/Security/login'],
    ['lost password', '/Security/lostpassword'],
] as const) {
    test(`the ${name} page shows the module's header and credits instead of the Silverstripe branding`, async ({ page, baseURL }) => {
        const response = await page.goto(path);
        expect(response?.status()).toBe(200);

        // Header: the built-in shield-lock icon (no project LoginIcon.ss), linked to the site root.
        const brand = page.locator('header.app-brand');
        await expect(brand).toHaveCount(1);
        const icon = brand.locator('a.login-icon');
        await expect(icon.locator('svg.bi-shield-lock')).toBeVisible();
        expect(new URL((await icon.getAttribute('href'))!).href).toBe(new URL('/', baseURL).href);
        // app_brand is unset by default: icon only, no brand text.
        await expect(brand.locator('.app-brand__text')).toHaveCount(0);
        // login-forms' own form header is hidden by the module's stylesheet.
        for (const header of await page.locator('.login-form__header').all()) {
            await expect(header).toBeHidden();
        }

        // Credits: the unconfigured built_by hint, a line break, and the default powered_by line.
        // This is where login-forms shows its Silverstripe logo; the module's SilverStripeLogo.ss
        // replaces it, so there is no logo image left.
        const credits = page.locator('.app-credits');
        await expect(credits).toHaveCount(1);
        await expect(credits.locator('code')).toHaveText('Set config-value: SecurityBrandingExtension.built_by');
        await expect(credits).toContainText('Powered by Silverstripe');
        await expect(credits.locator('a', { hasText: 'Silverstripe' })).toHaveAttribute('href', 'https://silverstripe.org');
        await expect(credits.locator('br')).toHaveCount(1);
        await expect(page.locator('footer img, footer svg')).toHaveCount(0);
    });
}

test('the branded login form still logs in', async ({ page }) => {
    await page.goto('/Security/login?BackURL=/admin');
    await page.locator('input[name="Email"]').fill('admin');
    await page.locator('input[name="Password"]').fill('admin');
    await page.locator('[name="action_doLogin"]').click();
    await expect(page).toHaveURL(/\/admin/);
    await expect(page.locator('.cms-menu')).toBeVisible();
});
