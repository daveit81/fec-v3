<?php
/**
 * Changelog view — published Atlas events across published entities
 * (/operators/changelog).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/publicbase.php';
require_once JPATH_COMPONENT . '/helpers/icons.php';
require_once JPATH_COMPONENT . '/helpers/route.php';

class AtlasViewChangelog extends JView
{
    public $events = array();

    public function display($tpl = null)
    {
        $model = new AtlasModelChangelog();
        $this->events = $model->getEvents(150);

        $doc = JFactory::getDocument();
        $doc->setTitle('Operator Atlas Changelog — licence moves, migrations, strike-offs');
        $doc->setDescription('Dated, sourced record of material changes to the legal entities behind casino brands: licence grants and losses, entity migrations, registry strike-offs and rebrands.');
        // Canonical URL - deterministic, from atlas_route (the same content
        // also answers on the non-SEF URL, so the canonical matters).
        $rel = atlas_route('index.php?option=com_atlas&view=changelog');
        $rootPath = JURI::root(true);
        if ($rootPath !== '' && strpos($rel, $rootPath) === 0) {
            $rel = substr($rel, strlen($rootPath));
        }
        $doc->addCustomTag('<link href="' . htmlspecialchars(rtrim(JURI::root(), '/') . $rel, ENT_QUOTES, 'UTF-8') . '" rel="canonical" />');


        // Design-system stylesheet (me-page / au-hero / me-hub-card) reused from
        // com_authors for full site coherence, then the slim atlas-specific one.
        $methCss = JPATH_SITE . '/components/com_authors/assets/methodology.css';
        if (file_exists($methCss)) {
            $doc->addStyleSheet(JURI::root(true) . '/components/com_authors/assets/methodology.css?v=' . (@filemtime($methCss) ? filemtime($methCss) : 1));
        }
        $base       = JURI::root(true) . '/components/com_atlas/assets/';
        $assetsPath = JPATH_SITE . '/components/com_atlas/assets/';
        $v = @filemtime($assetsPath . 'atlas.css');
        if (!$v) $v = 1;
        $doc->addStyleSheet($base . 'atlas.css?v=' . $v);

        parent::display($tpl);
    }
}
