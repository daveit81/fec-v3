<?php
defined('_JEXEC') or die('Restricted access');

/**
 * SEO meta overrides per-cid (brand pages — view=casino).
 *
 * Formato:
 *   <cid> => [
 *     'title' => '...',   // opzionale
 *     'desc'  => '...',   // opzionale
 *   ],
 *
 * Se un cid non è presente qui, il view usa i meta generati dinamicamente
 * dal nome del casinò (es. "Playamo Bonuses & Codes — Verified FXCheck™").
 *
 * Linee guida (dall'Excel title_surgery_FEC.xlsx):
 * - Title: 70-95 char. Pattern consigliato:
 *     [Brand] Casino Bonus Codes (April 2026) | Verified by FXCheck™ | FreeExtraChips
 * - Desc: 145-160 char. Pattern consigliato:
 *     Compare [Brand] bonus codes with verified wagering math and player reports.
 *     See FXCheck™ before you claim.
 * - Ricordati: SpinLogic Gaming (non RTG) se il casinò usa quel motore.
 * - NO virgolette doppie interne non escapate.
 * - Il mese "(April 2026)" va aggiornato mensilmente o, meglio,
 *   reso dinamico con una variabile.
 */

return [

    // ==== ESEMPIO ====
    1099 => [
         'title' => 'Ozwin Casino Bonus Codes & Free Spins (' . date("F Y") . ') | FreeExtraChips',
        'desc'  => 'Compare Ozwin Casino bonus codes and free spins with verified wagering, cashout caps, and SpinLogic games. See FXCheck™ player signals before you claim.',
     ],
     
    835 => [
         'title' => 'Playamo Casino Bonus Codes & Promo Code (' . date("F Y") . ') | FXCheck™ | FreeExtraChips',
        'desc'  => 'Compare Playamo Casino bonus codes and promo codes with verified wagering, cashout caps, and payment eligibility. See FXCheck™ before you claim.',
     ],
     
    804 => [
         'title' => 'Slots.lv No Deposit Bonus Codes & Free Spins (' . date("F Y") . ') | FXCheck™ Verified | FreeExtraChips',
        'desc'  => 'Compare Slots.lv bonus codes, no deposit offers and free cash with verified wagering, cashout caps, and SpinLogic games. See FXCheck™ before you claim.',
     ],
     
    1128 => [
         'title' => 'Ripper Casino No Deposit Bonus & Free Spins (' . date("F Y") . ') | FXCheck™ Verified | FreeExtraChips',
        'desc'  => 'Compare Ripper Casino no deposit bonus codes and free spins with verified wagering, cashout caps, and SpinLogic games. See FXCheck™ player signals before you claim.',
     ],
     
    829 => [
         'title' => 'FortuneJack Bonus Codes & Free Spins (' . date("F Y") . ') | FXCheck™ Verified | FreeExtraChips',
        'desc'  => 'Compare FortuneJack bonus codes and free spins with verified wagering, cashout caps, and BTC deposit math. See FXCheck™ player signals before you claim.',
     ],
     
    1101 => [
         'title' => 'Woo Casino Bonus Codes & Free Spins (' . date("F Y") . ') | Wagering & FXCheck™ | FreeExtraChips',
        'desc'  => 'Compare Woo Casino bonus codes and free spins with verified 40x wagering, cashable terms, and crypto vs fiat bonus paths. See FXCheck™ before you claim.',
     ],
     
    1100 => [
         'title' => 'Casino Rocket Bonus Codes & Free Spins (' . date("F Y") . ') | Wagering & FXCheck™ | FreeExtraChips',
        'desc'  => 'Compare Casino Rocket bonus codes and free spins with verified 45x wagering, cashable terms, and geo eligibility checks. See FXCheck™ before you claim.',
     ],
     
    1138 => [
         'title' => 'Mr.O Casino Bonus Codes & Free Chips (' . date("F Y") . ') | Wagering & FXCheck™ | FreeExtraChips',
        'desc'  => 'Compare Mr.O Casino bonus codes and free chips with 30x wagering on no-deposit offers, RTG SpinLogic games, and verified terms. See FXCheck™ before you claim.',
     ],
     
     
     


];