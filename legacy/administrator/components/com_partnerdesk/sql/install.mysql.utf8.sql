-- Partner Desk submissions table for FEC
-- Status codes: 0 pending, 1 approved, 2 rejected

CREATE TABLE IF NOT EXISTS `#__fec_brand_submissions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand_name` VARCHAR(190) NOT NULL DEFAULT '',
  `casino_url` VARCHAR(255) NOT NULL DEFAULT '',
  `aff_program_name` VARCHAR(190) NOT NULL DEFAULT '',
  `aff_program_url` VARCHAR(255) NOT NULL DEFAULT '',
  `am_name` VARCHAR(190) NOT NULL DEFAULT '',
  `am_email` VARCHAR(190) NOT NULL DEFAULT '',
  `payload` MEDIUMTEXT NOT NULL,
  `status` TINYINT(3) NOT NULL DEFAULT 0,
  `admin_notes` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL,
  `reviewed` DATETIME NULL DEFAULT NULL,
  `reviewed_by` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created`),
  KEY `idx_am_email` (`am_email`),
  KEY `idx_brand` (`brand_name`),
  KEY `idx_ip_created` (`ip`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
