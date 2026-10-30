<?php

namespace App\Fec\Desk;

/**
 * The jackpot desk of v3 (phase 5), rewritten rather than ported. com_jackpots'
 * desk on Joomla (view=dashboard: x-editable cells posted to task=actions.edit and
 * actions.editsoft, scripts loaded from vitalets.github.io) wrote whatever column
 * of jos_jackpots it was sent, with no login until the gate of 30 Oct 2026 and no
 * token. What it edited, checked against the code that reads it (30 Oct 2026):
 *
 *   - name, currency, soft: rewritten every 15 minutes by fec:jackpots:update from
 *     the tracker tables (JackpotsFold::columns()), so an edit never lasted;
 *   - logo: read by nothing (the jackpot pages took /components/com_jackpots/media/
 *     logo/<jid>.png);
 *   - jos_providers.restrictions: read only by the jackpot pages of /jpots/, which
 *     answer 500 today and 410 after the root switch;
 *   - published: the one that matters, the jackpot section of the casino reviews
 *     (fec_get_casino_jackpots(): published = 1 only).
 *
 * So the closed list is one column: published, 0 or 1.
 */
final class JackpotsDesk
{
    /** Columns of jos_jackpots the desk writes. */
    public const EDITABLE = ['published'];

    /** Rows per page of the list. */
    public const PER_PAGE = 100;

    /** The published value of a toggle form, or null when it is not 0 or 1. */
    public static function published(mixed $value): ?int
    {
        $value = is_string($value) || is_int($value) ? (string) $value : '';

        return $value === '0' || $value === '1' ? (int) $value : null;
    }

    /**
     * A jid as the tracker writes them (the gameId of its table in freeextr_jptest,
     * "<cid>_<game>"): any non-empty string the column can hold. It travels in the
     * form's body and the query binds it, so no spelling is refused here.
     */
    public static function validJid(mixed $jid): bool
    {
        return is_string($jid) && $jid !== '' && strlen($jid) <= 191 && ! preg_match('/[\x00-\x1F]/', $jid);
    }

    /**
     * The old dashboard's "Last Up Diff": Good under 12 hours, Fair up to a day, Poor
     * beyond (or never updated).
     */
    public static function freshness(?string $lastUpdate, int $now): string
    {
        $ts = $lastUpdate ? strtotime($lastUpdate) : false;
        if ($ts === false) {
            return 'Poor';
        }
        $diff = $now - $ts;
        if ($diff > 86400) {
            return 'Poor';
        }

        return $diff >= 43200 ? 'Fair' : 'Good';
    }
}
