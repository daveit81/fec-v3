{{-- Global search overlay, verbatim from templates/fecv2/index.php; driven by /js/fecv2.js (results from the legacy search endpoint until com_fecsearch is ported). --}}
<div class="fec-search" id="fec-search" role="dialog" aria-modal="true" aria-label="Global search" hidden>
  <div class="fec-search__backdrop" data-search-close></div>

  <div class="fec-search__panel" role="document">
    <div class="fec-search__top">
      <button class="fec-search__back" type="button" data-search-close aria-label="Close search">
        <span aria-hidden="true">←</span>
      </button>

      <div class="fec-search__field">
        <span class="fec-search__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="2"></path>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
          </svg>
        </span>

        <input class="fec-search__input"
               type="search"
               inputmode="search"
               autocomplete="off"
               placeholder="Search bonuses, casinos, guides…"
               aria-label="Search"
               data-search-input />

        <button class="fec-search__clear" type="button" data-search-clear aria-label="Clear" hidden>
          <span aria-hidden="true">×</span>
        </button>
      </div>

      <button class="fec-search__esc" type="button" data-search-close>ESC</button>
    </div>

    <div class="fec-search__popular" data-search-popular>
      <div class="fec-search__sub">Popular searches</div>

      <div class="fec-search__chips" data-search-popular-chips>
        <button class="fec-chip" type="button" data-search-suggest="no deposit">No Deposit</button>
        <button class="fec-chip" type="button" data-search-suggest="free spins">Free Spins</button>
        <button class="fec-chip" type="button" data-search-suggest="no wagering">No Wagering</button>
        <button class="fec-chip" type="button" data-search-suggest="crypto">Crypto</button>
        <button class="fec-chip" type="button" data-search-suggest="exclusive">Exclusive</button>
        <button class="fec-chip" type="button" data-search-suggest="high roller">High Roller</button>
        <button class="fec-chip" type="button" data-search-suggest="new casinos">New Casinos</button>
      </div>
    </div>

    <div class="fec-search__recent" data-search-recent hidden>
      <div class="fec-search__sub">
        Recent searches
        <button class="fec-search__clearrec" type="button" data-search-recent-clear>Clear</button>
      </div>
      <div class="fec-search__chips" data-search-recent-chips></div>
    </div>

    <div class="fec-search__body" data-search-body>
      <div class="fec-search__state" data-search-loading hidden>
        <div class="fec-skel">
          <div class="fec-skel__line"></div>
          <div class="fec-skel__line"></div>
          <div class="fec-skel__line"></div>
        </div>
      </div>

      <div class="fec-search__state" data-search-empty hidden>
        <div class="fec-search__emptytitle">No results</div>
        <div class="fec-search__emptyhint">Try a casino name, “no wagering”, or a bonus code.</div>
      </div>

      <div class="fec-search__groups" data-search-groups hidden></div>
    </div>
  </div>
</div>
