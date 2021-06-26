# Silverstripe Login-form Branding

Replaces/reduces the default Silverstripe branding of the [login forms module]() and makes it easily configurable.


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

