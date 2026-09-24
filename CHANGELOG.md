# Changelog

## 2.0.0 (unreleased)

Silverstripe 5 and 6. Silverstripe 4 is dropped, which is why this is a major release; there are no
other breaking changes. See [UPGRADING.md](UPGRADING.md).

### Breaking

- **Silverstripe 4 is no longer supported.** `silverstripe/framework` is now `^5 || ^6` (was
  `^4 || ^5 || ^6`) and PHP `^8.1`. Silverstripe 4 projects keep resolving the `1.x` tags.
- `silverstripe/login-forms` is constrained to `^5 || ^6` instead of `@stable`, so a future
  login-forms major is not picked up before this module has been checked against it.

### Fixed

- **Silverstripe 6: `LoginIconTemplateAvailable()` could fatal when called directly** with "Call to
  undefined method SSViewer::hasTemplate()". Its Silverstripe 6 branch was guarded by
  `ClassInfo::exists()` on the template engine class written with a leading backslash, which never
  matches the class manifest and does not autoload, so whenever the engine had not been loaded yet
  the Silverstripe 5 call ran instead. The login page itself was not affected in practice: the
  module's only caller is the login `AppHeader.ss` template, which runs during a template render,
  when the engine is already loaded. A call from PHP code before any template has rendered did
  fatal. It now asks the injected `TemplateEngine` service, which also honours a project that
  replaces the template engine.
- **Silverstripe 6: `application_name_overrides_title` did nothing.** It relied on SiteConfig's
  `updateCurrentSiteConfig` hook, which `silverstripe/siteconfig` 6 no longer calls, so
  `$SiteConfig.Title` kept showing the stored title.
- **Silverstripe 5: `application_name_overrides_title` could overwrite the stored site title.**
  The override was assigned after the record loaded, which marked it as changed, so any `write()`
  of the current SiteConfig - for example saving Settings with the Title field removed - stored the
  application name as the title.

  Both title-override fixes apply the override while the record is loaded (DataObject's
  `augmentHydrateFields` extension point), on both majors. It no longer marks the record as changed
  and is never written back, including by `forceChange()->write()`, which marks every field as
  changed: the extension writes the stored title in that case (`onBeforeWrite()`) and re-applies
  the override in memory afterwards. A title typed in Settings (with
  `application_name_clear_fields: false`) is still stored.
  `SiteConfigBrandingExtension::updateCurrentSiteConfig()` is kept as a deprecated no-op.

### Added

- A behavioural test suite (`tests/`) covering both extensions, every config option and the
  rendered login page, run in CI against Silverstripe 5 and 6.
- README: requirements, installation, a version compatibility table, a reference table of the
  login branding options, and the previously undocumented `hide_cms_page_permissions` option.
- `funding` in `composer.json`.

### Open

- Issue #1 (fall back to `SiteConfig.Title` / `application_name` when `app_brand` is not set) is a
  feature request and is not part of this release.

## 1.1.0

- `hide_cms_page_permissions` config: remove page permission fields from SiteConfig.

## 1.0.0

- `SiteConfigBrandingExtension`: optionally use `LeftAndMain.application_name` as the admin site
  name instead of `SiteConfig.Title`.
