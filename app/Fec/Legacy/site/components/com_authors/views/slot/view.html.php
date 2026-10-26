<?php
/**
 * Slot view — single slot detail page (V2.6).
 *
 * URL: /slots/<slug>
 *
 * Data plumbed to template:
 *   $this->slot          slot record (object) with joined game info
 *   $this->observations  list of #__slot_rtp_observations for this gid
 *   $this->bonuses       active bonuses on this slot
 *   $this->author        verified_by_author row (or null)
 *   $this->jsonLdGraph   pre-encoded JSON-LD @graph
 */

defined('_JEXEC') or die;

require_once JPATH_COMPONENT . '/helpers/authors.php';

jimport('joomla.application.component.view');

class AuthorsViewSlot extends JView
{
    public $slot         = null;
    public $observations = array();
    public $bonuses      = array();
    public $author       = null;
    public $jsonLdGraph  = '';
    public $limit        = 10;
    public $limitstart   = 0;
    public $totalBonuses = 0;
    public $pagination   = null;
    public $blogPosts    = array();   // 2.7.72: blog posts linked to this slot
    public $blogTotal    = 0;

    public function display($tpl = null)
    {
        $slug = JRequest::getString('slug', '');
        if ($slug === '') {
            throw new Exception('Slot not specified', 404);
        }

        // Use the slot model directly (avoid Joomla's model resolution which
        // sometimes returns the wrong default in mixed-view components)
        require_once JPATH_COMPONENT . '/models/slot.php';
        $model = new AuthorsModelSlot();

        $slot = $model->getBySlug($slug);
        if (!$slot) {
            throw new Exception('Slot not found', 404);
        }

        // Pagination params
        $this->limit      = 10;
        $this->limitstart = max(0, JRequest::getInt('limitstart', 0));

        $this->slot         = $slot;
        $this->observations = $model->getObservations($slot->gid);
        $this->totalBonuses = $model->getActiveBonusCount($slot->gid);
        $this->bonuses      = $model->getActiveBonuses($slot->gid, $this->limit, $this->limitstart);

        // JPagination instance
        jimport('joomla.html.pagination');
        $this->pagination = new JPagination($this->totalBonuses, $this->limitstart, $this->limit);

        if (!empty($slot->verified_by_author_id)) {
            $this->author = $model->getAuthor((int)$slot->verified_by_author_id);
        }

        // Blog posts about this slot and its family (2.7.72)
        $this->blogPosts = $model->getBlogPosts($slot->gid, 4);
        $this->blogTotal = $this->blogPosts ? $model->getBlogPostCount($slot->gid) : 0;

        // Document setup
        $doc = JFactory::getDocument();
        $title = $slot->game_name . ' Slot — RTP, Volatility & Active Bonuses · FreeExtraChips';
        $doc->setTitle($title);

        $rtpStr = '';
        if ($slot->rtp_theoretical_max !== null) {
            $rtpStr = number_format((float)$slot->rtp_theoretical_max, 2) . '% RTP';
            if ($slot->rtp_theoretical_min !== null
                && (float)$slot->rtp_theoretical_min < (float)$slot->rtp_theoretical_max) {
                $rtpStr .= ' (tier-low ' . number_format((float)$slot->rtp_theoretical_min, 2) . '%)';
            }
        }
        $providerStr = $slot->provider_name ? ' by ' . $slot->provider_name : '';
        $desc = trim(
            $slot->game_name . $providerStr . '. '
            . ($rtpStr ? $rtpStr . '. ' : '')
            . (count($this->observations) > 0 ? 'Per-casino RTP observations included. ' : '')
            . 'Sources verified against the provider primary page and archived on the Wayback Machine.'
        );
        $doc->setDescription(substr($desc, 0, 300));
        // Retired slots are dead content for crawlers but kept accessible
        // for historical reference (incoming links, old bookmarks).
        if (!empty($slot->retired) && (int)$slot->retired === 1) {
            $doc->setMetaData('robots', 'noindex, follow');
        } else {
            $doc->setMetaData('robots', 'index, follow');
        }

        // Canonical URL — always points to page 1 (limitstart=0) so paginated
        // pages don't fragment indexing weight.
        $canonical = JURI::root() . 'slots/' . $slot->slug;
        $doc->addHeadLink($canonical, 'canonical');

        // For paginated pages beyond page 1, add prev/next link tags
        // (helps both crawlers and AI Mode understand the series).
        if ($this->limitstart > 0) {
            $prevStart = max(0, $this->limitstart - $this->limit);
            $prevUrl   = $canonical . ($prevStart > 0 ? '?limitstart=' . $prevStart : '');
            $doc->addHeadLink($prevUrl, 'prev');
        }
        if ($this->limitstart + $this->limit < $this->totalBonuses) {
            $nextUrl = $canonical . '?limitstart=' . ($this->limitstart + $this->limit);
            $doc->addHeadLink($nextUrl, 'next');
        }

        // Stylesheet
        $base = JURI::root(true) . '/components/com_authors/assets/';
        $assetsPath = JPATH_SITE . '/components/com_authors/assets/';
        $doc->addStyleSheet($base . 'methodology.css?v=' . (@filemtime($assetsPath . 'methodology.css') ?: 1));
        $doc->addStyleSheet($base . 'slot.css?v=' . (@filemtime($assetsPath . 'slot.css') ?: 1));

        // JSON-LD
        $this->jsonLdGraph = $this->buildJsonLdGraph($slot, $this->author, $canonical, $this->blogPosts);
        if ($this->jsonLdGraph) {
            $doc->addCustomTag('<script type="application/ld+json">' . $this->jsonLdGraph . '</script>');
        }

        parent::display($tpl);
    }

    /**
     * Build the JSON-LD @graph for this slot page.
     *
     * Types emitted:
     *   - Organization (FreeExtraChips, publisher)
     *   - WebSite
     *   - Article (the page itself — type=Article so AI consumers index it
     *     as evergreen content rather than NewsArticle)
     *   - VideoGame (the slot, with custom RTP properties)
     *   - Person (verified_by_author if present)
     *   - BreadcrumbList
     */
    protected function buildJsonLdGraph($slot, $author, $canonical, $blogPosts = array())
    {
        $orgId      = JURI::root() . '#organization';
        $siteId     = JURI::root() . '#website';
        $pageId     = $canonical . '#article';
        $gameId     = $canonical . '#game';
        $authorId   = '';
        if ($author && !empty($author['slug'])) {
            $authorId = JURI::root() . 'authors/' . $author['slug'] . '#person';
        }

        $graph = array();

        $graph[] = array(
            '@type'  => 'Organization',
            '@id'    => $orgId,
            'name'   => 'FreeExtraChips',
            'url'    => rtrim(JURI::root(), '/'),
        );

        $graph[] = array(
            '@type'     => 'WebSite',
            '@id'       => $siteId,
            'url'       => rtrim(JURI::root(), '/'),
            'name'      => 'FreeExtraChips',
            'publisher' => array('@id' => $orgId),
        );

        // VideoGame node — the slot itself, with custom RTP properties as additionalProperty.
        $game = array(
            '@type' => 'VideoGame',
            '@id'   => $gameId,
            'name'  => $slot->game_name,
            'gamePlatform' => array('Online', 'Web', 'Mobile'),
            'applicationCategory' => 'Game',
            'genre' => 'Casino',
        );
        if (!empty($slot->provider_name)) {
            $game['publisher'] = array(
                '@type' => 'Organization',
                'name'  => $slot->provider_name,
            );
        }
        if (!empty($slot->release_year)) {
            $game['datePublished'] = (string)$slot->release_year;
        }

        // additionalProperty: RTP and other slot metadata.
        $props = array();
        if ($slot->rtp_theoretical_max !== null) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'RTP (theoretical, maximum tier)',
                'value' => (string)number_format((float)$slot->rtp_theoretical_max, 2) . '%',
            );
        }
        if ($slot->rtp_theoretical_min !== null
            && (float)$slot->rtp_theoretical_min < (float)$slot->rtp_theoretical_max) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'RTP (theoretical, minimum tier)',
                'value' => (string)number_format((float)$slot->rtp_theoretical_min, 2) . '%',
            );
        }
        if (!empty($slot->volatility)) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Volatility',
                'value' => $slot->volatility,
            );
        }
        if (!empty($slot->max_win_x)) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Maximum win',
                'value' => $slot->max_win_x . 'x bet',
            );
        }
        if (!empty($slot->reels)) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Reels',
                'value' => (string)$slot->reels,
            );
        }
        if (!empty($slot->paylines)) {
            $props[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Paylines',
                'value' => (string)$slot->paylines,
            );
        }
        if (!empty($props)) {
            $game['additionalProperty'] = $props;
        }

        // 2.7.72: blog posts about the slot (subjectOf) and about its family
        // (mentions, from the post side). The @id matches the BlogPosting
        // node fec_blog.php 1.0.9 emits on the post page.
        $subjectOf = array();
        foreach ($blogPosts as $bp) {
            if (empty($bp['title'])) continue;
            $postUrl = AuthorsHelper::blogPostUrl($bp['id'], $bp['alias'], isset($bp['catid']) ? $bp['catid'] : 0);
            if (strpos($postUrl, 'http') !== 0) $postUrl = rtrim(JURI::root(), '/') . '/' . ltrim($postUrl, '/');
            $node = array(
                '@type'    => 'BlogPosting',
                '@id'      => $postUrl . '#blogposting',
                'headline' => $bp['title'],
                'url'      => $postUrl,
            );
            if (!empty($bp['publish_up']) && strpos($bp['publish_up'], '0000-00-00') !== 0) {
                $node['datePublished'] = gmdate('c', strtotime($bp['publish_up'] . ' UTC'));
            }
            if (!empty($bp['created_by_alias'])) $node['author'] = array('@type' => 'Person', 'name' => $bp['created_by_alias']);
            if (isset($bp['slot_rel']) && $bp['slot_rel'] === 'game') $subjectOf[] = $node;
        }
        if (!empty($subjectOf)) {
            $game['subjectOf'] = $subjectOf;
        }

        $graph[] = $game;

        // Article node — the page about the game
        $article = array(
            '@type'         => 'Article',
            '@id'           => $pageId,
            'headline'      => $slot->game_name . ' — RTP & Active Bonuses',
            'about'         => array('@id' => $gameId),
            'mainEntityOfPage' => $canonical,
            'publisher'     => array('@id' => $orgId),
            'isPartOf'      => array('@id' => $siteId),
        );
        if (!empty($slot->last_verified_date) && $slot->last_verified_date !== '0000-00-00') {
            $article['dateModified'] = $slot->last_verified_date;
        }
        if (!empty($slot->published_at) && $slot->published_at !== '0000-00-00 00:00:00') {
            $article['datePublished'] = substr($slot->published_at, 0, 10);
        }
        if ($authorId !== '' && $author) {
            $article['author'] = array(
                '@type' => 'Person',
                '@id'   => $authorId,
                'name'  => $author['name'],
                'url'   => JURI::root() . 'authors/' . $author['slug'],
            );
            // Also emit Person node
            $graph[] = array(
                '@type' => 'Person',
                '@id'   => $authorId,
                'name'  => $author['name'],
                'url'   => JURI::root() . 'authors/' . $author['slug'],
                'description' => !empty($author['short_bio']) ? $author['short_bio'] : '',
                'jobTitle'    => !empty($author['role']) ? $author['role'] : '',
            );
        }
        $graph[] = $article;

        // BreadcrumbList
        $graph[] = array(
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(
                array(
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => rtrim(JURI::root(), '/'),
                ),
                array(
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Slots',
                    'item' => JURI::root() . 'slots',
                ),
                array(
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $slot->game_name,
                ),
            ),
        );

        return json_encode(array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
