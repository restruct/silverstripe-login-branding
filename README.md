# Silverstripe Login-form Branding

Replaces/reduces the default Silverstripe branding of the [login forms module](https://github.com/silverstripe/silverstripe-login-forms) and makes it easily configurable.
<img width="762" src="https://user-images.githubusercontent.com/1005986/123509152-2cbbfd00-d674-11eb-9373-70ad2ad4157b.png">

The icon above- and credits below the form are configurable via Yaml:
```YML
Restruct\SilverStripe\AdminBranding\SecurityBrandingExtension:
  built_by: 'Built by <a href="https://yoursite.tld" target="_blank">Your Company</a>'
  powered_by: 'Powered by <a href="https://silverstripe.org" target="_blank">Silverstripe</a>'
  include_icon: true
```

## Add your (client’s) logo/brand above the form

Add an `AppBrand.ss` template file in either `[app]/templates/Includes/` or `themes/[client-theme]/templates/Includes/`, eg:
```HTML
<h1 class="app-brand__logo">AppBrand</h1>
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

Shield-lock icon kindly provided by [Bootstrap Icons](https://icons.getbootstrap.com/icons/shield-lock/).
