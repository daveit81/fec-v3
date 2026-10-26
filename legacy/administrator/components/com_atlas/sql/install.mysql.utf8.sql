-- ============================================================
-- FEC Operator Atlas - schema v1.0
-- Target: MariaDB 10.6, InnoDB, utf8mb4
-- Prefix: hash-underscore for the Joomla installer - idempotent, safe where tables exist
-- Note: comments contain no apostrophes (splitSql safety)
-- ============================================================

CREATE TABLE IF NOT EXISTS `#__atlas_entities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `entity_type` ENUM('operator','holding','commercial','affiliate_program','payment_shell','unknown') NOT NULL DEFAULT 'operator',
  `jurisdiction` CHAR(2) DEFAULT NULL,
  `reg_number` VARCHAR(100) DEFAULT NULL,
  `reg_address` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','struck_off','dissolved','dormant','unverified') NOT NULL DEFAULT 'unverified',
  `status_date` DATE DEFAULT NULL,
  `successor_entity_id` INT UNSIGNED DEFAULT NULL,
  `first_seen` DATE DEFAULT NULL,
  `last_verified` DATETIME DEFAULT NULL,
  `public_notes` TEXT,
  `editor_notes` TEXT,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_atlas_entities_slug` (`slug`),
  KEY `idx_atlas_entities_status` (`status`),
  KEY `idx_atlas_entities_jurisdiction` (`jurisdiction`),
  KEY `idx_atlas_entities_successor` (`successor_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__atlas_licences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_id` INT UNSIGNED NOT NULL,
  `regulator` VARCHAR(100) NOT NULL,
  `licence_number` VARCHAR(150) DEFAULT NULL,
  `licence_type` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','revoked','expired','ghost','unverified') NOT NULL DEFAULT 'unverified',
  `valid_from` DATE DEFAULT NULL,
  `valid_to` DATE DEFAULT NULL,
  `last_verified` DATETIME DEFAULT NULL,
  `confidence` ENUM('confirmed','probable','single_source') NOT NULL DEFAULT 'single_source',
  `editor_notes` TEXT,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atlas_licences_entity` (`entity_id`),
  KEY `idx_atlas_licences_status` (`status`),
  CONSTRAINT `fk_atlas_licences_entity` FOREIGN KEY (`entity_id`)
    REFERENCES `#__atlas_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temporal brand to entity mapping. date_to NULL means current era.
-- App layer rule: max one current operator link per contentid.
CREATE TABLE IF NOT EXISTS `#__atlas_brand_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contentid` INT UNSIGNED NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `relation_type` ENUM('operator','owner','network','platform_provider') NOT NULL DEFAULT 'operator',
  `date_from` DATE DEFAULT NULL,
  `date_to` DATE DEFAULT NULL,
  `confidence` ENUM('confirmed','probable','single_source') NOT NULL DEFAULT 'single_source',
  `notes` TEXT,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atlas_bl_contentid` (`contentid`),
  KEY `idx_atlas_bl_entity` (`entity_id`),
  KEY `idx_atlas_bl_current` (`contentid`,`relation_type`,`date_to`),
  CONSTRAINT `fk_atlas_bl_entity` FOREIGN KEY (`entity_id`)
    REFERENCES `#__atlas_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__atlas_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_date` DATE NOT NULL,
  `event_type` ENUM('registered','licence_granted','licence_revoked','licence_migration','entity_migration','struck_off','rebrand','address_change','ownership_change','other') NOT NULL DEFAULT 'other',
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `contentid` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `confidence` ENUM('confirmed','probable','single_source') NOT NULL DEFAULT 'single_source',
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atlas_events_date` (`event_date`),
  KEY `idx_atlas_events_entity` (`entity_id`),
  KEY `idx_atlas_events_contentid` (`contentid`),
  KEY `idx_atlas_events_pub` (`published`,`event_date`),
  CONSTRAINT `fk_atlas_events_entity` FOREIGN KEY (`entity_id`)
    REFERENCES `#__atlas_entities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__atlas_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_type` ENUM('registry_pdf','regulator_validator','tc_firstparty','site_footer','industry_desk','court_record','other') NOT NULL DEFAULT 'other',
  `url` VARCHAR(500) DEFAULT NULL,
  `retrieved_at` DATETIME DEFAULT NULL,
  `archived_path` VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atlas_sources_type` (`source_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Polymorphic source attachment. Enables the two source rule via COUNT.
CREATE TABLE IF NOT EXISTS `#__atlas_source_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_id` INT UNSIGNED NOT NULL,
  `ref_table` ENUM('entities','licences','brand_links','events') NOT NULL,
  `ref_id` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_atlas_sl` (`source_id`,`ref_table`,`ref_id`),
  KEY `idx_atlas_sl_ref` (`ref_table`,`ref_id`),
  CONSTRAINT `fk_atlas_sl_source` FOREIGN KEY (`source_id`)
    REFERENCES `#__atlas_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convenience view: current operator per brand
CREATE OR REPLACE VIEW `vw_atlas_current_ownership` AS
SELECT
  bl.`contentid`,
  bl.`entity_id`,
  e.`slug`        AS `entity_slug`,
  e.`name`        AS `entity_name`,
  e.`entity_type`,
  e.`jurisdiction`,
  e.`reg_number`,
  e.`status`      AS `entity_status`,
  bl.`date_from`,
  bl.`confidence`,
  e.`published`   AS `entity_published`
FROM `#__atlas_brand_links` bl
INNER JOIN `#__atlas_entities` e ON e.`id` = bl.`entity_id`
WHERE bl.`relation_type` = 'operator'
  AND bl.`date_to` IS NULL;
