<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// import Joomla view library
jimport('joomla.application.component.view');

/**
 * Wagering Calculator - standalone tool page (F2, rg-page layout)
 * SEF URL: /tools/wagering-calculator (see sef_ext/com_bonuses.php)
 *
 * Uses the site editorial page markup (au-/rg- classes from
 * components/com_authors/assets/page.css + fec-edition tokens), same
 * family as the disclosure and responsible gambling pages. The widget
 * renders full width between the hero and the two-column article
 * layout: the au-content column is too narrow for the widget internal
 * two-column grid, whose breakpoint is viewport based.
 *
 * Deliberately self-contained: no renders/common.php and no
 * fec_common_lib.php dependency. The byline queries #__casino_authors
 * directly; without the dossier row it degrades to plain text.
 */
class BonusesViewWcalc extends JView
{
  // Update when the article content changes materially.
  var $lastReviewed = '2026-08-16';

  // Overwriting JView display method
  function display($tpl = null)
  {
    require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_wcal_widget.php';

    $doc = JFactory::getDocument();
    $doc->setTitle('Casino Bonus Wagering Calculator - FreeExtraChips');
    $doc->setMetaData('description', 'Wagering requirement calculator for casino bonuses: real turnover, game contribution, daily pace and the max bet and cashout terms that void winnings.');
    $doc->setMetaData('robots', 'index,follow');

    // editorial page styles (rg-/au- classes) + widget assets
    $doc->addStyleSheet(JUri::root(true) . '/components/com_authors/assets/page.css');
    fec_wcal_add_assets();

    // ---- author lookup (adjust slug/name here if the dossier differs) ----
    $author = $this->getAuthorRow('david-romano', 'David Romano');

    $name = 'David Romano';
    $role = 'Casino Bonus Analyst';
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

    echo '<div class="fecx rg-page" id="wcal-top">';

    // ================= hero =================
    echo '<section class="au-hero rg-hero"><div class="wrap">';
    echo '<div class="eyebrow">FEC Tools &middot; No. 01</div>';
    echo '<h1 class="au-hero-title">Wagering Calculator</h1>';
    echo '<p class="au-hero-lede">Enter a bonus exactly as the casino states it. '
       . 'The calculator shows the real stake volume required before a withdrawal '
       . 'is allowed, tracks progress, and flags the terms that most often void winnings.</p>';
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
    echo '<div class="wrap wcal-tool" id="calculator">';
    fec_wcal_render_widget(array());
    echo '</div>';

    // ================= article + sidebar =================
    echo '<div class="wrap au-main rg-main">';

    echo '<article class="au-content rg-content">';
    echo <<<'FECART'
<section id="what-wagering-is" class="rg-section">
<h2>What a wagering requirement actually is</h2>
<p>A wagering requirement is the total amount of bets a casino requires before bonus-related funds can be withdrawn. It is always expressed as a multiplier, but the multiplier alone says very little: 30&times; applied to the bonus only and 30&times; applied to deposit plus bonus are two very different obligations. The first field to check in any set of terms is not the number - it is what the number applies to.</p>
</section>

<section id="reading-the-terms" class="rg-section">
<h2>Reading the terms into the calculator</h2>
<p>The tool mirrors the structure of real bonus terms, so each field maps to a clause. The multiplier and its base ("Bonus only" versus "Deposit + Bonus") come first. Game contribution is the second lever: when table games count 10%, every euro staked on them advances the requirement by ten cents, which multiplies the real turnover by ten. The withdrawal-side clauses follow: whether the bonus is cashable or removed at withdrawal (sticky), any maximum cashout - stated as a fixed amount, a multiple of the deposit, or a multiple of the bonus - and any maximum bet allowed while wagering.</p>
</section>

<section id="worked-example" class="rg-section">
<h2>A worked example</h2>
<p>Take a common structure: 100% match up to &euro;100, deposit of &euro;100, 30&times; wagering on deposit plus bonus. The bonus credited is &euro;100, the wagering base is &euro;200, and the required turnover is &euro;6,000 on slots at full contribution. Move the same requirement to a table game counting 10% and the real stake volume becomes &euro;60,000. Neither figure appears anywhere in the promotional copy; both follow directly from two lines of the terms.</p>
</section>

<section id="traps" class="rg-section">
<h2>The clauses that void winnings</h2>
<p>Most confiscated withdrawals trace back to three clauses, all of them checkable before playing. A maximum bet rule (commonly &euro;5 per spin while wagering) voids the bonus on a single oversized stake. A maximum cashout caps what any win can pay - a &euro;100 ceiling under a &euro;6,000 turnover obligation changes the economics of the offer entirely, and the calculator flags exactly that ratio. An expiry window turns the requirement into a daily pace: &euro;6,000 in seven days means &euro;857 per day, every day. If the pace is not realistic for the bankroll, the conclusion comes before the deposit, not after it.</p>
</section>

<section id="tracking-progress" class="rg-section">
<h2>Tracking progress</h2>
<p>The "wagered so far" field converts total bets on the selected game into counted turnover, and saved bonuses are stored only in the browser - nothing is sent to a server. Figures shown in the casino cashier remain authoritative: the calculator models the arithmetic of the published terms, and the current terms and conditions of the casino always prevail.</p>
</section>

<section id="telegram-reminders" class="rg-section">
<h2>Deadline reminders on Telegram</h2>
<p>Once the "days left" field is filled in, the reminder button activates. It opens the FreeExtraChips bot (@fecbonusbot) on Telegram, and a single tap on Start schedules a message roughly 48 hours before the declared deadline - the last point where a daily pace can realistically be corrected rather than merely observed. If the deadline is already closer than that, the reminder arrives within the hour instead. Setting a new reminder for the same bonus replaces the previous one, and blocking the bot cancels everything. What gets stored is the chat, the bonus reference and the date: no name, no email, no tracking beyond that.</p>
</section>
FECART;
    echo '</article>';

    // ---- sidebar ----
    echo '<aside class="au-side rg-side" aria-label="On this page">';
    echo '<div class="au-side-card rg-toc"><div class="eyebrow">On this page</div><ul>'
       . '<li><a href="#calculator">The calculator</a></li>'
       . '<li><a href="#what-wagering-is">What wagering is</a></li>'
       . '<li><a href="#reading-the-terms">Reading the terms</a></li>'
       . '<li><a href="#worked-example">A worked example</a></li>'
       . '<li><a href="#traps">Clauses that void winnings</a></li>'
       . '<li><a href="#tracking-progress">Tracking progress</a></li>'
       . '<li><a href="#telegram-reminders">Telegram reminders</a></li>'
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
       . '<p style="margin-top: 8px;"><a href="/methodology">How we verify bonuses</a> &mdash; the methodology behind ranking criteria.</p>'
       . '<p style="margin-top: 8px;"><a href="/responsible-gambling">Responsible Gambling</a> &mdash; warning signs and helplines.</p>'
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

  /**
   * Fetch the signing author from #__casino_authors. Returns assoc or null.
   */
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
      error_log('wcalc author lookup failed: ' . $db->getErrorMsg());
      return null;
    }
    return ($row && !empty($row['name'])) ? $row : null;
  }

  /**
   * Article + Person JSON-LD, same pattern as helpers/authors.php.
   */
  function addJsonLd($author)
  {
    $siteBase = rtrim(JUri::root(), '/');
    $uri = JUri::getInstance();
    $pageUrl = rtrim($uri->toString(array('scheme', 'host', 'path')), '/');
    if ($pageUrl === '') $pageUrl = $siteBase;

    $person = array(
      '@type' => 'Person',
      'name'  => $author ? $author['name'] : 'David Romano',
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
      'headline' => 'Casino Bonus Wagering Calculator',
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