<?php
/**
 * AtlasModelDashboard
 *
 * Data layer for the FEC Operator Atlas editorial dashboard.
 *
 * Auth: hard-gated to a whitelist of Joomla user IDs (DASHBOARD_ALLOWED_USERS),
 * same pattern as AuthorsModelDashboard.
 *
 * Tables:
 *   #__atlas_entities      — operators, holdings, commercial umbrellas, payment shells
 *   #__atlas_licences      — per-entity licences with confidence grades
 *   #__atlas_brand_links   — temporal brand <-> entity map (date_to NULL = current era)
 *   #__atlas_events        — dated changelog items
 *   #__atlas_sources       — evidence records
 *   #__atlas_source_links  — polymorphic junction (two-source rule counting)
 *
 * Codified rules (enforced here, not in the UI):
 *   R1 — single_source is NEVER publishable. Publishing an entity is allowed,
 *        but single_source licences/links are excluded from public render;
 *        setPublished() returns them as warnings so the editor sees what
 *        will NOT surface. An entity whose own status is unverified cannot
 *        be published at all.
 *   R2 — two-source rule: upgrading a licence confidence to confirmed
 *        requires at least TWO linked sources. Enforced on the transition
 *        only (backfilled rows are grandfathered until next edit).
 *   R3 — one current operator per brand: a second operator link with
 *        date_to NULL on the same contentid is rejected; close the old
 *        era first (era firewall — history is never deleted).
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AtlasModelDashboard extends JModel
{
    /** User IDs allowed to access the dashboard. Keep this list short. */
    const DASHBOARD_ALLOWED_USERS = '42';

    /** Entity list page size. */
    const LIST_LIMIT = 50;

    public static $entityTypes  = array('operator','holding','commercial','affiliate_program','payment_shell','unknown');
    public static $entityStatus = array('active','struck_off','dissolved','dormant','unverified');
    public static $licStatus    = array('active','revoked','expired','ghost','unverified');
    public static $confidence   = array('confirmed','probable','single_source');
    public static $relations    = array('operator','owner','network','platform_provider');
    public static $sourceTypes  = array('registry_pdf','regulator_validator','tc_firstparty','site_footer','industry_desk','court_record','other');
    public static $eventTypes   = array('registered','licence_granted','licence_revoked','licence_migration','entity_migration','struck_off','rebrand','address_change','ownership_change','other');

    /**
     * Check whether the current logged-in Joomla user is on the whitelist.
     */
    public static function isAuthorized()
    {
        $user = JFactory::getUser();
        if (!$user || $user->guest) {
            return false;
        }
        $allowed = array_map('intval', explode(',', self::DASHBOARD_ALLOWED_USERS));
        return in_array((int)$user->id, $allowed, true);
    }

    // -----------------------------------------------------------------------
    // Stats
    // -----------------------------------------------------------------------

    public function getStats()
    {
        $db = JFactory::getDbo();

        $db->setQuery("SELECT COUNT(*) FROM #__atlas_entities");
        $entities = (int)$db->loadResult();

        $db->setQuery("SELECT COUNT(*) FROM #__atlas_entities WHERE published = 1");
        $published = (int)$db->loadResult();

        $db->setQuery("SELECT COUNT(*) FROM #__atlas_licences");
        $licences = (int)$db->loadResult();

        $db->setQuery("SELECT COUNT(*) FROM #__atlas_brand_links WHERE relation_type = 'operator' AND date_to IS NULL");
        $currentOps = (int)$db->loadResult();

        $db->setQuery("SELECT confidence, COUNT(*) AS n FROM #__atlas_licences GROUP BY confidence");
        $byConf = array();
        foreach ((array)$db->loadObjectList() as $row) {
            $byConf[$row->confidence] = (int)$row->n;
        }

        return array(
            'entities'         => $entities,
            'published'        => $published,
            'licences'         => $licences,
            'current_operator_links' => $currentOps,
            'licences_by_confidence' => $byConf,
        );
    }

    // -----------------------------------------------------------------------
    // Entities
    // -----------------------------------------------------------------------

    public function getEntitiesCount($q = '', $type = '', $status = '')
    {
        $db = JFactory::getDbo();
        $where = $this->entitiesWhere($db, $q, $type, $status);
        $db->setQuery("SELECT COUNT(*) FROM #__atlas_entities e WHERE " . $where);
        return (int)$db->loadResult();
    }

    protected function entitiesWhere($db, $q, $type, $status)
    {
        $where = array('1 = 1');
        if ($q !== '') {
            $esc = $db->getEscaped($q);
            $where[] = "(e.name LIKE '%" . $esc . "%' OR e.slug LIKE '%" . $esc . "%' OR e.reg_number LIKE '%" . $esc . "%')";
        }
        if ($type !== '' && in_array($type, self::$entityTypes, true)) {
            $where[] = "e.entity_type = '" . $db->getEscaped($type) . "'";
        }
        if ($status !== '' && in_array($status, self::$entityStatus, true)) {
            $where[] = "e.status = '" . $db->getEscaped($status) . "'";
        }
        return implode(' AND ', $where);
    }

    /**
     * Entity list with per-row rollups: licence count, current brand count,
     * and whether any attached datum is still single_source (drives the
     * publish-readiness badge in the UI).
     */
    public function getEntitiesList($q = '', $type = '', $status = '', $limit = null, $start = 0)
    {
        if ($limit === null) $limit = self::LIST_LIMIT;
        $limit = (int)$limit;
        $start = max(0, (int)$start);
        $db = JFactory::getDbo();
        $where = $this->entitiesWhere($db, $q, $type, $status);

        $sql = "
            SELECT
                e.id, e.slug, e.name, e.entity_type, e.jurisdiction,
                e.reg_number, e.status, e.published,
                (SELECT COUNT(*) FROM #__atlas_licences l WHERE l.entity_id = e.id) AS lic_count,
                (SELECT COUNT(*) FROM #__atlas_brand_links b
                   WHERE b.entity_id = e.id AND b.date_to IS NULL) AS brand_count,
                (SELECT COUNT(*) FROM #__atlas_licences l2
                   WHERE l2.entity_id = e.id AND l2.confidence = 'single_source') +
                (SELECT COUNT(*) FROM #__atlas_brand_links b2
                   WHERE b2.entity_id = e.id AND b2.date_to IS NULL AND b2.confidence = 'single_source') AS single_source_count
            FROM #__atlas_entities e
            WHERE " . $where . "
            ORDER BY e.name ASC";
        $db->setQuery($sql, $start, $limit);
        return (array)$db->loadObjectList();
    }

    public function getEntity($id)
    {
        $id = (int)$id;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT e.*, s.name AS successor_name, s.slug AS successor_slug
            FROM #__atlas_entities e
            LEFT JOIN #__atlas_entities s ON s.id = e.successor_entity_id
            WHERE e.id = " . $id);
        $entity = $db->loadObject();
        if (!$entity) return null;

        $db->setQuery("SELECT id, name, slug FROM #__atlas_entities WHERE successor_entity_id = " . $id . " ORDER BY name");
        $entity->predecessors = (array)$db->loadObjectList();

        return $entity;
    }

    public function getEntityLicences($entityId)
    {
        $entityId = (int)$entityId;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT l.*,
                   (SELECT COUNT(*) FROM #__atlas_source_links sl
                      WHERE sl.ref_table = 'licences' AND sl.ref_id = l.id) AS source_count
            FROM #__atlas_licences l
            WHERE l.entity_id = " . $entityId . "
            ORDER BY (l.status = 'active') DESC, l.regulator ASC");
        return (array)$db->loadObjectList();
    }

    public function getEntityBrandLinks($entityId)
    {
        $entityId = (int)$entityId;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT b.*, c.title AS review_title
            FROM #__atlas_brand_links b
            LEFT JOIN #__content c ON c.id = b.contentid
            WHERE b.entity_id = " . $entityId . "
            ORDER BY (b.date_to IS NULL) DESC, c.title ASC");
        return (array)$db->loadObjectList();
    }

    public function getEntityEvents($entityId)
    {
        $entityId = (int)$entityId;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT ev.*
            FROM #__atlas_events ev
            WHERE ev.entity_id = " . $entityId . "
            ORDER BY ev.event_date DESC, ev.id DESC");
        return (array)$db->loadObjectList();
    }

    public function getEntitySources($entityId)
    {
        $entityId = (int)$entityId;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT s.*, sl.id AS link_id
            FROM #__atlas_source_links sl
            INNER JOIN #__atlas_sources s ON s.id = sl.source_id
            WHERE sl.ref_table = 'entities' AND sl.ref_id = " . $entityId . "
            ORDER BY s.id DESC");
        return (array)$db->loadObjectList();
    }

    public function getLicenceSources($licenceId)
    {
        $licenceId = (int)$licenceId;
        $db = JFactory::getDbo();
        $db->setQuery("
            SELECT s.*, sl.id AS link_id
            FROM #__atlas_source_links sl
            INNER JOIN #__atlas_sources s ON s.id = sl.source_id
            WHERE sl.ref_table = 'licences' AND sl.ref_id = " . $licenceId . "
            ORDER BY s.id DESC");
        return (array)$db->loadObjectList();
    }

    /**
     * Insert/update an entity. Never touches published (setPublished only).
     */
    public function saveEntity($data)
    {
        $db = JFactory::getDbo();
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        $slug = isset($data['slug']) ? strtolower(trim($data['slug'])) : '';
        $name = isset($data['name']) ? trim($data['name']) : '';
        if ($name === '') return array('ok' => false, 'error' => 'name_required');
        if ($slug === '') $slug = $this->slugify($name);
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
            return array('ok' => false, 'error' => 'bad_slug');
        }

        $type = isset($data['entity_type']) ? $data['entity_type'] : 'operator';
        if (!in_array($type, self::$entityTypes, true)) return array('ok' => false, 'error' => 'bad_type');
        $status = isset($data['status']) ? $data['status'] : 'unverified';
        if (!in_array($status, self::$entityStatus, true)) return array('ok' => false, 'error' => 'bad_status');

        // slug uniqueness
        $db->setQuery("SELECT id FROM #__atlas_entities WHERE slug = '" . $db->getEscaped($slug) . "'" . ($id ? " AND id <> " . $id : ""));
        if ((int)$db->loadResult()) return array('ok' => false, 'error' => 'slug_taken');

        $oldSlug = '';
        $wasPublished = 0;
        if ($id) {
            $db->setQuery("SELECT slug, published FROM #__atlas_entities WHERE id = " . $id);
            $prev = $db->loadObject();
            if ($prev) { $oldSlug = $prev->slug; $wasPublished = (int)$prev->published; }
        }

        $successor = isset($data['successor_entity_id']) ? (int)$data['successor_entity_id'] : 0;
        if ($successor && $successor === $id) return array('ok' => false, 'error' => 'successor_self');
        if ($successor) {
            $db->setQuery("SELECT id FROM #__atlas_entities WHERE id = " . $successor);
            if (!(int)$db->loadResult()) return array('ok' => false, 'error' => 'successor_missing');
        }

        $fields = array(
            "slug = '" . $db->getEscaped($slug) . "'",
            "name = '" . $db->getEscaped($name) . "'",
            "entity_type = '" . $db->getEscaped($type) . "'",
            "jurisdiction = " . $this->nullable($db, isset($data['jurisdiction']) ? strtolower(trim($data['jurisdiction'])) : ''),
            "reg_number = " . $this->nullable($db, isset($data['reg_number']) ? trim($data['reg_number']) : ''),
            "reg_address = " . $this->nullable($db, isset($data['reg_address']) ? trim($data['reg_address']) : ''),
            "status = '" . $db->getEscaped($status) . "'",
            "status_date = " . $this->nullableDate($db, isset($data['status_date']) ? $data['status_date'] : ''),
            "successor_entity_id = " . ($successor ? $successor : 'NULL'),
            "editor_notes = " . $this->nullable($db, isset($data['editor_notes']) ? trim($data['editor_notes']) : ''),
            "public_notes = " . $this->nullable($db, isset($data['public_notes']) ? trim($data['public_notes']) : ''),
        );

        if ($id) {
            $db->setQuery("UPDATE #__atlas_entities SET " . implode(', ', $fields) . " WHERE id = " . $id);
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            if ($wasPublished && $oldSlug !== '' && $oldSlug !== $slug) {
                $this->sh404SyncEntity($oldSlug, 0);
                $this->sh404SyncEntity($slug, 1);
            }
        } else {
            $db->setQuery("INSERT INTO #__atlas_entities SET " . implode(', ', $fields) . ", published = 0");
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            $id = (int)$db->insertid();
        }
        return array('ok' => true, 'id' => $id, 'slug' => $slug);
    }

    /**
     * R1 — publish gate.
     */
    public function setPublished($id, $published)
    {
        $id = (int)$id;
        $published = $published ? 1 : 0;
        $db = JFactory::getDbo();

        $entity = $this->getEntity($id);
        if (!$entity) return array('ok' => false, 'error' => 'not_found');

        $warnings = array();
        if ($published) {
            if ($entity->status === 'unverified') {
                return array('ok' => false, 'error' => 'unverified_entity');
            }
            // Collect single_source data that will NOT render publicly (R1).
            $db->setQuery("SELECT CONCAT(regulator, ' ', COALESCE(licence_number, '(no number)')) FROM #__atlas_licences WHERE entity_id = " . $id . " AND confidence = 'single_source'");
            foreach ((array)$db->loadColumn() as $s) $warnings[] = 'licence hidden (single_source): ' . $s;
            $db->setQuery("
                SELECT CONCAT(COALESCE(c.title, CONCAT('contentid ', b.contentid)), ' [', b.relation_type, ']')
                FROM #__atlas_brand_links b
                LEFT JOIN #__content c ON c.id = b.contentid
                WHERE b.entity_id = " . $id . " AND b.confidence = 'single_source' AND b.date_to IS NULL");
            foreach ((array)$db->loadColumn() as $s) $warnings[] = 'brand link hidden (single_source): ' . $s;
        }

        $db->setQuery("UPDATE #__atlas_entities SET published = " . $published . " WHERE id = " . $id);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');

        $seo = $this->sh404SyncEntity($entity->slug, $published);

        return array('ok' => true, 'published' => $published, 'warnings' => $warnings, 'seo' => $seo);
    }

    // -----------------------------------------------------------------------
    // Licences
    // -----------------------------------------------------------------------

    public function saveLicence($data)
    {
        $db = JFactory::getDbo();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
        if (!$entityId) return array('ok' => false, 'error' => 'entity_required');

        $regulator = isset($data['regulator']) ? trim($data['regulator']) : '';
        if ($regulator === '') return array('ok' => false, 'error' => 'regulator_required');

        $status = isset($data['status']) ? $data['status'] : 'unverified';
        if (!in_array($status, self::$licStatus, true)) return array('ok' => false, 'error' => 'bad_status');
        $confidence = isset($data['confidence']) ? $data['confidence'] : 'probable';
        if (!in_array($confidence, self::$confidence, true)) return array('ok' => false, 'error' => 'bad_confidence');

        // R2 — two-source rule on the transition to confirmed.
        if ($confidence === 'confirmed') {
            $wasConfirmed = false;
            if ($id) {
                $db->setQuery("SELECT confidence FROM #__atlas_licences WHERE id = " . $id);
                $wasConfirmed = ($db->loadResult() === 'confirmed');
            }
            if (!$wasConfirmed) {
                $n = 0;
                if ($id) {
                    $db->setQuery("SELECT COUNT(*) FROM #__atlas_source_links WHERE ref_table = 'licences' AND ref_id = " . $id);
                    $n = (int)$db->loadResult();
                }
                if ($n < 2) {
                    return array('ok' => false, 'error' => 'two_source_rule', 'sources' => $n);
                }
            }
        }

        $fields = array(
            "entity_id = " . $entityId,
            "regulator = '" . $db->getEscaped($regulator) . "'",
            "licence_number = " . $this->nullable($db, isset($data['licence_number']) ? trim($data['licence_number']) : ''),
            "licence_type = " . $this->nullable($db, isset($data['licence_type']) ? trim($data['licence_type']) : ''),
            "status = '" . $db->getEscaped($status) . "'",
            "valid_from = " . $this->nullableDate($db, isset($data['valid_from']) ? $data['valid_from'] : ''),
            "valid_to = " . $this->nullableDate($db, isset($data['valid_to']) ? $data['valid_to'] : ''),
            "confidence = '" . $db->getEscaped($confidence) . "'",
            "editor_notes = " . $this->nullable($db, isset($data['editor_notes']) ? trim($data['editor_notes']) : ''),
        );
        // last_verified: only touched when the form sends the key (older cached
        // clients omit it and must not wipe the stored value). Empty clears.
        if (isset($data['last_verified'])) {
            $fields[] = "last_verified = " . $this->nullableDate($db, $data['last_verified']);
        }

        if ($id) {
            $db->setQuery("UPDATE #__atlas_licences SET " . implode(', ', $fields) . " WHERE id = " . $id);
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        } else {
            $db->setQuery("INSERT INTO #__atlas_licences SET " . implode(', ', $fields));
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            $id = (int)$db->insertid();
        }
        return array('ok' => true, 'id' => $id);
    }

    public function deleteLicence($id)
    {
        $id = (int)$id;
        $db = JFactory::getDbo();
        $db->setQuery("DELETE FROM #__atlas_source_links WHERE ref_table = 'licences' AND ref_id = " . $id);
        $db->query();
        $db->setQuery("DELETE FROM #__atlas_licences WHERE id = " . $id);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true);
    }

    // -----------------------------------------------------------------------
    // Brand links
    // -----------------------------------------------------------------------

    public function saveBrandLink($data)
    {
        $db = JFactory::getDbo();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $entityId  = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
        $contentid = isset($data['contentid']) ? (int)$data['contentid'] : 0;
        if (!$entityId || !$contentid) return array('ok' => false, 'error' => 'refs_required');

        $relation = isset($data['relation_type']) ? $data['relation_type'] : 'operator';
        if (!in_array($relation, self::$relations, true)) return array('ok' => false, 'error' => 'bad_relation');
        $confidence = isset($data['confidence']) ? $data['confidence'] : 'probable';
        if (!in_array($confidence, self::$confidence, true)) return array('ok' => false, 'error' => 'bad_confidence');

        $dateTo = isset($data['date_to']) ? trim($data['date_to']) : '';
        $isCurrent = ($dateTo === '');

        // The review must exist in the catalogue.
        $db->setQuery("SELECT c.title FROM #__content c INNER JOIN #__jreviews_content j ON j.contentid = c.id WHERE c.id = " . $contentid);
        $title = $db->loadResult();
        if ($title === null) return array('ok' => false, 'error' => 'review_not_found');

        // R4 — relation semantics: only operating-type entities can hold an
        // operator link. Programs and commercial umbrellas are networks.
        if ($relation === 'operator') {
            $db->setQuery("SELECT entity_type FROM #__atlas_entities WHERE id = " . $entityId);
            $etype = $db->loadResult();
            if (!in_array($etype, array('operator', 'holding', 'unknown'), true)) {
                return array('ok' => false, 'error' => 'relation_semantics', 'entity_type' => $etype);
            }
        }

        // R3 — one current operator per brand.
        if ($relation === 'operator' && $isCurrent) {
            $db->setQuery("
                SELECT b.id, e.name
                FROM #__atlas_brand_links b
                INNER JOIN #__atlas_entities e ON e.id = b.entity_id
                WHERE b.contentid = " . $contentid . "
                  AND b.relation_type = 'operator'
                  AND b.date_to IS NULL" . ($id ? " AND b.id <> " . $id : ""));
            $existing = $db->loadObject();
            if ($existing) {
                return array('ok' => false, 'error' => 'operator_conflict',
                             'existing_id' => (int)$existing->id, 'existing_entity' => $existing->name);
            }
        }

        $fields = array(
            "contentid = " . $contentid,
            "entity_id = " . $entityId,
            "relation_type = '" . $db->getEscaped($relation) . "'",
            "date_from = " . $this->nullableDate($db, isset($data['date_from']) ? $data['date_from'] : ''),
            "date_to = " . $this->nullableDate($db, $dateTo),
            "confidence = '" . $db->getEscaped($confidence) . "'",
            "notes = " . $this->nullable($db, isset($data['notes']) ? trim($data['notes']) : ''),
        );

        if ($id) {
            $db->setQuery("UPDATE #__atlas_brand_links SET " . implode(', ', $fields) . " WHERE id = " . $id);
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        } else {
            $db->setQuery("INSERT INTO #__atlas_brand_links SET " . implode(', ', $fields));
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            $id = (int)$db->insertid();
        }
        return array('ok' => true, 'id' => $id, 'review_title' => $title);
    }

    /**
     * Era close — the R3-compliant way to hand a brand to a new operator.
     */
    public function closeBrandLink($id, $dateTo)
    {
        $id = (int)$id;
        $db = JFactory::getDbo();
        $dt = $this->nullableDate($db, $dateTo);
        if ($dt === 'NULL') return array('ok' => false, 'error' => 'date_required');
        $db->setQuery("UPDATE #__atlas_brand_links SET date_to = " . $dt . " WHERE id = " . $id);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true);
    }

    public function deleteBrandLink($id)
    {
        $id = (int)$id;
        $db = JFactory::getDbo();
        $db->setQuery("DELETE FROM #__atlas_source_links WHERE ref_table = 'brand_links' AND ref_id = " . $id);
        $db->query();
        $db->setQuery("DELETE FROM #__atlas_brand_links WHERE id = " . $id);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true);
    }

    public function searchReviews($q)
    {
        $db = JFactory::getDbo();
        $q = trim($q);
        if ($q === '') return array();
        $esc = $db->getEscaped($q);
        $db->setQuery("
            SELECT c.id AS contentid, c.title,
                   (SELECT e.name FROM #__atlas_brand_links b
                      INNER JOIN #__atlas_entities e ON e.id = b.entity_id
                      WHERE b.contentid = c.id AND b.relation_type = 'operator' AND b.date_to IS NULL
                      LIMIT 1) AS current_operator
            FROM #__content c
            INNER JOIN #__jreviews_content j ON j.contentid = c.id
            WHERE c.title LIKE '%" . $esc . "%'
            ORDER BY c.title ASC", 0, 20);
        return (array)$db->loadObjectList();
    }

    // -----------------------------------------------------------------------
    // Sources
    // -----------------------------------------------------------------------

    public function listSources($q = '')
    {
        $db = JFactory::getDbo();
        $where = '1 = 1';
        if (trim($q) !== '') {
            $esc = $db->getEscaped(trim($q));
            $where = "(s.description LIKE '%" . $esc . "%' OR s.url LIKE '%" . $esc . "%')";
        }
        $db->setQuery("
            SELECT s.*,
                   (SELECT COUNT(*) FROM #__atlas_source_links sl WHERE sl.source_id = s.id) AS link_count
            FROM #__atlas_sources s
            WHERE " . $where . "
            ORDER BY s.id DESC", 0, 30);
        return (array)$db->loadObjectList();
    }

    public function saveSource($data)
    {
        $db = JFactory::getDbo();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $desc = isset($data['description']) ? trim($data['description']) : '';
        if ($desc === '') return array('ok' => false, 'error' => 'description_required');
        $stype = isset($data['source_type']) ? trim($data['source_type']) : 'other';
        if (!in_array($stype, self::$sourceTypes, true)) $stype = 'other';

        $fields = array(
            "source_type = '" . $db->getEscaped($stype) . "'",
            "url = " . $this->nullable($db, isset($data['url']) ? trim($data['url']) : ''),
            "description = '" . $db->getEscaped($desc) . "'",
            "retrieved_at = " . $this->nullableDate($db, isset($data['captured_at']) ? $data['captured_at'] : ''),
        );
        if ($id) {
            $db->setQuery("UPDATE #__atlas_sources SET " . implode(', ', $fields) . " WHERE id = " . $id);
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        } else {
            $db->setQuery("INSERT INTO #__atlas_sources SET " . implode(', ', $fields));
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            $id = (int)$db->insertid();
        }
        return array('ok' => true, 'id' => $id);
    }

    public function linkSource($sourceId, $refTable, $refId)
    {
        $sourceId = (int)$sourceId;
        $refId = (int)$refId;
        $allowed = array('entities','licences','brand_links','events');
        if (!in_array($refTable, $allowed, true)) return array('ok' => false, 'error' => 'bad_ref_table');
        $db = JFactory::getDbo();
        $db->setQuery("SELECT id FROM #__atlas_source_links WHERE source_id = " . $sourceId . " AND ref_table = '" . $db->getEscaped($refTable) . "' AND ref_id = " . $refId);
        if ((int)$db->loadResult()) return array('ok' => true, 'already' => 1);
        $db->setQuery("INSERT INTO #__atlas_source_links SET source_id = " . $sourceId . ", ref_table = '" . $db->getEscaped($refTable) . "', ref_id = " . $refId);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true, 'id' => (int)$db->insertid());
    }

    public function unlinkSource($linkId)
    {
        $db = JFactory::getDbo();
        $db->setQuery("DELETE FROM #__atlas_source_links WHERE id = " . (int)$linkId);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true);
    }

    // -----------------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------------

    public function saveEvent($data)
    {
        $db = JFactory::getDbo();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $entityId = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
        if (!$entityId) return array('ok' => false, 'error' => 'entity_required');

        $etype = isset($data['event_type']) ? $data['event_type'] : 'other';
        if (!in_array($etype, self::$eventTypes, true)) return array('ok' => false, 'error' => 'bad_event_type');
        $confidence = isset($data['confidence']) ? $data['confidence'] : 'probable';
        if (!in_array($confidence, self::$confidence, true)) return array('ok' => false, 'error' => 'bad_confidence');

        $title = isset($data['title']) ? trim($data['title']) : '';
        if ($title === '') return array('ok' => false, 'error' => 'title_required');
        $dt = $this->nullableDate($db, isset($data['event_date']) ? $data['event_date'] : '');
        if ($dt === 'NULL') return array('ok' => false, 'error' => 'date_required');

        // R1 also applies to changelog items.
        $published = !empty($data['published']) ? 1 : 0;
        if ($published && $confidence === 'single_source') {
            return array('ok' => false, 'error' => 'single_source_unpublishable');
        }

        $fields = array(
            "entity_id = " . $entityId,
            "event_date = " . $dt,
            "event_type = '" . $db->getEscaped($etype) . "'",
            "title = '" . $db->getEscaped($title) . "'",
            "description = " . $this->nullable($db, isset($data['description']) ? trim($data['description']) : ''),
            "confidence = '" . $db->getEscaped($confidence) . "'",
            "published = " . $published,
        );
        if ($id) {
            $db->setQuery("UPDATE #__atlas_events SET " . implode(', ', $fields) . " WHERE id = " . $id);
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        } else {
            $db->setQuery("INSERT INTO #__atlas_events SET " . implode(', ', $fields));
            if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
            $id = (int)$db->insertid();
        }
        return array('ok' => true, 'id' => $id);
    }

    public function deleteEvent($id)
    {
        $db = JFactory::getDbo();
        $db->setQuery("DELETE FROM #__atlas_source_links WHERE ref_table = 'events' AND ref_id = " . (int)$id);
        $db->query();
        $db->setQuery("DELETE FROM #__atlas_events WHERE id = " . (int)$id);
        if (!$db->query()) return array('ok' => false, 'error' => 'db_error');
        return array('ok' => true);
    }

    // -----------------------------------------------------------------------
    // Drift - live reconciliation between jReviews and the Atlas
    // -----------------------------------------------------------------------

    /** SQL expression: same normalization as site clustering. */
    protected function normExpr($col)
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" . $col . ",' ',''),'.',''),',',''),'&',''),'-',''),'(',''),')',''),'/',''))";
    }

    /**
     * Four-section drift report, mirroring atlas_sync_01:
     *   missing_network / missing_operator - actionable (Create link)
     *   no_entity - groups with no Atlas entity (Add entity shortcut)
     *   owner_mismatch - informational (the Atlas is often the corrected side)
     */
    public function getDriftReport()
    {
        $db = JFactory::getDbo();
        $ne = $this->normExpr('e.name');
        $nn = $this->normExpr('jc.jr_network');
        $no = $this->normExpr('jc.jr_owner');
        $out = array('missing_network' => array(), 'missing_operator' => array(),
                     'no_entity' => array(), 'owner_mismatch' => array());

        $db->setQuery("
            SELECT c.id AS contentid, c.title, TRIM(jc.jr_network) AS group_label,
                   e.id AS entity_id, e.slug AS entity_slug, e.name AS entity_name
            FROM #__jreviews_content jc
            JOIN #__content c ON c.id = jc.contentid
            JOIN #__atlas_entities e ON " . $ne . " = " . $nn . "
            WHERE TRIM(COALESCE(jc.jr_network, '')) <> ''
              AND NOT EXISTS (SELECT 1 FROM #__atlas_brand_links b
                              WHERE b.contentid = c.id AND b.entity_id = e.id
                                AND b.relation_type = 'network' AND b.date_to IS NULL)
            ORDER BY group_label, c.title", 0, 200);
        $out['missing_network'] = (array)$db->loadObjectList();

        $db->setQuery("
            SELECT c.id AS contentid, c.title, TRIM(jc.jr_owner) AS group_label,
                   e.id AS entity_id, e.slug AS entity_slug, e.name AS entity_name
            FROM #__jreviews_content jc
            JOIN #__content c ON c.id = jc.contentid
            JOIN #__atlas_entities e ON " . $ne . " = " . $no . "
            WHERE TRIM(COALESCE(jc.jr_owner, '')) <> ''
              AND e.entity_type IN ('operator', 'holding', 'unknown')
              AND NOT EXISTS (SELECT 1 FROM #__atlas_brand_links b
                              WHERE b.contentid = c.id AND b.entity_id = e.id
                                AND b.relation_type = 'operator' AND b.date_to IS NULL)
            ORDER BY group_label, c.title", 0, 200);
        $out['missing_operator'] = (array)$db->loadObjectList();

        $ne2 = $this->normExpr('e2.name');
        $db->setQuery("
            SELECT t.grp AS group_label, COUNT(*) AS members
            FROM (
              SELECT TRIM(jc.jr_network) AS grp, " . $nn . " AS nk
              FROM #__jreviews_content jc WHERE TRIM(COALESCE(jc.jr_network, '')) <> ''
              UNION ALL
              SELECT TRIM(jc.jr_owner), " . $no . "
              FROM #__jreviews_content jc WHERE TRIM(COALESCE(jc.jr_owner, '')) <> ''
            ) t
            WHERE NOT EXISTS (SELECT 1 FROM #__atlas_entities e2 WHERE " . $ne2 . " = t.nk)
            GROUP BY t.grp
            ORDER BY members DESC, t.grp", 0, 300);
        $out['no_entity'] = (array)$db->loadObjectList();

        $db->setQuery("
            SELECT b.contentid, c.title,
                   COALESCE(NULLIF(TRIM(jc.jr_owner), ''), '(empty)') AS jr_owner_value,
                   e.slug AS entity_slug
            FROM #__atlas_brand_links b
            JOIN #__atlas_entities e ON e.id = b.entity_id
            JOIN #__content c ON c.id = b.contentid
            LEFT JOIN #__jreviews_content jc ON jc.contentid = b.contentid
            WHERE b.relation_type = 'operator' AND b.date_to IS NULL
              AND " . $ne . " <> " . $this->normExpr("COALESCE(jc.jr_owner, '')") . "
            ORDER BY c.title", 0, 100);
        $out['owner_mismatch'] = (array)$db->loadObjectList();

        return $out;
    }

    // -----------------------------------------------------------------------
    // sh404SEF sync - the URL row is born on publish and dies on unpublish
    // -----------------------------------------------------------------------

    /**
     * Resolve the com_atlas PUBLIC menu item (view=operators) route + Itemid.
     * Returns array(route, itemid) or array('', 0) when no menu item exists.
     */
    protected function atlasPublicMenu()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = array('', 0);
        $app = JFactory::getApplication();
        if (is_object($app) && method_exists($app, 'getMenu')) {
            $menu = $app->getMenu();
            if ($menu) {
                $items = $menu->getItems('component', 'com_atlas');
                if (is_array($items)) {
                    $pick = null;
                    foreach ($items as $it) {
                        if (strpos((string)$it->link, 'view=operators') !== false) { $pick = $it; break; }
                    }
                    if ($pick === null && count($items)) $pick = $items[0];
                    if ($pick) $cache = array(ltrim($pick->route, '/'), (int)$pick->id);
                }
            }
        }
        return $cache;
    }

    /**
     * Keep the sh404SEF URL table in step with the publish flag.
     * Fully defensive: a missing sh404 table or a failed query never breaks
     * the publish itself. On publish: purge poisoned negative-cache rows for
     * the slug, insert the canonical mapping, ensure the changelog mapping.
     * On unpublish: remove the mapping so the URL 404s again.
     */
    protected function sh404SyncEntity($slug, $published)
    {
        $db = JFactory::getDbo();
        $db->setQuery("SHOW TABLES LIKE " . $db->quote($db->getPrefix() . 'sh404sef_urls'));
        if (!$db->loadResult()) return 'no_sh404';

        $menu = $this->atlasPublicMenu();
        if ($menu[0] === '') return 'no_menu_item';
        $route  = $menu[0];
        $itemid = $menu[1];
        $oldurl = $route . '/' . $slug;

        // Poisoned rows (empty newurl) for this URL always go.
        $db->setQuery("DELETE FROM `#__sh404sef_urls` WHERE `oldurl` = " . $db->quote($oldurl) . " AND (`newurl` = '' OR `newurl` IS NULL)");
        $db->query();

        if (!$published) {
            $db->setQuery("DELETE FROM `#__sh404sef_urls` WHERE `oldurl` = " . $db->quote($oldurl));
            $db->query();
            return 'removed';
        }

        $newurl = 'index.php?option=com_atlas&Itemid=' . (int)$itemid . '&lang=en&slug=' . $slug . '&view=operator';
        $db->setQuery("SELECT COUNT(*) FROM `#__sh404sef_urls` WHERE `oldurl` = " . $db->quote($oldurl) . " AND `newurl` <> ''");
        if (!(int)$db->loadResult()) {
            $db->setQuery("INSERT INTO `#__sh404sef_urls` (`cpt`, `rank`, `oldurl`, `newurl`, `dateadd`) VALUES (0, 0, " . $db->quote($oldurl) . ", " . $db->quote($newurl) . ", CURDATE())");
            $db->query();
        }

        // Changelog mapping, ensured once.
        $clOld = $route . '/changelog';
        $db->setQuery("SELECT COUNT(*) FROM `#__sh404sef_urls` WHERE `oldurl` = " . $db->quote($clOld) . " AND `newurl` <> ''");
        if (!(int)$db->loadResult()) {
            $clNew = 'index.php?option=com_atlas&Itemid=' . (int)$itemid . '&lang=en&view=changelog';
            $db->setQuery("INSERT INTO `#__sh404sef_urls` (`cpt`, `rank`, `oldurl`, `newurl`, `dateadd`) VALUES (0, 0, " . $db->quote($clOld) . ", " . $db->quote($clNew) . ", CURDATE())");
            $db->query();
        }
        return 'mapped';
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    protected function slugify($name)
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'entity';
    }

    /** '' -> NULL, otherwise quoted string. */
    protected function nullable($db, $value)
    {
        $value = (string)$value;
        if ($value === '') return 'NULL';
        return "'" . $db->getEscaped($value) . "'";
    }

    /** '' or invalid -> NULL, otherwise quoted YYYY-MM-DD. */
    protected function nullableDate($db, $value)
    {
        $value = trim((string)$value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return 'NULL';
        return "'" . $db->getEscaped($value) . "'";
    }
}
