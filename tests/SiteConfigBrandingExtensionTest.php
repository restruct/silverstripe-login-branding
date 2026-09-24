<?php

namespace Restruct\LoginBranding\Tests;

use Restruct\SilverStripe\AdminBranding\SiteConfigBrandingExtension;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Covers the SiteConfig half of the module: LeftAndMain.application_name overriding
 * SiteConfig.Title, and the Settings fields it removes.
 *
 * The host project installs silverstripe/cms, so the 'auto' mode of hide_cms_page_permissions is
 * exercised on its "CMS present, keep the fields" branch only; the CMS-absent branch needs a host
 * without silverstripe/cms and is not covered here.
 */
class SiteConfigBrandingExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** The page-permission fields hide_cms_page_permissions removes. */
    private const PERMISSION_FIELDS = [
        'CanViewType',
        'ViewerGroups',
        'ViewerMembers',
        'CanEditType',
        'EditorGroups',
        'EditorMembers',
        'CanCreateTopLevelType',
        'CreateTopLevelGroups',
        'CreateTopLevelMembers',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A stored title that differs from the application name, so an override is observable.
        $config = SiteConfig::current_site_config();
        $config->Title = 'Stored Title';
        $config->write();

        Config::modify()->set(LeftAndMain::class, 'application_name', 'Branded App');
    }

    public function testExtensionIsAppliedToSiteConfig()
    {
        $this->assertTrue(SiteConfig::has_extension(SiteConfigBrandingExtension::class));
    }

    public function testConfigDefaults()
    {
        $this->assertFalse(SiteConfig::config()->get('application_name_overrides_title'));
        $this->assertTrue(SiteConfig::config()->get('application_name_clear_fields'));
        $this->assertSame('auto', SiteConfig::config()->get('hide_cms_page_permissions'));
    }

    public function testTitleIsNotOverriddenByDefault()
    {
        $this->assertSame('Stored Title', SiteConfig::current_site_config()->Title);
    }

    /**
     * Regression: on Silverstripe 6 the override silently did nothing. It hung off the
     * `updateCurrentSiteConfig` hook, which SiteConfig::current_site_config() no longer calls in
     * silverstripe/siteconfig 6, so $SiteConfig.Title kept showing the stored title.
     */
    public function testApplicationNameOverridesTitleOnCurrentSiteConfig()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);

        $this->assertSame('Branded App', SiteConfig::current_site_config()->Title);
        // Templates read the value through obj(), not the magic getter.
        $this->assertSame('Branded App', SiteConfig::current_site_config()->obj('Title')->getValue());
    }

    public function testOverrideIsInMemoryOnly()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);

        $config = SiteConfig::current_site_config();
        $this->assertFalse($config->isChanged('Title'), 'override must not mark the record dirty');
        $config->write();

        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', false);
        $this->assertSame('Stored Title', SiteConfig::get()->byID($config->ID)->Title);
    }

    public function testEmptyApplicationNameLeavesTitleAlone()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);
        Config::modify()->set(LeftAndMain::class, 'application_name', '');

        $this->assertSame('Stored Title', SiteConfig::current_site_config()->Title);
    }

    public function testFieldsAreLeftAloneWithoutOverride()
    {
        // clear_fields defaults to true, but only applies when the override is on.
        $fields = $this->cmsFields();

        $this->assertNotNull($fields->dataFieldByName('Title'));
        $this->assertNotNull($fields->dataFieldByName('Tagline'));
    }

    public function testClearFieldsTrueRemovesTitleAndTagline()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);

        $fields = $this->cmsFields();

        $this->assertNull($fields->dataFieldByName('Title'));
        $this->assertNull($fields->dataFieldByName('Tagline'));
        // The (now empty) Main tab stays: removing it is what 'tab' mode is for.
        $this->assertNotNull($fields->findTab('Root.Main'));
    }

    public function testClearFieldsFalseKeepsTitleAndTagline()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);
        Config::modify()->set(SiteConfig::class, 'application_name_clear_fields', false);

        $fields = $this->cmsFields();

        $this->assertNotNull($fields->dataFieldByName('Title'));
        $this->assertNotNull($fields->dataFieldByName('Tagline'));
    }

    public function testClearFieldsTabRemovesTheEmptyMainTab()
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);
        Config::modify()->set(SiteConfig::class, 'application_name_clear_fields', 'tab');

        $fields = $this->cmsFields();

        $this->assertNull($fields->dataFieldByName('Title'));
        $this->assertNull($fields->findTab('Root.Main'));
        $this->assertNotNull($fields->findTab('Root.Access'), 'other tabs must survive');
    }

    public function testPagePermissionsKeptInAutoModeWhenCmsIsInstalled()
    {
        $fields = $this->cmsFields();

        foreach (self::PERMISSION_FIELDS as $name) {
            $this->assertNotNull($fields->dataFieldByName($name), "$name should be kept");
        }
        $this->assertNotNull($fields->findTab('Root.Access'));
    }

    public function testPagePermissionsRemovedWhenForced()
    {
        Config::modify()->set(SiteConfig::class, 'hide_cms_page_permissions', true);

        $fields = $this->cmsFields();

        foreach (self::PERMISSION_FIELDS as $name) {
            $this->assertNull($fields->dataFieldByName($name), "$name should be removed");
        }
        // Access holds nothing else on a stock install, so the emptied tab goes too.
        $this->assertNull($fields->findTab('Root.Access'));
    }

    public function testPagePermissionsKeptWhenDisabled()
    {
        Config::modify()->set(SiteConfig::class, 'hide_cms_page_permissions', false);

        $fields = $this->cmsFields();

        foreach (self::PERMISSION_FIELDS as $name) {
            $this->assertNotNull($fields->dataFieldByName($name), "$name should be kept");
        }
    }

    private function cmsFields(): FieldList
    {
        return SiteConfig::current_site_config()->getCMSFields();
    }
}
