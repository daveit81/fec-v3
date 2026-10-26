--
-- com_authors V2.7.3 schema
--
-- New table #__methodology_topics for DB-backed methodology topic registry.
-- Replaces the hardcoded array in AuthorsModelMethodology::getTopics — the
-- model retains the hardcoded array as a graceful fallback if the table is
-- missing or empty (zero-downtime upgrade).
--

CREATE TABLE IF NOT EXISTS `#__methodology_topics` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`              VARCHAR(64)  NOT NULL,
  `status`            ENUM('live','coming_soon','external','draft') NOT NULL DEFAULT 'coming_soon',
  `title`             VARCHAR(255) NOT NULL,
  `tagline`           TEXT,
  `short_description` TEXT,
  `description`       TEXT,
  `author_slug`       VARCHAR(64)  DEFAULT NULL,
  `reviewer_slug`     VARCHAR(64)  DEFAULT NULL,
  `version`           VARCHAR(16)  DEFAULT NULL,
  `last_updated`      DATE         DEFAULT NULL,
  `tmpl`              VARCHAR(64)  DEFAULT NULL,
  `external_url`      VARCHAR(255) DEFAULT NULL,
  `sort_order`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Seed the four current topics. INSERT IGNORE makes this idempotent for
-- repeated upgrades. Values mirror the hardcoded array from V2.7.2.
--

INSERT IGNORE INTO `#__methodology_topics`
  (slug, status, title, tagline, short_description, description, author_slug, reviewer_slug, version, last_updated, tmpl, external_url, sort_order)
VALUES
  ('fxcheck', 'live', 'How FXCheck Works',
   'Player-verified bonus status: how we calculate it, what each status means, and how to read it.',
   'Player-verified bonus status from real Yes/No reports. Live thresholds, status definitions, recency weighting, and editorial overrides.',
   'Methodology behind the FXCheck system that powers bonus status signals on FreeExtraChips.',
   'david-romano', 'sabrina-oconnell', 'v3', '2026-05-23', 'fxcheck', NULL, 10),

  ('rtp-verification', 'live', 'How We Verify In-Game RTP',
   'We check the RTP each slot actually serves vs. the value its provider publishes — and flag bonuses where the configured RTP is lower than expected.',
   'How we identify slot configurations where the in-game RTP is set below the provider published value.',
   'Methodology behind FreeExtraChips RTP verification system: provider primary sources, Wayback archiving, multi-tier RTP disclosure (Pragmatic Play, Play n Go), and per-casino paytable observations for opaque providers (RTG/SpinLogic/Rival).',
   'david-romano', 'sabrina-oconnell', 'v1', '2026-05-24', 'rtp-verification', NULL, 20),

  ('editorial-standards', 'live', 'Editorial Standards',
   'How reviews are written, sourced, fact-checked, and corrected — plus our affiliate disclosure and conflict-of-interest policy.',
   'How our reviews are written, reviewed, and corrected. Source hierarchy, affiliate disclosure, and corrections policy.',
   'Editorial standards at FreeExtraChips: source hierarchy (provider primary, regulator certificate, in-game observation, operator T and C), affiliate disclosure model, fact-checking workflow, update cadence per data type, correction policy, and conflict-of-interest rules.',
   'sabrina-oconnell', 'ethan-walker', 'v1', '2026-05-25', 'editorial-standards', NULL, 30),

  ('frank', 'external', 'How FRank Ranks Casinos',
   'Our proprietary casino ranking algorithm, in development since 2007.',
   'Proprietary ranking algorithm combining license validity, software providers, banking options, and weighted player reviews.',
   '',
   NULL, NULL, NULL, NULL, NULL, '/who-is-frank', 40);
