<?php

namespace App\Fec\Url\Patterns;

use App\Fec\Url\ResolvedUrl;
use App\Fec\Url\UrlPattern;
use App\Fec\Url\UrlRegistry;
use App\Models\Fec\Bonus;

/**
 * /bonuses/<casino-slug>/<offer-slug>-id<bid>
 *
 * The single-bonus page (com_bonuses view=details). The legacy router builds
 * these URLs from the casino list URL (/bonuses/<casino-slug>, which IS in the
 * registry as view=casino&cid=N) plus a slug of the offer and the bid; it
 * never writes them to jos_sh404sef_urls, so the registry has 7.7k historical
 * "details" rows but none of the current -id<bid> form. The bid is the only
 * part that matters for the lookup: cid and type come from jos_bonuses, as
 * the legacy query string carried them (view=details&type=..&bid=..&cid=..).
 *
 * meta.casino_match tells whether the casino slug in the path is the one the
 * registry maps to the bonus's own casino (a moved bonus or a hand-made link
 * gives false): the handler decides what to do with it, the resolver reports it.
 */
final class BonusDetails implements UrlPattern
{
    public const REGEX = '#^bonuses/([a-z0-9][a-z0-9-]*)/([a-z0-9][a-z0-9-]*)-id([0-9]+)/?$#i';

    public function name(): string
    {
        return 'bonus_details';
    }

    public function match(string $path, UrlRegistry $registry): ?ResolvedUrl
    {
        $path = UrlRegistry::normalize($path);
        if (! preg_match(self::REGEX, $path, $m)) {
            return null;
        }

        [, $casinoSlug, $offerSlug, $bid] = $m;
        $bid = (int) $bid;
        $source = 'pattern:'.$this->name();
        $meta = ['bid' => $bid, 'casino_slug' => strtolower($casinoSlug), 'offer_slug' => strtolower($offerSlug)];

        $bonus = Bonus::query()->where('bid', $bid)->first(['bid', 'cid', 'type', 'status']);
        if (! $bonus) {
            return new ResolvedUrl('miss', $path, [], null, null, null, null, null, $source, $meta + ['bonus_exists' => false]);
        }

        // The casino list URL is a registry page: view=casino&cid=N.
        $casino = $registry->lookup('bonuses/'.strtolower($casinoSlug));
        $casinoCid = $casino->isPage() && $casino->option() === 'com_bonuses' && $casino->view() === 'casino'
            ? $casino->int('cid')
            : null;

        $type = strtolower(trim((string) $bonus->type));
        $query = [
            'option' => 'com_bonuses',
            'view' => 'details',
            'type' => $type,
            'bid' => (string) $bid,
            'cid' => (string) $bonus->cid,
        ];
        $newurl = 'index.php?option=com_bonuses&view=details&type='.rawurlencode($type).'&bid='.$bid.'&cid='.(int) $bonus->cid;

        return new ResolvedUrl('page', $path, $query, '/'.$path, null, null, null, $newurl, $source, $meta + [
            'bonus_exists' => true,
            'bonus_status' => (string) $bonus->status,
            'casino_cid_from_path' => $casinoCid,
            'casino_match' => $casinoCid !== null && $casinoCid === (int) $bonus->cid,
        ]);
    }
}
