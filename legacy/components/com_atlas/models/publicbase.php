<?php
/**
 * AtlasModelPublicbase
 *
 * Shared helpers for the PUBLIC Atlas views. The public rendering rules
 * from the design doc are enforced here, at the query level:
 *
 *   - only entities with published = 1 are ever selected;
 *   - rows with confidence = single_source are NEVER selected
 *     (licences, brand links, events alike);
 *   - editor_notes is never part of a SELECT list in this file;
 *   - events additionally require published = 1.
 *
 * Templates receive pre-filtered data and cannot leak what the models
 * never load.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AtlasModelPublicbase extends JModel
{
    /** Jurisdiction display map (ISO-2 lowercase -> label). Fallback: uppercase code. */
    public static $jurisdictions = array(
        'cw' => 'Curaçao',
        'cr' => 'Costa Rica',
        'ee' => 'Estonia',
        'mt' => 'Malta',
        'gb' => 'United Kingdom',
        'bz' => 'Belize',
        'km' => 'Anjouan (Comoros)',
        'cy' => 'Cyprus',
        'ca' => 'Canada',
        'im' => 'Isle of Man',
        'gi' => 'Gibraltar',
        'vg' => 'British Virgin Islands',
        'pa' => 'Panama',
        'sc' => 'Seychelles',
        'mh' => 'Marshall Islands',
        'nl' => 'Netherlands',
        'cz' => 'Czech Republic',
        'sk' => 'Slovakia',
        'ua' => 'Ukraine',
        'au' => 'Australia',
    );

    public static $typeLabels = array(
        'operator'          => 'Operator',
        'holding'           => 'Holding',
        'commercial'        => 'Commercial layer',
        'affiliate_program' => 'Affiliate program',
        'payment_shell'     => 'Payment entity',
        'unknown'           => 'Unclassified',
    );

    public static $confidenceLabels = array(
        'confirmed' => 'Verified — two independent sources',
        'probable'  => 'Probable — single strong source',
    );

    public static function jurisdictionLabel($code)
    {
        $code = strtolower(trim((string)$code));
        if ($code === '') return '';
        return isset(self::$jurisdictions[$code]) ? self::$jurisdictions[$code] : strtoupper($code);
    }

    public static function typeLabel($type)
    {
        return isset(self::$typeLabels[$type]) ? self::$typeLabels[$type] : $type;
    }

    /** Same normalization as site clustering (fec_sister_owner_norm). */
    public static function clusterNorm($name)
    {
        $name = strtolower(trim((string)$name));
        return str_replace(array(' ', '.', ',', '&', '-', '(', ')', '/'), '', $name);
    }

    /**
     * Registered cluster page for this entity name, if one exists.
     * Matches jos_fec_owner_groups by norm_key; returns object(slug, label)
     * or null. Defensive: a missing clusters table returns null silently.
     */
    public static function getClusterFor($entityName)
    {
        $db = JFactory::getDbo();
        $db->setQuery("SHOW TABLES LIKE " . $db->quote($db->getPrefix() . 'fec_owner_groups'));
        if (!$db->loadResult()) return null;
        $norm = self::clusterNorm($entityName);
        if ($norm === '') return null;
        $db->setQuery("SELECT slug, label FROM #__fec_owner_groups WHERE norm_key = " . $db->quote($norm) . " AND COALESCE(slug, '') <> ''");
        $row = $db->loadObject();
        return $row ? $row : null;
    }
}

/**
 * AtlasModelOperators — index of published entities.
 */
class AtlasModelOperators extends AtlasModelPublicbase
{
    public function getPublishedEntities()
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT e.id, e.slug, e.name, e.entity_type, e.jurisdiction, e.reg_number,
                   e.status, e.status_date, e.public_notes,
                   (SELECT COUNT(*) FROM #__atlas_brand_links b
                      WHERE b.entity_id = e.id AND b.relation_type = 'operator'
                        AND b.date_to IS NULL AND b.confidence <> 'single_source') AS brand_count,
                   (SELECT COUNT(*) FROM #__atlas_brand_links bn
                      WHERE bn.entity_id = e.id AND bn.relation_type = 'network'
                        AND bn.date_to IS NULL AND bn.confidence <> 'single_source') AS net_count,
                   (SELECT COUNT(*) FROM #__atlas_licences l
                      WHERE l.entity_id = e.id AND l.confidence <> 'single_source') AS lic_count
            FROM #__atlas_entities e
            WHERE e.published = 1
            ORDER BY e.entity_type ASC, e.name ASC");
        return (array)$db->loadObjectList();
    }

    public function getIndexStats()
    {
        $db = JFactory::getDbo();
        $db->setQuery("SELECT COUNT(*) FROM #__atlas_entities WHERE published = 1");
        $entities = (int)$db->loadResult();
        $db->setQuery("
            SELECT COUNT(*) FROM #__atlas_brand_links b
            INNER JOIN #__atlas_entities e ON e.id = b.entity_id AND e.published = 1
            WHERE b.relation_type = 'operator' AND b.date_to IS NULL AND b.confidence <> 'single_source'");
        $brands = (int)$db->loadResult();
        $db->setQuery("
            SELECT COUNT(*) FROM #__atlas_licences l
            INNER JOIN #__atlas_entities e ON e.id = l.entity_id AND e.published = 1
            WHERE l.confidence <> 'single_source'");
        $licences = (int)$db->loadResult();
        $db->setQuery("SELECT MAX(modified) FROM #__atlas_entities WHERE published = 1");
        $lastUpdate = $db->loadResult();
        return array('entities' => $entities, 'brands' => $brands, 'licences' => $licences, 'last_update' => $lastUpdate);
    }
}

/**
 * AtlasModelOperator — single published entity by slug.
 */
class AtlasModelOperator extends AtlasModelPublicbase
{
    public function getEntityBySlug($slug)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT e.id, e.slug, e.name, e.entity_type, e.jurisdiction, e.reg_number,
                   e.reg_address, e.status, e.status_date, e.public_notes,
                   e.successor_entity_id, e.first_seen, e.last_verified, e.modified
            FROM #__atlas_entities e
            WHERE e.published = 1 AND e.slug = '" . $db->getEscaped($slug) . "'");
        $entity = $db->loadObject();
        if (!$entity) return null;

        // Successor: link only when the successor itself is published.
        $entity->successor = null;
        if ((int)$entity->successor_entity_id) {
            $db->setQuery("
                SELECT name, slug, published FROM #__atlas_entities
                WHERE id = " . (int)$entity->successor_entity_id);
            $entity->successor = $db->loadObject();
        }

        // Predecessors (published-aware the same way).
        $db->setQuery("
            SELECT name, slug, published FROM #__atlas_entities
            WHERE successor_entity_id = " . (int)$entity->id . " ORDER BY name");
        $entity->predecessors = (array)$db->loadObjectList();

        return $entity;
    }

    public function getLicences($entityId)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT l.regulator, l.licence_number, l.licence_type, l.status,
                   l.valid_from, l.valid_to, l.confidence,
                   (SELECT COUNT(*) FROM #__atlas_source_links sl
                      WHERE sl.ref_table = 'licences' AND sl.ref_id = l.id) AS source_count
            FROM #__atlas_licences l
            WHERE l.entity_id = " . (int)$entityId . "
              AND l.confidence <> 'single_source'
            ORDER BY (l.status = 'active') DESC, l.regulator ASC");
        return (array)$db->loadObjectList();
    }

    public function getBrandLinks($entityId)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT b.contentid, b.relation_type, b.date_from, b.date_to, b.confidence,
                   c.title AS review_title, c.alias AS review_alias, c.catid, cat.alias AS cat_alias
            FROM #__atlas_brand_links b
            INNER JOIN #__content c ON c.id = b.contentid
            LEFT JOIN #__categories cat ON cat.id = c.catid
            WHERE b.entity_id = " . (int)$entityId . "
              AND b.confidence <> 'single_source'
            ORDER BY (b.date_to IS NULL) DESC, b.relation_type ASC, c.title ASC");
        return (array)$db->loadObjectList();
    }

    public function getEvents($entityId)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT ev.event_date, ev.event_type, ev.title, ev.description, ev.confidence
            FROM #__atlas_events ev
            WHERE ev.entity_id = " . (int)$entityId . "
              AND ev.published = 1
              AND ev.confidence <> 'single_source'
            ORDER BY ev.event_date DESC, ev.id DESC");
        return (array)$db->loadObjectList();
    }

    public function getSources($entityId)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT s.source_type, s.url, s.description, s.retrieved_at
            FROM #__atlas_source_links sl
            INNER JOIN #__atlas_sources s ON s.id = sl.source_id
            WHERE sl.ref_table = 'entities' AND sl.ref_id = " . (int)$entityId . "
            ORDER BY s.id ASC");
        return (array)$db->loadObjectList();
    }
}

/**
 * AtlasModelChangelog — published events across published entities.
 */
class AtlasModelChangelog extends AtlasModelPublicbase
{
    public function getEvents($limit = 100)
    {
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT ev.event_date, ev.event_type, ev.title, ev.description, ev.confidence,
                   e.name AS entity_name, e.slug AS entity_slug
            FROM #__atlas_events ev
            INNER JOIN #__atlas_entities e ON e.id = ev.entity_id AND e.published = 1
            WHERE ev.published = 1 AND ev.confidence <> 'single_source'
            ORDER BY ev.event_date DESC, ev.id DESC", 0, (int)$limit);
        return (array)$db->loadObjectList();
    }
}
