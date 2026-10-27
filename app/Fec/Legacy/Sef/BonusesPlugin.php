<?php

namespace App\Fec\Legacy\Sef;

use Illuminate\Support\Facades\DB;

/**
 * legacy/components/com_bonuses/sef_ext/com_bonuses.php, branch by branch.
 *
 * The plugin reads the link's variables ($view, $type, $subtype, $bid, $cid,
 * $filter, $fq, $meth, $curr), the bonus row (label, bonus, currency) and the
 * casino article's title (jos_content, id = cid), and fills $title with the
 * segments sefGetLocation() slugifies and joins: "bonuses", "", then "/" and
 * the view's own pieces. PHP 5.6 semantics are kept where the plugin relied
 * on them: an undefined variable is an empty string, an unknown type label
 * is an empty segment.
 */
final class BonusesPlugin implements SefPlugin
{
    /** $bonustype of helpers/bonustypesarray.php (the file sets variables at file scope, so the map is copied). */
    public const TYPE_LABELS = [
        'nd' => 'No Deposit Bonus', 'nds' => 'No Deposit Spins', 'fs' => 'Free Spins', 'fsx' => 'Extra Spins',
        'fp' => 'Free Play', 'wbp' => 'Welcome Bonus', 'su' => '1st Deposit Match', '2d' => '2nd Deposit Match',
        '3d' => '3rd Deposit Match', '4d' => '4th Deposit Match', '5d' => '5th Deposit Match', '6d' => '6th Deposit Match',
        '7d' => '7th Deposit Match', '8d' => '8th Deposit Match', '9d' => '9th Deposit Match', '10d' => '10th Deposit Match',
        'hr' => 'High Roller Bonus', 'sl' => 'Slots Bonus', 'bj' => 'BlackJack Bonus', 'vp' => 'Video Poker Bonus',
        'rl' => 'Reload Bonus', 'cb' => 'CashBack Bonus', 'rb' => 'Rakeback Bonus', 'db' => 'Deposit Bonus',
        'fc' => 'Free Cash', 'mon' => 'Monday Bonus', 'tue' => 'Tuesday Bonus', 'wed' => 'Wednesday Bonus',
        'thu' => 'Thursday Bonus', 'fri' => 'Friday Bonus', 'sat' => 'Saturday Bonus', 'sun' => 'Sunday Bonus',
        'wb' => 'Weekend Bonus', 'wkb' => 'Weekly Bonus', 'crypto' => 'Crypto Bonuses', 'sweeps' => 'Sweeps Casino Bonuses',
    ];

    /** Views the plugin never SEFs (the desk): the link stays index.php?... */
    private const RAW_VIEWS = ['add', 'edit', 'manage', 'clicks', 'home'];

    public static function title(array &$vars): ?array
    {
        if (isset($vars['task'])) {
            return null;
        }
        $view = (string) ($vars['view'] ?? '');
        if (in_array($view, self::RAW_VIEWS, true)) {
            return null;
        }

        $type = $vars['type'] ?? null;
        $label = $type === null ? null : (self::TYPE_LABELS[$type] ?? null);
        $title = ['bonuses', ''];

        switch ($view) {
            case 'show':
                switch ($type) {
                    case 'fs':
                        $title[] = '/';
                        $title[] = (string) $label;
                        switch ($vars['subtype'] ?? null) {
                            case 'nd':
                                $title[] = '/';
                                $title[] = 'no-deposit/';
                                break;
                            case 'dr':
                                $title[] = '/';
                                $title[] = 'deposit-required/';
                                break;
                        }
                        break;
                    case 'nd':
                        $title[] = '/';
                        $title[] = 'no-deposit-casinos';
                        break;
                    case 'crypto':
                        // URL /bonuses/crypto, stored as crypto=yes (not type=crypto)
                        $vars['crypto'] = 'yes';
                        unset($vars['type']);
                        $title[] = '/';
                        $title[] = 'crypto';
                        break;
                    case 'hr':
                        $title[] = '/';
                        $title[] = 'high-roller-bonuses';
                        break;
                    case 'sl':
                        $title[] = '/';
                        $title[] = 'slots-bonuses';
                        break;
                    case 'bj':
                        $title[] = '/';
                        $title[] = 'blackjack-bonuses';
                        break;
                    case 'vp':
                        $title[] = '/';
                        $title[] = 'video-poker-bonuses';
                        break;
                    default:
                        $title[] = '/';
                        $title[] = (string) $label;
                        break;
                }
                break;

            case 'details':
                $bonus = self::bonus($vars['bid'] ?? null);
                switch ($type) {
                    case 'fs':
                    case 'fp':
                        $currttl = '';
                        $percttl = '';
                        break;
                    case 'nd':
                    case 'fc':
                        $currttl = (string) ($bonus['currency'] ?? '');
                        $percttl = '';
                        break;
                    default:
                        $currttl = '';
                        $percttl = 'percent';
                        break;
                }
                $title[] = '/';
                $title[] = self::casinoTitle($vars['cid'] ?? null).'/';
                $title[] = $currttl.(string) ($bonus['bonus'] ?? '').$percttl.'-'.(string) ($bonus['label'] ?? '').'-id'.(string) ($bonus['bid'] ?? '');
                break;

            case 'wcalc':
                return ['tools', '', '/', 'wagering-calculator'];

            case 'cmp':
                return ['tools', '', '/', 'casino-comparator'];

            case 'casino':
                $title[] = '/';
                $title[] = self::casinoTitle($vars['cid'] ?? null).'/';
                break;

            case 'ads':
                $title[] = '/';
                $title[] = 'ctr/'.(string) ($vars['cid'] ?? '').'/'.(string) ($vars['bid'] ?? '').'/';
                break;

            case 'tc':
                $title[] = '/';
                $title[] = 'tc/'.(string) ($vars['cid'] ?? '').'/'.(string) ($vars['bid'] ?? '').'/';
                break;

            case 'search':
                $filter = (string) ($vars['filter'] ?? '');
                $fq = (string) ($vars['fq'] ?? '');
                switch ($filter) {
                    case 'software':
                    case 'exclusive':
                    case 'cashable':
                    case 'allowed-countries':
                    case 'restricted-countries':
                    case 'code':
                    case 'currency':
                        $url = $filter.'/'.$fq.'/';
                        break;
                    case 'wr':
                        $url = $filter.'/'.$fq.(string) ($vars['meth'] ?? '').'/';
                        break;
                    default:
                        $curr = (string) ($vars['curr'] ?? '');
                        $url = $filter.'/'.($curr === '' ? 'usd' : $curr).$fq.'/';
                        break;
                }
                // "if (isset($filter) && !isset($type) || empty($type))": a filter without a type
                if ((isset($vars['filter']) && $type === null) || empty($type)) {
                    $title[] = '/';
                    $title[] = $url;
                } elseif (isset($vars['filter'])) {
                    switch ($type) {
                        case 'nd':
                            $title[] = '/';
                            $title[] = 'no-deposit-casinos/'.$url.'/';
                            break;
                        case 'hr':
                            $title[] = '/';
                            $title[] = 'high-roller-bonuses/'.$url.'/';
                            break;
                        default:
                            $title[] = '/';
                            $title[] = (string) $label.'/'.$url.'/';
                            break;
                    }
                }
                break;

            default:
                // an unknown view (authors, list...): "bonuses/" alone, as the plugin left $title
                break;
        }

        return $title;
    }

    /** @return array{bid: int, label: string, bonus: string, currency: string, type: string}|null */
    private static function bonus(mixed $bid): ?array
    {
        if ($bid === null || ! is_numeric($bid)) {
            return null;
        }
        $row = DB::connection('fec')->table('bonuses')->where('bid', (int) $bid)->first(['bid', 'label', 'bonus', 'currency', 'type']);

        return $row === null ? null : (array) $row;
    }

    /** JTable::getInstance('content')->load($cid)->get('title'): the casino article's title. */
    private static function casinoTitle(mixed $cid): string
    {
        if ($cid === null || ! is_numeric($cid)) {
            return '';
        }

        return (string) (DB::connection('fec')->table('content')->where('id', (int) $cid)->value('title') ?? '');
    }
}
