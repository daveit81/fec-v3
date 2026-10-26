<?php
defined('_JEXEC') or die('Restricted access');

/**
 * Intro editoriali per brand page (view=casino).
 *
 * Formato:
 *   <cid> => '<section class="fec-intro" aria-label="...">...</section>',
 *
 * Regole:
 * - HTML pulito: solo <section>, <p>, <strong>, <a>, <span>, <em>.
 * - Niente <h1> (già presente in pagina).
 * - Niente <script>, <style>, <iframe>.
 * - Usa <strong> per valori chiave (wagering, cashout caps, provider).
 * - Lunghezza: 2-4 paragrafi, ~400-700 char totali.
 * - SpinLogic Gaming (non RTG) se il casinò usa quel motore.
 * - Link interni relativi (/bonuses/..., /how-we-verify, etc.).
 *
 * NOTA: L'HTML qui dentro viene stampato raw, senza escape.
 *       È editoriale controllato, NON input utente.
 *       Non mettere MAI dati da utenti/form in questo file.
 */

return [

    // ==== ESEMPIO: Ozwin ====
    1099 => '<section class="fec-intro" aria-label="About Ozwin Casino bonuses">
<p>
  Ozwin Casino runs on <strong>SpinLogic Gaming</strong> (formerly RTG),
  so the free spins below are tied to familiar SpinLogic titles — Fortunate
  Buddha, Cash Bandits 3, T-Rex II, Plentiful Treasure. Wagering is typical
  SpinLogic: <strong>60x(b)</strong> on no-deposit offers,
  <strong>30x(b+d)</strong> on match deposits. Cashout caps vary by offer
  (check each bonus&apos;s Details page), and they often matter more than the
  wagering itself for actual profitability.
</p>
      <p class="fec-intro__links">
        <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
        <span class="fec-dot"></span>
        <a href="/how-we-verify">How we verify</a>
        <span class="fec-dot"></span>
        <a href="/bonuses/free-spins/no-deposit/">Compare free spins no deposit</a>
      </p>
    </section>',
    

    835 => '<section class="fec-intro" aria-label="About Playamo Casino bonuses">
  <p>
    Playamo Casino runs a <strong>multi-provider software stack</strong> —
    you&apos;ll see NetEnt titles like Starburst, Gonzo&apos;s Quest and Steam Tower in
    the free spins below, alongside games from Betsoft, Microgaming, Pragmatic
    Play, Amatic and Play&apos;n GO. Wagering is consistent across the board:
    <strong>50x(b)</strong> on free spins, <strong>50x(b+d)</strong> on the
    welcome match. The welcome package uses a single code (<strong>FIRSTDEP</strong>)
    for both the match deposit and the 100 bonus spins. Before you claim,
    verify the max cashout and payment method eligibility in each offer&apos;s
    Details — Playamo&apos;s terms vary by bonus type more than by slot.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/free-spins/">Compare free spins bonuses</a>
  </p>
</section>',

    804 => '<section class="fec-intro" aria-label="About Slots.lv bonuses">
  <p>
    Slots.lv runs on <strong>SpinLogic Gaming</strong> (formerly RTG)
    and is part of the Bodog group, alongside Bovada and Cafe Casino —
    so the slot library and bonus structure mirror what you&apos;ll see at
    those sister sites. <strong>Wagering varies sharply by bonus type</strong>:
    the $22 no-deposit code carries 100x(b), the $25 free cash drops to
    60x(b), the 30 free spins on Golden Buffalo run a player-friendly
    35x(b), the slots welcome matches (200% up to $1,000 and 200% up to
    $3,000) sit at 35x(b+d), while the blackjack match runs the highest
    at 120x(b+d). <strong>Slots.lv is US-only</strong> — accounts from
    outside the United States are not eligible. Before you claim, verify
    the cashout cap and currency settings (USD or BTC) in each offer&apos;s Details.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/crypto">Crypto-friendly bonuses</a>
  </p>
</section>',

    1128 => '<section class="fec-intro" aria-label="About Ripper Casino bonuses">
  <p>
    Ripper Casino runs primarily on <strong>SpinLogic Gaming</strong> (formerly RTG)
    and is part of the <strong>Total Network</strong>, alongside Uptown Aces,
    Uptown Pokies, Red Stag, SlotsPlus, Las Vegas USA and Fair Go — so the bonus
    structure and platform feel will be familiar if you&apos;ve played at any sister
    site. <strong>Wagering varies by offer</strong>: free spins on featured slots
    typically sit between 30x and 60x on bonus winnings, while no-deposit
    credits run on the higher end. SpinLogic-style cashout caps apply on most
    free credits, so verify the cap and game eligibility in each offer&apos;s Details
    — the cashout cap matters more than the wagering for actual profitability.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/free-spins/no-deposit/">Compare free spins no deposit</a>
  </p>
</section>',

    829 => '<section class="fec-intro" aria-label="About FortuneJack bonuses">
  <p>
    FortuneJack is one of the longest-running <strong>crypto-native casinos</strong>
    on the market, accepting Bitcoin, Ethereum, Litecoin, Dogecoin and several
    other altcoins as primary payment methods rather than fiat. The slot
    library aggregates titles from <strong>NetEnt, Pragmatic Play, Play&apos;n GO,
    Endorphina and Microgaming</strong>, alongside provably fair in-house games
    that are common signature features of crypto-first operators. <strong>Wagering
    varies by offer</strong>: deposit matches typically sit around 40x(b+d) and
    free spins around 50x(b), but some active free spins offers list wagering
    as "Not stated" — verify the exact terms in each offer&apos;s Details before
    claiming. Bonuses are denominated in crypto rather than USD, which can affect
    cashout math depending on coin volatility at the time of withdrawal.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/crypto">Compare crypto bonuses</a>
  </p>
</section>',

    1101 => '<section class="fec-intro" aria-label="About Woo Casino bonuses">
  <p>
    Woo Casino is a multi-currency operator accepting both <strong>fiat
    (EUR, USD)</strong> and <strong>crypto (Bitcoin, Ethereum, Litecoin
    and others)</strong>, with separate welcome paths depending on which
    deposit method you choose: a 100% fiat match (lower amount, smaller
    risk) or a 200% crypto match (higher amount, but the bonus winnings
    are <strong>non-cashable until specific terms are met</strong> —
    verify in Details before claiming). Wagering is consistent at 40x
    across all active offers — slightly below the 50x category average
    that competitors typically apply. The slot library aggregates titles
    from <strong>Pragmatic Play, NetEnt, Microgaming, Quickspin, Yggdrasil,
    Push Gaming and BGaming</strong>. Before you claim, verify cashable
    status and currency match in each offer&apos;s Details.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/crypto">Compare crypto bonuses</a>
  </p>
</section>',

    1100 => '<section class="fec-intro" aria-label="About Casino Rocket bonuses">
  <p>
    Casino Rocket is a multi-currency operator accepting both <strong>fiat
    (EUR, USD, AUD, CAD)</strong> and <strong>crypto (Bitcoin, Ethereum,
    Litecoin and others)</strong>, part of the <strong>Samurai Partners
    network</strong> alongside <a href="https://www.freeextrachips.com/online-casinos/21bit-casino">21Bit</a>, Ritzo Casino, All Star, Spin Samurai
    and Wild Fortune — so the platform feel and bonus structure will be
    familiar if you&apos;ve played at any sister site. <strong>Wagering is
    consistent at 45x across all active offers</strong>, in line with
    Samurai Partners&apos; typical structure but still below the 50x category
    average. The slot library aggregates titles from <strong>Pragmatic
    Play, NetEnt, Microgaming, Quickspin, Yggdrasil, Push Gaming and
    BGaming</strong>. <strong>Some offers are geo-restricted</strong> —
    verify your country is eligible in each offer&apos;s Details before claiming.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/crypto">Compare crypto bonuses</a>
  </p>
</section>',

    1138 => '<section class="fec-intro" aria-label="About Mr.O Casino bonuses">
  <p>
    Mr.O Casino runs entirely on <strong>SpinLogic Gaming</strong> (formerly RTG)
    and is a <strong>multi-currency operator</strong> accepting both
    <strong>fiat (USD)</strong> and <strong>crypto (Bitcoin, Ethereum,
    Litecoin and Tether USDT)</strong>, Curaçao licensed and operating
    since 2023. The platform feel will be familiar if you&apos;ve played
    at other RTG-powered sites — same slot library (around 300 titles),
    same bonus structure, same lack of live dealer section.
    <strong>Wagering varies by offer</strong>: no-deposit chips typically
    sit at 30x on bonus, while deposit matches run at 45x on bonus + deposit
    — both within reasonable territory for SpinLogic-driven operators.
    SpinLogic-style cashout caps apply on most free credits, so verify
    the cap and game eligibility in each offer&apos;s Details — the
    cashout cap matters more than the wagering for actual profitability.
  </p>
  <p class="fec-intro__links">
    <a href="/bonuses/no-deposit-casinos#red-flags">See red flag patterns</a>
    <span class="fec-dot"></span>
    <a href="/how-we-verify">How we verify</a>
    <span class="fec-dot"></span>
    <a href="/bonuses/crypto">Compare crypto bonuses</a>
  </p>
</section>',




];