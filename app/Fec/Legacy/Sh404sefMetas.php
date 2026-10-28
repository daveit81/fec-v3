<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Document;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The "Title and metas" of sh404SEF (jos_sh404sef_metas), as the live site
 * applies them.
 *
 * The redazione set them per page in sh404SEF's back end, years before the
 * meta_overrides_*.php files of com_bonuses: 810 rows, 49 for com_bonuses
 * URLs (27 Oct 2026), none for com_authors. sh404SEF applies them on the
 * finished page, after the component and the template: a non-empty title,
 * description, keywords or robots replaces the page's own, a non-empty
 * canonical replaces the canonical link. The row is the one whose newurl is
 * the page's own non-SEF URL, the newurl of the registry row the requested
 * path resolved to (rank 0), compared by the database as sh404SEF compared
 * it; a row keyed on another spelling of the same query never applies (17 of
 * the 49 on the live site). The Open Graph fields of the table are left to
 * the lib's own social metas (og_enable 2 = sh404SEF's global setting, off).
 */
final class Sh404sefMetas
{
    public static function apply(Document $doc, ?string $newurl): void
    {
        $row = self::row($newurl);
        if ($row === null) {
            return;
        }

        if (trim((string) $row->metatitle) !== '') {
            $doc->setTitle((string) $row->metatitle);
        }
        if (trim((string) $row->metadesc) !== '') {
            $doc->setDescription((string) $row->metadesc);
        }
        if (trim((string) $row->metakey) !== '') {
            $doc->setMetaData('keywords', (string) $row->metakey);
        }
        if (trim((string) $row->metarobots) !== '') {
            $doc->setMetaData('robots', (string) $row->metarobots);
        }
        if (trim((string) $row->canonical) !== '') {
            $doc->headLinks = array_values(array_filter($doc->headLinks, fn ($l) => $l['relation'] !== 'canonical'));
            $doc->addHeadLink(trim((string) $row->canonical), 'canonical');
        }
    }

    /**
     * The row of the page's own non-SEF URL, if the redazione set one
     * (metatitle, metadesc, metakey, metarobots, canonical).
     */
    public static function row(?string $newurl): ?object
    {
        $newurl = trim((string) $newurl);
        if ($newurl === '') {
            return null;
        }

        try {
            return DB::connection('fec')->table('sh404sef_metas')
                ->where('newurl', $newurl)
                ->orderBy('id')
                ->first(['metatitle', 'metadesc', 'metakey', 'metarobots', 'canonical']);
        } catch (Throwable $e) {
            error_log('sh404sef metas lookup failed: '.$e->getMessage());

            return null;
        }
    }
}
