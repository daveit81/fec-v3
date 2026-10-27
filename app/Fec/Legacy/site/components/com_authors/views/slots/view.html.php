<?php
/**
 * Slots index view — /slots
 *
 * Lists all published slot pages. Filterable by provider (server side, the
 * /slots/p/<provider> URLs) and, since 2.7.91, re-orderable and filterable
 * client side over the full server-rendered grid: the page is one cached
 * document, the reader changes the order and the filters in the browser.
 */
defined('_JEXEC') or die;

require_once JPATH_COMPONENT . '/helpers/authors.php';

jimport('joomla.application.component.view');

class AuthorsViewSlots extends JView
{
    public $slots        = array();
    public $allSlots     = array();
    public $providers    = array();
    public $filter_provider = '';
    public $stats        = array();
    public $glance       = array();
    public $jsonLdGraph  = '';

    public function display($tpl = null)
    {
        require_once JPATH_COMPONENT . '/models/slot.php';
        $model = new AuthorsModelSlot();

        // Provider filter comes from the URL — Joomla SEF lowercases path
        // segments, so what arrives is the lowercase form. Resolve back to
        // the display name so the active chip can be matched and the title/
        // description can be tailored.
        $providerSlug = JRequest::getString('provider', '');
        $this->filter_provider = '';
        $this->providers = $model->getProvidersWithPublishedSlots();
        if ($providerSlug !== '') {
            foreach ($this->providers as $p) {
                if (strtolower($p->name) === strtolower($providerSlug)) {
                    $this->filter_provider = $p->name;
                    break;
                }
            }
        }

        $this->slots = $model->getPublishedSlots($this->filter_provider);

        // The hero numbers and the at-a-glance rows describe the whole
        // database even on a provider-filtered page.
        $this->allSlots = ($this->filter_provider === '') ? $this->slots : $model->getPublishedSlots('');
        $this->stats  = $this->computeStats($this->allSlots, $this->providers);
        $this->glance = $this->computeGlance($this->allSlots);

        $doc = JFactory::getDocument();
        $count = count($this->slots);
        $total = count($this->allSlots);
        $doc->setTitle('RTP-Verified Slots Database · FreeExtraChips');
        $doc->setDescription('Slot RTP data verified against provider primary sources and archived on the Wayback Machine. ' . $total . ' slot' . ($total === 1 ? '' : 's') . ' currently documented, with multi-tier RTP disclosure and per-casino observations. Sort by RTP, tier spread, max win or verification date.');

        // SEO: the root /slots is indexable; provider-filtered variants are
        // thin/duplicate content (same H1, same layout, only the visible
        // subset changes), so noindex them and canonical to the parent.
        // 'follow' keeps internal link equity flowing to slot detail pages.
        $canonical = JURI::root() . 'slots';
        if ($this->filter_provider !== '') {
            $doc->setMetaData('robots', 'noindex, follow');
            // canonical stays at /slots — consolidates authority on parent
        } else {
            $doc->setMetaData('robots', 'index, follow');
        }
        $doc->addHeadLink($canonical, 'canonical');

        $base = JURI::root(true) . '/components/com_authors/assets/';
        $assetsPath = JPATH_SITE . '/components/com_authors/assets/';
        $doc->addStyleSheet($base . 'methodology.css?v=' . (@filemtime($assetsPath . 'methodology.css') ?: 1));
        $doc->addStyleSheet($base . 'slot.css?v=' . (@filemtime($assetsPath . 'slot.css') ?: 1));

        // JSON-LD: CollectionPage + ItemList of the documented slots
        $orgId  = JURI::root() . '#organization';
        $listId = $canonical . '#list';
        $items  = array();
        $pos = 0;
        foreach ($this->allSlots as $s) {
            if ($pos >= 100) break;
            $pos++;
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'url'      => JURI::root() . 'slots/' . rawurlencode($s->slug),
                'name'     => $s->game_name,
            );
        }
        $graph = array(
            array(
                '@type' => 'Organization',
                '@id'   => $orgId,
                'name'  => 'FreeExtraChips',
                'url'   => rtrim(JURI::root(), '/'),
            ),
            array(
                '@type'    => 'CollectionPage',
                '@id'      => $canonical . '#page',
                'url'      => $canonical,
                'name'     => 'RTP-Verified Slots',
                'description' => $total . ' slot RTP records sourced from provider primary documentation, archived on the Wayback Machine.',
                'publisher' => array('@id' => $orgId),
                'numberOfItems' => $total,
                'mainEntity' => array('@id' => $listId),
            ),
            array(
                '@type' => 'ItemList',
                '@id'   => $listId,
                'name'  => 'RTP-verified slots',
                'numberOfItems' => $total,
                'itemListOrder' => 'https://schema.org/ItemListUnordered',
                'itemListElement' => $items,
            ),
            array(
                '@type' => 'BreadcrumbList',
                'itemListElement' => array(
                    array(
                        '@type' => 'ListItem', 'position' => 1,
                        'name' => 'Home',
                        'item' => rtrim(JURI::root(), '/'),
                    ),
                    array(
                        '@type' => 'ListItem', 'position' => 2,
                        'name' => 'Slots',
                    ),
                ),
            ),
        );
        $this->jsonLdGraph = json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $doc->addCustomTag('<script type="application/ld+json">' . $this->jsonLdGraph . '</script>');

        parent::display($tpl);
    }

    /**
     * Hero strip numbers, computed over the whole published set.
     */
    protected function computeStats($slots, $providers)
    {
        $stats = array(
            'total'        => count($slots),
            'providers'    => count($providers),
            'multi'        => 0,
            'highest'      => null,   // slot object with the top RTP max
            'lastVerified' => '',     // YYYY-MM-DD
            'lastSlot'     => null,
        );
        foreach ($slots as $s) {
            if ($s->is_multi_tier) $stats['multi']++;
            if ($s->rtp_theoretical_max !== null) {
                if ($stats['highest'] === null || (float)$s->rtp_theoretical_max > (float)$stats['highest']->rtp_theoretical_max) {
                    $stats['highest'] = $s;
                }
            }
            $d = (string)$s->last_verified_date;
            if ($d !== '' && $d !== '0000-00-00' && $d > $stats['lastVerified']) {
                $stats['lastVerified'] = $d;
                $stats['lastSlot'] = $s;
            }
        }
        return $stats;
    }

    /**
     * Three short lists for the at-a-glance row: highest RTP on file,
     * widest tier spread, most recently verified. Three entries each.
     */
    protected function computeGlance($slots)
    {
        $byRtp = array();
        $bySpread = array();
        $byDate = array();
        foreach ($slots as $s) {
            if ($s->rtp_theoretical_max !== null) $byRtp[] = $s;
            if ($s->is_multi_tier) $bySpread[] = $s;
            $d = (string)$s->last_verified_date;
            if ($d !== '' && $d !== '0000-00-00') $byDate[] = $s;
        }
        fec_php5_usort($byRtp, function ($a, $b) {
            $x = (float)$a->rtp_theoretical_max; $y = (float)$b->rtp_theoretical_max;
            if ($x == $y) return strcmp($a->game_name, $b->game_name);
            return ($x < $y) ? 1 : -1;
        });
        fec_php5_usort($bySpread, function ($a, $b) {
            $x = (float)$a->rtp_theoretical_max - (float)$a->rtp_theoretical_min;
            $y = (float)$b->rtp_theoretical_max - (float)$b->rtp_theoretical_min;
            if ($x == $y) return strcmp($a->game_name, $b->game_name);
            return ($x < $y) ? 1 : -1;
        });
        fec_php5_usort($byDate, function ($a, $b) {
            $c = strcmp((string)$b->last_verified_date, (string)$a->last_verified_date);
            if ($c !== 0) return $c;
            return strcmp((string)$b->published_at, (string)$a->published_at);
        });
        return array(
            'rtp'    => array_slice($byRtp, 0, 3),
            'spread' => array_slice($bySpread, 0, 3),
            'recent' => array_slice($byDate, 0, 3),
        );
    }
}
