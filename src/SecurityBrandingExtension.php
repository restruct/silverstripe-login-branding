<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements;

/**
 * Applies to the {@see Security} controller to add & make some configurable branding options available in templates.
 */
class SecurityBrandingExtension
    extends Extension
{
    private static $use_app_brand_template = false;
    private static $include_icon = true;
    private static $app_brand = null;
    private static $built_by = '<code>Set config-value: SecurityBrandingExtension.built_by</code>';
    private static $powered_by = 'Powered by <a href="https://silverstripe.org" target="_blank">Silverstripe</a>';

    /**
     * Show a notice on Security pages (login, lost password, ...) once the page's security token no
     * longer matches the session, so the user refreshes BEFORE typing credentials that the form
     * would then reject with "Your session has expired". See README "Expired login page notice".
     *
     * @config
     * @var bool
     */
    private static $expired_notice = true;

    /**
     * Seconds between token checks while the page is visible; 0 (the default, or less) disables
     * the periodic check. The page always checks when the tab becomes visible, regains focus or is
     * restored from the back/forward cache, which is when a stale page is about to be used.
     *
     * Default 0 because every check carries the session cookie, and Session::init() then calls
     * session_start(), which refreshes the session's garbage-collection timestamp. A periodic check
     * would therefore keep the session alive for as long as the tab stays open and visible, and
     * defeat the site's idle timeout (also on e.g. Security/changepassword). The visibility/focus
     * checks only touch the session when the user comes back, which they would do anyway.
     *
     * Even when set, the periodic check is skipped while LeftAndMain.session_keepalive_ping is
     * false: a site that switched off the CMS keep-alive does not want this one either.
     *
     * @config
     * @var int
     */
    private static $expired_notice_interval = 0;

    /**
     * Merged into the Security controller's own allowed_actions: private statics on an Extension
     * are merged into the config of the class it is applied to. A plain list entry (not
     * 'action' => true), because login-forms' EnablerExtension lower-cases the VALUES of this list
     * to decide which actions to theme.
     *
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'checktoken',
    ];

    /**
     * Security action name of the token check, also used by the client script's endpoint URL.
     */
    public const CHECK_TOKEN_ACTION = 'checktoken';

    /**
     * Name of the <meta> tag that hands the notice's endpoint, texts and interval to the client
     * script. A head tag rather than template markup, so it reaches the page whatever theme (or
     * project override of AppHeader.ss) renders it.
     */
    public const EXPIRED_NOTICE_META = 'login-branding-expired-notice';

    /**
     * Adds the expired-notice script to every page the Security controller renders. The action is
     * not known yet at init (Controller::doInit() runs before the URL is parsed into an action), so
     * this cannot be limited to the form actions - it does not need to be: Requirements only reach
     * rendered HTML, and the script does nothing on a page without a form carrying a SecurityID.
     */
    public function onAfterInit()
    {
        if (!Config::inst()->get(self::class, 'expired_notice')) {
            return;
        }

        $owner = $this->getOwner();
        $interval = $this->expiredNoticeInterval();

        # HTML::createTag() escapes every attribute value, so translated texts are safe here.
        Requirements::insertHeadTags(HTML::createTag('meta', [
            'name' => self::EXPIRED_NOTICE_META,
            'data-endpoint' => $owner->Link(self::CHECK_TOKEN_ACTION),
            'data-token-name' => SecurityToken::get_default_name(),
            'data-interval' => (string) $interval,
            'data-message' => _t(self::class . '.EXPIRED_NOTICE', 'The login page has expired.'),
            'data-link-text' => _t(self::class . '.EXPIRED_NOTICE_REFRESH', 'Refresh the page to log in.'),
        ]), self::EXPIRED_NOTICE_META);

        Requirements::css('restruct/silverstripe-login-branding: client/css/expired-notice.css');
        # defer: the script needs the form in the DOM, and must not delay rendering the login page.
        Requirements::javascript('restruct/silverstripe-login-branding: client/js/expired-notice.js', ['defer' => true]);
    }

    /**
     * The periodic-check interval handed to the page, in seconds; 0 means no periodic check.
     * Negative config values are clamped to 0, and the check is dropped entirely while
     * LeftAndMain.session_keepalive_ping is false, because each check keeps the session alive.
     */
    protected function expiredNoticeInterval(): int
    {
        $interval = max(0, (int) Config::inst()->get(self::class, 'expired_notice_interval'));

        # A string class name plus class_exists(), so this module does not need silverstripe/admin.
        # uninherited, the same way LeftAndMain itself reads it before sending its ping.
        $leftAndMain = 'SilverStripe\\Admin\\LeftAndMain';
        if ($interval > 0 && class_exists($leftAndMain)
            && !Config::inst()->get($leftAndMain, 'session_keepalive_ping', Config::UNINHERITED)
        ) {
            return 0;
        }

        return $interval;
    }

    /**
     * Answers whether the security token a page carries still matches the one in the session, as
     * JSON `{"valid": true|false}` and nothing else.
     *
     * Read-only as far as the TOKEN goes: SecurityToken::getValue() (and so check()/checkRequest()) GENERATES
     * and stores a token when the session has none, which would make SessionMiddleware start a
     * session - and set a cookie - for every anonymous visitor this is asked about. Instead the
     * stored value is read straight from the session under the token's name, which is the key
     * SecurityToken::setValue() writes it under. Session::get() does not start a session, and
     * Session::save() only starts one when data changed, so a request without a session leaves
     * without one.
     *
     * NOT free of side effects on an existing session, though: a request that carries the session
     * cookie makes Session::init() resume the session (session_start()), which refreshes its
     * garbage-collection timestamp - just like the CMS keep-alive ping. That is why the periodic
     * check is off by default (see $expired_notice_interval).
     *
     * POST only, so no cache or proxy replays an answer and the token is not put in a URL (and
     * so not in access logs).
     */
    public function checktoken(HTTPRequest $request): HTTPResponse
    {
        if (!Config::inst()->get(self::class, 'expired_notice')) {
            # httpError() throws an HTTPResponse_Exception, so this never actually returns.
            return $this->getOwner()->httpError(404);
        }

        # An explicit Cache-Control header is kept by HTTPCacheControlMiddleware::applyToResponse(),
        # which only fills in headers the response does not have yet; disableCache() as well, the
        # way Security::ping() does, so no other layer marks the answer cacheable.
        HTTPCacheControlMiddleware::singleton()->disableCache(true);
        $response = HTTPResponse::create()
            ->addHeader('Cache-Control', 'no-store')
            ->addHeader('Content-Type', 'application/json; charset=utf-8');

        if (!$request->isPOST()) {
            return $response
                ->setStatusCode(405)
                ->addHeader('Allow', 'POST')
                ->setBody('');
        }

        $name = SecurityToken::get_default_name();
        $submitted = $request->postVar($name);
        # getSession() throws when a request carries no Session object at all; treat that as "no
        # session" too rather than erroring.
        $stored = $request->hasSession() ? $request->getSession()->get($name) : null;

        # No session, no stored token, or no submitted token -> not valid. hash_equals() compares
        # in constant time, so the answer leaks nothing about how much of a guess matched.
        $valid = is_string($submitted) && $submitted !== ''
            && is_string($stored) && $stored !== ''
            && hash_equals($stored, $submitted);

        return $response->setBody(json_encode(['valid' => $valid]));
    }

    public function IncludeLoginIcon()
    {
        return Config::inst()->get(self::class, 'include_icon');
    }

    public function AppBrand()
    {
        return Config::inst()->get(self::class, 'app_brand');
    }

    public function UseAppBrandTemplate()
    {
        return Config::inst()->get(self::class, 'use_app_brand_template');
    }

    public function LoginIconTemplateAvailable()
    {
        $loginIconTemplates = ['LoginIcon', 'Includes/LoginIcon'];
        // SS6+: template lookup moved from SSViewer to the injectable TemplateEngine service.
        // Detected by the framework's own interface, with an autoloading interface_exists(): the
        // previous guard, ClassInfo::exists() on the engine class name with a leading backslash,
        // does not autoload and never matches the class manifest, so on a request where the
        // engine was not loaded yet it fell through to SSViewer::hasTemplate() and fataled.
        // Asking the Injector (not SSTemplateEngine directly) also honours a project that swaps
        // the template engine.
//        if(ClassInfo::exists('\SilverStripe\TemplateEngine\SSTemplateEngine')){
//            return \SilverStripe\TemplateEngine\SSTemplateEngine::singleton()->hasTemplate($loginIconTemplates);
//        }
        if (interface_exists(\SilverStripe\View\TemplateEngine::class)) {
            return Injector::inst()->get(\SilverStripe\View\TemplateEngine::class)->hasTemplate($loginIconTemplates);
        }
        // SS5 fallback
        return \SilverStripe\View\SSViewer::hasTemplate($loginIconTemplates);
    }

    public function BrandingFragment($option)
    {
        return DBHTMLVarchar::create()->setValue( Config::inst()->get(self::class, $option) );
    }
}
