<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\ClassInfo;
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
 *   hide_cms_page_permissions: 'auto'       — remove page permission fields if CMS module not installed
 *                              true         — always remove page permission fields
 *                              false        — leave page permission fields
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
     * @config 'auto' = remove if CMS not installed, true = always remove, false = leave
     */
    private static bool|string $hide_cms_page_permissions = 'auto';

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
        # Remove Title/Tagline when application_name takes precedence
        if ($this->owner->config()->get('application_name_overrides_title')) {
            $clearFields = $this->owner->config()->get('application_name_clear_fields');
            if ($clearFields) {
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

        # Remove CMS page permission fields when CMS module is not used
        $this->maybeHideCmsPagePermissions($fields);
    }

    /**
     * Remove page-related permission fields from SiteConfig when the CMS module
     * is not installed or when explicitly configured to do so.
     *
     * Removes: CanViewType, CanEditType, CanCreateTopLevelType and their
     * associated group/member selector fields.
     */
    private function maybeHideCmsPagePermissions(FieldList $fields): void
    {
        $hide = $this->owner->config()->get('hide_cms_page_permissions');

        if ($hide === false) {
            return;
        }

        # 'auto' mode: only hide if silverstripe/cms is not installed
        if ($hide === 'auto' && ClassInfo::exists('SilverStripe\\CMS\\Model\\SiteTree')) {
            return;
        }

        # Remove the page permission fields (from InheritedPermissionsExtension + SiteConfig)
        $fields->removeByName([
            'CanViewType',
            'ViewerGroups',
            'ViewerMembers',
            'CanEditType',
            'EditorGroups',
            'EditorMembers',
            'CanCreateTopLevelType',
            'CreateTopLevelGroups',
            'CreateTopLevelMembers',
        ]);

        # Remove the empty Access tab if no fields remain
        $accessTab = $fields->findTab('Root.Access');
        if ($accessTab && $accessTab->Fields()->count() === 0) {
            $fields->removeByName('Access');
        }
    }
}
