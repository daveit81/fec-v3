-- com_authors 2.7.55 -- FEC Payout Index monthly editions: frozen snapshots of the live register, one row per edition YYYY-MM, with diff vs previous edition, card path, newsletter text and per-channel announcement timestamps consumed by fecxbot_cron.php
CREATE TABLE IF NOT EXISTS `#__fec_payout_index_editions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition` CHAR(7) NOT NULL,
  `number` SMALLINT UNSIGNED NOT NULL,
  `published_at` DATETIME NOT NULL,
  `data_as_of` DATE NULL,
  `desk_tests` INT UNSIGNED NOT NULL DEFAULT 0,
  `desk_casinos` INT UNSIGNED NOT NULL DEFAULT 0,
  `stopped_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `brands_with_median` INT UNSIGNED NOT NULL DEFAULT 0,
  `payload` MEDIUMTEXT NOT NULL,
  `diff` MEDIUMTEXT NULL,
  `card_path` VARCHAR(255) NULL,
  `newsletter_text` TEXT NULL,
  `announce_x` DATETIME NULL,
  `announce_tg` DATETIME NULL,
  `announce_dc` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_edition` (`edition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
