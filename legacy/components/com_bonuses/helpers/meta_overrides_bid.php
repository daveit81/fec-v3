<?php
defined('_JEXEC') or die('Restricted access');

/**
 * SEO meta overrides per-bid.
 *
 * Formato:
 *   <bid> => [
 *     'title' => '...',   // opzionale
 *     'desc'  => '...',   // opzionale
 *   ],
 *
 * Se un bid non è presente qui, il template usa i meta generati dinamicamente.
 * Se è presente ma ha solo 'title' (o solo 'desc'), sovrascrive solo quello.
 *
 * Linee guida:
 * - Title: 60-95 char
 * - Desc: 145-160 char
 * - NO virgolette doppie interne non escapate
 * - Ricordati: SpinLogic Gaming (non RTG)
 */

return [

    // ==== ESEMPIO ====
    8676 => [
      'title' => '7Bit Casino 75 Free Spins (' . date("F Y") . ') | Wagering & FXCheck™ | FreeExtraChips',
       'desc'  => "Compare 7Bit Casino's 75 free spins offer with verified wagering, cashout caps, and crypto deposit math. See FXCheck™ before you claim.",
       ],
       
    8033 => [
      'title' => 'Avantgarde Casino $50 No Deposit Bonus (' . date("F Y") . ') | Wagering & FXCheck™ | FreeExtraChips',
       'desc'  => "Compare Avantgarde Casino's $50 no deposit bonus with verified 35x wagering, $100 cashout cap, and 7-day clearance window. See FXCheck™ before you claim.",
       ],

];