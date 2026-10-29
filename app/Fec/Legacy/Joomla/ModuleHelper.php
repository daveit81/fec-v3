<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JModuleHelper: only getLayoutPath(), which the mod_menu layouts call to
 * include each other. Joomla 2.5's lookup: the template's override
 * (JPATH_THEMES/<template>/html/<module>/<layout>.php), then the module's
 * own tmpl; a layout may be given as "template:layout" ("_" = the current
 * template). Only overrides exist in the ported site tree.
 */
class ModuleHelper
{
    public static function getLayoutPath(string $module, string $layout = 'default'): string
    {
        $template = (string) Factory::getApplication()->getTemplate();
        $defaultLayout = $layout;
        if (str_contains($layout, ':')) {
            [$owner, $name] = explode(':', $layout, 2);
            $template = $owner === '_' ? $template : $owner;
            $layout = $name;
            $defaultLayout = $name !== '' ? $name : 'default';
        }
        $tPath = JPATH_THEMES.'/'.$template.'/html/'.$module.'/'.$layout.'.php';
        $bPath = JPATH_BASE.'/modules/'.$module.'/tmpl/'.$defaultLayout.'.php';
        $dPath = JPATH_BASE.'/modules/'.$module.'/tmpl/default.php';

        return file_exists($tPath) ? $tPath : (file_exists($bPath) ? $bPath : $dPath);
    }
}
