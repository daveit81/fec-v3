{{-- Cookie dialog, verbatim from templates/fecv2/index.php; driven by /js/fecv2.js. --}}
<div class="fec-cookie" role="dialog" aria-modal="true" aria-labelledby="fec-cookie-title" hidden>
  <div class="fec-cookie__inner">
    <div class="fec-cookie__text">
      <strong id="fec-cookie-title">We use cookies</strong>
      <p>
        We use essential cookies to make the site work.
        With your consent, we may also use analytics cookies to improve FreeExtraChips.
      </p>
    </div>

    <div class="fec-cookie__actions">
      <button class="fec-btn fec-btn--primary" data-cookie="accept">Accept</button>
      <button class="fec-btn fec-btn--ghost" data-cookie="settings" aria-expanded="false" aria-controls="fec-cookie-settings">
        Settings
      </button>
      <button class="fec-link" data-cookie="reject">Reject non-essential</button>
    </div>

    <div class="fec-cookie__settings" id="fec-cookie-settings" hidden>
      <div class="fec-cookie__row">
        <div class="fec-cookie__rowtext">
          <div class="fec-cookie__rowtitle">Essential</div>
          <div class="fec-cookie__rowdesc">Always on. Needed for the site to work.</div>
        </div>
        <div class="fec-cookie__pill">On</div>
      </div>

      <div class="fec-cookie__row">
        <div class="fec-cookie__rowtext">
          <div class="fec-cookie__rowtitle">Analytics</div>
          <div class="fec-cookie__rowdesc">Helps us understand usage and improve pages.</div>
        </div>

        <label class="fec-switch">
          <input type="checkbox" id="fec-cookie-analytics" />
          <span class="fec-switch__ui" aria-hidden="true"></span>
          <span class="sr-only">Enable analytics cookies</span>
        </label>
      </div>

      <div class="fec-cookie__save">
        <button class="fec-btn fec-btn--primary" data-cookie="save">Save settings</button>
      </div>
    </div>
  </div>
</div>
