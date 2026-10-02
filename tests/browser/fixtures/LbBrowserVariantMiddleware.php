<?php

namespace Restruct\LbBrowser;

use Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;

/**
 * BROWSER-TEST FIXTURE ONLY - lets one spec see the module CONFIGURED without changing the defaults
 * every other spec checks: a request carrying the cookie lb-browser-variant=configured gets the
 * options below for that request only, as if a project had set them in YAML. Registered as a
 * Director middleware by fixtures/_config/variant.yml.
 *
 * The runner copies tests/browser/fixtures/ into the scratch host's app/; in the module itself it
 * sits behind tests/browser/_manifest_exclude, so no real install ever loads it.
 */
class LbBrowserVariantMiddleware implements HTTPMiddleware
{
    public const COOKIE = 'lb-browser-variant';

    public const CONFIGURED = [
        # HTML on purpose: the template prints app_brand RAW.
        'app_brand' => 'Acme <em>Intranet</em>',
        'include_icon' => false,
        'built_by' => 'Built by <a href="https://example.org/">Example Studio</a>',
        # Empty: no powered-by line, and so no line break between the two credits.
        'powered_by' => '',
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        if (Cookie::get(self::COOKIE) === 'configured') {
            foreach (self::CONFIGURED as $key => $value) {
                Config::modify()->set(SecurityBrandingExtension::class, $key, $value);
            }
        }
        return $delegate($request);
    }
}
