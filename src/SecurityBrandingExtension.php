<?php

namespace Restruct\SilverStripe\AdminBranding;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
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
        // SS6+: template lookup moved from SSViewer to the injectable TemplateEngine service.
        // Detected by the framework's own interface, with an autoloading interface_exists(): the
        // previous guard, ClassInfo::exists() on the engine class name with a leading backslash,
        // does not autoload and never matches the class manifest, so on a request where the
        // engine was not loaded yet it fell through to SSViewer::hasTemplate() and fataled.
        // Asking the Injector (not SSTemplateEngine directly) also honours a project that swaps
        // the template engine.
//        if(ClassInfo::exists('\SilverStripe\TemplateEngine\SSTemplateEngine')){
//            return \SilverStripe\TemplateEngine\SSTemplateEngine::singleton()->hasTemplate($loginIconTemplates);
//        }
        if (interface_exists(\SilverStripe\View\TemplateEngine::class)) {
            return Injector::inst()->get(\SilverStripe\View\TemplateEngine::class)->hasTemplate($loginIconTemplates);
        }
        // SS5 fallback
        return \SilverStripe\View\SSViewer::hasTemplate($loginIconTemplates);
    }

    public function BrandingFragment($option)
    {
        return DBHTMLVarchar::create()->setValue( Config::inst()->get(self::class, $option) );
    }
}
