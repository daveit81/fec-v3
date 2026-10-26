-- com_authors 2.7.66 -- Blog desk: front-end editor for the Casino Bonus Blog
--
-- The side table of the blog (created by fec_blog_schema.sql, owned by the
-- blog, not by this component) gets what the desk needs:
--   eb_post_id becomes nullable: posts written in the desk have no EasyBlog
--     id (the UNIQUE key accepts any number of NULLs, the importer still
--     upserts on it);
--   source_mode / source_text keep the text as the author typed it (plain
--     mode only) so a post written as plain text is re-edited as plain text.
-- MariaDB syntax (IF NOT EXISTS on ADD COLUMN). No apostrophes in comments.

ALTER TABLE `#__fec_blog_meta` MODIFY `eb_post_id` INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `#__fec_blog_meta` ADD COLUMN IF NOT EXISTS `source_mode` VARCHAR(8) NULL DEFAULT NULL AFTER `import_flags`;

ALTER TABLE `#__fec_blog_meta` ADD COLUMN IF NOT EXISTS `source_text` MEDIUMTEXT NULL DEFAULT NULL AFTER `source_mode`;
