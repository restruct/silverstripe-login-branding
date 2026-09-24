<?php

namespace Restruct\LoginBranding\Tests;

use Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\LoginForms\EnablerExtension;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Security\Security;

/**
 * Covers the Security-controller half of the module: the extension is applied, its config options
 * reach the template helpers, and the login page it themes actually renders the configured
 * branding on the Silverstripe major under test.
 */
class SecurityBrandingExtensionTest extends FunctionalTest
{
    // The login page is rendered for real, which needs a database behind the Security controller.
    protected $usesDatabase = true;

    /**
     * Theme identifier for the fixture theme under tests/fixture-theme, which supplies a
     * project-style LoginIcon.ss. A module theme with a leading "/" after the colon resolves to a
     * path inside the module (ThemeResourceLoader::getPath()).
     */
    private const FIXTURE_THEME = 'restruct/silverstripe-login-branding:/tests/fixture-theme';

    /**
     * SSViewer's active themes are a plain static, which SapphireTest does NOT restore between
     * tests (it restores Config only), so a test that sets them must put them back.
     */
    private array $themesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->themesBefore = \SilverStripe\View\SSViewer::get_themes();
    }

    protected function tearDown(): void
    {
        \SilverStripe\View\SSViewer::set_themes($this->themesBefore);
        parent::tearDown();
    }

    public function testExtensionIsAppliedToSecurity()
    {
        $this->assertTrue(Security::has_extension(SecurityBrandingExtension::class));
    }

    public function testLoginThemeIsInsertedBeforeTheLoginFormsTheme()
    {
        // Our AppHeader.ss / SilverStripeLogo.ss only win if our theme sits BEFORE login-forms' own
        // theme in the cascade; after it, the module silently does nothing.
        $themes = Config::inst()->get(EnablerExtension::class, 'login_themes');
        $ours = array_search('restruct/silverstripe-login-branding:login-forms', $themes, true);
        $theirs = array_search('silverstripe/login-forms:login-forms', $themes, true);

        $this->assertNotFalse($ours, 'module theme missing from login_themes');
        $this->assertNotFalse($theirs, 'login-forms theme missing from login_themes');
        $this->assertLessThan($theirs, $ours);
    }

    public function testConfigDefaults()
    {
        $ext = $this->security();

        $this->assertTrue((bool) $ext->IncludeLoginIcon());
        $this->assertNull($ext->AppBrand());
        $this->assertFalse((bool) $ext->UseAppBrandTemplate());
        $this->assertStringContainsString(
            'SecurityBrandingExtension.built_by',
            $ext->BrandingFragment('built_by')->getValue(),
            'unconfigured built_by should tell the developer what to set'
        );
        $this->assertStringContainsString('silverstripe.org', $ext->BrandingFragment('powered_by')->getValue());
    }

    public function testConfigOptionsChangeTheHelpers()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'include_icon', false);
        Config::modify()->set(SecurityBrandingExtension::class, 'app_brand', 'Cycle App');
        Config::modify()->set(SecurityBrandingExtension::class, 'use_app_brand_template', true);

        $ext = $this->security();
        $this->assertFalse((bool) $ext->IncludeLoginIcon());
        $this->assertSame('Cycle App', $ext->AppBrand());
        $this->assertTrue((bool) $ext->UseAppBrandTemplate());
    }

    public function testBrandingFragmentIsHtmlAndEmptyWhenUnset()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'built_by', 'Built by <a href="#">Us</a>');
        Config::modify()->set(SecurityBrandingExtension::class, 'powered_by', null);

        $ext = $this->security();
        $built = $ext->BrandingFragment('built_by');
        $this->assertInstanceOf(DBHTMLVarchar::class, $built);
        $this->assertSame('Built by <a href="#">Us</a>', $built->getValue());

        // The template uses `<% if $BrandingFragment('powered_by') %>`, so an unset option must be
        // falsy there, or an empty line and a stray <br> are rendered.
        $this->assertFalse($ext->BrandingFragment('powered_by')->exists());
    }

    public function testLoginIconTemplateAvailableIsFalseWithoutAProjectTemplate()
    {
        $this->assertFalse($this->security()->LoginIconTemplateAvailable());
    }

    /**
     * Regression: on Silverstripe 6 LoginIconTemplateAvailable() could fatal with "Call to undefined
     * method SSViewer::hasTemplate()". Its SS6 branch was guarded by ClassInfo::exists() on the
     * engine class name written with a leading backslash; ClassInfo::exists() does not autoload,
     * and the class-manifest lookup never matches a leading backslash, so whenever the engine had
     * not been loaded yet it fell through to the SS5 call that SS6 removed. (Measured: in a freshly
     * booted SS 6.2.7 app it fatals.)
     *
     * That path cannot be reproduced inside SapphireTest, because the test bootstrap has already
     * loaded the engine. So this pins the fix instead: on SS6 the lookup must go through the
     * injectable TemplateEngine service - which also means a project that swaps the engine is
     * honoured. On the unfixed code the spy below is never asked.
     */
    public function testLoginIconLookupUsesTheInjectedTemplateEngineOnSs6()
    {
        if (!interface_exists(\SilverStripe\View\TemplateEngine::class)) {
            $this->markTestSkipped('Silverstripe 5 has no TemplateEngine; SSViewer::hasTemplate() is covered above.');
        }

        // Anonymous, so no class implementing an SS6-only type ever enters the class manifest,
        // where it would fatal a Silverstripe 5 flush.
        $spy = new class extends \SilverStripe\TemplateEngine\SSTemplateEngine {
            public array $asked = [];

            public function hasTemplate(array|string $templateCandidates): bool
            {
                $this->asked[] = $templateCandidates;
                return true;
            }
        };
        \SilverStripe\Core\Injector\Injector::inst()->registerService($spy, \SilverStripe\View\TemplateEngine::class);

        $this->assertTrue($this->security()->LoginIconTemplateAvailable());
        $this->assertSame([['LoginIcon', 'Includes/LoginIcon']], $spy->asked);
    }

    public function testLoginIconTemplateAvailableFindsAProjectTemplate()
    {
        $this->useLoginThemes([self::FIXTURE_THEME]);

        $this->assertTrue($this->security()->LoginIconTemplateAvailable());
    }

    public function testLoginPageRendersConfiguredBranding()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'app_brand', 'Cycle App');
        Config::modify()->set(SecurityBrandingExtension::class, 'built_by', 'Built by <em>CoolCompany</em>');

        $response = $this->get(Security::login_url());
        $body = $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('class="app-brand__text">Cycle App</h1>', $body);
        // Rendered as HTML, not escaped: built_by is documented as accepting markup.
        $this->assertStringContainsString('Built by <em>CoolCompany</em>', $body);
        $this->assertStringContainsString('Powered by <a href="https://silverstripe.org"', $body);
        // Default icon, since no LoginIcon.ss is available.
        $this->assertStringContainsString('bi-shield-lock', $body);
        $this->assertStringNotContainsString('Set config-value', $body);
    }

    public function testLoginPageUsesProjectLoginIconTemplate()
    {
        $this->useLoginThemes([self::FIXTURE_THEME]);

        $body = $this->get(Security::login_url())->getBody();

        $this->assertStringContainsString('loginbranding-fixture-icon', $body);
        $this->assertStringNotContainsString('bi-shield-lock', $body);
    }

    public function testIncludeIconFalseRemovesTheIcon()
    {
        Config::modify()->set(SecurityBrandingExtension::class, 'include_icon', false);

        $body = $this->get(Security::login_url())->getBody();

        $this->assertStringNotContainsString('class="login-icon"', $body);
        $this->assertStringNotContainsString('bi-shield-lock', $body);
    }

    /**
     * The extension's helpers, reached through the controller it is applied to - which is how the
     * templates reach them too. (Extension is not Injectable on Silverstripe 5, so it has no
     * create() of its own.)
     */
    private function security(): Security
    {
        return Security::create();
    }

    /**
     * Put extra themes at the top of the login theme cascade, as a project would with its own
     * `Before: '#admin-branding'` config block.
     */
    private function useLoginThemes(array $themes): void
    {
        $current = Config::inst()->get(EnablerExtension::class, 'login_themes');
        Config::modify()->set(EnablerExtension::class, 'login_themes', array_merge($themes, $current));
        // LoginIconTemplateAvailable() is also called outside a request in these tests, where the
        // login-forms EnablerExtension has not switched themes yet - so set them directly as well.
        \SilverStripe\View\SSViewer::set_themes(array_merge($themes, $current));
    }
}
