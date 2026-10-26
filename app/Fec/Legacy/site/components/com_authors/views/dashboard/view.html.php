<?php
/**
 * Dashboard view — RTP database editorial dashboard (frontend, gated).
 *
 * Access: only Joomla user IDs in AuthorsModelDashboard::DASHBOARD_ALLOWED_USERS
 * can see this. Everyone else gets a 403.
 *
 * Layout: single template (default.php), mobile-first. The "slot detail"
 * panel is rendered server-side in a hidden div and swapped client-side
 * via JS (no SPA — we keep one HTTP round-trip per write).
 *
 * Writes go to /index.php?option=com_authors&task=<task> (controllers/dashboard.php),
 * which return JSON. The page never reloads.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

// Need the model file for the auth helper before display() is called.
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsViewDashboard extends JView
{
    public $authorized        = false;
    public $slots             = array();
    public $coverage          = array('total' => 0, 'documented' => 0);
    public $search            = '';
    public $filter            = 'all';
    public $top_n_target      = 30;
    public $token             = '';
    public $limit             = 50;
    public $limitstart        = 0;
    public $totalSlots        = 0;

    public function display($tpl = null)
    {
        $doc  = JFactory::getDocument();
        $user = JFactory::getUser();

        // ---- Auth gate -----------------------------------------------------
        $this->authorized = AuthorsModelDashboard::isAuthorized();
        if (!$this->authorized) {
            // Render a minimal 403 page (still inside the site template).
            $doc->setTitle('Editorial dashboard — access denied');
            // We intentionally don't link to login/registration: this isn't a
            // public-facing feature, it's a team tool. Wrong user → dead end.
            parent::display('403');
            return;
        }

        // ---- Data load -----------------------------------------------------
        $model = $this->getModel() ?: new AuthorsModelDashboard();

        $this->search = JRequest::getString('q', '', 'get');
        $f = JRequest::getCmd('filter', 'all', 'get');
        if (!in_array($f, array('all', 'missing', 'needs_refresh', 'verified'), true)) {
            $f = 'all';
        }
        $this->filter = $f;

        $this->limit      = 50;
        $this->limitstart = max(0, JRequest::getInt('limitstart', 0));
        $this->totalSlots = $model->getSlotsCount($this->search);

        $this->slots    = $model->getSlotsList($this->search, $this->filter, $this->limit, $this->limitstart);
        $this->coverage = $model->getCoverageStats($this->top_n_target);
        $this->token    = JUtility::getToken();

        // ---- Assets + meta -------------------------------------------------
        $doc->setTitle('Editorial dashboard — RTP database');
        // Discourage indexing the editorial tool URLs (defense in depth — the
        // auth gate would already 403 a search engine).
        $doc->setMetaData('robots', 'noindex, nofollow');

        $base = JURI::root(true) . '/components/com_authors/assets/';
        // Cache-buster: filemtime() of each asset file. Changes every time
        // the file is updated -> browser always picks up new versions.
        // The previous static $manifestVersion was forgotten between bumps,
        // serving stale dashboard.js for releases 2.7.3 through 2.7.10.
        $assetsPath = JPATH_SITE . '/components/com_authors/assets/';
        $vCss = @filemtime($assetsPath . 'dashboard.css') ?: 1;
        $vJs  = @filemtime($assetsPath . 'dashboard.js')  ?: 1;
        $doc->addStyleSheet($base . 'dashboard.css?v=' . $vCss);
        $doc->addScript($base . 'dashboard.js?v=' . $vJs);

        // Stash ajax endpoint + CSRF token for JS to pick up.
        $endpoint = JRoute::_('index.php?option=com_authors', false);
        $authors = $model->getActiveAuthors();
        $doc->addScriptDeclaration(
            "window.FXDashboard = {" .
            " endpoint: " . json_encode($endpoint) . "," .
            " token: " . json_encode($this->token) . "," .
            " imageBase: " . json_encode(JURI::root(true) . '/images/rtp/') . "," .
            " authors: " . json_encode($authors) .
            "};"
        );

        parent::display($tpl);
    }
}
