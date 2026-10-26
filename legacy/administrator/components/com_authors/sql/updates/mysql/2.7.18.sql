CREATE TABLE IF NOT EXISTS `#__fec_verifications` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `casino_id`    INT UNSIGNED NOT NULL,
  `status`       ENUM('draft','committed') NOT NULL DEFAULT 'draft',
  `step`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `data`         MEDIUMTEXT NULL,
  `created_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL,
  `updated_at`   DATETIME NOT NULL,
  `committed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_casino_status` (`casino_id`, `status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
