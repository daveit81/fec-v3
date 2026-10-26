<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Author Bonuses model — paginated list of bonuses authored by a given
 * casino_author, used by the activity tab "Bonuses reviewed" on the
 * single Author profile page.
 *
 * FEC V2.1 rewrite: getItemsByAuthorId now fetches the rich column set
 * the new au-row template needs (brand, max_bonus, game, fp_time*, wr,
 * wr_meth, code, status, added) plus LEFT JOIN to #__bonus_feedback_agg
 * for the FXCheck yes/no counts shown in each row.
 */
class AuthorsModelAuthorBonuses extends JModel
{
    /**
     * Total bonus count for this author, optionally filtered to status=active.
     *
     * @param int  $authorId    casino_authors.id
     * @param bool $activeOnly  when true, count only status='active'
     */
    public function getTotalByAuthorId($authorId, $activeOnly = 0)
    {
        $authorId = (int)$authorId;
        if ($authorId <= 0) return 0;

        $db = JFactory::getDbo();
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bonuses'))
            ->where($db->quoteName('author_id') . ' = ' . $authorId)
            ->where($db->quoteName('status') . ' <> ' . $db->quote('pending'));

        if ((int)$activeOnly === 1) {
            $q->where($db->quoteName('status') . ' = ' . $db->quote('active'));
        }

        $db->setQuery($q);
        $n = (int)$db->loadResult();
        if ($db->getErrorNum()) {
            error_log('AuthorsModelAuthorBonuses::getTotalByAuthorId: ' . $db->getErrorMsg());
            return 0;
        }
        return $n;
    }

    /**
     * Paginated rich-row list. Each row contains the columns needed by the
     * au-row template:
     *   - bid, cid                (route building)
     *   - type, bonus, max_bonus, currency, game, fp_time, fp_time_den
     *                              (fec_bonus_full_title + fec_amount_primary)
     *   - wr, wr_meth             (fec_vm_wager_label)
     *   - code, status, added, brand
     *   - fx_yes, fx_no           (LEFT JOIN to bonus_feedback_agg; 0 if no
     *                              feedback aggregated yet)
     *
     * @param int  $authorId
     * @param int  $limit       page size
     * @param int  $start       offset (0-based)
     * @param bool $activeOnly  status='active' filter
     */
    public function getItemsByAuthorId($authorId, $limit = 10, $start = 0, $activeOnly = 0)
    {
        $authorId = (int)$authorId;
        $limit    = max(1, (int)$limit);
        $start    = max(0, (int)$start);
        if ($authorId <= 0) return array();

        $db = JFactory::getDbo();

        // Raw SQL for clarity (and to bypass the JDatabaseQuery LIMIT quirk
        // that previously bit the team page Recent Work query).
        $sql = 'SELECT'
             . ' b.bid, b.cid, b.type, b.bonus, b.max_bonus, b.currency,'
             . ' b.game, b.fp_time, b.fp_time_den,'
             . ' b.wr, b.wr_meth, b.code,'
             . ' b.status, b.added, b.brand,'
             . ' COALESCE(agg.yes_count, 0) AS fx_yes,'
             . ' COALESCE(agg.no_count, 0)  AS fx_no'
             . ' FROM ' . $db->quoteName('#__bonuses') . ' AS b'
             . ' LEFT JOIN ' . $db->quoteName('#__bonus_feedback_agg') . ' AS agg'
             . '   ON agg.bid = b.bid'
             . ' WHERE b.author_id = ' . $authorId
             . '   AND b.status <> ' . $db->quote('pending');

        if ((int)$activeOnly === 1) {
            $sql .= ' AND b.status = ' . $db->quote('active');
        }

        $sql .= ' ORDER BY b.added DESC'
              . ' LIMIT ' . $start . ', ' . $limit;

        $db->setQuery($sql);
        $rows = $db->loadAssocList();

        if ($db->getErrorNum()) {
            error_log('AuthorsModelAuthorBonuses::getItemsByAuthorId: '
                . $db->getErrorMsg() . ' | SQL: ' . $sql);
            return array();
        }
        if (!is_array($rows)) return array();

        // Normalise nullable string columns so the template doesn't have to
        foreach ($rows as &$r) {
            foreach (array('brand','code','game','wr_meth') as $k) {
                if (!isset($r[$k]) || $r[$k] === null) $r[$k] = '';
                $r[$k] = trim((string)$r[$k]);
            }
            // fx_yes/fx_no come back as strings from MySQL; coerce to int
            $r['fx_yes'] = (int)$r['fx_yes'];
            $r['fx_no']  = (int)$r['fx_no'];
        }
        unset($r);

        return $rows;
    }
}