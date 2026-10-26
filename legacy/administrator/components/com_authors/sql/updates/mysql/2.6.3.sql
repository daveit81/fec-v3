--
-- com_authors V2.6.3 schema bump
--
-- No schema changes. Triggers Joomla to re-run prior migrations on systems
-- where the V2.6.2 install failed mid-SQL due to a comment containing a
-- semicolon (Joomla splitSql does not skip line comments).
--

SELECT 1;
