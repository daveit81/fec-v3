-- com_authors 2.7.65 -- Blog contributors: Nik Davos profile + Editorial Standards bylines paragraph (topic v1.2)
--
-- Nik Davos is the professional pen name of the external contributor of the
-- Casino Bonus Blog (Joomla user 9156, previously bylined Player101). Idempotent:
-- the INSERT is skipped when the slug already exists, the UPDATE refreshes the
-- text fields either way. avatar_url stays empty until his portrait lands
-- (the profile renders initials over the type hue meanwhile).
-- NOTE for future migrations: Joomla 2.5 splitSql() is not comment-aware, so
-- comments in these files must not contain apostrophes or semicolons.

INSERT INTO `#__casino_authors`
  (`slug`, `name`, `role`, `bio`, `short_bio`, `avatar_url`, `author_type`, `joined_year`, `languages`, `expertise_json`, `credentials_json`, `joomla_user_id`, `is_active`)
SELECT
  'nik-davos',
  'Nik Davos',
  'Slot & game-release analyst · Contributor',
  '',
  '',
  '',
  'contributor',
  2016,
  'English',
  '[]',
  '[]',
  9156,
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__casino_authors` WHERE `slug` = 'nik-davos');

UPDATE `#__casino_authors` SET
  `name`             = 'Nik Davos',
  `role`             = 'Slot & game-release analyst · Contributor',
  `bio`              = 'Mega Moolah, Starburst and other V1\'s of sequels were roaming casino top charts when Nik typed his first words about online slots. Little did he know to be carouselled for two decades to tell you another story in the universe of provocative themes, horizonless win-lines and bamboozling mechanics. He promises to stop writing when Pragmatic releases their last Big Bass game.',
  `short_bio`        = 'Two decades on the slot beat: new releases, mechanics, jackpots and live tables, one story at a time on the Casino Bonus Blog.',
  `author_type`      = 'contributor',
  `joined_year`      = 2016,
  `languages`        = 'English',
  `expertise_json`   = '["Slot releases","Game mechanics","Progressive jackpots","Live casino","NetEnt","Microgaming","Playtech","Pragmatic Play"]',
  `credentials_json` = '[{"t":"Casino Bonus Blog contributor since 2016","d":"Covers slot launches, live-casino tables and jackpot wins under his own byline. Every post is dated and signed."},{"t":"Independent contributor","d":"Not a member of the editorial desk. Editorial responsibility for what he publishes here sits with FreeExtraChips."},{"t":"Twenty years on the slot beat","d":"Started writing about online slots when the first sequels of Mega Moolah and Starburst were topping casino charts."}]',
  `joomla_user_id`   = 9156,
  `is_active`        = 1
WHERE `slug` = 'nik-davos';

-- Editorial Standards: the Bylines section now covers outside contributors.
UPDATE `#__methodology_topics` SET `version` = 'v1.2', `last_updated` = '2026-10-13' WHERE `slug` = 'editorial-standards' AND `kind` = 'methodology';
