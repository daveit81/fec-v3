CREATE TABLE IF NOT EXISTS `#__fec_verification_evidence` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `verification_id` INT UNSIGNED NOT NULL,
  `casino_id`       INT UNSIGNED NOT NULL,
  `step`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `field_code`      VARCHAR(32) NOT NULL,
  `visibility`      ENUM('public','private') NOT NULL DEFAULT 'private',
  `original_name`   VARCHAR(255) NOT NULL,
  `stored_name`     VARCHAR(128) NOT NULL,
  `mime_type`       VARCHAR(64) NOT NULL,
  `byte_size`       INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_at`     DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_verification` (`verification_id`),
  KEY `idx_casino` (`casino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
