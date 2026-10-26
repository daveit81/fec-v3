--
-- com_authors V2.6.8 migration
--
-- Adds retired flag to slot records. Retired is orthogonal to published:
--   retired=1 published=1 -> shown with banner + noindex
--   retired=1 published=0 -> hidden entirely
--   retired=0 published=1 -> normal live page
--   retired=0 published=0 -> draft
--

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `retired` TINYINT(1) NOT NULL DEFAULT 0 AFTER `published_at`;

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `retired_at` DATETIME DEFAULT NULL AFTER `retired`;

ALTER TABLE `#__slot_rtp`
  ADD COLUMN IF NOT EXISTS `retired_reason` VARCHAR(255) DEFAULT NULL AFTER `retired_at`;

DROP INDEX IF EXISTS `idx_retired` ON `#__slot_rtp`;
CREATE INDEX `idx_retired` ON `#__slot_rtp` (`retired`);
