import {
    test,
    expect,
    MIN_GAP_MS,
    expectTokenCheck,
    formToken,
    nextTokenCheck,
    notice,
    regainFocus,
    watchTokenChecks,
} from './support';

// The expired login page notice: client/js/expired-notice.js asks Security/checktoken whether the
// page's SecurityID still matches the session when the tab comes back, and shows a notice with a
// refresh link once it does not (SecurityBrandingExtension::onAfterInit() / checktoken()).

test('Security pages carry the notice settings, script and stylesheet', async ({ page }) => {
    const assets: string[] = [];
    page.on('response', (r) => {
        if (/silverstripe-login-branding\/client\//.test(r.url())) {
            assets.push(`${r.status()} ${new URL(r.url()).pathname}`);
        }
    });
    await page.goto('/Security/login');

    const meta = page.locator('head meta[name="login-branding-expired-notice"]');
    await expect(meta).toHaveCount(1);
    expect(new URL((await meta.getAttribute('data-endpoint'))!, page.url()).pathname).toBe('/Security/checktoken');
    await expect(meta).toHaveAttribute('data-token-name', 'SecurityID');
    // expired_notice_interval defaults to 0: no periodic check (each check keeps the session alive).
    await expect(meta).toHaveAttribute('data-interval', '0');
    await expect(meta).toHaveAttribute('data-message', 'This page has expired.');
    await expect(meta).toHaveAttribute('data-link-text', 'Refresh the page to continue.');

    // Deferred, so it never delays rendering the login page (SS5 writes defer="defer", SS6 a bare defer).
    expect(await page.locator('script[src*="client/js/expired-notice.js"]').evaluate((s: HTMLScriptElement) => s.defer)).toBe(true);
    await expect.poll(() => assets.some((a) => /^200 .*\/client\/js\/expired-notice\.js$/.test(a))).toBe(true);
    expect(assets.some((a) => /^200 .*\/client\/css\/expired-notice\.css$/.test(a)), `stylesheet loaded: ${assets}`).toBe(true);
});

test('a page whose token the session still knows shows no notice', async ({ page }) => {
    await page.goto('/Security/login');
    const token = await formToken(page);

    const checked = nextTokenCheck(page);
    await regainFocus(page);
    expect(await expectTokenCheck(await checked, token)).toEqual({ valid: true });

    await expect(notice(page)).toHaveCount(0);
});

test('once the session is gone, coming back shows the notice, and its link reloads to a working form', async ({ page, context }) => {
    await page.goto('/Security/login?BackURL=/admin');
    const staleToken = await formToken(page);

    // The session ends while the page stays open (here: its cookie goes, as when PHP's session
    // garbage collection removes it).
    await context.clearCookies();
    const checked = nextTokenCheck(page);
    await regainFocus(page);
    expect(await expectTokenCheck(await checked, staleToken)).toEqual({ valid: false });

    // The notice: an alert right above the form, with the translated texts and a refresh link.
    await expect(notice(page)).toHaveCount(1);
    await expect(notice(page)).toHaveAttribute('role', 'alert');
    await expect(notice(page)).toHaveText('This page has expired. Refresh the page to continue.');
    const form = page.locator('form#MemberLoginForm_LoginForm');
    expect(await notice(page).evaluate((n) => n.nextElementSibling?.id)).toBe(await form.getAttribute('id'));

    // Once shown, checking stops: the next "tab came back" (after the script's own minimum gap
    // between checks) asks nothing.
    const checks = watchTokenChecks(page);
    await page.waitForTimeout(MIN_GAP_MS + 200);
    await regainFocus(page);
    await page.waitForTimeout(500);
    expect(checks(), 'token checks after the notice').toBe(0);

    // The link reloads the page: a fresh form with a fresh token, and no notice.
    const reloaded = page.waitForEvent('load');
    await notice(page).locator('a').click();
    await reloaded;
    await expect(notice(page)).toHaveCount(0);
    const freshToken = await formToken(page);
    expect(freshToken).not.toBe(staleToken);

    // ...which the session knows again, and which logs in.
    const again = nextTokenCheck(page);
    await regainFocus(page);
    expect(await expectTokenCheck(await again, freshToken)).toEqual({ valid: true });
    await page.locator('input[name="Email"]').fill('admin');
    await page.locator('input[name="Password"]').fill('admin');
    await page.locator('[name="action_doLogin"]').click();
    await expect(page).toHaveURL(/\/admin/);
});
