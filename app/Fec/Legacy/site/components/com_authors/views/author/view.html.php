<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/helpers/authors.php';

/**
 * Author view — single profile (/authors/<slug>).
 *
 * FEC V2.1 Author Profile migration. Rewrites the legacy AuthorsViewAuthor
 * to drive the new au-* template. Key changes vs. the pre-migration version:
 *
 *   1. Loads fec_common_lib.php + bonustypesarray/currenciesarray INSIDE
 *      display() (template index.php loads them too late for the component).
 *   2. Augments the single author item with computed fields: initials,
 *      avatar_hue, expertise[], credentials[], languages[], joined_year,
 *      years_at_fec, plus stat counts for the 4 activity tabs and the
 *      sidebar Quick Facts (latest activity formatted, activity average).
 *   3. Pagination intact for the "Bonuses reviewed" tab; the activeOnly
 *      sub-filter ?active=1 still works. V2.7.65 adds the "Blog posts" tab
 *      (Casino Bonus Blog posts by the mapped Joomla user, paginated) and
 *      makes it the landing tab for profiles that have posts and no bonuses.
 *   4. JSON-LD now emits an Organization + ProfilePage + Person @graph,
 *      with the Person @id matching exactly the one emitted by the team
 *      listing — so Google sees one canonical Person across both pages.
 *   5. Legacy assets/authors.css no longer linked; fec-edition.css is
 *      loaded centrally by index.php when fec_is_fecx_author() is true.
 */
class AuthorsViewAuthor extends JView
{
    public $item         = null;
    public $bonusItems   = array();
    public $bonusTotal   = 0;
    public $pagination   = null;
    public $activeOnly   = 0;
    public $tab          = 'bonuses';
    public $counts       = array();
    public $activityAvg  = '0 / day';
    public $bonustypeMap = array();
    public $currencyMap  = array();
    public $jsonLdGraph  = '';
    public $blogItems    = array();   // V2.7.65 — "Blog posts" tab rows
    public $blogTotal    = 0;
    public $blogPagination = null;

    public function display($tpl = null)
    {
        // Joomla 2.5 loads the template index.php AFTER the component runs,
        // so we cannot rely on its require_once'd helpers. Load here in
        // method scope; require_once's once-guard makes the index.php re-load
        // a no-op later in the same request.
        $fecBonusHelpers = JPATH_SITE . '/components/com_bonuses/helpers';
        if (file_exists($fecBonusHelpers . '/fec_common_lib.php')) {
            require_once $fecBonusHelpers . '/fec_common_lib.php';
        }
        if (file_exists($fecBonusHelpers . '/bonustypesarray.php')) {
            require_once $fecBonusHelpers . '/bonustypesarray.php';
        }
        if (file_exists($fecBonusHelpers . '/currenciesarray.php')) {
            require_once $fecBonusHelpers . '/currenciesarray.php';
        }
        // Casino Bonus Blog helper (V2.7.65): post routes, labels, thumbs for
        // the "Blog posts" tab. Optional — AuthorsHelper falls back without it.
        AuthorsHelper::loadBlogHelper();
        // Mirror the team listing's fallback pattern so the page works even
        // if the helper files use different variable names or aren't present.
        $bonustypeMapFallback = array(
            'nd'  => 'No Deposit',  'fs'  => 'Free Spins',
            'su'  => '1st Deposit Match', '1st' => '1st Deposit',
            'rl'  => 'Reload', 'cb' => 'Cashback',
            'fp'  => 'Free Play', 'fc' => 'Free Cash',
            'hr'  => 'High Roller',
        );
        $currencyMapFallback = array(
            'USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;',
            'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$',
        );
        $loadedBt = (isset($bonustypeMap) && is_array($bonustypeMap) && !empty($bonustypeMap)) ? $bonustypeMap : array();
        $loadedCu = (isset($currencyMap)  && is_array($currencyMap)  && !empty($currencyMap))  ? $currencyMap  : array();
        $this->bonustypeMap = $loadedBt + $bonustypeMapFallback;
        $this->currencyMap  = $loadedCu + $currencyMapFallback;

        // ---- Fetch the author -------------------------------------------------
        $slug = JRequest::getVar('slug', '', 'REQUEST', 'STRING');
        $model = $this->getModel('author');
        $item  = $model->getItemBySlug($slug);
        if (!$item) {
            throw new Exception('Author not found', 404);
        }

        // Augment item with computed fields
        $item['initials'] = function_exists('fec_initials_from_name')
            ? fec_initials_from_name($item['name']) : '';
        $item['avatar_hue'] = function_exists('fec_avatar_hue_from_type')
            ? fec_avatar_hue_from_type($item['author_type']) : 220;
        $jy = (int)$item['joined_year'];
        $item['years_at_fec'] = ($jy > 0 && $jy <= (int)date('Y')) ? ((int)date('Y') - $jy) : 0;

        $this->item = $item;

        // ---- Bonuses tab list -------------------------------------------------
        $limit       = (int)JRequest::getInt('limit', 10);
        $start       = max(0, (int)JRequest::getInt('limitstart', 0));
        $this->activeOnly = (int)JRequest::getInt('active', 0);

        // ---- Tab counts + sidebar Quick Facts --------------------------------
        // Fetched before the tab is resolved: the default tab depends on them.
        $this->counts = $model->getCountsForAuthor((int)$item['id'], (int)$item['joomla_user_id']);
        $this->activityAvg = $model->getActivityAverage((int)$item['id'], (int)$item['joomla_user_id'], 30);

        // Tab routing — server-side, clean URLs (?tab=bonuses|casinos|fxchecks|guides|rtp-verifications|blog).
        // Default tab is bonuses, except for a profile whose record is the
        // blog (posts, no bonuses): a contributor lands on "Blog posts"
        // (V2.7.65). Invalid values fall back to the default.
        $validTabs   = array('bonuses', 'casinos', 'fxchecks', 'guides', 'rtp-verifications', 'blog');
        $blogCount   = isset($this->counts['blog_posts']) ? (int)$this->counts['blog_posts'] : 0;
        $bonusCount  = isset($this->counts['bonuses']) ? (int)$this->counts['bonuses'] : 0;
        $defaultTab  = ($blogCount > 0 && $bonusCount === 0) ? 'blog' : 'bonuses';
        $tabReq      = strtolower(trim((string)JRequest::getVar('tab', $defaultTab)));
        $this->tab   = in_array($tabReq, $validTabs, true) ? $tabReq : $defaultTab;

        require_once JPATH_COMPONENT . '/models/authorbonuses.php';
        $bonusModel = new AuthorsModelAuthorBonuses();

        $this->bonusTotal = $bonusModel->getTotalByAuthorId((int)$item['id'], $this->activeOnly);

        // limitstart is shared by the two paginated tabs; it only applies to
        // the one being shown, the other list starts at 0.
        $bonusStart = ($this->tab === 'bonuses') ? $start : 0;
        if ($bonusStart >= $this->bonusTotal) {
            $bonusStart = 0;
            if ($this->tab === 'bonuses') JRequest::setVar('limitstart', 0);
        }

        $this->bonusItems = $bonusModel->getItemsByAuthorId((int)$item['id'], $limit, $bonusStart, $this->activeOnly);

        jimport('joomla.html.pagination');
        $this->pagination = new JPagination($this->bonusTotal, $bonusStart, $limit);

        // ---- Blog posts tab (V2.7.65) ----------------------------------------
        // Only the list of the active tab is fetched; the pill count comes
        // from $counts['blog_posts'] regardless.
        if ($this->tab === 'blog') {
            $blogLimit = 20;
            $this->blogTotal = $model->getBlogTotal((int)$item['id'], (int)$item['joomla_user_id']);
            $blogStart = $start;
            if ($blogStart >= $this->blogTotal) {
                $blogStart = 0;
                JRequest::setVar('limitstart', 0);
            }
            $this->blogItems = $model->getBlogPosts((int)$item['id'], (int)$item['joomla_user_id'], $blogLimit, $blogStart);
            $this->blogPagination = new JPagination($this->blogTotal, $blogStart, $blogLimit);
        }

        // ---- RTP verifications tab data --------------------------------------
        // Only fetch the list when this tab is active. The count for the tab
        // pill comes from $counts['rtp_verifications'] above regardless of tab.
        $this->verifiedSlots = ($this->tab === 'rtp-verifications')
            ? $model->getVerifiedSlots((int)$item['id'], 50)
            : array();

        // ---- Document setup --------------------------------------------------
        $doc = JFactory::getDocument();
        $titleParts = array($item['name']);
        if (!empty($item['role'])) $titleParts[] = $item['role'];
        $titleParts[] = 'FreeExtraChips';
        $doc->setTitle(implode(' · ', $titleParts));

        $metaDesc = trim($item['short_bio']) !== ''
            ? $item['short_bio']
            : strip_tags($item['bio']);
        $metaDesc = trim(preg_replace('/\s+/', ' ', $metaDesc));
        if (strlen($metaDesc) > 200) $metaDesc = substr($metaDesc, 0, 197) . '…';
        if ($metaDesc !== '') $doc->setMetaData('description', $metaDesc);

        // bylines.css (V2.7.59): the pen-name line in the hero. Tiny file,
        // same cache-buster pattern as methodology.css.
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/bylines.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/bylines.css') ?: 1)
        );

        // ---- JSON-LD @graph (Organization + ProfilePage + Person) ------------
        // The Person @id MUST match exactly the one emitted by the team
        // listing page (template_authors_tmpl_default.php) so Google sees
        // a single canonical Person entity across both pages.
        $this->jsonLdGraph = $this->buildJsonLdGraph($item, $metaDesc);

        parent::display($tpl);
    }

    /**
     * Build the JSON-LD @graph for the profile page.
     * Emits: Organization, WebSite, ProfilePage, Person (canonical).
     */
    protected function buildJsonLdGraph($item, $metaDesc)
    {
        $siteRoot  = rtrim(JUri::root(), '/');
        $orgId     = $siteRoot . '/#organization';
        $webSiteId = $siteRoot . '/#website';

        $authorRel    = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode((string)$item['slug']));
        $authorAbsUrl = $this->absUrl($authorRel, $siteRoot);
        $personId     = $authorAbsUrl . '#person';

        $graph = array();

        // Organization
        // publishingPrinciples (V2.7.59): the Editorial Standards page, which
        // carries the byline / pen-name policy. Same value as the team page.
        $orgNode = array(
            '@type' => 'Organization',
            '@id'   => $orgId,
            'name'  => 'FreeExtraChips',
            'url'   => $siteRoot . '/',
        );
        $standardsAbsUrl = $this->absUrl(AuthorsHelper::methodologyUrl('editorial-standards'), $siteRoot);
        if ($standardsAbsUrl !== '') $orgNode['publishingPrinciples'] = $standardsAbsUrl;
        $graph[] = $orgNode;

        // WebSite — required so ProfilePage.isPartOf has a Schema-valid
        // target. isPartOf on a WebPage (or its subclasses ProfilePage /
        // AboutPage) only accepts CreativeWork subtypes; Organization is
        // not valid there. WebSite.publisher links back to Organization.
        $graph[] = array(
            '@type'     => 'WebSite',
            '@id'       => $webSiteId,
            'url'       => $siteRoot . '/',
            'name'      => 'FreeExtraChips',
            'publisher' => array('@id' => $orgId),
        );

        // ProfilePage — wraps the page, mainEntity → Person
        $profilePage = array(
            '@type'      => 'ProfilePage',
            '@id'        => $authorAbsUrl . '#profilepage',
            'url'        => $authorAbsUrl,
            'name'       => $item['name'] . (!empty($item['role']) ? ' · ' . $item['role'] : ''),
            'mainEntity' => array('@id' => $personId),
            'isPartOf'   => array('@id' => $webSiteId),
        );
        if ($metaDesc !== '') $profilePage['description'] = $metaDesc;
        $graph[] = $profilePage;

        // Person canonical entity
        $person = array(
            '@type'    => 'Person',
            '@id'      => $personId,
            'name'     => $item['name'],
            'url'      => $authorAbsUrl,
            'worksFor' => array('@id' => $orgId),
        );
        if (!empty($item['role']))      $person['jobTitle']    = $item['role'];
        if (!empty($item['short_bio'])) $person['description'] = $item['short_bio'];
        elseif (!empty($item['bio']))   $person['description'] = strip_tags($item['bio']);
        if (!empty($item['avatar_url'])) $person['image']      = $this->absUrl($item['avatar_url'], $siteRoot);
        // knowsAbout: expertise tags become Schema's expertise hint
        if (!empty($item['expertise']) && is_array($item['expertise'])) {
            $person['knowsAbout'] = array_values($item['expertise']);
        }
        // knowsLanguage: language names (Schema accepts plain strings)
        if (!empty($item['languages']) && is_array($item['languages'])) {
            $person['knowsLanguage'] = array_values($item['languages']);
        }
        $graph[] = $person;

        $doc = array('@context' => 'https://schema.org', '@graph' => $graph);
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($json !== false) ? $json : '';
    }

    /**
     * Absoluteify a URL: pass-through if already http(s)://, otherwise
     * prefix with $siteRoot.
     */
    protected function absUrl($u, $siteRoot)
    {
        $u = trim((string)$u);
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        return $siteRoot . '/' . ltrim($u, '/');
    }
}