--
-- com_authors V2.4 migration
-- Adds RTP database tables: jos_slot_rtp + jos_slot_rtp_observations
-- Safe to re-run (IF NOT EXISTS).
--

CREATE TABLE IF NOT EXISTS `#__slot_rtp` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `gid` INT(11) NOT NULL COMMENT 'FK to #__games.gid',
  `rtp_theoretical_max` DECIMAL(5,2) DEFAULT NULL COMMENT 'Highest configured RTP, e.g. 96.50',
  `rtp_theoretical_min` DECIMAL(5,2) DEFAULT NULL COMMENT 'Lowest configured RTP, e.g. 88.12 for Pragmatic Play tier-low',
  `rtp_variants_json` TEXT DEFAULT NULL COMMENT 'JSON array of all tier variants, e.g. ["96.50","95.50","94.50","88.12"]',
  `max_win_x` INT(11) DEFAULT NULL COMMENT 'Max win multiplier, e.g. 5000',
  `volatility` ENUM('low','low-med','medium','med-high','high') DEFAULT NULL,
  `paylines` VARCHAR(50) DEFAULT NULL COMMENT 'Could be "20" or "Ways to Win" or "Cluster Pays"',
  `reels` TINYINT(4) DEFAULT NULL,
  `release_year` YEAR DEFAULT NULL,
  `primary_source_url` VARCHAR(500) DEFAULT NULL COMMENT 'Provider game page URL (authoritative)',
  `primary_source_archived_url` VARCHAR(500) DEFAULT NULL COMMENT 'Wayback Machine snapshot URL',
  `certificate_url` VARCHAR(500) DEFAULT NULL COMMENT 'GLI / eCOGRA / iTechLabs certificate URL if available',
  `last_verified_date` DATE DEFAULT NULL,
  `verified_by_author_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'FK to #__casino_authors.id',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gid` (`gid`),
  KEY `idx_verified` (`last_verified_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `#__slot_rtp_observations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `gid` INT(11) NOT NULL COMMENT 'FK to #__games.gid',
  `cid` INT(11) NOT NULL COMMENT 'FK to #__jreviews_content.contentid (casino)',
  `rtp_observed` DECIMAL(5,2) DEFAULT NULL,
  `observed_date` DATE DEFAULT NULL,
  `evidence_type` ENUM('paytable_screenshot','casino_disclosure','regulator_certificate','player_report') DEFAULT 'paytable_screenshot',
  `screenshot_path` VARCHAR(500) DEFAULT NULL COMMENT 'Path under /images/rtp/<gid>/...',
  `verified_by_author_id` INT(10) UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gid_cid` (`gid`, `cid`),
  KEY `idx_gid` (`gid`),
  KEY `idx_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
