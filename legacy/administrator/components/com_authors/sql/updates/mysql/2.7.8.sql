--
-- com_authors V2.7.8 schema bump
--
-- No structural schema changes. Updates the methodology_topics registry to
-- reflect the FRank 2.0 refresh (May 2026) and reorders the four methodology
-- cards on the /methodology hub.
--
-- Idempotent: re-running produces the same end state.
--

-- FRank record — align with the new /who-is-frank page and FRank engine 2.0
UPDATE `#__methodology_topics`
SET status            = 'external',
    title             = 'FRank — How We Rank Casinos',
    tagline           = 'FreeExtraChips ranking algorithm, in development since 2007',
    short_description = 'FRank is the rule-based scoring system that orders every casino in our directory. Version 2.0 (May 2026) enumerates each input, removes legacy opacity, and integrates the FXCheck player-reported layer.',
    external_url      = '/who-is-frank',
    author_slug       = '',
    reviewer_slug     = '',
    version           = '2.0',
    last_updated      = '2026-05-28',
    sort_order        = 1,
    updated_at        = NOW()
WHERE slug = 'frank';

-- Order the four cards on /methodology
-- FRank first (flagship), then Editorial Standards (foundational policy),
-- RTP Verification (data methodology), FXCheck (newest player layer).
UPDATE `#__methodology_topics` SET sort_order = 2 WHERE slug = 'editorial-standards';
UPDATE `#__methodology_topics` SET sort_order = 3 WHERE slug = 'rtp-verification';
UPDATE `#__methodology_topics` SET sort_order = 4 WHERE slug = 'fxcheck';
