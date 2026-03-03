<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Extension on SiteConfig to optionally use LeftAndMain.application_name
 * as the authoritative admin site name instead of the editable SiteConfig.Title.
 *
 * Config:
 *   application_name_overrides_title: true  — use application_name for $SiteConfig.Title
 *   application_name_clear_fields: false    — leave Title/Tagline fields in SiteConfig
 *                                   true    — remove Title + Tagline fields
 *                                   'tab'   — remove fields + remove empty Main tab (if other tabs exist)
 */
class SiteConfigBrandingExtension extends Extension
{
    /**
     * @config Use LeftAndMain.application_name instead of SiteConfig.Title in admin nav
     */
    private static bool $application_name_overrides_title = false;

    /**
     * @config false = leave fields, true = remove Title+Tagline, 'tab' = also remove empty Main tab
     */
    private static bool|string $application_name_clear_fields = true;

    /**
     * Modifies SiteConfig in-memory so $SiteConfig.Title returns application_name.
     * Called via SiteConfig::current_site_config() extension hook.
     */
    public function updateCurrentSiteConfig(SiteConfig $siteConfig): void
    {
        if (!$this->owner->config()->get('application_name_overrides_title')) {
            return;
        }

        $appName = LeftAndMain::config()->get('application_name');
        if ($appName) {
            $siteConfig->Title = $appName;
        }
    }

    /**
     * Remove Title + Tagline fields when application_name takes precedence.
     * Optionally remove the empty Main tab when clear_fields is 'tab'.
     */
    public function updateCMSFields(FieldList $fields): void
    {
        if (!$this->owner->config()->get('application_name_overrides_title')) {
            return;
        }

        $clearFields = $this->owner->config()->get('application_name_clear_fields');
        if (!$clearFields) {
            return;
        }

        # Remove the Title + Tagline fields
        $fields->removeByName(['Title', 'Tagline']);

        # 'tab' mode: also remove the Main tab if it's now empty and other tabs exist
        if ($clearFields === 'tab') {
            $mainTab = $fields->findTab('Root.Main');
            /** @var TabSet $rootTabs */
            $rootTabs = $fields->findTab('Root');
            if (
                $mainTab
                && $mainTab->Fields()->count() === 0
                && $rootTabs
                && $rootTabs->Tabs()->count() > 1
            ) {
                $fields->removeByName('Main');
            }
        }
    }
}
