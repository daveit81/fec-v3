<?php
/**
 * @package     plg_system_feccanonicalfix
 * @version     3.0.0
 * @copyright   FreeExtraChips
 * @license     GPL v2 or later
 *
 * Fix homepage canonical: ensure trailing slash matches og:url and JSON-LD url.
 *
 * v3 CHANGES vs v2
 *   - v2's regex required `<link\s+...` and a specific attribute layout. The
 *     real canonical tag did not match (confirmed by the debug marker saying
 *     "NO canonical tag matched"), so the cause is some byte-level detail the
 *     regex did not anticipate.
 *   - v3 uses a far more permissive approach: it locates ANY <link ...> tag
 *     whose attributes include rel=canonical AND an href pointing at the bare
 *     host (with or without trailing slash, any quote style, any attribute
 *     order, any whitespace incl. tabs/newlines). It then rebuilds the tag
 *     from scratch. Practically nothing short of a totally malformed tag can
 *     escape it.
 *   - v3's debug marker now also HEX-DUMPS the first <link...canonical...>
 *     tag it finds, so if it STILL does not match we can read the exact bytes
 *     straight from view-source — no external script, no curl.
 *
 * BACKGROUND
 *   The canonical tag is emitted at the very top of <head>, OUTSIDE
 *   <jdoc:include type="head" /> - injected by a GTM / Consent Mode plugin.
 *   It can't be fixed from the template, so we rewrite the rendered HTML in
 *   onAfterRender.
 *
 * SCOPE
 *   - Front-end only.
 *   - Only rewrites the bare-host home canonical (no path). A canonical with
 *     a path like /bonuses/ is left untouched.
 *   - Idempotent. Single pass. Negligible cost.
 */

defined('_JEXEC') or die;

class plgSystemFeccanonicalfix extends JPlugin
{
    public function onAfterRender()
    {
        $app = JFactory::getApplication();

        // Front-end only.
        if (method_exists($app, 'isAdmin') && $app->isAdmin()) {
            return true;
        }
        if (defined('JPATH_ADMINISTRATOR')
            && isset($_SERVER['SCRIPT_FILENAME'])
            && strpos($_SERVER['SCRIPT_FILENAME'], 'administrator') !== false) {
            return true;
        }

        // Grab the rendered body.
        $body = '';
        if (class_exists('JResponse') && method_exists('JResponse', 'getBody')) {
            $body = JResponse::getBody();
        } elseif (method_exists($app, 'getBody')) {
            $body = $app->getBody();
        } else {
            return true;
        }
        if ($body === '' || $body === null) {
            return true;
        }

        // Debug toggle: plugin param OR ?fec_canon_debug=1 on the URL.
        $debug = (int) $this->params->get('debug', 0) === 1;
        if (!$debug
            && isset($_GET['fec_canon_debug'])
            && $_GET['fec_canon_debug'] == '1') {
            $debug = true;
        }

        $canonical = '<link href="https://www.freeextrachips.com/" rel="canonical" />';

        // STEP 1 - find every <link ...> tag in the document. The `s` flag
        // lets a tag span newlines; [^>]* is greedy within one tag only.
        $count       = 0;
        $firstCanon  = null; // for debug hex dump
        $body = preg_replace_callback(
            '#<link\b[^>]*>#is',
            function ($m) use ($canonical, &$count, &$firstCanon) {
                $tag = $m[0];

                // Must mention rel and canonical. Be liberal: rel = canonical
                // with optional quotes and any whitespace.
                if (!preg_match('#\brel\s*=\s*["\']?\s*canonical\s*["\']?#i', $tag)) {
                    return $tag; // not a canonical link, leave alone
                }

                if ($firstCanon === null) {
                    $firstCanon = $tag; // remember for debug
                }

                // Must have an href pointing at the bare host (optionally with
                // a single trailing slash) - NOT a deeper path. We capture the
                // href value and inspect it.
                if (!preg_match('#\bhref\s*=\s*["\']?\s*([^"\'>\s]+)#i', $tag, $hm)) {
                    return $tag; // no usable href, leave alone
                }
                $href = rtrim($hm[1]);

                // Normalise: strip trailing slash for comparison.
                $hrefNoSlash = rtrim($href, '/');

                // Only touch the bare-host home canonical.
                if (preg_match('#^https?://www\.freeextrachips\.com$#i', $hrefNoSlash)) {
                    $count++;
                    return $canonical;
                }

                // Some other canonical (a real path) - leave it.
                return $tag;
            },
            $body
        );

        if ($body === null) {
            // preg error - bail without touching anything.
            return true;
        }

        if ($debug) {
            $info = 'ran; ';
            if ($count > 0) {
                $info .= $count . ' canonical tag(s) rewritten';
            } else {
                $info .= 'NO canonical tag matched';
                if ($firstCanon !== null) {
                    // Hex-dump the first canonical-ish tag we saw so we can
                    // read the exact bytes straight from view-source.
                    $hex = '';
                    $len = strlen($firstCanon);
                    for ($i = 0; $i < $len; $i++) {
                        $hex .= sprintf('%02x', ord($firstCanon[$i]));
                        if ($i < $len - 1) $hex .= ' ';
                    }
                    $info .= ' | first canonical-ish tag seen: '
                           . str_replace('--', '- -', $firstCanon)
                           . ' | HEX: ' . $hex;
                } else {
                    $info .= ' | no <link> tag with rel~=canonical found in body at all';
                }
            }
            $marker = "\n<!-- fec_canonicalfix v3: " . $info . " -->\n";
            $pos = stripos($body, '</head>');
            if ($pos !== false) {
                $body = substr($body, 0, $pos) . $marker . substr($body, $pos);
            }
        }

        // Write back.
        if (class_exists('JResponse') && method_exists('JResponse', 'setBody')) {
            JResponse::setBody($body);
        } elseif (method_exists($app, 'setBody')) {
            $app->setBody($body);
        }

        return true;
    }
}
