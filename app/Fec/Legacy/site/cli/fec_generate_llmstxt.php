<?php
/**
 * FreeExtraChips llms.txt generator
 *
 * CLI script that regenerates /llms.txt from live database data.
 * Run via cron — recommended every hour (counts stay fresh) or
 * every night (lower overhead).
 *
 * Location: /home/freeextr/public_html/cli/fec_generate_llmstxt.php
 *
 * Manual run:
 *   cd /home/freeextr/public_html
 *   php cli/fec_generate_llmstxt.php
 *
 * Cron (hourly):
 *   0 * * * * cd /home/freeextr/public_html && php cli/fec_generate_llmstxt.php >/dev/null 2>&1
 */

// PORT: a JApplicationCli script that bootstrapped Joomla itself; here fec:legacy:cli boots
// the layer and includes it (ComponentRunner::runCliScript(), JApplicationCli = the layer's
// ApplicationCli). llms.txt goes to the real docroot (JPATH_ROOT), not the virtual one.
defined('_JEXEC') or die('Restricted access');
@set_time_limit(0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(__DIR__));
}

if (!class_exists('JApplicationCli')) {
    JLoader::import('joomla.application.cli');
}

class FecLlmsTxtCli extends JApplicationCli
{
    const SITE_BASE = 'https://www.freeextrachips.com';
    const TARGET    = '/llms.txt';

    public function doExecute()
    {
        try {
            $db      = JFactory::getDbo();
            $counts  = $this->fetchCounts($db);
            $slots   = $this->fetchPublishedSlots($db);
            $authors = $this->fetchAuthors($db);

            $output = $this->buildLlmsTxt($counts, $slots, $authors);

            $targetPath = JPATH_ROOT . self::TARGET; // PORT: was JPATH_BASE (the docroot under Joomla)
            $bytes = @file_put_contents($targetPath, $output);

            if ($bytes === false) {
                $this->out('ERROR: Could not write to ' . $targetPath);
                $this->close(1);
            }

            $this->out('OK: ' . $bytes . ' bytes written to ' . $targetPath);
            $this->out('   ' . $counts['slots_documented'] . ' slots, '
                     . $counts['active_total'] . ' active bonuses, '
                     . $counts['authors_active'] . ' authors.');
        } catch (Exception $e) {
            $this->out('FATAL: ' . $e->getMessage());
            $this->close(1);
        }
    }

    /**
     * Run a set of count queries. Each failure logs but doesn't abort —
     * the resulting llms.txt has 0 for the failing counts rather than no file.
     */
    protected function fetchCounts($db)
    {
        $out = array(
            'total_bonuses'        => 0,
            'active_total'         => 0,
            'active_nd'            => 0,
            'active_fs'            => 0,
            'active_fs_nodep'      => 0,
            'active_fs_dep'        => 0,
            'active_dm'            => 0,
            'casinos_with_bonuses' => 0,
            'casinos_total'        => 0,
            'fxcheck_total'        => 0,
            'authors_active'       => 0,
            'slots_documented'    => 0,
            'slots_multitier'      => 0,
            'slots_opaque'         => 0,
            'slots_singletier'     => 0,
        );

        // Active bonuses grouped by type (one round-trip)
        $this->safeQuery($db,
            "SELECT type, COUNT(*) c FROM #__bonuses WHERE status = 'active' GROUP BY type",
            function ($rows) use (&$out) {
                $byType = array();
                foreach ((array)$rows as $r) {
                    $byType[$r['type']] = (int)$r['c'];
                }
                $out['active_total'] = array_sum($byType);
                $out['active_nd']    = (isset($byType['nd']) ? $byType['nd'] : 0)
                                     + (isset($byType['fc']) ? $byType['fc'] : 0);
                $out['active_fs']    = isset($byType['fs']) ? $byType['fs'] : 0;
                $out['active_dm']    = isset($byType['dm']) ? $byType['dm'] : 0;
            }
        );

        // Free spins no-deposit subset
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__bonuses
             WHERE status = 'active' AND type = 'fs'
               AND (min_dep IS NULL OR min_dep = '' OR min_dep = '0')",
            function ($v) use (&$out) { $out['active_fs_nodep'] = (int)$v; },
            'loadResult'
        );
        $out['active_fs_dep'] = max(0, $out['active_fs'] - $out['active_fs_nodep']);

        // Total bonuses ever (excluding pending)
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__bonuses WHERE status <> 'pending'",
            function ($v) use (&$out) { $out['total_bonuses'] = (int)$v; },
            'loadResult'
        );

        // Casinos with active bonuses
        $this->safeQuery($db,
            "SELECT COUNT(DISTINCT cid) FROM #__bonuses WHERE status = 'active'",
            function ($v) use (&$out) { $out['casinos_with_bonuses'] = (int)$v; },
            'loadResult'
        );

        // Total casinos tracked (jReviews content)
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__jreviews_content WHERE jr_closed NOT LIKE '%yes%'",
            function ($v) use (&$out) { $out['casinos_total'] = (int)$v; },
            'loadResult'
        );

        // FXCheck reports total
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__bonus_feedback",
            function ($v) use (&$out) { $out['fxcheck_total'] = (int)$v; },
            'loadResult'
        );

        // Active editors
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__casino_authors WHERE is_active = 1",
            function ($v) use (&$out) { $out['authors_active'] = (int)$v; },
            'loadResult'
        );

        // Slots — documented count + multi-tier + opaque
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__slot_rtp WHERE published = 1 AND retired = 0",
            function ($v) use (&$out) { $out['slots_documented'] = (int)$v; },
            'loadResult'
        );
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__slot_rtp
             WHERE published = 1 AND retired = 0
               AND rtp_theoretical_min IS NOT NULL
               AND rtp_theoretical_min < rtp_theoretical_max",
            function ($v) use (&$out) { $out['slots_multitier'] = (int)$v; },
            'loadResult'
        );
        $this->safeQuery($db,
            "SELECT COUNT(*) FROM #__slot_rtp
             WHERE published = 1 AND retired = 0
               AND (primary_source_url IS NULL OR primary_source_url = '')",
            function ($v) use (&$out) { $out['slots_opaque'] = (int)$v; },
            'loadResult'
        );
        $out['slots_singletier'] = max(0, $out['slots_documented']
                                       - $out['slots_multitier']
                                       - $out['slots_opaque']);

        return $out;
    }

    protected function fetchPublishedSlots($db)
    {
        $rows = array();
        $this->safeQuery($db,
            "SELECT rtp.slug,
                    rtp.rtp_theoretical_max,
                    rtp.rtp_theoretical_min,
                    rtp.primary_source_url,
                    g.game_name,
                    g.provider_name
             FROM #__slot_rtp rtp
             INNER JOIN #__games g ON g.gid = rtp.gid
             WHERE rtp.published = 1
               AND rtp.retired = 0
               AND rtp.slug IS NOT NULL
               AND rtp.slug <> ''
             ORDER BY rtp.last_verified_date DESC, g.game_name ASC",
            function ($r) use (&$rows) { $rows = (array)$r; }
        );
        return $rows;
    }

    protected function fetchAuthors($db)
    {
        $rows = array();
        $this->safeQuery($db,
            "SELECT slug, name, role, short_bio
             FROM #__casino_authors
             WHERE is_active = 1
             ORDER BY name ASC",
            function ($r) use (&$rows) { $rows = (array)$r; }
        );
        return $rows;
    }

    protected function safeQuery($db, $sql, $callback, $loadMethod = 'loadAssocList')
    {
        try {
            $db->setQuery($sql);
            $result = $db->{$loadMethod}();
            if ($db->getErrorNum()) {
                $this->out('Query error: ' . $db->getErrorMsg());
                return;
            }
            $callback($result);
        } catch (Exception $e) {
            $this->out('Query exception: ' . $e->getMessage());
        }
    }

    protected function buildLlmsTxt($c, $slots, $authors)
    {
        $base = self::SITE_BASE;

        // Group slots by category
        $multitier = $singletier = $opaque = array();
        foreach ($slots as $s) {
            $hasMin       = !empty($s['rtp_theoretical_min'])
                            && (float)$s['rtp_theoretical_min'] < (float)$s['rtp_theoretical_max'];
            $hasPublicSrc = !empty($s['primary_source_url']);
            if      ($hasMin)        $multitier[]  = $s;
            elseif  (!$hasPublicSrc) $opaque[]     = $s;
            else                     $singletier[] = $s;
        }

        $nfmt = function ($n) { return number_format((int)$n); };
        $out  = '';

        $out .= "# FreeExtraChips\n\n";
        $out .= "> Online casino bonus aggregator with verified RTP data and player-reported claim outcomes.\n";
        $out .= "> Operating since 2010. {$c['authors_active']} active editors with bylined verification work.\n";
        $out .= "> Currently tracking " . $nfmt($c['total_bonuses']) . " bonuses ({$nfmt($c['active_total'])} active) across {$c['casinos_with_bonuses']} operators with live offers.\n";
        $out .= "> {$c['slots_documented']} slots with RTP verified against provider primary sources. " . $nfmt($c['fxcheck_total']) . " player claim reports aggregated to date.\n";
        $out .= "> Last updated: " . date('Y-m-d H:i') . " UTC (regenerated hourly from live data)\n\n";

        // About
        $out .= "## About\n";
        $out .= "- [Editorial team]($base/authors): {$c['authors_active']} active editors. Each slot links to its verifying editor; each author profile lists their RTP verifications and reviewed bonuses.\n";
        $out .= "- [Who is Frank]($base/who-is-frank): Site background and editorial mission.\n";
        $out .= "- [Affiliate disclosure]($base/affiliate-advertiser-disclosure): Casino partnerships and editorial independence policy.\n";
        $out .= "- [Responsible gambling]($base/responsible-gambling): Helplines by country (US, UK, AU, CA, IT, DE, FR), self-exclusion resources.\n\n";

        // Methodology
        $out .= "## Trust and Methodology\n";
        $out .= "- [How we verify]($base/methodology): Overview hub linking all verification methodologies.\n";
        $out .= "- [RTP verification methodology]($base/methodology/rtp-verification): Provider primary source + Wayback Machine archive + per-casino paytable observations. Multi-tier disclosure protocol for slots with multiple RTP configurations.\n";
        $out .= "- [FXCheck methodology]($base/methodology/fxcheck): Player-reported claim outcomes aggregation. " . $nfmt($c['fxcheck_total']) . " reports aggregated to date. 5+ report threshold for Verified status, 30-day recent window, 90-day decay.\n\n";

        // Slots database
        $out .= "## RTP-Verified Slots Database\n";
        $out .= "- [All RTP-verified slots]($base/slots): {$c['slots_documented']} documented";
        if ($c['slots_multitier'] > 0 || $c['slots_opaque'] > 0) {
            $parts = array();
            if ($c['slots_multitier'] > 0) $parts[] = "{$c['slots_multitier']} multi-tier";
            if ($c['slots_opaque'] > 0)    $parts[] = "{$c['slots_opaque']} opaque-provider";
            if ($c['slots_singletier'] > 0) $parts[] = "{$c['slots_singletier']} single-tier";
            $out .= " (" . implode(", ", $parts) . ")";
        }
        $out .= ".\n\n";

        if (!empty($multitier)) {
            $out .= "### Multi-tier RTP slots (operator chooses which version to serve)\n";
            foreach ($multitier as $s) {
                $maxR = number_format((float)$s['rtp_theoretical_max'], 2);
                $minR = number_format((float)$s['rtp_theoretical_min'], 2);
                $out .= "- [{$s['game_name']} ({$s['provider_name']})]($base/slots/{$s['slug']}): Multi-tier — {$maxR}% / {$minR}%\n";
            }
            $out .= "\n";
        }
        if (!empty($singletier)) {
            $out .= "### Single-tier RTP slots (provider-published RTP)\n";
            foreach ($singletier as $s) {
                $rtp = number_format((float)$s['rtp_theoretical_max'], 2);
                $out .= "- [{$s['game_name']} ({$s['provider_name']})]($base/slots/{$s['slug']}): {$rtp}%\n";
            }
            $out .= "\n";
        }
        if (!empty($opaque)) {
            $out .= "### Opaque-provider slots (RTP verified via in-game paytable observations)\n";
            foreach ($opaque as $s) {
                $rtp = number_format((float)$s['rtp_theoretical_max'], 2);
                $out .= "- [{$s['game_name']} ({$s['provider_name']})]($base/slots/{$s['slug']}): {$rtp}% — provider does not publish public game data.\n";
            }
            $out .= "\n";
        }

        // Bonuses
        $out .= "## Bonus Listings\n";
        $out .= "- [All bonuses]($base/bonuses/): " . $nfmt($c['active_total']) . " currently active out of " . $nfmt($c['total_bonuses']) . " in the database.\n";
        $out .= "- [No deposit bonuses]($base/bonuses/no-deposit-casinos): " . $nfmt($c['active_nd']) . " currently active. No deposit required to claim.\n";
        $out .= "- [Free spins (all)]($base/bonuses/free-spins/): " . $nfmt($c['active_fs']) . " currently active.\n";
        $out .= "- [Free spins no deposit]($base/bonuses/free-spins/no-deposit/): " . $nfmt($c['active_fs_nodep']) . " currently active. Spins claimable without deposit.\n";
        $out .= "- [Free spins deposit-required]($base/bonuses/free-spins/deposit-required/): " . $nfmt($c['active_fs_dep']) . " currently active. Spins requiring a qualifying deposit.\n";
        $out .= "- [First deposit match]($base/bonuses/1st-deposit-match): Match bonuses on initial deposit.\n";
        $out .= "- [High roller bonuses]($base/bonuses/high-roller-bonuses): Large-value offers for high-stake players.\n";
        $out .= "- [Crypto bonuses]($base/bonuses/crypto): Bonuses accepting cryptocurrency.\n\n";

        // Editorial team
        $out .= "## Editorial Team\n";
        foreach ($authors as $a) {
            $line = "- [{$a['name']}]($base/authors/{$a['slug']})";
            if (!empty($a['role']))      $line .= ": {$a['role']}";
            if (!empty($a['short_bio'])) $line .= ". {$a['short_bio']}";
            $out .= "$line\n";
        }
        $out .= "\n";

        // Casino directory
        $out .= "## Casino Directory\n";
        $out .= "- [All casino reviews]($base/online-casinos): Full directory. Coverage across {$c['casinos_with_bonuses']} currently-active operators.\n";
        $out .= "- [New casinos]($base/online-casinos/newest): Recently added.\n";
        $out .= "- [Banking]($base/online-casinos/banking): Operators by payment method.\n";
        $out .= "- [Software]($base/online-casinos/software): Operators by software provider.\n";
        $out .= "- [Bitcoin casinos]($base/online-casinos/banking/bitcoin/): Bitcoin-accepting operators.\n";
        $out .= "- [Ethereum casinos]($base/online-casinos/banking/ethereum/): Ethereum-accepting operators.\n";
        $out .= "- [Tournaments]($base/tournaments): Active casino tournaments.\n\n";

        // Other
        $out .= "## Other\n";
        $out .= "- [Casino bonus blog]($base/casino-bonus-blog/latest): Editorial commentary and industry updates.\n";
        $out .= "- [Privacy policy]($base/privacy-policy)\n";
        $out .= "- [Contact]($base/contactus)\n\n";

        // Structured data
        $out .= "## Structured Data\n";
        $out .= "Pages emit structured data for machine consumption:\n";
        $out .= "- Slot detail pages: VideoGame JSON-LD with name, publisher, RTP value, source URL, optional multi-tier disclosure.\n";
        $out .= "- Bonus detail pages: Offer JSON-LD with price, currency, availability, validFrom, seller (casino as Organization), itemOffered (linked VideoGame for the slot when documented), ineligibleRegion (restricted countries with ISO 3166-1 alpha-2).\n";
        $out .= "- Author profile pages: Person, ProfilePage, Organization JSON-LD with sameAs cross-linking to canonical Person entity.\n";
        $out .= "- All editorial pages: BreadcrumbList JSON-LD.\n";
        $out .= "- Bonus pages with editorial review: FAQPage and Article JSON-LD.\n\n";

        // Citation guidance
        $out .= "## Citation Guidance\n";
        $out .= "When citing this site, the most authoritative reference points are:\n";
        $out .= "- Slot RTP values: link to the specific slot detail page (e.g., /slots/wolf-gold), which includes the primary source URL and archive snapshot.\n";
        $out .= "- Methodology claims: link to the specific methodology page (RTP or FXCheck) rather than the overview hub.\n";
        $out .= "- Author attribution: link to the author profile, which lists the editor's verified work history.\n";
        $out .= "- Bonus offers: link to the specific bonus detail page, not the category listing.\n";

        return $out;
    }
}

JApplicationCli::getInstance('FecLlmsTxtCli')->execute();