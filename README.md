# Silverstripe Login-form (de)branding

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

Reduces the Silverstripe branding of the [login forms module](https://github.com/silverstripe/silverstripe-login-forms) and makes it easily configurable.
Optionally, it also makes `LeftAndMain.application_name` the admin's site name instead of the editable `SiteConfig.Title` (see [SiteConfig Title Override](#siteconfig-title-override)).

## Requirements

* Silverstripe 5 or 6, with `silverstripe/login-forms`
* PHP 8.1 or newer
* `silverstripe/siteconfig` for the SiteConfig title override (optional; the extension is only applied when it is installed)

## Installation

```
composer require restruct/silverstripe-login-branding
```

The branding applies to the login forms as soon as the module is installed; everything below is optional configuration.

## Version compatibility

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `main` | `2.x` | `^5 \|\| ^6` | `^8.1` |
| (tags only) | `0.1.2` - `1.1.x` | `^4 \|\| ^5 \|\| ^6` | not declared |
| (tags only) | `0.1.1` | `^4 \|\| ^5` | not declared |
| (tags only) | `0.1` | `^4` | not declared |

Silverstripe 4 reached end of life in April 2025 and is no longer supported or tested here. Projects
still on it can stay on the `1.x` or `0.1.x` tags, which remain available. On Silverstripe 6, use
`2.x`: the earlier tags carry two defects fixed there (see [CHANGELOG.md](CHANGELOG.md)).

`main` is the only maintained line: it supports every Silverstripe version this module still
targets, so there is no separate maintenance branch. A version branch will be created only when a
change cannot be made compatible across the supported range.

**`composer.json` is the source of truth** for exact constraints; this table is a quick reference.

## Login form branding

Reduces the branding of the login forms like this:

<img width="682" height="499" alt="Screenshot 2025-10-01 at 09 36 01" src="https://github.com/user-attachments/assets/b5896364-5796-42a1-9588-b49212d63382" />

The branding above- and credits below the form are configurable via Yaml, options:
```YML
Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension:
  include_icon: true # default: true
  app_brand: 'Cycle App' # default: null (= just an icon/logo)
  built_by: 'Built by <a href="...">CoolCompany™</a>' # default: unconfigured-warning
  powered_by: 'Powered by <a href="https://silverstripe.org" target="_blank">Silverstripe</a>' # = default
```

| Option | Default | Effect |
|--------|---------|--------|
| `include_icon` | `true` | Show the icon (a project `LoginIcon.ss`, or the built-in shield-lock) above the form |
| `app_brand` | `null` | Text shown next to the icon; unset shows the icon only |
| `built_by` | a `<code>` hint telling you to set it | HTML credit line below the form; set to `null` to hide it |
| `powered_by` | `Powered by <a href="https://silverstripe.org">Silverstripe</a>` | HTML second credit line; set to `null` to hide it |
| `use_app_brand_template` | `false` | Render a project `AppBrand.ss` instead of icon + `app_brand` (legacy, see below) |

`built_by` and `powered_by` are output as HTML, unescaped - put only trusted, developer-written markup in them.

## Custom icon/logo/branding

<img width="672" height="499" alt="Screenshot 2025-10-01 at 09 43 48" src="https://github.com/user-attachments/assets/2354e9aa-71c4-4cb9-8380-b42ce5969dad" />

**Place a `LoginIcon.ss` template** somewhere in `[app]/templates/[Includes/]` or `themes/[client-theme]/templates/[Includes/]`, eg:

```html
<!-- LoginIcon.ss - bike icon -->
<svg width="16" height="16" fill="var(--color-link-primary)" class="bi bi-bicycle" viewBox="0 0 16 16">
    <path d="M4 4.5a.5.5 0 0 1 .5-.5H6a.5.5 0 0 1 0 1v.5h4.14l.386-1.158A.5.5 0 0 1 11 4h1a.5.5 0 0 1 0 
    1h-.64l-.311.935.807 1.29a3 3 0 1 1-.848.53l-.508-.812-2.076 3.322A.5.5 0 0 1 8 10.5H5.959a3 3 0 1 
    1-1.815-3.274L5 5.856V5h-.5a.5.5 0 0 1-.5-.5m1.5 2.443-.508.814c.5.444.85 1.054.967 1.743h1.139zM8 
    9.057 9.598 6.5H6.402zM4.937 9.5a2 2 0 0 0-.487-.877l-.548.877zM3.603 8.092A2 2 0 1 0 4.937 
    10.5H3a.5.5 0 0 1-.424-.765zm7.947.53a2 2 0 1 0 .848-.53l1.026 1.643a.5.5 0 1 1-.848.53z"/>
</svg>
<!-- Optionally add some inline <style> while at it... -->
```
**And configure:**
```YML
# config.yml
Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension:
  app_brand: 'Cycle App' # leave empty/unconfigured to show just the SVG (eg client's company logo)
  built_by: 'Built by <a href="https://restruct.nl" target="_blank">Restruct web & apps</a>'
```

### (Legacy:) Replace header using custom `AppBrand.ss` template
Configure extension to use AppBrand.ss template instead:
```YML
Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension:
  use_app_brand_template: true
```
Add an `AppBrand.ss` template file somewhere, eg `[app]/templates/[Includes/]` or `themes/[client-theme]/templates/[Includes/]`:
```HTML
<a class="login-icon" href="$AbsoluteBaseURL">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock" viewBox="0 0 16 16">
        <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
        <path d="M9.5 6.5a1.5 1.5 0 0 1-1 1.415l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99a1.5 1.5 0 1 1 2-1.415z"/>
    </svg>
    <h1 class="app-brand__logo">AppBrand</h1>
</a>
```
And set the theme as highest prio on `SilverStripe\LoginForms\EnablerExtension` (before `#admin-branding`):
```YML
---
Before:
  - '#admin-branding'
---
SilverStripe\LoginForms\EnablerExtension:
  login_themes:
    - 'client-theme'
```

<img width="429" src="https://user-images.githubusercontent.com/1005986/123509163-35143800-d674-11eb-8844-c0fed19c9afb.png">

Optionally set `Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension.include_icon` to `false` to remove the icon.

Shield-lock + bicycle icons kindly provided by [Bootstrap Icons](https://icons.getbootstrap.com/).

## SiteConfig Title Override

By default, the admin panel shows `SiteConfig.Title` (editable under Settings) in the left nav and browser tab. If you set `LeftAndMain.application_name` in config, it gets ignored when SiteConfig is installed.

This module can make `application_name` the authoritative source, overriding `SiteConfig.Title` in-memory and optionally removing the now-redundant fields from Settings.
The override applies whenever a SiteConfig record is loaded, and is never written back: the stored title stays as it was.

```yml
# Set the application name
SilverStripe\Admin\LeftAndMain:
  application_name: 'My App'

# Enable the override
SilverStripe\SiteConfig\SiteConfig:
  application_name_overrides_title: true
  application_name_clear_fields: 'tab'  # see options below
```

### `application_name_clear_fields` options

| Value | Behavior |
|-------|----------|
| `false` | Override title but leave Title/Tagline fields in Settings |
| `true` *(default)* | Remove Title + Tagline fields from Settings |
| `'tab'` | Remove fields + remove the empty Main tab (if other tabs remain) |

### `hide_cms_page_permissions` options

Removes the page-permission fields (who can view / edit / create top-level pages) from Settings,
which mean nothing on a site without pages.

| Value | Behavior |
|-------|----------|
| `'auto'` *(default)* | Remove them only when `silverstripe/cms` is not installed |
| `true` | Always remove them |
| `false` | Always leave them |

The Access tab is removed as well when nothing else is left on it.

```yml
SilverStripe\SiteConfig\SiteConfig:
  hide_cms_page_permissions: true
```

## Running the tests

The module cannot be tested on its own: it needs a host Silverstripe project (with
`silverstripe/recipe-cms` and `silverstripe/recipe-testing`). Require it there through a Composer
**path repository with `symlink: true`** - `/tests` is `export-ignore`, so a dist or mirrored install
contains no tests - add `Restruct\LoginBranding\Tests\` pointing at the module's `tests/` to the
host's `autoload-dev`, copy `phpunit.xml.dist` to the host root as `phpunit.xml`, then:

```bash
# Silverstripe 5 (PHPUnit 9) - the path must come before flush=1
vendor/bin/phpunit vendor/restruct/silverstripe-login-branding/tests flush=1

# Silverstripe 6 (PHPUnit 11) - a flush=1 argument is ignored, use the env var
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit --testsuite loginbranding
```

CI runs the same suite against Silverstripe 5 and 6 on every push; see `.github/workflows/ci.yml`.
