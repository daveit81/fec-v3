<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/helpers/authors.php';

/**
 * Page view — drives editorial / trust / public-service pages.
 *
 * V2.2 ships the 'responsible-gambling' topic. Future topics are config-only
 * additions in AuthorsModelPage::getTopics() + a new tmpl/default_<slug>.php.
 *
 * Data plumbed to the template:
 *   $this->topic       slug ('responsible-gambling')
 *   $this->config      topic config array (title, tagline, last_updated, ...)
 *   $this->author      primary author row (or null)
 *   $this->reviewer    reviewer row (or null)
 *   $this->jsonLdGraph pre-encoded JSON-LD @graph string
 */
class AuthorsViewPage extends JView
{
    public $topic       = '';
    public $config      = array();
    public $author      = null;
    public $reviewer    = null;
    public $jsonLdGraph = '';

    /** 2.7.55 — Payout Index editions: current edition row (or null) and the light list of all editions. */
    public $edition     = null;
    public $editions    = array();

    public function display($tpl = null)
    {
        // ---- Resolve topic ---------------------------------------------------
        // The URL may pass either ?slug=... or ?topic=... so the menu manager
        // configuration is flexible. Falls back to responsible-gambling as the
        // V2.2 default since it's the only live topic.
        $topic = strtolower(trim((string)JRequest::getVar('slug', '')));
        if ($topic === '') {
            $topic = strtolower(trim((string)JRequest::getVar('topic', 'responsible-gambling')));
        }
        if ($topic === '') $topic = 'responsible-gambling';

        // ---- Load model manually --------------------------------------------
        // Joomla's auto-bind only works when the view name == model file name.
        // Here both are 'page' so getModel('page') would work, but we use the
        // explicit instantiation pattern consistently across the component to
        // avoid the silent-null trap we hit on methodologyhub. See also
        // views/methodologyhub/view.html.php and views/author/view.html.php.
        require_once JPATH_COMPONENT . '/models/page.php';
        $model = new AuthorsModelPage();

        $config = $model->getTopicConfig($topic);
        if (!$config) {
            throw new Exception('Editorial page not found', 404);
        }

        $this->topic  = $topic;
        $this->config = $config;

        // ---- Payout Index editions (2.7.55) ---------------------------------
        // /online-casinos/fast-payouts/YYYY-MM renders a frozen edition with
        // the same template; the live page lists the editions in its sidebar.
        $editionRel = '';
        if ($topic === 'fast-payouts') {
            require_once JPATH_COMPONENT . '/models/payoutindex.php';
            $ym = trim((string)JRequest::getVar('edition', '', 'default', 'string'));
            if ($ym !== '') {
                $this->edition = AuthorsModelPayoutindex::edition($ym);
                if (!$this->edition) {
                    throw new Exception('Edition not found', 404);
                }
                $label = AuthorsModelPayoutindex::editionNumberLabel($this->edition['number'])
                       . ', ' . AuthorsModelPayoutindex::editionMonthLabel($this->edition['edition']);
                $this->config['title']   = 'FEC Payout Index: ' . $label;
                $this->config['tagline'] = 'Frozen on ' . date('j F Y', strtotime($this->edition['published_at']))
                    . '. Desk-tested withdrawal times as they stood, fastest first, with what changed since the previous edition.';
                $this->config['description'] = $label . ' of the FEC Payout Index: ' . (int)$this->edition['desk_tests']
                    . ' casino withdrawals timed by FreeExtraChips staff, fastest first, frozen on '
                    . date('j F Y', strtotime($this->edition['published_at'])) . '. What changed since the last edition.';
                $this->config['last_updated'] = substr((string)$this->edition['published_at'], 0, 10);
                $config = $this->config;
                $editionRel = AuthorsModelPayoutindex::editionPath($this->edition['edition']);
            }
            $this->editions = AuthorsModelPayoutindex::editions();
        }

        // ---- Licence Watch editions (2.7.61) --------------------------------
        // /online-casinos/licence-watch            → latest published edition
        // /online-casinos/licence-watch/YYYY-MM    → that edition
        // Drafts render only for the desk user with ?preview=1 (noindex).
        if ($topic === 'licence-watch') {
            require_once JPATH_COMPONENT . '/models/licencewatch.php';
            $user = JFactory::getUser();
            $isDesk = ($user && (int)$user->get('id') === AuthorsModelLicencewatch::DESK_USER_ID);
            $preview = $isDesk && (int)JRequest::getVar('preview', 0, 'default', 'int') === 1;
            $ym = trim((string)JRequest::getVar('edition', '', 'default', 'string'));
            $this->editions = AuthorsModelLicencewatch::editions($preview);
            if ($ym !== '') {
                $this->edition = AuthorsModelLicencewatch::edition($ym, $preview);
                if (!$this->edition) throw new Exception('Edition not found', 404);
                $editionRel = AuthorsModelLicencewatch::editionPath($ym);
            } else {
                $this->edition = AuthorsModelLicencewatch::latest($preview);
            }
            if ($this->edition) {
                $label = AuthorsModelLicencewatch::editionNumberLabel($this->edition['number'])
                       . ', ' . AuthorsModelLicencewatch::editionMonthLabel($this->edition['edition']);
                $p = $this->edition['payload'];
                if (!empty($this->edition['sections']['title'])) {
                    $this->config['title'] = (string)$this->edition['sections']['title'];
                } elseif ($ym !== '') {
                    $this->config['title'] = 'Licence Watch, ' . $label;
                }
                $this->config['description'] = 'Licence Watch, ' . $label . ': ' . (int)$p['totals']['live'] . ' live casinos by declared regulator, '
                    . (int)$p['verified']['brands'] . ' verified against the registers, ' . (int)$p['no_regulator']['total']
                    . ' with no gambling regulator, and what moved since the previous edition.';
                $this->config['last_updated'] = substr((string)($this->edition['published_at'] ? $this->edition['published_at'] : $this->edition['frozen_at']), 0, 10);
                if ((string)$this->edition['status'] !== 'published') {
                    JFactory::getDocument()->setMetaData('robots', 'noindex, nofollow');
                }
                if (!empty($this->edition['author_slug']))   $this->config['author_slug']   = (string)$this->edition['author_slug'];
                if (!empty($this->edition['reviewer_slug'])) $this->config['reviewer_slug'] = (string)$this->edition['reviewer_slug'];
                $config = $this->config;
            }
        }

        // ---- Welcome Package Index (2.7.78) ---------------------------------
        // The page's URL is its public path, not the menu route: the Bonuses
        // parent menu item does not carry the /bonuses prefix the site uses, and
        // every inbound link (bonus band, casino block, hub pill, bot, method
        // page) points at AuthorsModelPackageindex::INDEX_PATH. Head canonical,
        // og:url and the WebPage node all take that path.
        $pageRelFixed = '';
        if ($topic === 'welcome-packages') {
            require_once JPATH_COMPONENT . '/models/packageindex.php';
            $pageRelFixed = AuthorsModelPackageindex::INDEX_PATH;
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
        $doc->setTitle($config['title'] . ' · FreeExtraChips');

        // Page view leans on fec-edition.css for tokens and au-* primitives.
        // Site index.php must trigger .fecx body class for view=page (same
        // trigger as view=methodology / view=methodologyhub). page.css adds
        // only patterns specific to editorial pages (helpline cards, self-check
        // grid, country tabs). Scoped under .fecx so it's a no-op elsewhere.
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/page.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/page.css') ?: 1)
        );

        $metaDesc = isset($config['description']) ? trim((string)$config['description']) : '';
        if ($metaDesc === '' && isset($config['tagline'])) {
            $metaDesc = trim((string)$config['tagline']);
        }
        $metaDesc = preg_replace('/\s+/', ' ', $metaDesc);
        if (strlen($metaDesc) > 200) $metaDesc = substr($metaDesc, 0, 197) . '…';
        if ($metaDesc !== '') $doc->setMetaData('description', $metaDesc);

        // ---- Edition head: canonical + social card (2.7.55) ----------------
        if ($this->edition && $topic === 'licence-watch') {
            if ($editionRel !== '') {
                // 2.7.63: the live URL shows the latest published edition, so the
                // stamped URL of that same edition is a duplicate until the next
                // one replaces it: canonical to the live page while it is current,
                // to itself once superseded (the sidebar links it from then on).
                $latestPub = '';
                foreach ((array)$this->editions as $e) {
                    if ((string)$e['status'] === 'published') { $latestPub = (string)$e['edition']; break; }
                }
                $isCurrent = ((string)$this->edition['status'] === 'published' && $latestPub === (string)$this->edition['edition']);
                $doc->addHeadLink(rtrim(JUri::root(), '/') . ($isCurrent ? AuthorsModelLicencewatch::INDEX_PATH : $editionRel), 'canonical');
            }
        } elseif ($this->edition) {
            $siteRootHead = rtrim(JUri::root(), '/');
            $doc->addHeadLink($siteRootHead . $editionRel, 'canonical');
            $doc->addCustomTag('<meta property="og:type" content="article">');
            $doc->addCustomTag('<meta property="og:title" content="' . htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') . '">');
            $doc->addCustomTag('<meta property="og:url" content="' . htmlspecialchars($siteRootHead . $editionRel, ENT_QUOTES, 'UTF-8') . '">');
            if (!empty($this->edition['card_path'])) {
                $cardAbs = $siteRootHead . $this->edition['card_path'];
                $doc->addCustomTag('<meta property="og:image" content="' . htmlspecialchars($cardAbs, ENT_QUOTES, 'UTF-8') . '">');
                $doc->addCustomTag('<meta property="og:image:width" content="1200">');
                $doc->addCustomTag('<meta property="og:image:height" content="630">');
                $doc->addCustomTag('<meta name="twitter:card" content="summary_large_image">');
                $doc->addCustomTag('<meta name="twitter:image" content="' . htmlspecialchars($cardAbs, ENT_QUOTES, 'UTF-8') . '">');
            }
        } elseif ($pageRelFixed !== '') {
            $siteRootHead = rtrim(JUri::root(), '/');
            $doc->addHeadLink($siteRootHead . $pageRelFixed, 'canonical');
            $doc->addCustomTag('<meta property="og:type" content="website">');
            $doc->addCustomTag('<meta property="og:title" content="' . htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') . '">');
            $doc->addCustomTag('<meta property="og:url" content="' . htmlspecialchars($siteRootHead . $pageRelFixed, ENT_QUOTES, 'UTF-8') . '">');
        } elseif ($topic === 'fast-payouts' && !empty($this->editions) && !empty($this->editions[0]['card_path'])) {
            // Live page: the latest edition card doubles as its social image.
            $cardAbs = rtrim(JUri::root(), '/') . $this->editions[0]['card_path'];
            $doc->addCustomTag('<meta property="og:image" content="' . htmlspecialchars($cardAbs, ENT_QUOTES, 'UTF-8') . '">');
            $doc->addCustomTag('<meta property="og:image:width" content="1200">');
            $doc->addCustomTag('<meta property="og:image:height" content="630">');
            $doc->addCustomTag('<meta name="twitter:card" content="summary_large_image">');
            $doc->addCustomTag('<meta name="twitter:image" content="' . htmlspecialchars($cardAbs, ENT_QUOTES, 'UTF-8') . '">');
        }

        // ---- JSON-LD --------------------------------------------------------
        $this->jsonLdGraph = $this->buildJsonLdGraph($topic, $config, $metaDesc, $editionRel !== '' ? $editionRel : $pageRelFixed);

        // ---- Pick the topic-specific template -------------------------------
        // Joomla resolves parent::display('foo') to tmpl/default_foo.php.
        // Falls back to tmpl/default.php (404-style) if topic doesn't ship
        // a template. Should never happen for status='live' topics.
        if (!empty($config['tmpl'])) {
            $tpl = (string)$config['tmpl'];
        }

        parent::display($tpl);
    }

    /**
     * JSON-LD @graph: Organization + WebSite + WebPage + author/reviewer
     * Person references. Person @ids match com_authors single-author view so
     * Google sees one canonical Person entity across pages.
     *
     * Why WebPage instead of Article (the methodology pattern):
     *   Editorial pages here are informational / public-service content, not
     *   editorial commentary. WebPage with mainEntity is the correct shape
     *   for Google's YMYL guidance on safety / health / financial pages.
     */
    protected function buildJsonLdGraph($topic, $config, $metaDesc, $pageRelOverride = '')
    {
        $siteRoot   = rtrim(JUri::root(), '/');
        $orgId      = $siteRoot . '/#organization';
        $webSiteId  = $siteRoot . '/#website';
        $schemaType = !empty($config['schema_type']) ? (string)$config['schema_type'] : 'WebPage';

        // Page absolute URL — built from the JRoute pattern. The actual public
        // URL depends on how the admin configured the menu alias (typically
        // /responsible-gambling rather than /page/responsible-gambling).
        // JRoute resolves to the menu alias if one exists, otherwise emits
        // the raw query string.
        $pageRel = ($pageRelOverride !== '') ? $pageRelOverride
                 : JRoute::_('index.php?option=com_authors&view=page&slug=' . urlencode($topic));
        $pageAbs = $this->absUrl($pageRel, $siteRoot);
        $pageId  = $pageAbs . '#webpage';

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

        // The page itself
        $page = array(
            '@type'      => $schemaType,
            '@id'        => $pageId,
            'url'        => $pageAbs,
            'name'       => $config['title'],
            'inLanguage' => 'en',
            'isPartOf'   => array('@id' => $webSiteId),
            'publisher'  => array('@id' => $orgId),
        );
        if ($metaDesc !== '') $page['description'] = $metaDesc;

        if (!empty($config['last_updated'])) {
            $page['datePublished'] = $config['last_updated'];
            $page['dateModified']  = $config['last_updated'];
        }

        // Author (Person @id matching the author profile page)
        if ($this->author && !empty($this->author['slug'])) {
            $authorRel = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($this->author['slug']));
            $authorAbs = $this->absUrl($authorRel, $siteRoot);
            $page['author'] = array(
                '@type' => 'Person',
                '@id'   => $authorAbs . '#person',
                'name'  => $this->author['name'],
                'url'   => $authorAbs,
            );
        }

        // Reviewer (Google recognizes reviewedBy on Article and via mainEntity
        // on WebPage; safe to include even where Google ignores the property).
        if ($this->reviewer && !empty($this->reviewer['slug'])) {
            $reviewerRel = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($this->reviewer['slug']));
            $reviewerAbs = $this->absUrl($reviewerRel, $siteRoot);
            $page['reviewedBy'] = array(
                '@type' => 'Person',
                '@id'   => $reviewerAbs . '#person',
                'name'  => $this->reviewer['name'],
                'url'   => $reviewerAbs,
            );
        }

        $graph[] = $page;

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
