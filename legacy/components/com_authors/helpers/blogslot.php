<?php
/**
 * AuthorsHelperBlogslot — links a blog post to an RTP-verified slot (V2.7.72).
 *
 * A post carries #__fec_blog_meta.slot_gid (#__games.gid, the key of
 * #__slot_rtp) and slot_rel:
 *   game    the post is about that slot;
 *   family  the post is about a sequel, a variant or the jackpot network of
 *           that slot (Reactoonz 100 -> Reactoonz, Immortal Romance 2 ->
 *           Immortal Romance, "Mega Moolah pays ..." -> Mega Moolah).
 *
 * The slot page lists the posts (both relations, labelled) and the post shows
 * the verified figures of the slot; for a family post the box names the
 * original, never passing its RTP off as the sequel's.
 *
 * Matching is by game name only: #__games.provider_value and the blog's
 * provider_slug use different vocabularies (jReviews multi-select values with
 * asterisks on one side, topgame for Pragmatic Play on the other), so the
 * provider cannot act as a tie-breaker. normalize() and match() are mirrored
 * in the one-off backfill script fec_blog_slot_links.php: keep the two in
 * step.
 */

defined('_JEXEC') or die;

class AuthorsHelperBlogslot
{
    /**
     * Lowercase, no trademark marks, straight quotes dropped, roman numerals
     * II-IV as digits, every other run of non-alphanumerics is one space.
     * "Gonzo's Quest" -> "gonzos quest", "Dead or Alive II" -> "dead or alive 2",
     * "Mercy of the Gods(TM)" -> "mercy of the gods".
     */
    public static function normalize($s)
    {
        $s = html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8');
        $s = str_replace(array("\xE2\x80\x99", "\xE2\x80\x98", '`', "\xC2\xB4"), "'", $s);   // curly quotes, backtick, acute
        $s = preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', $s);                           // (TM) (R) (C)
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = str_replace("'", '', $s);
        $s = str_replace('&', ' and ', $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        $s = ' ' . trim($s) . ' ';
        $s = str_replace(array(' iii ', ' ii ', ' iv '), array(' 3 ', ' 2 ', ' 4 '), $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * The game named by a title written the desk way, i.e. what comes before
     * "by Studio", a colon or a dash: "Reactoonz 100: Play'n GO Remixes..."
     * -> "reactoonz 100". A title without a delimiter returns '' (unknown).
     */
    public static function gamePart($title)
    {
        $t = (string) $title;
        $cut = strlen($t);
        foreach (array('/\s+by\s+/iu', '/:/u', '/\s[\x{2013}\x{2014}-]\s/u', '/\s\(/u') as $re) {
            if (preg_match($re, $t, $m, PREG_OFFSET_CAPTURE) && $m[0][1] < $cut) $cut = $m[0][1];
        }
        if ($cut === strlen($t)) return '';
        return self::normalize(substr($t, 0, $cut));
    }

    /**
     * Every slot of #__slot_rtp (published or not: a link made today shows
     * up the day the slot is verified) with its normalized name. Cached per
     * request. Empty when the tables are missing.
     */
    public static function slots()
    {
        static $rows = null;
        if ($rows !== null) return $rows;
        $rows = array();
        $db = JFactory::getDbo();
        try {
            $db->setQuery('SELECT r.gid, r.slug, r.published, r.retired, g.game_name, g.provider_name'
                . ' FROM ' . $db->quoteName('#__slot_rtp') . ' r INNER JOIN ' . $db->quoteName('#__games') . ' g ON g.gid = r.gid'
                . ' ORDER BY g.game_name');
            $list = $db->loadAssocList();
            if ($db->getErrorNum() || !is_array($list)) return $rows;
        } catch (Exception $e) { return $rows; }
        foreach ($list as $r) {
            $r['norm'] = self::normalize($r['game_name']);
            if ($r['norm'] === '') continue;
            $r['verified'] = ((int) $r['published'] === 1 && (int) $r['retired'] === 0);
            $rows[(int) $r['gid']] = $r;
        }
        return $rows;
    }

    /**
     * Match a title against a list of slots (slots() by default). Returns
     * array(gid, rel, name, slug, verified, candidates) or null. The longest
     * slot name contained in the title wins (Starburst XXXtreme beats
     * Starburst); a published slot wins a tie. rel is "game" when the game
     * part of the title is exactly the slot name, "family" otherwise.
     */
    public static function match($title, $slots = null)
    {
        if ($slots === null) $slots = self::slots();
        $norm = self::normalize($title);
        if ($norm === '' || !$slots) return null;
        $hay = ' ' . $norm . ' ';
        $best = null; $cands = array();
        foreach ($slots as $s) {
            if (strpos($hay, ' ' . $s['norm'] . ' ') === false) continue;
            $cands[] = $s['game_name'];
            if ($best === null
                || strlen($s['norm']) > strlen($best['norm'])
                || (strlen($s['norm']) === strlen($best['norm']) && !empty($s['verified']) && empty($best['verified']))) {
                $best = $s;
            }
        }
        if ($best === null) return null;
        $rel = (self::gamePart($title) === $best['norm']) ? 'game' : 'family';
        return array('gid' => (int) $best['gid'], 'rel' => $rel, 'name' => $best['game_name'], 'slug' => $best['slug'],
                     'verified' => !empty($best['verified']), 'candidates' => $cands);
    }

    /** One slot row from slots(), or null. */
    public static function find($gid)
    {
        $gid = (int) $gid;
        $slots = self::slots();
        return isset($slots[$gid]) ? $slots[$gid] : null;
    }

    /** Select options for the desk: gid => "Game name (verified | not yet verified | retired)". */
    public static function options()
    {
        $out = array();
        foreach (self::slots() as $gid => $s) {
            $tag = !empty($s['verified']) ? 'verified' : ((int) $s['retired'] === 1 ? 'retired' : 'not yet verified');
            $out[$gid] = $s['game_name'] . ' (' . $tag . ')';
        }
        return $out;
    }

    /** Relation options for the desk. */
    public static function relOptions()
    {
        return array('game' => 'This slot', 'family' => 'Same family: sequel, variant or jackpot network');
    }

    /** Human label of a relation for lists and summaries. */
    public static function relLabel($rel)
    {
        return ((string) $rel === 'game') ? 'About this slot' : 'Same family';
    }
}
