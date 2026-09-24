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
 *   application_name_overrides_title: true  — use application_name for $SiteConfig.Title (in memory, never written, also not by forceChange()->write())
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
     * The stored Title of each record the override was applied to, keyed by the record object.
     * Each value is wrapped in a one-element array: WeakMap::offsetExists() is false for a null
     * value, and a stored Title can be null (an empty Varchar is stored as NULL).
     *
     * Not a config static (config only reads private statics), and a WeakMap so an entry goes away
     * with its record. Kept on the class rather than on the extension instance because the owner a
     * given extension instance points at is not guaranteed to stay the same record.
     */
    protected static ?\WeakMap $storedTitles = null;

    /**
     * Makes $SiteConfig.Title return application_name, in memory only.
     *
     * DataObject::hydrate() extension point (framework 5 and 6): the returned fields are written
     * into the record AND its "original" state while the object is built from the database, so
     * the override never marks the record as changed and a later write() does not persist the
     * application name over the stored title. forceChange()->write() is handled in onBeforeWrite().
     *
     * Only records loaded from the database pass through here. On the single request that first
     * creates the SiteConfig record (normally dev/build), current_site_config() returns the
     * freshly created object and the stored default title shows for that request.
     */
    public function augmentHydrateFields(): array
    {
        if (!$this->owner->config()->get('application_name_overrides_title')) {
            return [];
        }

        $appName = LeftAndMain::config()->get('application_name');
        if (!$appName) {
            return [];
        }

        # Remember the stored title before it is replaced, so onBeforeWrite() can put it back when a
        # forced write would otherwise persist the application name (see there). At this point the
        # record holds the raw database row; the returned override is applied after this returns.
        self::$storedTitles ??= new \WeakMap();
        self::$storedTitles[$this->owner] = [$this->owner->getField('Title')];

        return ['Title' => $appName];
    }

    /**
     * Keeps the override from ever being written back as the stored title.
     *
     * The override is in the record's "original" state, so an ordinary write() does not see Title as
     * changed and leaves the column alone. forceChange() is different: it marks EVERY field changed,
     * so forceChange()->write() (as used by some import, sync and migration code) would store the
     * application name as the site title. When the value about to be written is still the injected
     * application name, the stored title is written instead.
     *
     * A title typed in Settings (application_name_clear_fields: false) differs from the injected value
     * and is written as usual. The one indistinguishable case is typing exactly the application name:
     * that is not stored, which is invisible while the override is on.
     */
    public function onBeforeWrite(): void
    {
        $appName = $this->injectedTitle();
        if ($appName === null || !$this->owner->isChanged('Title')) {
            return;
        }

        if (!self::$storedTitles?->offsetExists($this->owner)) {
            return; // Not hydrated with the override (eg a new record): nothing was injected.
        }

        if ($this->owner->getField('Title') === $appName) {
            $this->owner->setField('Title', self::$storedTitles[$this->owner][0]);
        }
    }

    /**
     * Re-applies the override in memory after a write of a record it was applied to, so the record
     * keeps behaving as it did after loading, and remembers what was actually stored. write() resets
     * the "original" state to the record right after this hook, so the value put back here does not
     * count as a change either.
     */
    public function onAfterWrite(): void
    {
        $appName = $this->injectedTitle();
        if ($appName === null || !self::$storedTitles?->offsetExists($this->owner)) {
            return; // Only records the override was applied to at load; others are left as written.
        }

        self::$storedTitles[$this->owner] = [$this->owner->getField('Title')];
        $this->owner->setField('Title', $appName);
    }

    /**
     * The application name the override injects, or null while the override is off or has no value.
     */
    private function injectedTitle(): ?string
    {
        if (!$this->owner->config()->get('application_name_overrides_title')) {
            return null;
        }

        return LeftAndMain::config()->get('application_name') ?: null;
    }

    /**
     * No longer does anything; kept so existing callers do not break. See augmentHydrateFields().
     *
     * This used to apply the override from SiteConfig::current_site_config()'s
     * `updateCurrentSiteConfig` hook, which had two problems: silverstripe/siteconfig 6 no longer
     * calls that hook, so on Silverstripe 6 the override silently did nothing; and on Silverstripe
     * 5 assigning Title after load marked the record as changed, so any write() of the current
     * SiteConfig (eg saving Settings with the Title field removed) stored the application name as
     * the site title.
     *
     * @deprecated 2.0.0 The override is applied at hydration; this hook is a no-op.
     */
    public function updateCurrentSiteConfig(SiteConfig $siteConfig): void
    {
//        if (!$this->owner->config()->get('application_name_overrides_title')) {
//            return;
//        }
//
//        $appName = LeftAndMain::config()->get('application_name');
//        if ($appName) {
//            $siteConfig->Title = $appName;
//        }
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
