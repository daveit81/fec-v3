<?php
/**
 * Not a copy of sh404SEF: its place among the system plugins of the live
 * site (fec.legacy.plugins.system: after the core "sef" plugin, before
 * feccanonicalfix). sh404SEF wrote its canonical into the finished page in
 * its own onAfterRender, after "sef" had made the page's relative URLs
 * absolute: what it wrote stays as written. The rewrite itself is
 * App\Fec\Legacy\Sh404sefPage and Sh404sefMetas, run on the document before
 * the layout; the layout prints their canonical under a placeholder "sef"
 * leaves alone, put back here (App\Fec\Legacy\Sh404sefOutput).
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSystemSh404sef extends JPlugin
{
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        if ($app->isAdmin()) {
            return true;
        }
        JResponse::setBody(\App\Fec\Legacy\Sh404sefOutput::restore((string) JResponse::getBody()));

        return true;
    }
}
