<?php
defined('_JEXEC') or die;

class FecsearchModelApi extends JModelLegacy
{
  private function normalizeQuery($q)
  {
    $q = trim((string)$q);
    $q = preg_replace('/\s+/', ' ', $q);
    if (function_exists('mb_strlen') && mb_strlen($q) > 64) $q = mb_substr($q, 0, 64);
    return $q;
  }

  private function qNorm($q)
  {
    $q = strtolower(trim((string)$q));
    $q = preg_replace('/\s+/', ' ', $q);
    return $q;
  }

  private function normalizeApiUrl($url)
  {
    $url = (string)$url;
    if ($url === '') return $url;

    if ($url[0] !== '/' && strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
      $url = '/' . $url;
    }
    $url = str_replace('/api/', '/', $url);
    $url = preg_replace('#^//+#', '/', $url);
    return $url;
  }

  private function bonusMeta($row)
  {
    $meta = array();

    if (isset($row['wr']) && (string)$row['wr'] === '0') $meta[] = 'No wagering';

    $currency = isset($row['currency']) ? $row['currency'] : '';
    $type     = isset($row['type']) ? $row['type'] : '';
    $hay = strtolower($currency . ' ' . $type);

    if (strpos($hay, 'btc') !== false || strpos($hay, 'eth') !== false || strpos($hay, 'crypto') !== false) $meta[] = 'Crypto';
    if (strpos($hay, 'fs') !== false || strpos($hay, 'spin') !== false) $meta[] = 'Free spins';
    if (!empty($row['code'])) $meta[] = 'Code';
    if (!empty($row['exclusive']) && (string)$row['exclusive'] === 'yes') $meta[] = 'Exclusive';
    if (!empty($row['max_cashout']) && is_numeric($row['max_cashout'])) $meta[] = 'Cashout cap';
    if (!empty($row['status']) && (string)$row['status'] === 'expired') $meta[] = 'Expired';
    $meta[] = 'FXCheck';

    $meta = array_values(array_unique($meta));
    return array_slice($meta, 0, 4);
  }

  public function search()
  {
    $input = JFactory::getApplication()->input;

    $qRaw = $input->getString('q', '');
    $q = $this->normalizeQuery($qRaw);

    $type = strtolower($input->getString('type', 'bonuses')); // bonuses|casinos
    if ($type !== 'bonuses' && $type !== 'casinos') $type = 'bonuses';

    $limit = (int)$input->getInt('limit', 20);
    if ($limit < 1) $limit = 20;
    if ($limit > 50) $limit = 50;

    $offset = (int)$input->getInt('offset', 0);
    if ($offset < 0) $offset = 0;

    $len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
    if ($q === '' || $len < 2) {
      return array('q' => $q, 'type' => $type, 'total' => 0, 'offset' => $offset, 'limit' => $limit, 'has_more' => false, 'items' => array());
    }

    $db = JFactory::getDbo();
    $prefix = method_exists($db, 'getPrefix') ? $db->getPrefix() : 'jos_';

    $tblBonuses = $prefix . 'bonuses';
    $tblContent = $prefix . 'content';
    $tblJreviews = $prefix . 'jreviews_content';

    $like   = $db->Quote('%' . $db->escape($q, false) . '%');
    $qExact = $db->Quote($db->escape($q, false));

    $qn = $this->qNorm($q);

    // Pills
    $isNoWagering = in_array($qn, array('no wagering','no-wagering','wagering free','wagering-free','wr 0','wr=0'), true);
    $isCode = in_array($qn, array('code','bonus code','with code','has code','promo code','coupon'), true);
    $isExclusive = in_array($qn, array('exclusive','exclusives','exclusive bonus','exclusive bonuses'), true);
    $isCrypto = in_array($qn, array('crypto','cryptocurrency','bitcoin','btc','eth','ethereum'), true);

    if ($type === 'casinos') {
      // Casino Reviews: catid=8 only
      $where = "c.state = 1 AND c.catid = 8 AND (c.title LIKE $like OR c.alias LIKE $like)";
      $countSql = "SELECT COUNT(*) FROM `$tblContent` c WHERE $where";
      $db->setQuery($countSql);
      $total = (int)$db->loadResult();

      $sql = "SELECT c.id AS cid, c.title, c.alias
              FROM `$tblContent` c
              WHERE $where
              ORDER BY c.id DESC
              LIMIT " . (int)$offset . ", " . (int)$limit;

      $db->setQuery($sql);
      $rows = $db->loadAssocList();
      if (!is_array($rows)) $rows = array();

      $items = array();
      foreach ($rows as $r) {
        $cid   = (int)$r['cid'];
        $title = !empty($r['title']) ? $r['title'] : 'Casino';

        $url = JRoute::_('index.php?option=com_content&view=article&id=' . $cid, false);
        $url = $this->normalizeApiUrl($url);

        if (!$url) {
          $slug = !empty($r['alias']) ? $r['alias'] : ('casino-' . $cid);
          $url = '/online-casinos/' . $slug;
        }

        $items[] = array(
          'type'  => 'casino',
          'title' => $title,
          'url'   => $url,
          'meta'  => array('Casino review'),
        );
      }

      $hasMore = ($offset + count($items)) < $total;

      return array(
        'q' => $q,
        'type' => $type,
        'total' => $total,
        'offset' => $offset,
        'limit' => $limit,
        'has_more' => $hasMore,
        'items' => $items,
      );
    }

    // Bonuses
    $where = "(a.status IS NULL OR a.status = '' OR a.status <> 'pending')";
    $joinJreviews = false;

    if ($isNoWagering) {
      $where .= " AND (a.wr = 0 OR a.wr = '0')";
    } else if ($isCode) {
      $where .= " AND (a.code IS NOT NULL AND TRIM(a.code) <> '')";
    } else if ($isExclusive) {
      $where .= " AND (a.exclusive = 'yes')";
    } else if ($isCrypto) {
      // Crypto: based on jreviews currencies field (star-list style)
      $joinJreviews = true;
      $where .= " AND (b.jr_currencies LIKE '%*btc*%' OR b.jr_currencies LIKE '%*eth*%' OR b.jr_currencies LIKE '%*ltc*%' OR b.jr_currencies LIKE '%*usdc*%' OR b.jr_currencies LIKE '%*usdt*%')";
    } else {
      $where .= " AND (
          a.code LIKE $like
          OR a.label LIKE $like
          OR a.brand LIKE $like
          OR a.type LIKE $like
          OR a.currency LIKE $like
          OR c.title LIKE $like
          OR c.alias LIKE $like
          OR a.code = $qExact
        )";
    }

    $join = "LEFT JOIN `$tblContent` c ON c.id = a.cid";
    if ($joinJreviews) {
      $join .= " LEFT JOIN `$tblJreviews` b ON b.contentid = a.cid";
    }

    $countSql = "SELECT COUNT(*) FROM `$tblBonuses` a $join WHERE $where";
    $db->setQuery($countSql);
    $total = (int)$db->loadResult();

    $sql = "SELECT
              a.bid, a.cid, a.code, a.label, a.brand, a.type, a.currency,
              a.status, a.wr, a.max_cashout, a.exclusive,
              c.title AS casino_title, c.alias AS casino_alias
            FROM `$tblBonuses` a
            $join
            WHERE $where
            ORDER BY
              CASE WHEN a.status = 'expired' THEN 1 ELSE 0 END,
              a.bid DESC
            LIMIT " . (int)$offset . ", " . (int)$limit;

    $db->setQuery($sql);
    $rows = $db->loadAssocList();
    if (!is_array($rows)) $rows = array();

    $items = array();
    foreach ($rows as $r) {
      $casinoTitle = !empty($r['casino_title']) ? $r['casino_title'] : (!empty($r['brand']) ? $r['brand'] : 'Bonus');
      $label       = !empty($r['label']) ? $r['label'] : (!empty($r['type']) ? $r['type'] : 'Bonus');

      $bid  = (int)$r['bid'];
      $cid  = (int)$r['cid'];
      $btype = isset($r['type']) ? (string)$r['type'] : '';

      $url = JRoute::_(
        'index.php?option=com_bonuses&view=details&type=' . urlencode($btype) . '&bid=' . $bid . '&cid=' . $cid,
        false
      );
      $url = $this->normalizeApiUrl($url);

      $items[] = array(
        'type' => 'bonus',
        'title' => trim($casinoTitle . ' — ' . $label),
        'url' => $url,
        'meta' => $this->bonusMeta($r),
      );
    }

    $hasMore = ($offset + count($items)) < $total;

    return array(
      'q' => $q,
      'type' => $type,
      'total' => $total,
      'offset' => $offset,
      'limit' => $limit,
      'has_more' => $hasMore,
      'items' => $items,
    );
  }
}
