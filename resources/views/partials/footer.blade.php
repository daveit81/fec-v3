{{--
    Footer of templates/fecv2/index.php, verbatim. The newsletter block that
    the legacy lib renders first inside the footer (fec_render_email_capture_block
    'footer', compact) is deliberately absent: its form posts to the legacy
    /subscribe.php with a server-side signature and comes back with the
    newsletter port. Slot kept so the position is explicit.
--}}
@php($social = config('fec.social'))
<footer class="fec-footer" role="contentinfo">
  <div class="fec-footer__inner fec-container">
@yield('footer_newsletter')

    <div class="fec-footer__top">
      <a class="fec-footer__brand" href="/">
        <span class="fec-footer__mark" aria-hidden="true"></span>
        <span class="fec-footer__brandtxt">
          <span class="fec-footer__title">
            FreeExtra<span class="fec-footer__dot" aria-hidden="true">•</span>Chips™
          </span>
          <span class="fec-footer__sub">Verified bonus listings &amp; reviews</span>
        </span>
      </a>

      <div class="fec-footer__cols">

        <div class="fec-footer__col">
          <div class="fec-footer__h">Casinos</div>
          <a class="fec-footer__a" href="/online-casinos">Casino Reviews</a>
          <a class="fec-footer__a" href="/online-casinos?sort=newest">New Casinos</a>
          <a class="fec-footer__a" href="/online-casinos?filters=established_5y">Established Casinos</a>
          <a class="fec-footer__a" href="/online-casinos/fast-payouts">Fast Payouts</a>
          <a class="fec-footer__a" href="/online-casinos?filters=crypto">Crypto Friendly</a>
          <a class="fec-footer__a" href="/tools/casino-comparator">Casino Comparator</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">Bonuses</div>
          <a class="fec-footer__a" href="/bonuses/">All Bonuses</a>
          <a class="fec-footer__a" href="/bonuses/no-deposit-casinos">No Deposit</a>
          <a class="fec-footer__a" href="/bonuses/free-spins/">Free Spins</a>
          <a class="fec-footer__a" href="/bonuses/free-spins/no-deposit/">Free Spins No Deposit</a>
          <a class="fec-footer__a" href="/bonuses/high-roller-bonuses">High Roller</a>
          <a class="fec-footer__a" href="/tools/wagering-calculator">Wagering Calculator</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">Transparency</div>
          <a class="fec-footer__a" href="/methodology">How we verify</a>
          <a class="fec-footer__a" href="/methodology/rtp-verification">RTP methodology</a>
          <a class="fec-footer__a" href="/methodology/fxcheck">FXCheck™ methodology</a>
          <a class="fec-footer__a" href="/methodology/editorial-standards">Editorial Standards</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">About</div>
          <a class="fec-footer__a" href="/who-is-frank">Who is Frank?</a>
          <a class="fec-footer__a" href="/authors">Editorial Team</a>
          <a class="fec-footer__a" href="/contactus/">Contact</a>

          <div class="fec-footer__mini">
            <div class="fec-footer__badge">18+</div>
            <span class="fec-footer__muted">Please gamble responsibly.</span>
          </div>

          <a class="fec-footer__a" href="https://www.begambleaware.org/" target="_blank" rel="noopener">BeGambleAware.org</a>
        </div>

      </div>
    </div>

    <div class="fec-footer__social" aria-label="Follow FreeExtraChips">
      <a class="fec-footer__soc" href="{{ $social['x'] }}" target="_blank" rel="noopener" aria-label="FreeExtraChips on X">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>X</span>
      </a>
      <a class="fec-footer__soc" href="{{ $social['telegram'] }}" target="_blank" rel="noopener" aria-label="FreeExtraChips on Telegram">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M22 2 11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 2 15 22l-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Telegram</span>
      </a>
      <a class="fec-footer__soc" href="{{ $social['discord'] }}" target="_blank" rel="noopener" aria-label="FreeExtraChips on Discord">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 12h4M8 10v4M15 13h.01M18 11h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.152A4 4 0 0 0 17.32 5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Discord</span>
      </a>
    </div>
    <style>
      .fec-footer__social{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 6px}
      .fec-footer__soc{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border:1px solid rgba(28,31,36,.18);border-radius:999px;font-size:13px;line-height:1;color:inherit;text-decoration:none}
      .fec-footer__soc svg{display:block}
      .fec-footer__soc:hover{border-color:currentColor}
    </style>

    <div class="fec-footer__bottom">
      <div class="fec-footer__muted">© {{ date('Y') }} FreeExtraChips. Content may change; always check terms at the casino.</div>
      <div class="fec-footer__muted">FXCheck™ is based on player reports (Yes/No).</div>
    </div>

  </div>
</footer>
