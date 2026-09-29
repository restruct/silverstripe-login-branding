<?php

namespace Restruct\LoginBranding\Tests;

use Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * The expired login page notice (issue #3): the Security/checktoken endpoint that tells the page
 * whether its SecurityID still matches the session, and the page wiring that loads the script.
 */
class ExpiredNoticeTest extends FunctionalTest
{
    # The login page is rendered in some tests, and FunctionalTest's own setUp/tearDown can touch
    # the database (session-manager's LoginSession on logOut()), so do not run without one.
    protected $usesDatabase = true;

    private const ENDPOINT = 'Security/checktoken';

    public function testValidTokenIsReportedValid()
    {
        $this->session()->set($this->tokenName(), 'a-stored-token');

        $response = $this->post(self::ENDPOINT, [$this->tokenName() => 'a-stored-token']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('{"valid":true}', $response->getBody());
    }

    public function testWrongTokenIsReportedInvalid()
    {
        $this->session()->set($this->tokenName(), 'a-stored-token');

        $response = $this->post(self::ENDPOINT, [$this->tokenName() => 'an-old-token']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('{"valid":false}', $response->getBody());
        # The stored token is left alone: the check must not reset or replace it.
        $this->assertSame('a-stored-token', $this->session()->get($this->tokenName()));
    }

    public function testMissingSubmittedTokenIsReportedInvalid()
    {
        $this->session()->set($this->tokenName(), 'a-stored-token');

        $response = $this->post(self::ENDPOINT, ['Other' => 'x']);

        $this->assertSame('{"valid":false}', $response->getBody());
    }

    /**
     * The whole-stack version of "no session": a session without a stored token. If the endpoint
     * generated one (as SecurityToken::getValue() does), SessionMiddleware would write it into the
     * session and FunctionalTest's session would carry it afterwards.
     */
    public function testNoStoredTokenIsInvalidAndStoresNothing()
    {
        $this->assertNull($this->session()->get($this->tokenName()));

        # FunctionalTest disables tokens, which swaps in NullSecurityToken (it never stores
        # anything); enabled, a SecurityToken-based implementation would store one and be caught.
        SecurityToken::enable();
        try {
            $response = $this->post(self::ENDPOINT, [$this->tokenName() => 'a-guess']);
        } finally {
            SecurityToken::disable();
        }

        $this->assertSame('{"valid":false}', $response->getBody());
        $this->assertNull($this->session()->get($this->tokenName()), 'the check must not create a token');
        $this->assertSame([], $this->session()->getAll() ?: [], 'the check must not write to the session');
        $this->assertEmpty($response->getHeader('Set-Cookie'), 'the check must not set a cookie');
    }

    /**
     * A request that arrives WITHOUT a session (no cookie) carries a Session object that is not
     * started. Session::save() only starts a session when data changed, so an untouched,
     * unstarted session after the action means no session is started and no cookie is sent.
     *
     * Inside the test runner every request through the HTTP stack gets an already "started"
     * Session (HTTPRequestBuilder builds it from an array), so this calls the action directly on a
     * request with an unstarted one - registered as THE current request and controller, so a
     * SecurityToken-based implementation would write into exactly this session and be caught.
     */
    public function testRequestWithoutSessionStartsNone()
    {
        $session = new Session(null);
        $request = new HTTPRequest('POST', self::ENDPOINT, [], [$this->tokenName() => 'a-guess']);
        $request->setSession($session);
        $this->assertFalse($session->isStarted());

        $security = Security::create();
        $security->setRequest($request);
        Injector::inst()->registerService($request, HTTPRequest::class);
        $security->pushCurrent();
        # SecurityToken::check() is a no-op when tokens are disabled (FunctionalTest disables them),
        # so enable them to give a token-generating implementation the chance to show itself.
        SecurityToken::enable();
        try {
            $response = $security->checktoken($request);
        } finally {
            SecurityToken::disable();
            $security->popCurrent();
        }

        $this->assertInstanceOf(HTTPResponse::class, $response);
        $this->assertSame('{"valid":false}', $response->getBody());
        $this->assertFalse($session->isStarted(), 'the check must not start a session');
        $this->assertSame([], $session->changedData(), 'the check must leave nothing for Session::save() to write');
    }

    public function testEmptySubmittedTokenWithoutStoredTokenIsInvalid()
    {
        # Both sides empty must not count as a match (hash_equals('', '') is true).
        $response = $this->post(self::ENDPOINT, [$this->tokenName() => '']);

        $this->assertSame('{"valid":false}', $response->getBody());
    }

    public function testResponseIsJsonAndNotCacheable()
    {
        # In the dev environment the framework's own config (framework _config/config.yml) makes
        # the default state 'disabled' at forcing level 3, which already sends no-store and would
        # let the endpoint's own cache handling go untested. Start from a live-like, publicly
        # cacheable state instead: rebuild the middleware with an unforced default, then go public.
        Config::modify()->set(HTTPCacheControlMiddleware::class, 'defaultState', 'enabled');
        Config::modify()->set(HTTPCacheControlMiddleware::class, 'defaultForcingLevel', 0);
        HTTPCacheControlMiddleware::reset();
        HTTPCacheControlMiddleware::singleton()->enableCache()->publicCache();
        $this->assertSame('public', HTTPCacheControlMiddleware::singleton()->getState(), 'precondition');

        $response = $this->post(self::ENDPOINT, [$this->tokenName() => 'x']);

        $this->assertStringStartsWith('application/json', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('no-store', $response->getHeader('Cache-Control'));
    }

    /**
     * The same, without the HTTP stack: on a flushed test run (SS_PHPUNIT_FLUSH=1, as CI runs
     * SS6) HTTPCacheControlMiddleware::process() force-disables caching for every request because
     * the kernel is flushed, which hides whether the endpoint does so itself. Called directly, the
     * endpoint must both send no-store and leave the middleware in the disabled state, so that no
     * later middleware or extension can mark the answer cacheable.
     */
    public function testEndpointDisablesCachingItself()
    {
        Config::modify()->set(HTTPCacheControlMiddleware::class, 'defaultState', 'enabled');
        Config::modify()->set(HTTPCacheControlMiddleware::class, 'defaultForcingLevel', 0);
        HTTPCacheControlMiddleware::reset();
        HTTPCacheControlMiddleware::singleton()->enableCache()->publicCache();

        $request = new HTTPRequest('POST', self::ENDPOINT, [], [$this->tokenName() => 'x']);
        $request->setSession(new Session([]));
        $security = Security::create();
        $security->setRequest($request);
        $response = $security->checktoken($request);

        $this->assertStringContainsString('no-store', (string) $response->getHeader('Cache-Control'));
        $this->assertSame('disabled', HTTPCacheControlMiddleware::singleton()->getState());

        # And a public cache forced AFTER the endpoint ran must not win over it.
        HTTPCacheControlMiddleware::singleton()->publicCache(true);
        HTTPCacheControlMiddleware::singleton()->applyToResponse($response);
        $this->assertStringContainsString('no-store', (string) $response->getHeader('Cache-Control'));
    }

    public function testGetIsRefused()
    {
        $this->session()->set($this->tokenName(), 'a-stored-token');

        $response = $this->get(self::ENDPOINT . '?' . $this->tokenName() . '=a-stored-token');

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertSame('POST', $response->getHeader('Allow'));
        $this->assertStringNotContainsString('valid', (string) $response->getBody());
    }

    public function testLoginPageLoadsTheNotice()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'expired_notice_interval', 120);

        $body = $this->get(Security::login_url())->getBody();

        $this->assertStringContainsString('client/js/expired-notice.js', $body);
        $this->assertStringContainsString('client/css/expired-notice.css', $body);
        $this->assertMatchesRegularExpression('#<meta name="login-branding-expired-notice"[^>]*>#', $body);
        $meta = $this->metaTag($body);
        $this->assertStringContainsString('data-endpoint="' . Security::singleton()->Link('checktoken') . '"', $meta);
        $this->assertStringContainsString('data-token-name="SecurityID"', $meta);
        $this->assertStringContainsString('data-interval="120"', $meta);
        $this->assertStringContainsString('data-message="This page has expired."', $meta);
        $this->assertStringContainsString('data-link-text="Refresh the page to continue."', $meta);
    }

    public function testNoPeriodicCheckByDefault()
    {
        # Each check resumes the session and so keeps it alive; polling must be opt-in.
        $this->assertStringContainsString('data-interval="0"', $this->metaTag($this->get(Security::login_url())->getBody()));
    }

    public function testNegativeIntervalIsClampedToZero()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'expired_notice_interval', -30);

        $this->assertStringContainsString('data-interval="0"', $this->metaTag($this->get(Security::login_url())->getBody()));
    }

    public function testNoPeriodicCheckWhenTheCmsKeepaliveIsOff()
    {
        if (!class_exists(\SilverStripe\Admin\LeftAndMain::class)) {
            $this->markTestSkipped('silverstripe/admin is not installed.');
        }
        Config::modify()->set(SecurityBrandingExtension::class, 'expired_notice_interval', 120);

        Config::modify()->set(\SilverStripe\Admin\LeftAndMain::class, 'session_keepalive_ping', true);
        $this->assertStringContainsString('data-interval="120"', $this->metaTag($this->get(Security::login_url())->getBody()));

        Config::modify()->set(\SilverStripe\Admin\LeftAndMain::class, 'session_keepalive_ping', false);
        $this->assertStringContainsString('data-interval="0"', $this->metaTag($this->get(Security::login_url())->getBody()));
    }

    public function testNoticeTextsAreEscapedInTheMetaTag()
    {
        # Translations are data, not markup: a translator's quote or angle bracket must not break
        # out of the attribute. The real provider is wrapped (SapphireTest nests the Injector per
        # test, so the swap does not outlive it); anonymous, so it never enters the class manifest.
        $provider = \SilverStripe\i18n\i18n::getMessageProvider();
        \SilverStripe\Core\Injector\Injector::inst()->registerService(
            new class ($provider) implements \SilverStripe\i18n\Messages\MessageProvider {
                public function __construct(private \SilverStripe\i18n\Messages\MessageProvider $inner)
                {
                }

                public function translate($entity, $default, $injection)
                {
                    if (str_ends_with($entity, '.EXPIRED_NOTICE')) {
                        return 'Say "hi" <b>now</b>';
                    }
                    return $this->inner->translate($entity, $default, $injection);
                }

                public function pluralise($entity, $default, $injection, $count)
                {
                    return $this->inner->pluralise($entity, $default, $injection, $count);
                }
            },
            \SilverStripe\i18n\Messages\MessageProvider::class
        );

        $body = $this->get(Security::login_url())->getBody();

        $meta = $this->metaTag($body);
        $this->assertStringContainsString('data-message="Say &quot;hi&quot; &lt;b&gt;now&lt;/b&gt;"', $meta);
        $this->assertStringNotContainsString('<b>now</b>', $body);
    }

    public function testDisabledNoticeLoadsNothingAndClosesTheEndpoint()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'expired_notice', false);

        $body = $this->get(Security::login_url())->getBody();
        $this->assertStringNotContainsString('expired-notice.js', $body);
        $this->assertStringNotContainsString('login-branding-expired-notice', $body);

        $this->session()->set($this->tokenName(), 'a-stored-token');
        $response = $this->post(self::ENDPOINT, [$this->tokenName() => 'a-stored-token']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testEndpointIsNotThemedByLoginForms()
    {
        # login-forms switches themes for every allowed Security action not listed as excluded.
        $excluded = Config::inst()->get(\SilverStripe\LoginForms\EnablerExtension::class, 'excluded_actions');
        $this->assertContains('checktoken', $excluded);
    }

    private function tokenName(): string
    {
        return SecurityToken::get_default_name();
    }

    private function metaTag(string $body): string
    {
        preg_match('#<meta name="login-branding-expired-notice"[^>]*>#', $body, $m);
        return $m[0] ?? '';
    }
}
