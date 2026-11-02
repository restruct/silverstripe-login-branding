<?php

namespace Restruct\LbBrowser;

use Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;
use SilverStripe\LoginForms\EnablerExtension;

/**
 * BROWSER-TEST FIXTURE ONLY - the demo states the README screenshots show
 * (tests/browser/screenshots/readme.shot.ts). A request carrying the cookie lb-readme-shot gets,
 * for that request only:
 *
 *  - "stock":   the login page as silverstripe/login-forms renders it without this module (the
 *               module's login theme left out of login_themes, no expired-page notice);
 *  - "branded": the module configured the way a project would, with neutral demo branding: a
 *               project LoginIcon.ss (from tests/browser/screenshots/showcase-theme/), app_brand
 *               and built_by.
 *
 * Without the cookie nothing changes, so the browser specs, which never set it, are unaffected.
 * Registered as a Director middleware by fixtures/_config/readme-showcase.yml. Like the other
 * fixtures it is copied into the scratch host's app/ by the runner, and sits behind
 * tests/browser/_manifest_exclude in the module itself, so no real install ever loads it.
 */
class LbReadmeShowcaseMiddleware implements HTTPMiddleware
{
    public const COOKIE = 'lb-readme-shot';

    /**
     * The module's own login theme, as its _config/config.yml adds it to login_themes.
     */
    public const MODULE_THEME = 'restruct/silverstripe-login-branding:login-forms';

    /**
     * Demo theme with a project-style LoginIcon.ss. A module theme with a leading "/" after the
     * colon is a path inside that module (ThemeResourceLoader::getPath()); the module is installed
     * symlinked, so this resolves to tests/browser/screenshots/showcase-theme of the checkout.
     *
     * Deliberately NOT under fixtures/: the runner copies fixtures/ into the host's app/, and a
     * templates/ dir there is part of the $default theme, so its LoginIcon.ss would replace the
     * default shield-lock icon on every request (and break branding.spec.ts). Under tests/browser/
     * it sits behind _manifest_exclude and is only reached through this explicit theme path.
     */
    public const SHOWCASE_THEME = 'restruct/silverstripe-login-branding:/tests/browser/screenshots/showcase-theme';

    public const BRANDED = [
        # Neutral demo name; app_brand is printed RAW, so plain text is used here.
        'app_brand' => 'Acme Cycles',
        'include_icon' => true,
        'built_by' => 'Built by <a href="https://example.org/" target="_blank">Example Studio</a>',
        # powered_by keeps its default ("Powered by Silverstripe").
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        $variant = Cookie::get(self::COOKIE);
        $themes = (array) Config::inst()->get(EnablerExtension::class, 'login_themes');

        if ($variant === 'stock') {
            # set() replaces the merged list, so the module's theme is really gone for this request.
            Config::modify()->set(
                EnablerExtension::class,
                'login_themes',
                array_values(array_diff($themes, [self::MODULE_THEME]))
            );
            Config::modify()->set(SecurityBrandingExtension::class, 'expired_notice', false);
        } elseif ($variant === 'branded') {
            # First in the list, so its LoginIcon.ss is found before anything else.
            Config::modify()->set(EnablerExtension::class, 'login_themes', array_merge([self::SHOWCASE_THEME], $themes));
            foreach (self::BRANDED as $key => $value) {
                Config::modify()->set(SecurityBrandingExtension::class, $key, $value);
            }
        }

        return $delegate($request);
    }
}
