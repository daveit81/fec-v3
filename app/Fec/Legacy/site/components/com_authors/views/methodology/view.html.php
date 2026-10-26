<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/helpers/authors.php';

/**
 * Methodology view — drives /methodology/<topic>.
 *
 * V1 ships the "fxcheck" topic only. Adding a new topic is config-only in
 * AuthorsModelMethodology::getTopics() + a new tmpl/<topic>.php template.
 *
 * Data plumbed to the template:
 *   $this->topic       slug ('fxcheck')
 *   $this->config      topic config array (title, tagline, version, ...)
 *   $this->stats       array of live FXCheck stats (only when topic=fxcheck)
 *   $this->author      primary author row (or null)
 *   $this->reviewer    reviewer row (or null)
 *   $this->jsonLdGraph pre-encoded JSON-LD @graph string
 */
class AuthorsViewMethodology extends JView
{
    public $topic       = '';
    public $config      = array();
    public $stats       = array();
    public $author      = null;
    public $reviewer    = null;
    public $jsonLdGraph = '';

    public function display($tpl = null)
    {
        // ---- Resolve topic ---------------------------------------------------
        $topic = strtolower(trim((string)JRequest::getVar('topic', 'fxcheck')));
        if ($topic === '') $topic = 'fxcheck';

        $model  = $this->getModel('methodology');
        $config = $model->getTopicConfig($topic);
        if (!$config) {
            throw new Exception('Methodology topic not found', 404);
        }

        $this->topic  = $topic;
        $this->config = $config;

        // ---- Fetch live data (topic-specific) -------------------------------
        if ($topic === 'fxcheck') {
            $this->stats = $model->getFxcheckStats();
        } elseif ($topic === 'rtp-verification') {
            $this->stats = $model->getRtpStats();
        } elseif ($topic === 'fxshield') {
            $this->stats = $model->getFxshieldStats();
        } elseif ($topic === 'fxrate') {
            $this->stats = $model->getFxrateStats();
        }

        // ---- Authors --------------------------------------------------------
        if (!empty($config['author_slug'])) {
            $this->author = $model->getAuthorBySlug($config['author_slug']);
        }
        if (!empty($config['reviewer_slug'])) {
            $this->reviewer = $model->getAuthorBySlug($config['reviewer_slug']);
        }

        // ---- Document setup -------------------------------------------------
        $doc = JFactory::getDocument();
        $doc->setTitle($config['title'] . ' · Methodology · FreeExtraChips');

        // The methodology page leans on fec-edition.css for tokens and au-*
        // primitives. We assume the site index.php already loads it (it does
        // for view=author / view=authors via fec_is_fecx_author or similar
        // check). If your trigger function checks specifically for those two
        // views, extend it to include 'methodology' — otherwise the editorial
        // palette won't apply.
        //
        // methodology.css adds only the me-* patterns that don't exist in
        // fec-edition.css yet (verdict box, status table, FAQ, sidebar TOC).
        // It's scoped under .fecx so loading it on a non-fecx page is a no-op.
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/methodology.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/methodology.css') ?: 1)
        );

        $metaDesc = isset($config['description']) ? trim((string)$config['description']) : '';
        if ($metaDesc === '' && isset($config['tagline'])) {
            $metaDesc = trim((string)$config['tagline']);
        }
        $metaDesc = preg_replace('/\s+/', ' ', $metaDesc);
        if (strlen($metaDesc) > 200) $metaDesc = substr($metaDesc, 0, 197) . '…';
        if ($metaDesc !== '') $doc->setMetaData('description', $metaDesc);

        // ---- JSON-LD --------------------------------------------------------
        $this->jsonLdGraph = $this->buildJsonLdGraph($topic, $config, $metaDesc);

        // ---- Pick the topic-specific template -------------------------------
        // Falls back to default tmpl if a topic doesn't ship its own (it
        // should, but defensive).
        if (!empty($config['tmpl'])) {
            $tpl = (string)$config['tmpl'];
        }

        parent::display($tpl);
    }

    /**
     * JSON-LD @graph: Organization + WebSite + Article + author/reviewer
     * Person references. Person @ids match com_authors single-author view so
     * Google sees one canonical Person entity across pages.
     */
    protected function buildJsonLdGraph($topic, $config, $metaDesc)
    {
        $siteRoot  = rtrim(JUri::root(), '/');
        $orgId     = $siteRoot . '/#organization';
        $webSiteId = $siteRoot . '/#website';

        // Page absolute URL — built from the same JRoute pattern the router
        // expects, so we land on /methodology/<topic>.
        $pageRel = JRoute::_('index.php?option=com_authors&view=methodology&topic=' . urlencode($topic));
        $pageAbs = $this->absUrl($pageRel, $siteRoot);
        $articleId = $pageAbs . '#article';

        $graph = array();

        // Organization
        $graph[] = array(
            '@type' => 'Organization',
            '@id'   => $orgId,
            'name'  => 'FreeExtraChips',
            'url'   => $siteRoot . '/',
        );

        // WebSite
        $graph[] = array(
            '@type'     => 'WebSite',
            '@id'       => $webSiteId,
            'url'       => $siteRoot . '/',
            'name'      => 'FreeExtraChips',
            'publisher' => array('@id' => $orgId),
        );

        // Article — the methodology page itself
        $article = array(
            '@type'       => 'Article',
            '@id'         => $articleId,
            'url'         => $pageAbs,
            'headline'    => $config['title'],
            'articleSection' => 'Methodology',
            'inLanguage'  => 'en',
            'isPartOf'    => array('@id' => $webSiteId),
            'publisher'   => array('@id' => $orgId),
        );
        if ($metaDesc !== '') $article['description'] = $metaDesc;

        if (!empty($config['last_updated'])) {
            // Use ISO date (no time) — Google accepts both.
            $article['datePublished'] = $config['last_updated'];
            $article['dateModified']  = $config['last_updated'];
        }
        if (!empty($config['version'])) {
            $article['version'] = $config['version'];
        }

        // Author (Person @id matching the author profile page)
        if ($this->author && !empty($this->author['slug'])) {
            $authorRel = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($this->author['slug']));
            $authorAbs = $this->absUrl($authorRel, $siteRoot);
            $article['author'] = array(
                '@type' => 'Person',
                '@id'   => $authorAbs . '#person',
                'name'  => $this->author['name'],
                'url'   => $authorAbs,
            );
        }

        // Reviewer — use editor (or reviewedBy isn't a standard Schema field;
        // we use the JSON-LD "reviewedBy" property documented by Google for
        // YMYL content). Falls back gracefully if reviewer not configured.
        if ($this->reviewer && !empty($this->reviewer['slug'])) {
            $reviewerRel = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($this->reviewer['slug']));
            $reviewerAbs = $this->absUrl($reviewerRel, $siteRoot);
            $article['reviewedBy'] = array(
                '@type' => 'Person',
                '@id'   => $reviewerAbs . '#person',
                'name'  => $this->reviewer['name'],
                'url'   => $reviewerAbs,
            );
        }

        $graph[] = $article;

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
