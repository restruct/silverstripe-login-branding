# Upgrading

## 1.x to 2.0

2.0 supports Silverstripe 5 and 6 on PHP 8.1+. The only breaking change is that Silverstripe 4 is
dropped: on Silverstripe 4, keep a `^1` (or `^0.1`) constraint, and Composer will not offer 2.0.

On Silverstripe 5 and 6, change your constraint to `^2`:

```bash
composer require restruct/silverstripe-login-branding:^2
```

No configuration, template or class names changed. Nothing to edit in `_config` or `templates`.

### Behaviour you may notice

- **Silverstripe 6, `application_name_overrides_title: true`:** the override now actually applies.
  In 1.x it silently did nothing on Silverstripe 6, so `$SiteConfig.Title` (admin menu, and front
  end templates that use it) will now show `LeftAndMain.application_name` instead of the title
  stored in Settings. If you set the option but relied on the stored title showing, remove the
  option.
- **Silverstripe 5, `application_name_overrides_title: true`:** 1.x could write the application
  name into the stored site title when the current SiteConfig was saved. 2.0 never writes it. If
  your stored title was overwritten that way, the database still holds the application name as the
  title. It only shows if you ever turn the override off; to correct it, turn the override off,
  fix the title in Settings, and turn it back on.
- **`application_name_overrides_title: true`, both majors:** the override now applies to every
  SiteConfig record loaded from the database, not only the one returned by
  `SiteConfig::current_site_config()`. Code that loads SiteConfig another way (a `DataList`,
  `get_by_id()`) and reads `Title` now also gets `application_name`. With
  `application_name_clear_fields: false`, a title typed in Settings is saved but not shown while
  the override is on.
- `SiteConfigBrandingExtension::updateCurrentSiteConfig()` no longer does anything. Nothing should
  call it directly; if your code does, the override is now applied when SiteConfig is loaded.
