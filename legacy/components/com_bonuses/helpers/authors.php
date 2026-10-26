<?php
defined('_JEXEC') or die;

/**
 * Fetch author for a bonus id (bid). Returns assoc array or null.
 */
function fec_get_bonus_author($bid)
{
  $bid = (int)$bid;
  if ($bid <= 0) return null;

  $db = JFactory::getDbo();

  $query = $db->getQuery(true)
    ->select('a.id, a.slug, a.name, a.role, a.short_bio, a.avatar_url')
    ->from($db->quoteName('#__bonuses') . ' AS b')
    ->join('LEFT', $db->quoteName('#__casino_authors') . ' AS a ON a.id = b.author_id')
    ->where('b.bid = ' . $bid)
    ->where('b.author_id IS NOT NULL')
    ->where('a.id IS NOT NULL')
    ->where('a.is_active = 1');

  $db->setQuery($query, 0, 1);
  $row = $db->loadAssoc();

  if ($db->getErrorNum()) {
    error_log('fec_get_bonus_author query failed: ' . $db->getErrorMsg());
    return null;
  }

  if (!$row || empty($row['id'])) return null;

  // Normalize
  $row['id'] = (int)$row['id'];
  $row['slug'] = isset($row['slug']) ? trim($row['slug']) : '';
  $row['name'] = isset($row['name']) ? trim($row['name']) : '';
  $row['role'] = isset($row['role']) ? trim($row['role']) : '';
  $row['short_bio'] = isset($row['short_bio']) ? trim((string)$row['short_bio']) : '';
  $row['avatar_url'] = isset($row['avatar_url']) ? trim((string)$row['avatar_url']) : '';

  if ($row['slug'] === '' || $row['name'] === '') return null;

  return $row;
}

/**
 * Render author card HTML + schema.org markup.
 * Returns '' if no author.
 */
function fec_render_bonus_author_card($bid)
{
  $a = fec_get_bonus_author($bid);
  if (!$a) return '';

  $name = htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8');
  $role = htmlspecialchars($a['role'], ENT_QUOTES, 'UTF-8');

  $bioText = ($a['short_bio'] !== '') ? $a['short_bio'] : 'Casino review contributor.';
  $bio = htmlspecialchars($bioText, ENT_QUOTES, 'UTF-8');

  // Use com_authors router (and Itemid if present)
  $authorRoute = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($a['slug']));
  $authorUrlEsc = htmlspecialchars($authorRoute, ENT_QUOTES, 'UTF-8');

  // Avatar (relative ok)
  $avatar = $a['avatar_url'];
  $photoHtml = '';
  if ($avatar !== '') {
    $avatarEsc = htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8');
    $photoHtml = '<img class="author-avatar" src="'.$avatarEsc.'" alt="'.$name.'" width="72" height="72" loading="lazy">';
  } else {
    $photoHtml = '<span class="author-avatar author-avatar--placeholder" aria-hidden="true"></span>';
  }

  // Base URLs / IDs
  $siteBase = rtrim(JUri::root(), '/');

  // Build absolute author URL (prefer canonical route if possible)
  $authorAbs = $siteBase . $authorRoute;
  $authorAbs = preg_replace('#(?<!:)//+#', '/', $authorAbs); // collapse double slashes, keep https://
  $personId  = $authorAbs . '#person';

  // Page URL (use current URL without query when possible; better if matches canonical)
  $uri = JUri::getInstance();
  $pageUrl = $uri->toString(array('scheme','host','path'));
  $pageUrl = rtrim($pageUrl, '/');
  if ($pageUrl === '') $pageUrl = $siteBase;

  $orgId     = $siteBase . '#org';
  $webPageId = $pageUrl . '#webpage';
  $articleId = $pageUrl . '#article';

  // JSON-LD: Person (make image absolute if relative)
  $person = array(
    '@type'    => 'Person',
    '@id'      => $personId,
    'name'     => $a['name'],
    'url'      => $authorAbs,
    'jobTitle' => $a['role'],
    'description' => $bioText,
    'worksFor' => array(
      '@type' => 'Organization',
      'name'  => 'FreeExtraChips',
      'url'   => $siteBase
    )
  );

  if ($avatar !== '') {
    $img = $avatar;
    if (strpos($img, 'http') !== 0) {
      $img = $siteBase . $img;
      $img = preg_replace('#(?<!:)//+#', '/', $img);
    }
    $person['image'] = $img;
  }

  $pageTitle = JFactory::getDocument()->getTitle();

  // JSON-LD @graph: Organization + Person + WebPage + Article (links author properly)
  $jsonLdGraph = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
      array(
        '@type' => 'Organization',
        '@id'   => $orgId,
        'name'  => 'FreeExtraChips',
        'url'   => $siteBase
      ),
      $person,
      array(
        '@type' => 'WebPage',
        '@id'   => $webPageId,
        'url'   => $pageUrl,
        'name'  => $pageTitle,
        'publisher' => array('@id' => $orgId),
        'author'    => array('@id' => $personId),
        'mainEntity' => array('@id' => $articleId)
      ),
      array(
        '@type' => 'Article',
        '@id'   => $articleId,
        'mainEntityOfPage' => array('@id' => $webPageId),
        'headline' => $pageTitle,
        'author'   => array('@id' => $personId),
        'publisher'=> array('@id' => $orgId)
        // If you have dates available for the bonus page, add:
        // 'datePublished' => 'YYYY-MM-DD',
        // 'dateModified'  => 'YYYY-MM-DD'
      )
    )
  );

  $jsonLdStr = json_encode($jsonLdGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  return '
  <aside class="author-card author-card--bonus" aria-label="Author information">
    <div class="author-card__inner">
      <div class="author-card__photo">'.$photoHtml.'</div>
      <div class="author-meta">
        <div class="author-kicker">Written by</div>
        <h4 class="author-name"><a href="'.$authorUrlEsc.'" rel="author">'.$name.'</a></h4>
        <div class="author-role">'.$role.'</div>
        <p class="author-bio">'.$bio.'</p>
      </div>
    </div>
  </aside>
  <script type="application/ld+json">'.$jsonLdStr.'</script>';
}
