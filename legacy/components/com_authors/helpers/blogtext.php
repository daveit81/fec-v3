<?php
defined('_JEXEC') or die;

/**
 * Casino Bonus Blog — text pipeline of the Blog desk (V2.7.66).
 *
 * Everything the longform page needs is derived here from a title and a body,
 * so a contributor only writes. Pure functions, PHP 5.6 syntax, no database:
 * the provider map is passed in by AuthorsHelperBlogdesk::softMap().
 *
 *   normalize()        raw body (pasted HTML or plain text) -> clean HTML
 *   splitIntro()       clean HTML -> array(introtext, fulltext)
 *   textFromHtml()     HTML -> plain text (meta description, extraction)
 *   metaDescription()  intro text -> <= 155 chars at a word boundary
 *   classifyKind()     title -> slot | live | jackpot | news
 *   resolveProvider()  title + intro text + provider map -> slug/name
 *   extractSlotFields() texts -> rtp, volatility, max_win_x, grid, release_date
 *
 * The heuristics are the ones of fec_blog_import.php 1.0.x (minus the EasyBlog
 * tag and category inputs, which new posts do not have), so a post written in
 * the desk gets the same Game facts as the 710 imported ones.
 */
class AuthorsHelperBlogtext
{
    /** Tags kept by the sanitizer; everything else is unwrapped (text kept). */
    protected static $allowedTags = array(
        'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'a', 'ul', 'ol', 'li',
        'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'hr', 'sup', 'sub',
    );
    /** Attributes kept, per tag. */
    protected static $allowedAttrs = array(
        'a'   => array('href', 'title'),
        'img' => array('src', 'alt', 'width', 'height'),
        'td'  => array('colspan', 'rowspan'),
        'th'  => array('colspan', 'rowspan'),
    );
    /** Tags dropped WITH their content. */
    protected static $droppedTags = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'noscript', 'svg', 'canvas');

    /** Spellings -> provider as spelled in the jReviews jr_soft option text (both normalised). */
    public static $providerAliases = array(
        'evolution' => 'evolutiongaming', 'evo' => 'evolutiongaming', 'nlc' => 'nolimitcity', 'nolimit' => 'nolimitcity',
        'playngo' => 'playngo', 'btg' => 'bigtimegaming', 'yggdrasilgaming' => 'yggdrasil', 'redtigergaming' => 'redtiger',
        'blueprint' => 'blueprintgaming', 'nextgengaming' => 'nextgen', 'nyxgaming' => 'nyx', 'nyxgaminggroup' => 'nyx',
        'softswiss' => 'bgamingsoftswiss', 'bgaming' => 'bgamingsoftswiss', 'rtg' => 'spinlogicgaming',
        'realtimegaming' => 'spinlogicgaming', 'spinlogic' => 'spinlogicgaming', 'quickfire' => 'microgaming',
        'habanero' => 'habanerosystemsbv', 'wms' => 'williamsinteractivewms', 'williamsinteractive' => 'williamsinteractivewms',
        'jftw' => 'jftwjustforthewin', 'justforthewin' => 'jftwjustforthewin', 'iwg' => 'instantwingamingiwg',
        'elk' => 'elkstudios', 'pragmatic' => 'pragmaticplay', 'stormcraft' => 'stormcraftstudios', 'reelplay' => 'relaxgaming',
        'sg' => 'scientificgames', 'sgdigital' => 'scientificgames', 'barcrestgames' => 'barcrest',
        'netentertainment' => 'netent', 'gamesglobal' => 'gamesglobal',
    );

    /* =====================================================================
     * Body normalisation
     * ===================================================================== */

    /**
     * Does the raw body look like HTML pasted from an editor rather than
     * plain text? Only paragraph-level markup decides (<p>, <br>, <div>):
     * writers who type their own <h2>, <strong> or <a> tags inside otherwise
     * plain paragraphs stay in plain mode, where those tags are honoured.
     */
    public static function looksLikeHtml($raw)
    {
        return (bool) preg_match('#<(p|br|div|section|article)\b[^>]*>#i', (string) $raw);
    }

    /**
     * Raw body -> clean HTML. HTML is sanitised, plain text is converted with
     * the light conventions (blank line = paragraph, "## " = heading,
     * **bold**, *italic*, "- " lists, "> " quotes, [text](url) and bare URLs).
     * $mode: 'auto' | 'html' | 'plain'.
     */
    public static function normalize($raw, $mode = 'auto')
    {
        $raw = str_replace(array("\r\n", "\r"), "\n", (string) $raw);
        $raw = trim($raw);
        if ($raw === '') return '';
        if ($mode === 'auto') $mode = self::looksLikeHtml($raw) ? 'html' : 'plain';
        $html = ($mode === 'html') ? self::sanitizeHtml($raw) : self::plainToHtml($raw);
        return self::tidy($html);
    }

    /**
     * Plain text with light conventions -> HTML. Text is escaped, so a stray
     * "<" never becomes a tag, except for the tags a writer types on purpose:
     * a block that starts with a block tag (<h2>, <h3>, <ul>, <blockquote>,
     * <table>...) goes through the HTML sanitizer, and <strong>/<b>, <em>/<i>
     * and <a href> inside a paragraph are kept.
     */
    public static function plainToHtml($text)
    {
        $blocks = preg_split("/\n[ \t]*\n+/", trim((string) $text));
        $out = array();
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;
            // hand-typed block tags: let the sanitizer handle the block
            if (preg_match('#^<(h[1-6]|ul|ol|blockquote|table|p|div|hr)\b#i', $block)) {
                $clean = trim(self::sanitizeHtml($block));
                if ($clean !== '') $out[] = $clean;
                continue;
            }
            $lines = explode("\n", $block);
            $first = $lines[0];
            // headings
            if (preg_match('/^(#{1,4})\s+(.+)$/', $first, $m) && count($lines) === 1) {
                $level = max(2, min(4, strlen($m[1])));
                $out[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            // rule
            if (count($lines) === 1 && preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $first)) { $out[] = '<hr>'; continue; }
            // lists
            $isUl = true; $isOl = true;
            foreach ($lines as $l) {
                if (!preg_match('/^\s*[-*\x{2022}]\s+\S/u', $l)) $isUl = false;
                if (!preg_match('/^\s*\d{1,3}[.)]\s+\S/', $l)) $isOl = false;
            }
            if ($isUl || $isOl) {
                $tag = $isUl ? 'ul' : 'ol';
                $li = array();
                foreach ($lines as $l) {
                    $item = preg_replace($isUl ? '/^\s*[-*\x{2022}]\s+/u' : '/^\s*\d{1,3}[.)]\s+/', '', $l);
                    $li[] = '<li>' . self::inline(trim($item)) . '</li>';
                }
                $out[] = '<' . $tag . '>' . implode('', $li) . '</' . $tag . '>';
                continue;
            }
            // quote
            $isQuote = true;
            foreach ($lines as $l) if (!preg_match('/^\s*>\s?/', $l)) { $isQuote = false; break; }
            if ($isQuote) {
                $q = array();
                foreach ($lines as $l) $q[] = self::inline(trim(preg_replace('/^\s*>\s?/', '', $l)));
                $out[] = '<blockquote><p>' . implode('<br>', $q) . '</p></blockquote>';
                continue;
            }
            // paragraph; single newlines are line breaks
            $p = array();
            foreach ($lines as $l) $p[] = self::inline(trim($l));
            $out[] = '<p>' . implode('<br>', $p) . '</p>';
        }
        return implode("\n", $out);
    }

    /**
     * Inline conventions on a line of plain text. The text is escaped;
     * hand-typed <strong>/<b>, <em>/<i>, <a href="..."> and <br> survive
     * (normalised: b -> strong, i -> em, href cleaned, no other attributes).
     */
    protected static function inline($s)
    {
        $parts = preg_split('#(</?(?:strong|b|em|i|a|br|span|u|font|small|big|mark|code)\b[^>]*>)#i', (string) $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        $s = '';
        $openA = false;
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) { $s .= self::inlineText($part); continue; }
            if (preg_match('#^<(/?)(strong|b|em|i|a|br|span|u|font|small|big|mark|code)\b([^>]*)>$#i', $part, $m)) {
                $close = $m[1] === '/'; $tag = strtolower($m[2]);
                if (in_array($tag, array('span', 'u', 'font', 'small', 'big', 'mark', 'code'), true)) continue;   // tag dropped, text kept
                if ($tag === 'br') { $s .= '<br>'; continue; }
                if ($tag === 'b') $tag = 'strong';
                if ($tag === 'i') $tag = 'em';
                if ($tag === 'a') {
                    if ($close) { if ($openA) { $s .= '</a>'; $openA = false; } continue; }
                    $href = preg_match('#\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $m[3], $hm) ? (isset($hm[4]) && $hm[4] !== '' ? $hm[4] : (isset($hm[3]) && $hm[3] !== '' ? $hm[3] : $hm[2])) : '';
                    $href = self::cleanHref($href);
                    if ($href === '') continue;
                    if ($openA) $s .= '</a>';
                    $s .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'; $openA = true;
                    continue;
                }
                $s .= '<' . ($close ? '/' : '') . $tag . '>';
            }
        }
        if ($openA) $s .= '</a>';
        return $s;
    }

    /** Markdown-light on a run of plain text (escaped here). */
    protected static function inlineText($s)
    {
        $s = htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        // [text](url)
        $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/', function ($m) {
            $href = self::cleanHref(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
            if ($href === '') return $m[1];
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $m[1] . '</a>';
        }, $s);
        // bare URLs (not already inside an href)
        $s = preg_replace_callback('/(?<!["\'>=])\bhttps?:\/\/[^\s<]+[^\s<.,;:!?)\]]/i', function ($m) {
            $href = self::cleanHref(html_entity_decode($m[0], ENT_QUOTES, 'UTF-8'));
            if ($href === '') return $m[0];
            $label = preg_replace('#^https?://(www\.)?#i', '', $m[0]);
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        }, $s);
        // **bold**, __bold__
        $s = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/s', '<strong>$2</strong>', $s);
        // *italic*, _italic_ (not inside words for underscore)
        $s = preg_replace('/(?<![\w*])\*(?=\S)([^*]+?)(?<=\S)\*(?![\w*])/s', '<em>$1</em>', $s);
        $s = preg_replace('/(?<!\w)_(?=\S)([^_]+?)(?<=\S)_(?!\w)/s', '<em>$1</em>', $s);
        return $s;
    }

    /**
     * Pasted HTML -> allow-listed HTML through DOMDocument. Unknown tags are
     * unwrapped (their text stays), dangerous ones dropped with content,
     * attributes reduced to href/title/src/alt/width/height, h1 demoted,
     * internal absolute links made relative.
     */
    public static function sanitizeHtml($html)
    {
        $html = (string) $html;
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        if (!class_exists('DOMDocument')) {
            // No ext/dom: strip_tags with the allow list, attributes removed.
            $keep = '<' . implode('><', self::$allowedTags) . '>';
            $html = preg_replace('#<(' . implode('|', self::$droppedTags) . ')\b[^>]*>.*?</\1>#is', '', $html);
            $html = strip_tags($html, $keep);
            $html = preg_replace('#<(a|img)\b([^>]*)>#i', '<$1$2>', $html);
            return preg_replace('/\s(?!href=|src=|alt=|title=)[a-z-]+=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $html . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        self::cleanNode($doc, $body);
        self::wrapLoose($doc, $body);
        $out = '';
        foreach ($body->childNodes as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    /**
     * Text and inline elements sitting directly in the body (an editor paste
     * with loose lines, or plain paragraphs around a typed <h2>) are wrapped
     * in <p>; a blank line inside such text starts a new paragraph.
     */
    protected static function wrapLoose(DOMDocument $doc, DOMNode $body)
    {
        $blockTags = array('p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'table', 'hr', 'img');
        $children = array();
        foreach ($body->childNodes as $c) $children[] = $c;
        $p = null;
        $flush = function () use (&$p) { $p = null; };
        foreach ($children as $c) {
            $isBlock = ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->nodeName), $blockTags, true));
            if ($isBlock) { $flush(); continue; }
            if ($c->nodeType === XML_TEXT_NODE) {
                $chunks = preg_split("/\n[ \t]*\n+/", $c->nodeValue);
                if (count($chunks) > 1) {
                    // blank lines inside loose text: one paragraph per chunk
                    foreach ($chunks as $k => $chunk) {
                        if ($k > 0) $flush();
                        if (trim($chunk) === '') continue;
                        if ($p === null) { $p = $doc->createElement('p'); $body->insertBefore($p, $c); }
                        $p->appendChild($doc->createTextNode(trim($chunk, "\n")));
                    }
                    $body->removeChild($c);
                    continue;
                }
                if (trim($c->nodeValue) === '') { if ($p === null) { $body->removeChild($c); } else { $p->appendChild($c); } continue; }
            }
            if ($p === null) { $p = $doc->createElement('p'); $body->insertBefore($p, $c); }
            $p->appendChild($c);
        }
        // drop paragraphs that ended up empty
        $ps = array();
        foreach ($body->childNodes as $c) if ($c->nodeType === XML_ELEMENT_NODE && strtolower($c->nodeName) === 'p') $ps[] = $c;
        foreach ($ps as $c) if (trim(preg_replace('/\xc2\xa0/', ' ', $c->textContent)) === '' && !$c->getElementsByTagName('img')->length) $body->removeChild($c);
    }

    /** Recursive allow-list walk. */
    protected static function cleanNode(DOMDocument $doc, DOMNode $node)
    {
        // iterate over a static copy: the list changes while we unwrap/remove
        $children = array();
        foreach ($node->childNodes as $c) $children[] = $c;
        foreach ($children as $c) {
            if ($c->nodeType === XML_COMMENT_NODE) { $node->removeChild($c); continue; }
            if ($c->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($c->nodeName);
            if (in_array($tag, self::$droppedTags, true)) { $node->removeChild($c); continue; }
            if ($tag === 'h1') { $tag = 'h2'; $c = self::renameNode($doc, $c, 'h2'); }
            if ($tag === 'b') { $tag = 'strong'; $c = self::renameNode($doc, $c, 'strong'); }
            if ($tag === 'i') { $tag = 'em'; $c = self::renameNode($doc, $c, 'em'); }
            if (in_array($tag, array('h5', 'h6'), true)) { $tag = 'h4'; $c = self::renameNode($doc, $c, 'h4'); }
            if (in_array($tag, array('div', 'section', 'article', 'font', 'span', 'center', 'u', 'small', 'big', 'tt', 'mark', 'header', 'footer', 'main', 'figure', 'figcaption', 'pre', 'code'), true)) {
                // block containers become paragraphs when they hold only inline content,
                // otherwise they are unwrapped
                if (in_array($tag, array('div', 'section', 'article', 'center', 'header', 'footer', 'main', 'figure', 'pre'), true) && !self::hasBlockChild($c)) {
                    $c = self::renameNode($doc, $c, 'p'); $tag = 'p';
                } else {
                    self::cleanNode($doc, $c);
                    self::unwrap($c);
                    continue;
                }
            }
            if (!in_array($tag, self::$allowedTags, true)) { self::cleanNode($doc, $c); self::unwrap($c); continue; }
            // attributes
            $keep = isset(self::$allowedAttrs[$tag]) ? self::$allowedAttrs[$tag] : array();
            $attrs = array();
            foreach ($c->attributes as $a) $attrs[] = $a->nodeName;
            foreach ($attrs as $an) {
                $lower = strtolower($an);
                if (!in_array($lower, $keep, true)) { $c->removeAttribute($an); continue; }
                $val = $c->getAttribute($an);
                if ($lower === 'href') {
                    $clean = self::cleanHref($val);
                    if ($clean === '') { $c->removeAttribute($an); } else { $c->setAttribute($an, $clean); }
                } elseif ($lower === 'src') {
                    $clean = self::cleanSrc($val);
                    if ($clean === '') { $node->removeChild($c); continue 2; }
                    $c->setAttribute($an, $clean);
                } elseif ($lower === 'width' || $lower === 'height' || $lower === 'colspan' || $lower === 'rowspan') {
                    if (!preg_match('/^\d{1,4}$/', $val)) $c->removeAttribute($an);
                }
            }
            if ($tag === 'a' && !$c->hasAttribute('href')) { self::cleanNode($doc, $c); self::unwrap($c); continue; }
            if ($tag === 'img' && !$c->hasAttribute('src')) { $node->removeChild($c); continue; }
            self::cleanNode($doc, $c);
        }
    }

    protected static function hasBlockChild(DOMNode $n)
    {
        foreach ($n->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->nodeName), array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'blockquote', 'section', 'article', 'figure', 'hr', 'pre'), true)) return true;
        }
        return false;
    }

    protected static function renameNode(DOMDocument $doc, DOMNode $old, $newName)
    {
        $new = $doc->createElement($newName);
        foreach ($old->attributes as $a) $new->setAttribute($a->nodeName, $a->nodeValue);
        while ($old->firstChild) $new->appendChild($old->firstChild);
        $old->parentNode->replaceChild($new, $old);
        return $new;
    }

    protected static function unwrap(DOMNode $n)
    {
        $parent = $n->parentNode;
        if (!$parent) return;
        while ($n->firstChild) $parent->insertBefore($n->firstChild, $n);
        $parent->removeChild($n);
    }

    /**
     * href (raw, unescaped): http(s) or site-relative only; FEC absolute URLs
     * become relative; no javascript:/data:; '' for a site path that answers
     * 410 Gone (the caller then drops the link and keeps the text).
     */
    public static function cleanHref($href)
    {
        $h = trim(html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8'));
        if ($h === '') return '';
        if (preg_match('#^(javascript|data|vbscript):#i', $h)) return '';
        if (preg_match('#^https?://(www\.)?freeextrachips\.com(/.*)?$#i', $h, $m)) {
            $h = isset($m[2]) && $m[2] !== '' ? $m[2] : '/';
        }
        if (preg_match('#^(https?://|/|\#|mailto:)#i', $h)) {
            return ($h[0] === '/' && self::isGoneLink($h)) ? '' : $h;
        }
        // bare domain or relative path without a slash
        if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $h)) return 'https://' . $h;
        $h = '/' . ltrim($h, '/');
        return self::isGoneLink($h) ? '' : $h;
    }

    /**
     * Site paths that .htaccess answers with 410 Gone (jReviews tag and
     * listing pages). fec_blog.php owns the rule (FEC_BLOG_GONE_RE); the
     * literal below is the same expression for when that helper is not loaded.
     */
    public static function isGoneLink($path)
    {
        if (function_exists('fec_blog_is_gone_link')) return fec_blog_is_gone_link($path);
        $re = defined('FEC_BLOG_GONE_RE') ? FEC_BLOG_GONE_RE
            : '#^/(?:online-casinos/(?:software|banking|countries|languages|currencies|type)|casino-reviews|reviews|countries)(?:/|$)|^/[^/]+/tag(?:/|$)#i';
        $p = (string) parse_url((string) $path, PHP_URL_PATH);
        return (bool) preg_match($re, $p);
    }

    /** img src (raw): site images only (relative path under images/), FEC absolute URLs made relative, no hotlinking. */
    public static function cleanSrc($src)
    {
        $s = trim(html_entity_decode((string) $src, ENT_QUOTES, 'UTF-8'));
        if ($s === '' || preg_match('#^(javascript|data|vbscript):#i', $s)) return '';
        if (preg_match('#^https?://(www\.)?freeextrachips\.com/(.+)$#i', $s, $m)) $s = '/' . $m[2];
        if (preg_match('#^https?://#i', $s)) return '';   // no hotlinking
        $s = '/' . ltrim($s, '/');
        if (!preg_match('#^/(images|cdn-cgi)/#', $s)) return '';
        return $s;
    }

    /** Whitespace/empty-paragraph tidy on clean HTML. */
    public static function tidy($html)
    {
        $html = (string) $html;
        $html = str_replace("\xc2\xa0", ' ', $html);
        $html = preg_replace('/[ \t]*\n[ \t]*/', "\n", $html);
        // editor noise: "<p>\n\ttext</p>" -> "<p>text</p>"
        $html = preg_replace('#<(p|h[2-4]|li|td|th)>\s+#i', '<$1>', $html);
        $html = preg_replace('#\s+</(p|h[2-4]|li|td|th)>#i', '</$1>', $html);
        $html = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);
        $html = preg_replace('#<(strong|em|b|i)>\s*</\1>#i', '', $html);
        $html = preg_replace("/\n{2,}/", "\n", $html);
        $html = preg_replace('#(</(?:p|h[2-4]|ul|ol|blockquote|table|hr)>)(?!\n)#i', "$1\n", $html);
        return trim($html);
    }

    /* =====================================================================
     * Intro / text / meta
     * ===================================================================== */

    /**
     * Clean HTML -> array(introtext, fulltext). The intro is the first block
     * before any heading; a very short opening paragraph takes the next one
     * too. A leading heading leaves the intro empty and the whole body in
     * fulltext (the page concatenates both anyway).
     */
    public static function splitIntro($html)
    {
        $html = trim((string) $html);
        if ($html === '') return array('', '');
        $blocks = preg_split('#(?<=</p>|</ul>|</ol>|</blockquote>|</table>)\n#i', $html);
        $intro = array(); $rest = array();
        $words = 0;
        foreach ($blocks as $i => $b) {
            $b = trim($b);
            if ($b === '') continue;
            if (!$rest && $i < 2 && preg_match('#^<p[\s>]#i', $b) && $words < 25 && !preg_match('#<h[2-4]#i', $b)) {
                $intro[] = $b;
                $words += str_word_count(strip_tags($b));
                continue;
            }
            $rest[] = $b;
        }
        if (!$rest) return array(implode("\n", $intro), '');
        return array(implode("\n", $intro), implode("\n", $rest));
    }

    /** HTML -> single-line plain text. */
    public static function textFromHtml($html)
    {
        $s = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', (string) $html);
        $s = preg_replace('#<img[^>]*>#i', ' ', $s);
        $s = preg_replace('#<br\s*/?>#i', ' ', $s);
        $s = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote)>#i', ' ', $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $s = str_replace("\xc2\xa0", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim((string) $s);
    }

    /** Cut at a word boundary. */
    public static function clampWords($s, $max)
    {
        $s = trim((string) $s);
        if (self::len($s) <= $max) return $s;
        $cut = self::sub($s, 0, $max);
        $sp = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ', 0, 'UTF-8') : strrpos($cut, ' ');
        if ($sp !== false && $sp > 60) $cut = self::sub($cut, 0, $sp);
        return rtrim($cut, " \t,;:-\xe2\x80\x93");
    }

    /** Meta description from the intro (fallback: the body), <= 155 chars. */
    public static function metaDescription($introHtml, $fullHtml = '')
    {
        $t = self::textFromHtml($introHtml);
        if ($t === '') $t = self::textFromHtml($fullHtml);
        $t = str_replace(array('"', '<', '>'), '', $t);
        return self::clampWords($t, 155);
    }

    /* =====================================================================
     * Classification
     * ===================================================================== */

    /** Lowercase letters and digits only ("Play'n GO" -> "playngo", "&" -> "and"). */
    public static function norm($s)
    {
        $s = self::lower(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'));
        $s = str_replace(array('&', '+'), array('and', 'plus'), $s);
        return (string) preg_replace('/[^a-z0-9]+/u', '', $s);
    }

    /** Article kind from the title: slot | live | jackpot | news. */
    public static function classifyKind($title)
    {
        $title = html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8');
        if (preg_match('/\bslot\b/i', $title) || preg_match('/^.+\bby\b .+:/u', $title)) return 'slot';
        if (preg_match('/\blive (casino|dealer|roulette|blackjack|baccarat|game show|games?)\b/i', $title)) return 'live';
        if (preg_match('/\bjackpots?\b/i', $title)) return 'jackpot';
        if (preg_match('/\b\d{1,3}(?:,\d{3})+x\b|\bmax wins?\b|\bslots\b|\bmegaways\b/i', $title)) return 'slot';
        return 'news';
    }

    /**
     * Provider from the title ("Game by Studio:" first, then any option name
     * of 6+ chars in the title), then from the intro text (a single option
     * name of 6+ chars). $softMap: norm(option text) => array(value, text).
     * Returns array(slug|null, name|null, source).
     */
    public static function resolveProvider($title, $introText, $softMap)
    {
        if (!$softMap) return array(null, null, 'no-map');
        $aliases = self::$providerAliases;
        $resolve = function ($norm) use ($softMap, $aliases) {
            if ($norm === '') return null;
            if (isset($aliases[$norm])) $norm = $aliases[$norm];
            return isset($softMap[$norm]) ? $softMap[$norm] : null;
        };
        $titleText = html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8');
        if (preg_match('/\bby\s+([A-Z0-9][\w\'\x{2019}.&-]*(?:\s+[A-Z0-9][\w\'\x{2019}.&-]*){0,3})(\s*[:\-\x{2013}\x{2014}(|]|\s*$)?/u', $titleText, $m)) {
            $cand = trim($m[1]);
            $cand = preg_replace('/\s+(Slot|Review|Casino|Online|Launches|Launch|Releases|Release|Is|Has|Goes|Brings|Hits|Adds|Unveils|Debuts|Surprises|Exclusively|Out|Now)\b.*$/iu', '', $cand);
            $parts = preg_split('/\s+/', $cand);
            for ($n = count($parts); $n >= 1; $n--) {
                $r = $resolve(self::norm(implode(' ', array_slice($parts, 0, $n))));
                if ($r !== null) return array($r['value'], $r['text'], 'title');
            }
        }
        $scan = function ($text, $minLen) use ($softMap) {
            $tn = self::norm($text); $best = null;
            foreach ($softMap as $norm => $opt) {
                if (strlen($norm) < $minLen) continue;
                if (strpos($tn, $norm) !== false && ($best === null || strlen($norm) > strlen($best[0]))) $best = array($norm, $opt);
            }
            return $best;
        };
        $b = $scan($titleText, 6);
        if ($b !== null) return array($b[1]['value'], $b[1]['text'], 'title-scan');
        // intro: only when exactly one studio is named
        $tn = self::norm((string) $introText); $hits = array();
        foreach ($softMap as $norm => $opt) {
            if (strlen($norm) >= 6 && strpos($tn, $norm) !== false) $hits[$opt['value']] = $opt;
        }
        if (count($hits) === 1) { $opt = reset($hits); return array($opt['value'], $opt['text'], 'intro'); }
        return array(null, null, 'none');
    }

    /** Single distinct value or null. */
    protected static function uniqueOrNull($vals)
    {
        $vals = array_values(array_unique(array_map('strval', $vals)));
        return count($vals) === 1 ? $vals[0] : null;
    }

    /**
     * Structured slot fields from the text. Conservative: a value is kept only
     * when the intro states exactly one, else when the whole text states
     * exactly one; otherwise NULL.
     */
    public static function extractSlotFields($introText, $fullText, $createdTs)
    {
        $out = array('rtp' => null, 'volatility' => null, 'max_win_x' => null, 'grid' => null, 'release_date' => null);
        $scopes = array('intro' => (string) $introText, 'full' => $introText . ' ' . $fullText);

        foreach ($scopes as $t) {
            $vals = array();
            if (preg_match_all('/\b(\d{2}(?:\.\d{1,2})?)\s?%\s*(?:default\s+|theoretical\s+|base\s+)?(?:rtp|return)/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            if (preg_match_all('/\b(?:rtp|return to player|return rate)\b(?:\s+(?:of|is|at|sits at|stands at|comes in at|set at|rated at|a|the|default|theoretical|figure|value)|\s*:)*\s*(\d{2}(?:\.\d{1,2})?)\s?%/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            $vals = array_filter($vals, function ($v) { return $v >= 80 && $v <= 99.99; });
            if (!$vals) continue;
            $u = self::uniqueOrNull($vals);
            if ($u !== null) { $out['rtp'] = (float) $u; break; }
        }
        foreach ($scopes as $t) {
            $vals = array();
            if (preg_match_all('/\b(low|low-medium|medium-low|medium|medium-high|high-medium|high|very high|extreme|extremely high)\s+volatility\b/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            if (preg_match_all('/\bvolatility\b[^.;]{0,30}?\b(low|medium|high|extreme)\b/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            $vals = array_map('strtolower', $vals);
            if (!$vals) continue;
            $u = self::uniqueOrNull($vals);
            if ($u !== null) { $out['volatility'] = $u; break; }
        }
        foreach ($scopes as $t) {
            $vals = array();
            if (preg_match_all('/\b(\d{1,3}(?:,\d{3})+|\d{3,})x\s+(?:(?:max(?:imum)?|top)\s+(?:win|payout|prize)|(?:payout|win)\s+cap|cap|ceiling)\b/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            if (preg_match_all('/\b(?:max(?:imum)?\s+(?:win|payout)|(?:payout|win)\s+cap|top prize|ceiling|caps?(?:\s+\w+){0,3}\s+at)\b[^.;]{0,40}?\b(\d{1,3}(?:,\d{3})+|\d{3,})x\b/i', $t, $m)) $vals = array_merge($vals, $m[1]);
            $vals = array_map(function ($v) { return (int) str_replace(',', '', $v); }, $vals);
            $vals = array_filter($vals, function ($v) { return $v >= 100 && $v <= 1000000; });
            if (!$vals) continue;
            $u = self::uniqueOrNull($vals);
            if ($u !== null) { $out['max_win_x'] = (int) $u; break; }
        }
        foreach ($scopes as $t) {
            $vals = array();
            if (preg_match_all('/\b(\d)\s?x\s?(\d)\b(?=[^.;]{0,25}\b(?:grid|reels?|board|layout|footprint|scatter|cluster|setup|play\s?area))/i', $t, $m)) {
                foreach ($m[1] as $i => $a) $vals[] = $a . 'x' . $m[2][$i];
            }
            if (!$vals) continue;
            $u = self::uniqueOrNull($vals);
            if ($u !== null) { $out['grid'] = $u; break; }
        }
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December';
        foreach ($scopes as $t) {
            $vals = array();
            $re1 = '/\b(?:launch(?:es|ing|ed)?|releas(?:es|ed|ing)?|roll(?:s|ed|ing)?\s+out|arriv(?:es|ed|ing)|debut(?:s|ed|ing)?|go(?:es|ing)?\s+live|hit(?:s|ting)?\s+(?:the\s+)?lobbies|drops?|lands?|out\s+on|available\s+(?:from|on))\b[^.;]{0,60}?\b(' . $months . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i';
            $re2 = '/\b(?:today|tomorrow|yesterday|this\s+morning),?\s+(' . $months . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i';
            $re3 = '/\b(' . $months . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b[^.;]{0,40}?\b(?:launch|release|debut|rollout|roll-out)\b/i';
            foreach (array($re1, $re2, $re3) as $re) {
                if (preg_match_all($re, $t, $m, PREG_SET_ORDER)) {
                    foreach ($m as $x) {
                        $ts = strtotime($x[1] . ' ' . $x[2] . ' ' . $x[3]);
                        if ($ts !== false) $vals[] = date('Y-m-d', $ts);
                    }
                }
            }
            if (!$vals) continue;
            $u = self::uniqueOrNull($vals);
            if ($u !== null) {
                if (abs(strtotime($u) - (int) $createdTs) <= 120 * 86400) $out['release_date'] = $u;
                break;
            }
        }
        return $out;
    }

    /** Human summary of what was detected, for the desk message. */
    public static function describeMeta($meta)
    {
        $parts = array();
        $kinds = array('slot' => 'Slot release', 'live' => 'Live casino', 'jackpot' => 'Jackpots', 'promo' => 'Promotions', 'news' => 'News');
        $parts[] = isset($kinds[$meta['kind']]) ? $kinds[$meta['kind']] : 'News';
        if (!empty($meta['provider_name'])) $parts[] = $meta['provider_name'];
        if (!empty($meta['release_date'])) $parts[] = 'released ' . $meta['release_date'];
        if (!empty($meta['rtp'])) $parts[] = 'RTP ' . rtrim(rtrim(number_format((float) $meta['rtp'], 2, '.', ''), '0'), '.') . '%';
        if (!empty($meta['volatility'])) $parts[] = ucfirst($meta['volatility']) . ' volatility';
        if (!empty($meta['max_win_x'])) $parts[] = number_format((int) $meta['max_win_x']) . 'x max win';
        if (!empty($meta['grid'])) $parts[] = $meta['grid'] . ' grid';
        if (!empty($meta['slot_gid']) && class_exists('AuthorsHelperBlogslot')) {
            $vs = AuthorsHelperBlogslot::find((int) $meta['slot_gid']);
            if ($vs) $parts[] = 'Verified slot: ' . $vs['game_name'] . ' (' . ((isset($meta['slot_rel']) && $meta['slot_rel'] === 'game') ? 'this slot' : 'same family') . (!empty($vs['verified']) ? '' : ', not yet verified') . ')';
        }
        return implode(' · ', $parts);
    }

    /* ---- small multibyte helpers (mbstring may be missing on the host CLI) ---- */
    protected static function len($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
    protected static function sub($s, $a, $b) { return function_exists('mb_substr') ? mb_substr($s, $a, $b, 'UTF-8') : substr($s, $a, $b); }
    protected static function lower($s) { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
}
