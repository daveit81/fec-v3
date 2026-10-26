--
-- com_authors V2.6.1 schema bump
-- 
-- Purpose: this file exists to bump the schema version recorded in #__schemas,
-- ensuring 2.6.0.sql gets re-executed on upgrade from any partial-2.6.0 state
-- (caused by the original 2.6.0 SQL split bug). The actual migration work is
-- in 2.6.0.sql which is now idempotent.
-- 
-- No-op statement (safe under any database state).
--

SELECT 1;
