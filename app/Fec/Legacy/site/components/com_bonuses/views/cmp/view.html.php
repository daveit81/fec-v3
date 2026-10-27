<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla view library
jimport('joomla.application.component.view');

/**
 * Casino Comparator - standalone tool page (FEC Tools No. 02)
 * SEF URL: /tools/casino-comparator (see sef_ext/com_bonuses.php)
 *
 * Same rg-page editorial layout as the Wagering Calculator view. Dataset
 * comes from components/com_bonuses/data/cmp_casinos.json, built by the
 * fec_cmp_build.php CLI cron; the view only computes a filemtime cache
 * buster. Shareable state lives in ?c=slug1,slug2 handled client side,
 * so the HTML stays identical for every visitor (cache safe) and the
 * canonical remains the bare tool URL via sh404SEF.
 */
class BonusesViewCmp extends JView
{
  // Update when the article content changes materially.
  var $lastReviewed = '2026-08-16';

  function display($tpl = null)
  {
    require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_cmp_widget.php';

    $doc = JFactory::getDocument();
    $doc->setTitle('Casino Comparator - Compare Casinos Side by Side - FreeExtraChips');
    $doc->setMetaData('description', 'Compare two or three online casinos side by side: licence, operating company, country availability, payments, support and active warnings - from the verified FEC dataset.');
    $doc->setMetaData('robots', 'index,follow');

    $doc->addStyleSheet(JUri::root(true) . '/components/com_authors/assets/page.css');
    fec_cmp_add_assets();

    // dataset URL with mtime cache buster; missing file degrades in the JS
    $dataFile = JPATH_COMPONENT . DS . 'data' . DS . 'cmp_casinos.json';
    $mtime = file_exists($dataFile) ? (int) filemtime($dataFile) : 0;
    $dataUrl = JUri::root(true) . '/components/com_bonuses/data/cmp_casinos.json?v=' . $mtime;

    // ---- author lookup (adjust slug/name here if the dossier differs) ----
    $author = $this->getAuthorRow('ethan-walker', 'Ethan Walker');

    $name = 'Ethan Walker';
    $role = 'iGaming Compliance Analyst';
    $link = '';
    $avatar = '';
    $bio = '';
    if ($author) {
      $name = $author['name'];
      if (!empty($author['role'])) $role = $author['role'];
      if (!empty($author['slug'])) {
        $link = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']));
      }
      if (!empty($author['avatar_url'])) $avatar = $author['avatar_url'];
      if (!empty($author['short_bio'])) $bio = $author['short_bio'];
    }
    $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $roleEsc = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
    $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $dateIso = htmlspecialchars($this->lastReviewed, ENT_QUOTES, 'UTF-8');
    $dateHum = date('j M Y', strtotime($this->lastReviewed . ' 00:00:00'));

    echo '<div class="fecx rg-page" id="cmp-top">';

    // ================= hero =================
    echo '<section class="au-hero rg-hero"><div class="wrap">';
    echo '<div class="eyebrow">FEC Tools &middot; No. 02</div>';
    echo '<h1 class="au-hero-title">Casino Comparator</h1>';
    echo '<p class="au-hero-lede">Two or three casinos, side by side: licence, operating '
       . 'company, availability in your country, payments and support - from the same '
       . 'verified dataset behind FEC reviews.</p>';
    echo '<div class="rg-hero-meta">';
    echo '<div class="rg-hero-byline"><span class="rg-bl-label">Written by</span> ';
    if ($link !== '') {
      echo '<a href="' . $linkEsc . '" rel="author">' . $nameEsc . '</a>';
    } else {
      echo '<span>' . $nameEsc . '</span>';
    }
    echo ' <span class="rg-bl-role">' . $roleEsc . '</span></div>';
    echo '<div class="rg-hero-updated"><span class="rg-bl-label">Last reviewed</span> '
       . '<time datetime="' . $dateIso . '">' . htmlspecialchars($dateHum, ENT_QUOTES, 'UTF-8') . '</time></div>';
    echo '</div>';
    echo '</div></section>';

    // ================= tool, full width =================
    echo '<div class="wrap cmp-tool" id="comparator">';
    fec_cmp_render_widget($dataUrl);
    echo '</div>';

    // ================= article + sidebar =================
    echo '<div class="wrap au-main rg-main">';

    echo '<article class="au-content rg-content">';
    echo <<<'FECART'
<section id="why-facts" class="rg-section">
<h2>Why side-by-side facts beat marketing pages</h2>
<p>A casino homepage is a marketing document; its terms and conditions are a legal one. The comparator reads from neither: every row comes from the FEC fact sheet for that brand, compiled from the licence certificate, the operating entity named in the site footer and terms, and the restricted-country clauses verified against the first-party text where available. Two casinos that look interchangeable in their promotions frequently differ on the three clauses that decide disputes - jurisdiction, operating entity, and country eligibility.</p>
</section>

<section id="licence" class="rg-section">
<h2>A licence is not a licence</h2>
<p>The licence row deserves more weight than any bonus number. Jurisdictions differ materially in what they require of an operator and what recourse a player has when a withdrawal stalls: a licence with an active dispute channel and published sanctions is a different instrument from a registration that amounts to a mailbox. When two compared casinos hold licences from different jurisdictions, that difference - not the welcome offer - is usually the larger fact.</p>
</section>

<section id="same-operator" class="rg-section">
<h2>The same-operator problem</h2>
<p>The comparator raises a warning when selected casinos share an operating company. The match is exact against the certified operating entity in the FEC Operator Atlas, not a guess from similar branding. The point is counterparty risk: two accounts at brands run by the same entity are, in substance, one relationship. A payment problem at one brand tends to predict the second, bonus-abuse clauses are commonly enforced across sister sites as if they were a single venue, and self-exclusion at one brand does not always propagate to the other - in either direction, an outcome worth knowing before registering.</p>
</section>

<section id="availability" class="rg-section">
<h2>Country availability, verified first party</h2>
<p>The first row answers the question that voids more winnings than any wagering clause: whether the casino accepts players from a given country at all. Registering from a restricted country typically proceeds without obstacle - the check arrives at withdrawal, in the KYC stage, where clause and passport meet. FEC restricted-country data is compiled from the terms and conditions of the casino itself wherever the text is published; where only secondary sources exist, the fact sheet says so.</p>
</section>

<section id="reading-table" class="rg-section">
<h2>Reading the table</h2>
<p>A dash means the fact is unverified, not absent - the dataset never substitutes an assumption for a missing verification. Status warnings (flagged, suspended, blacklisted) appear with their documented reason and do not remove a casino from the tool: transparency about a problem is more useful than its concealment. The comparison can be shared: the address bar carries the selection, so a link reproduces the same table for the recipient.</p>
</section>
FECART;
    echo '</article>';

    // ---- sidebar ----
    echo '<aside class="au-side rg-side" aria-label="On this page">';
    echo '<div class="au-side-card rg-toc"><div class="eyebrow">On this page</div><ul>'
       . '<li><a href="#comparator">The comparator</a></li>'
       . '<li><a href="#why-facts">Why facts beat marketing</a></li>'
       . '<li><a href="#licence">A licence is not a licence</a></li>'
       . '<li><a href="#same-operator">The same-operator problem</a></li>'
       . '<li><a href="#availability">Country availability</a></li>'
       . '<li><a href="#reading-table">Reading the table</a></li>'
       . '</ul></div>';

    if ($author) {
      echo '<div class="au-side-card rg-reviewer-card">';
      echo '<div class="eyebrow">Written by</div>';
      if ($link !== '') echo '<a href="' . $linkEsc . '" class="rg-rc-link">';
      if ($avatar !== '') {
        echo '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="" class="rg-rc-avatar">';
      }
      echo '<div class="rg-rc-body"><div class="rg-rc-name">' . $nameEsc . '</div>'
         . '<div class="rg-rc-role">' . $roleEsc . '</div></div>';
      if ($link !== '') echo '</a>';
      if ($bio !== '') {
        echo '<p class="rg-rc-bio">' . htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') . '</p>';
      }
      echo '</div>';
    }

    echo '<div class="au-side-card rg-disclosure"><div class="eyebrow">Related pages</div>'
       . '<p style="margin-top: 8px;"><a href="/online-casinos/operators">Operator Atlas</a> &mdash; the public register behind the same-operator check.</p>'
       . '<p style="margin-top: 8px;"><a href="/tools/wagering-calculator">Wagering Calculator</a> &mdash; FEC Tools No. 01.</p>'
       . '</div>';
    echo '</aside>';

    echo '</div>'; // .wrap.au-main

    // ================= footer byline =================
    echo '<div class="wrap rg-footer-byline"><div class="hub-byline">';
    echo '<div class="person"><span class="who">Written by</span> <span class="name">';
    if ($link !== '') {
      echo '<a href="' . $linkEsc . '" rel="author">' . $nameEsc . '</a>';
    } else {
      echo $nameEsc;
    }
    echo '</span> <span class="role">' . $roleEsc . '</span></div>';
    echo '<div class="updated">Last reviewed: <time datetime="' . $dateIso . '">'
       . htmlspecialchars($dateHum, ENT_QUOTES, 'UTF-8') . '</time></div>';
    echo '</div></div>';

    echo '</div>'; // .rg-page

    $this->addJsonLd($author);

    parent::display($tpl);
  }

  function getAuthorRow($slug, $name)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
      ->select('id, slug, name, role, short_bio, avatar_url')
      ->from($db->quoteName('#__casino_authors'))
      ->where('(slug = ' . $db->quote($slug) . ' OR name = ' . $db->quote($name) . ')')
      ->where('is_active = 1');
    $db->setQuery($query, 0, 1);
    $row = $db->loadAssoc();
    if ($db->getErrorNum()) {
      error_log('cmp author lookup failed: ' . $db->getErrorMsg());
      return null;
    }
    return ($row && !empty($row['name'])) ? $row : null;
  }

  function addJsonLd($author)
  {
    $siteBase = rtrim(JUri::root(), '/');
    $uri = JUri::getInstance();
    $pageUrl = rtrim($uri->toString(array('scheme', 'host', 'path')), '/');
    if ($pageUrl === '') $pageUrl = $siteBase;

    $person = array(
      '@type' => 'Person',
      'name'  => $author ? $author['name'] : 'Ethan Walker',
      'worksFor' => array(
        '@type' => 'Organization',
        'name'  => 'FreeExtraChips',
        'url'   => $siteBase
      )
    );
    if ($author && !empty($author['role'])) $person['jobTitle'] = $author['role'];
    if ($author && !empty($author['slug'])) {
      $authorAbs = $siteBase . JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']));
      $person['url'] = preg_replace('#(?<!:)//+#', '/', $authorAbs);
    }

    $graph = array(
      '@context' => 'https://schema.org',
      '@type'    => 'Article',
      '@id'      => $pageUrl . '#article',
      'headline' => 'Casino Comparator - Compare Casinos Side by Side',
      'author'   => $person,
      'publisher' => array(
        '@type' => 'Organization',
        'name'  => 'FreeExtraChips',
        'url'   => $siteBase
      ),
      'dateModified' => $this->lastReviewed,
      'mainEntityOfPage' => $pageUrl
    );

    $json = json_encode($graph, JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
      JFactory::getDocument()->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
    }
  }
}