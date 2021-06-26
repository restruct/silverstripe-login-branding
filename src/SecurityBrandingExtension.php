<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;

/**
 * Applies to the {@see Security} controller to add & make some configurable branding options available in templates.
 */
class SecurityBrandingExtension
    extends Extension
{
    private static $built_by = '<code>Set config-value: SecurityBrandingExtension.built_by</code>';
    private static $powered_by = 'Powered by <a href="https://silverstripe.org" target="_blank">Silverstripe</a>';
    private static $include_icon = true;

    public function IncludeLoginIcon()
    {
        return Config::inst()->get(self::class, 'include_icon');
    }

    public function BrandingFragment($option)
    {
        return DBHTMLVarchar::create()->setValue( Config::inst()->get(self::class, $option) );
    }
}
