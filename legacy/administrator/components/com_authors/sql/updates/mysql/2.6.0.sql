--
-- com_authors V2.6 migration (rewritten v2)
-- 
-- Adds publishing fields to #__slot_rtp:
--   slug          : public URL segment (slots/<slug>)
--   published     : 0=draft, 1=live
--   published_at  : first publish timestamp
-- 
-- Backfills slugs for existing rows from #__games.game_name.
-- 
-- Idempotent: safe to re-run. Uses ADD COLUMN IF NOT EXISTS and
-- DROP+CREATE INDEX so partial states are handled cleanly.
-- Requires MariaDB 10.0+ for ADD COLUMN IF NOT EXISTS (released 2014).
-- 
-- IMPORTANT: NO apostrophes inside SQL line comments. The Joomla 2.5/3.x
-- splitSql parser treats apostrophes anywhere (even in comments) as
-- string delimiters, which breaks multi-statement files. Lesson re-learned.
--

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(100) DEFAULT NULL AFTER `gid`;

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_verified_date`;

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `published_at` DATETIME DEFAULT NULL AFTER `published`;

-- Indexes: DROP+CREATE so we can re-run safely.
-- DROP INDEX IF EXISTS supported in MariaDB 10.0.2+.
DROP INDEX IF EXISTS `idx_slug` ON `#__slot_rtp`;
CREATE INDEX `idx_slug` ON `#__slot_rtp` (`slug`);

DROP INDEX IF EXISTS `idx_published` ON `#__slot_rtp`;
CREATE INDEX `idx_published` ON `#__slot_rtp` (`published`);

-- Backfill slug from game_name. Idempotent via WHERE slug IS NULL OR EMPTY.
-- Order matters: spaces become hyphens FIRST, then strip stray characters,
-- so the space-boundary between words is preserved.
UPDATE `#__slot_rtp` rtp
INNER JOIN `#__games` g ON g.gid = rtp.gid
SET rtp.slug = LOWER(
  REPLACE(
    REPLACE(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(
                        REPLACE(TRIM(g.game_name),
                          ' ', '-'),
                        '\'', ''),
                      '!', ''),
                    '™', ''),
                  '®', ''),
                '$', 's'),
              ',', ''),
            ':', ''),
          '.', ''),
        '(', ''),
      ')', ''),
    '/', '-')
)
WHERE rtp.slug IS NULL OR rtp.slug = '';

-- Collapse double-hyphens from stripping in-word punctuation.
-- Run twice to handle pathological cases (triple-strip).
UPDATE `#__slot_rtp` SET slug = REPLACE(slug, '--', '-') WHERE slug LIKE '%--%';
UPDATE `#__slot_rtp` SET slug = REPLACE(slug, '--', '-') WHERE slug LIKE '%--%';
