-- com_authors 2.7.72 -- blog posts linked to RTP-verified slots
--
-- #__fec_blog_meta (side table of the blog, created by the blog importer)
-- gets the link to #__slot_rtp: slot_gid = #__games.gid, slot_rel = game
-- (the post is about that slot) or family (sequel, variant, jackpot story).
-- Filled by the desk on save and, once, by fec_blog_slot_links.php.
-- MariaDB syntax (IF NOT EXISTS). No apostrophes in comments.

ALTER TABLE `#__fec_blog_meta` ADD COLUMN IF NOT EXISTS `slot_gid` INT NULL DEFAULT NULL AFTER `grid`;

ALTER TABLE `#__fec_blog_meta` ADD COLUMN IF NOT EXISTS `slot_rel` VARCHAR(8) NULL DEFAULT NULL AFTER `slot_gid`;

ALTER TABLE `#__fec_blog_meta` ADD INDEX IF NOT EXISTS `idx_slot_gid` (`slot_gid`);
