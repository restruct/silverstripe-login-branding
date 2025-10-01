<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;

/**
 * Applies to the {@see Security} controller to add & make some configurable branding options available in templates.
 */
class SecurityBrandingExtension
    extends Extension
{
    private static $use_app_brand_template = false;
    private static $include_icon = true;
    private static $app_brand = null;
    private static $built_by = '<code>Set config-value: SecurityBrandingExtension.built_by</code>';
    private static $powered_by = 'Powered by <a href="https://silverstripe.org" target="_blank">Silverstripe</a>';

    public function IncludeLoginIcon()
    {
        return Config::inst()->get(self::class, 'include_icon');
    }

    public function AppBrand()
    {
        return Config::inst()->get(self::class, 'app_brand');
    }

    public function UseAppBrandTemplate()
    {
        return Config::inst()->get(self::class, 'use_app_brand_template');
    }

    public function LoginIconTemplateAvailable()
    {
        $loginIconTemplates = ['LoginIcon', 'Includes/LoginIcon'];
        // SS6+
        if(ClassInfo::exists('\SilverStripe\TemplateEngine\SSTemplateEngine')){
            return \SilverStripe\TemplateEngine\SSTemplateEngine::singleton()->hasTemplate($loginIconTemplates);
        }
        // SS4/5 fallback
        return \SilverStripe\View\SSViewer::hasTemplate($loginIconTemplates);
    }

    public function BrandingFragment($option)
    {
        return DBHTMLVarchar::create()->setValue( Config::inst()->get(self::class, $option) );
    }
}
