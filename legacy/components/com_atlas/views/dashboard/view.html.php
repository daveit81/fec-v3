<?php
/**
 * Dashboard view — FEC Operator Atlas editorial cockpit (frontend, gated).
 *
 * Access: only Joomla user IDs in AtlasModelDashboard::DASHBOARD_ALLOWED_USERS.
 * Everyone else gets a 403 (dead end by design — team tool, not a feature).
 *
 * Layout: server-renders the entity list; the entity detail panel and all
 * forms are built client-side and every write goes through AJAX
 * (controllers/dashboard.php) so the page never reloads.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/dashboard.php';

class AtlasViewDashboard extends JView
{
    public $authorized = false;
    public $entities   = array();
    public $stats      = array();
    public $search     = '';
    public $type       = '';
    public $status     = '';
    public $token      = '';
    public $limit      = 50;
    public $limitstart = 0;
    public $total      = 0;

    public function display($tpl = null)
    {
        $doc = JFactory::getDocument();

        $this->authorized = AtlasModelDashboard::isAuthorized();
        if (!$this->authorized) {
            $doc->setTitle('Operator Atlas — access denied');
            parent::display('403');
            return;
        }

        $model = $this->getModel() ? $this->getModel() : new AtlasModelDashboard();

        $this->search = JRequest::getString('q', '', 'get');
        $t = JRequest::getCmd('type', '', 'get');
        $this->type = in_array($t, AtlasModelDashboard::$entityTypes, true) ? $t : '';
        $s = JRequest::getCmd('status', '', 'get');
        $this->status = in_array($s, AtlasModelDashboard::$entityStatus, true) ? $s : '';

        $this->limitstart = max(0, JRequest::getInt('limitstart', 0));
        $this->total      = $model->getEntitiesCount($this->search, $this->type, $this->status);
        $this->entities   = $model->getEntitiesList($this->search, $this->type, $this->status, $this->limit, $this->limitstart);
        $this->stats      = $model->getStats();
        $this->token      = JUtility::getToken();

        $doc->setTitle('Operator Atlas — editorial dashboard');
        $doc->setMetaData('robots', 'noindex, nofollow');

        $base       = JURI::root(true) . '/components/com_atlas/assets/';
        $assetsPath = JPATH_SITE . '/components/com_atlas/assets/';
        $vCss = @filemtime($assetsPath . 'dashboard.css');
        if (!$vCss) $vCss = 1;
        $vJs  = @filemtime($assetsPath . 'dashboard.js');
        if (!$vJs) $vJs = 1;
        $doc->addStyleSheet($base . 'dashboard.css?v=' . $vCss);
        $doc->addScript($base . 'dashboard.js?v=' . $vJs);

        $endpoint = JRoute::_('index.php?option=com_atlas', false);
        $doc->addScriptDeclaration(
            "window.AtlasDashboard = {" .
            " endpoint: " . json_encode($endpoint) . "," .
            " token: " . json_encode($this->token) . "," .
            " enums: " . json_encode(array(
                'entity_types'  => AtlasModelDashboard::$entityTypes,
                'entity_status' => AtlasModelDashboard::$entityStatus,
                'lic_status'    => AtlasModelDashboard::$licStatus,
                'confidence'    => AtlasModelDashboard::$confidence,
                'relations'     => AtlasModelDashboard::$relations,
                'event_types'   => AtlasModelDashboard::$eventTypes,
            )) .
            "};"
        );

        parent::display($tpl);
    }
}
