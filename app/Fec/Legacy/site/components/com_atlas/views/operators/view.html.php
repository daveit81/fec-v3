<?php
/**
 * Operators view — public index of published Atlas entities (/operators).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/publicbase.php';
require_once JPATH_COMPONENT . '/helpers/icons.php';
require_once JPATH_COMPONENT . '/helpers/route.php';

class AtlasViewOperators extends JView
{
    public $entities = array();
    public $groups   = array();
    public $stats    = array();

    public function display($tpl = null)
    {
        $model = new AtlasModelOperators();
        $this->entities = $model->getPublishedEntities();
        $this->stats    = $model->getIndexStats();

        // Group by entity_type, preserving the model ORDER BY.
        $this->groups = array();
        foreach ($this->entities as $e) {
            $this->groups[$e->entity_type][] = $e;
        }

        $doc = JFactory::getDocument();
        $doc->setTitle('Casino Operator Atlas — who really runs each casino');
        $doc->setDescription('A structured public register of the legal entities behind casino brands: operators, holdings, licences and ownership history, with sourced confidence grades.');
        // Canonical URL - deterministic, from atlas_route (the same content
        // also answers on the non-SEF URL, so the canonical matters).
        $rel = atlas_route('index.php?option=com_atlas&view=operators');
        $rootPath = JURI::root(true);
        if ($rootPath !== '' && strpos($rel, $rootPath) === 0) {
            $rel = substr($rel, strlen($rootPath));
        }
        $doc->addCustomTag('<link href="' . htmlspecialchars(rtrim(JURI::root(), '/') . $rel, ENT_QUOTES, 'UTF-8') . '" rel="canonical" />');


        // Assets (public stylesheet, cache-busted like the dashboard).
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
