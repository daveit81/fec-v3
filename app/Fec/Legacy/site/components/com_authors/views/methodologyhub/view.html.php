<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/helpers/authors.php';

/**
 * Methodology Hub view — drives /how-we-verify (and /methodology root).
 *
 * Renders an overview of every methodology page on the site (FXCheck, FRank,
 * RTP verification, Editorial standards), with aggregate trust stats and
 * topic cards. The hub is the canonical entry for the "how we verify" link
 * that appears in every main page; deep-dive pages live at /methodology/<topic>.
 *
 * Data plumbed to the template:
 *   $this->topics       array of topic configs (incl. coming_soon + external)
 *   $this->stats        aggregate stats from AuthorsModelMethodology::getHubStats()
 *   $this->jsonLdGraph  pre-encoded JSON-LD @graph
 */
class AuthorsViewMethodologyhub extends JView
{
    public $topics      = array();
    public $stats       = array();
    public $jsonLdGraph = '';

    public function display($tpl = null)
    {
        // Joomla auto-binds models that match the view name. This view is
        // 'methodologyhub' but the model file is 'methodology.php' (shared
        // with the single-topic view), so getModel() returns null. Load and
        // instantiate it manually — same pattern as views/author/view.html.php
        // does with AuthorsModelAuthorBonuses.
        require_once JPATH_COMPONENT . '/models/methodology.php';
        $model = new AuthorsModelMethodology();

        // The hub lists methodology-kind topics only. Page-kind rows (the editorial
        // pages' registry since 2.7.38: RG, privacy, disclosure, the indexes) have
        // their own URLs and sections, and the methodology view refuses them; on
        // the hub they doubled the external cards. Absent kind = methodology.
        $this->topics = array();
        foreach ($model->getTopics() as $slug => $t) {
            $kind = (isset($t['kind']) && $t['kind'] !== '') ? $t['kind'] : 'methodology';
            if ($kind === 'page') continue;
            $this->topics[$slug] = $t;
        }
        $this->stats  = $model->getHubStats();

        // ---- Document setup -------------------------------------------------
        $doc = JFactory::getDocument();
        $doc->setTitle('How We Verify · Methodology · FreeExtraChips');

        $metaDesc = 'How FreeExtraChips verifies bonus offers and ranks casinos. '
                  . 'Player-verified status (FXCheck™), proprietary ranking (FRank), '
                  . 'in-game RTP checks, and editorial standards.';
        $doc->setMetaData('description', $metaDesc);

        // methodology.css covers hub styling too — same family, scoped under .fecx
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/methodology.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/methodology.css') ?: 1)
        );

        // ---- JSON-LD --------------------------------------------------------
        $this->jsonLdGraph = $this->buildJsonLdGraph($metaDesc);

        parent::display($tpl);
    }

    /**
     * JSON-LD @graph: Organization + WebSite + CollectionPage (the hub itself)
     * with hasPart pointing at each live methodology page. The CollectionPage
     * type is the schema.org-correct fit for a methodology overview.
     */
    protected function buildJsonLdGraph($metaDesc)
    {
        $siteRoot  = rtrim(JUri::root(), '/');
        $orgId     = $siteRoot . '/#organization';
        $webSiteId = $siteRoot . '/#website';

        // Hub canonical URL — bound to the menu item alias the user sets
        // ("how-we-verify"). Joomla's JRoute resolves to that path if a menu
        // item exists; falls back to ?option=...&view=methodologyhub otherwise.
        $hubRel = JRoute::_('index.php?option=com_authors&view=methodologyhub');
        $hubAbs = $this->absUrl($hubRel, $siteRoot);
        $hubId  = $hubAbs . '#hub';

        $graph = array();

        $graph[] = array(
            '@type' => 'Organization',
            '@id'   => $orgId,
            'name'  => 'FreeExtraChips',
            'url'   => $siteRoot . '/',
        );

        $graph[] = array(
            '@type'     => 'WebSite',
            '@id'       => $webSiteId,
            'url'       => $siteRoot . '/',
            'name'      => 'FreeExtraChips',
            'publisher' => array('@id' => $orgId),
        );

        // Build hasPart array of live methodology page references
        $hasPart = array();
        foreach ($this->topics as $slug => $t) {
            if (!isset($t['status']) || $t['status'] !== 'live') continue;
            $topicRel = JRoute::_('index.php?option=com_authors&view=methodology&topic=' . urlencode($slug));
            $hasPart[] = array(
                '@type' => 'Article',
                '@id'   => $this->absUrl($topicRel, $siteRoot) . '#article',
                'name'  => isset($t['title']) ? $t['title'] : '',
                'url'   => $this->absUrl($topicRel, $siteRoot),
            );
        }

        $collection = array(
            '@type'       => 'CollectionPage',
            '@id'         => $hubId,
            'url'         => $hubAbs,
            'name'        => 'How We Verify',
            'description' => $metaDesc,
            'isPartOf'    => array('@id' => $webSiteId),
            'publisher'   => array('@id' => $orgId),
        );
        if (!empty($hasPart)) $collection['hasPart'] = $hasPart;
        $graph[] = $collection;

        $doc = array('@context' => 'https://schema.org', '@graph' => $graph);
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($json !== false) ? $json : '';
    }

    protected function absUrl($u, $siteRoot)
    {
        $u = trim((string)$u);
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return $siteRoot . '/' . ltrim($u, '/');
    }
}
