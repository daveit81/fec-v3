--
-- com_authors V2.7.9 schema bump
--
-- Expand the volatility ENUM on #__slot_rtp to accommodate modern high-vol
-- slots that do not fit the legacy 5-level scale (Reactoonz 10/10 becomes
-- very-high, Dead or Alive 2 Extreme becomes extreme).
--
-- Backward compatible: existing values (low / low-med / medium / med-high /
-- high) remain valid. Re-running the ALTER on an already-expanded ENUM is a
-- no-op in MySQL.
--
-- NOTE: NO apostrophes inside line comments above. The Joomla 2.5/3.x
-- splitSql parser treats apostrophes anywhere as string delimiters, which
-- breaks multi-statement files and prevents prefix substitution. Lesson
-- re-learned, again.
--

ALTER TABLE `#__slot_rtp`
  MODIFY COLUMN `volatility`
  ENUM('low','low-med','medium','med-high','high','very-high','extreme')
  DEFAULT NULL;
