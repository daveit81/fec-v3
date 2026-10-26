-- com_authors 2.7.61 -- Licence Watch: quarterly licence register editions (frozen payload, diff, Atlas events and three desk-written sections) plus the page topic licence-watch
CREATE TABLE IF NOT EXISTS `#__fec_licence_watch_editions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition` CHAR(7) NOT NULL,
  `number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(12) NOT NULL DEFAULT 'draft',
  `frozen_at` DATETIME NOT NULL,
  `published_at` DATETIME NULL,
  `data_as_of` DATE NULL,
  `live_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `verified_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `curacao_declared` INT UNSIGNED NOT NULL DEFAULT 0,
  `no_regulator_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `payload` MEDIUMTEXT NOT NULL,
  `diff` MEDIUMTEXT NULL,
  `events` MEDIUMTEXT NULL,
  `sections` MEDIUMTEXT NULL,
  `author_slug` VARCHAR(64) NOT NULL DEFAULT 'ethan-walker',
  `reviewer_slug` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `modified_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lw_edition` (`edition`),
  KEY `idx_lw_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `#__methodology_topics` (`slug`, `kind`, `status`, `title`, `tagline`, `short_description`, `description`, `author_slug`, `reviewer_slug`, `version`, `last_updated`, `tmpl`, `schema_type`, `external_url`, `sort_order`) VALUES ('licence-watch', 'page', 'live', 'Licence Watch: Where Online Casinos Say They Are Licensed', 'A quarterly register of the licences behind every live casino on FreeExtraChips: what each brand declares, what we have verified against the regulators, and what moved since the last edition.', 'Quarterly licence register: live brands by declared regulator, the verified layer from the Operator Atlas, the Curacao transition, the new rails, brands with no regulator, and what moved in the quarter.', 'Quarterly register of online casino licences on FreeExtraChips: every live brand by declared regulator, the operators and licences verified against the registers, and what changed since the previous edition.', 'ethan-walker', 'david-romano', 'v1.0', '2026-10-09', 'licence-watch', 'WebPage', NULL, 25) ON DUPLICATE KEY UPDATE `kind` = VALUES(`kind`), `status` = VALUES(`status`), `title` = VALUES(`title`), `tagline` = VALUES(`tagline`), `short_description` = VALUES(`short_description`), `description` = VALUES(`description`), `author_slug` = VALUES(`author_slug`), `reviewer_slug` = VALUES(`reviewer_slug`), `tmpl` = VALUES(`tmpl`), `schema_type` = VALUES(`schema_type`), `version` = VALUES(`version`), `last_updated` = VALUES(`last_updated`), `sort_order` = VALUES(`sort_order`);
