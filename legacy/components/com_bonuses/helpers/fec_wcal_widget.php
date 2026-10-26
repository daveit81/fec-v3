<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * FEC Wagering Calculator widget (F2 standalone page + F3 details embed)
 *
 * Standalone helper by design: fec_common_lib.php is NOT touched and NOT
 * required. PHP 5.6 compatible. Assets live in com_bonuses css/ and js/.
 *
 * Usage:
 *   require_once JPATH_COMPONENT . DS . 'helpers' . DS . 'fec_wcal_widget.php';
 *   fec_wcal_add_assets();
 *   fec_wcal_render_widget(array('wr' => '30', 'wr-meth' => 'bd'));
 *
 * Every attribute is optional; the widget falls back to manual input for
 * anything not supplied. max-cashout must only be passed together with a
 * classified max-cashout-kind (abs | x_dep | x_bonus): unclassified legacy
 * caps are never prefiled as if they were absolute amounts.
 */

// 1.6.0 (2026-10-21): the "Game contribution" select follows the bonus terms.
// The details view passes the allowed and restricted game lists (games-ok /
// games-no, labels separated by "|"); a contribution category the terms
// exclude (table games, live casino, video poker ...) is shown disabled as
// "excluded by the terms", and a hint under the select repeats both lists.
// The default table (100/50/20/10/5) stays for whatever the terms allow,
// because the terms state what counts, not at what rate.
if (!defined('FEC_WCAL_VERSION')) {
  define('FEC_WCAL_VERSION', '1.6.0');
}

if (!function_exists('fec_wcal_contrib_category')) {
  /**
   * Contribution category of a game label: 'slots', 'vp', 'live', 'table'
   * or 'other'. Order matters: "Video Poker" before the poker/table check,
   * "Live Blackjack" is live, not table.
   */
  function fec_wcal_contrib_category($label)
  {
    $l = strtolower(trim((string)$label));
    if ($l === '') return '';
    if (strpos($l, 'video poker') !== false) return 'vp';
    if (strpos($l, 'live') !== false) return 'live';
    foreach (array('table', 'blackjack', 'roulette', 'baccarat', 'craps', 'poker', 'sic bo', 'pontoon', 'war') as $k) {
      if (strpos($l, $k) !== false) return 'table';
    }
    if (strpos($l, 'slot') !== false) return 'slots';
    return 'other';
  }
}

if (!function_exists('fec_wcal_contrib_select_html')) {
  /**
   * The contribution <select> (same ids and values the JS reads) with the
   * categories the terms exclude disabled, plus the terms hint. Both lists
   * arrive as labels separated by "|"; '' = unknown, nothing disabled.
   */
  function fec_wcal_contrib_select_html($gamesOk, $gamesNo)
  {
    $esc = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); };
    $split = function ($s) {
      $out = array();
      foreach (explode('|', (string)$s) as $p) { $p = trim($p); if ($p !== '') $out[] = $p; }
      return $out;
    };
    $ok = $split($gamesOk);
    $no = $split($gamesNo);
    $excluded = array();
    foreach ($no as $g) { $c = fec_wcal_contrib_category($g); if ($c !== '' && $c !== 'other') $excluded[$c] = true; }

    $dash = "\xE2\x80\x94";
    $cats = array(
      array('v' => '100', 'c' => 'slots', 'l' => 'Slots ' . $dash . ' 100%'),
      array('v' => '50',  'c' => 'other', 'l' => 'Other games ' . $dash . ' 50%'),
      array('v' => '20',  'c' => 'vp',    'l' => 'Video poker ' . $dash . ' 20%'),
      array('v' => '10',  'c' => 'table', 'l' => 'Table games / blackjack / roulette ' . $dash . ' 10%'),
      array('v' => '5',   'c' => 'live',  'l' => 'Live casino ' . $dash . ' 5%'),
    );
    $h = '<select id="inContrib">' . "\n";
    foreach ($cats as $cat) {
      $isEx = isset($excluded[$cat['c']]);
      $h .= '            <option value="' . $cat['v'] . '"'
          . (($cat['v'] === '100' && !$isEx) ? ' selected' : '')
          . ($isEx ? ' disabled' : '')
          . '>' . $esc($cat['l']) . ($isEx ? ' ' . $dash . ' excluded by the terms' : '') . '</option>' . "\n";
    }
    $h .= '            <option value="custom">Custom' . "\xE2\x80\xA6" . '</option>' . "\n";
    $h .= '          </select>' . "\n";

    $hint = '';
    if ($ok || $no) {
      $bits = array();
      if ($ok) $bits[] = '<b>Counts for this bonus:</b> ' . $esc(implode(', ', $ok));
      if ($no) $bits[] = '<b>Excluded:</b> ' . $esc(implode(', ', $no));
      $hint = '      <p class="wc-hint" id="contribTerms">' . implode(' ' . $dash . ' ', $bits) . '.</p>' . "\n";
    }
    return array($h, $hint);
  }
}

// Telegram bot username WITHOUT the @ (e.g. FreeExtraChipsBot).
// Leave empty to keep the reminder button disabled.
if (!defined('FEC_WCAL_TG_BOT')) {
  define('FEC_WCAL_TG_BOT', 'fecbonusbot');
}

if (!function_exists('fec_wcal_add_assets')) {
  /**
   * Queue widget CSS/JS once per page. Safe to call from multiple renderers.
   */
  function fec_wcal_add_assets()
  {
    static $done = false;
    if ($done) return;
    $done = true;

    $doc  = JFactory::getDocument();
    $base = JUri::root(true) . '/components/com_bonuses/';
    $doc->addStyleSheet($base . 'css/fec_wcal.css?v=' . FEC_WCAL_VERSION);
    $doc->addScript($base . 'js/fec_wcal.js?v=' . FEC_WCAL_VERSION);
  }
}

if (!function_exists('fec_wcal_render_widget')) {
  /**
   * Echo the widget root with data-attributes plus the full inner markup.
   * $attrs keys use the data-attribute spelling (dash separated).
   */
  function fec_wcal_render_widget($attrs = array())
  {
    $defaults = array(
      'brand' => '', 'label' => '', 'type' => 'su',
      'bonus' => '', 'max-bonus' => '', 'min-dep' => '',
      'wr' => '', 'wr-meth' => 'b', 'cashable' => 'yes',
      'max-cashout' => '', 'max-cashout-kind' => 'abs',
      'max-bet' => '', 'bid' => '', 'validity-days' => '',
      'games-ok' => '', 'games-no' => '',
      'tg-bot' => FEC_WCAL_TG_BOT
    );
    $a = array_merge($defaults, is_array($attrs) ? $attrs : array());

    $parts = array('class="fec-wcal"', 'id="fecWcal"');
    foreach (array_keys($defaults) as $k) {
      $parts[] = 'data-' . $k . '="'
        . htmlspecialchars((string)$a[$k], ENT_QUOTES, 'UTF-8') . '"';
    }

    echo '<div ' . implode(' ', $parts) . ">\n";
    echo <<<'FECWCAL'

    <!-- ================= INPUTS ================= -->
    <section class="wc-card" aria-labelledby="h-inputs">
      <h2 id="h-inputs">Your bonus</h2>

      <span class="wc-sub">Bonus type</span>
      <div class="wc-seg" role="group" aria-label="Bonus type" id="segType">
        <button type="button" data-v="match" aria-pressed="true">Deposit match</button>
        <button type="button" data-v="nd" aria-pressed="false">No deposit</button>
        <button type="button" data-v="fs" aria-pressed="false">Free spins winnings</button>
      </div>

      <span class="wc-sub">Amounts</span>
      <div class="wc-grid2">
        <div class="wc-field" id="fDeposit">
          <label for="inDeposit">Deposit</label>
          <input type="number" id="inDeposit" min="0" step="1" placeholder="e.g. 100" inputmode="decimal">
        </div>
        <div class="wc-field" id="fPct">
          <label for="inPct">Match <small>(%)</small></label>
          <input type="number" id="inPct" min="0" step="1" placeholder="e.g. 200" inputmode="numeric">
        </div>
        <div class="wc-field" id="fMaxBonus">
          <label for="inMaxBonus">Max bonus <small>(cap, optional)</small></label>
          <input type="number" id="inMaxBonus" min="0" step="1" placeholder="e.g. 1000" inputmode="decimal">
        </div>
        <div class="wc-field" id="fFixed" hidden>
          <label for="inFixed" id="lblFixed">Bonus amount</label>
          <input type="number" id="inFixed" min="0" step="0.5" placeholder="e.g. 50" inputmode="decimal">
          <p class="wc-hint" id="fsHint" hidden></p>
        </div>
      </div>

      <span class="wc-sub">Wagering requirement</span>
      <div class="wc-grid2">
        <div class="wc-field">
          <label for="inWr">Multiplier <small>(×)</small></label>
          <input type="number" id="inWr" min="0" step="1" placeholder="e.g. 30" inputmode="numeric">
        </div>
        <div class="wc-field">
          <label for="inWrMeth">Applies to</label>
          <select id="inWrMeth">
            <option value="b">Bonus only (B)</option>
            <option value="bd">Deposit + Bonus (D+B)</option>
          </select>
        </div>
        <div class="wc-field">
          <label for="inContrib">Game contribution</label>

FECWCAL;
    // 1.6.0: the select follows the terms (see fec_wcal_contrib_select_html)
    list($contribSelect, $contribHint) = fec_wcal_contrib_select_html($a['games-ok'], $a['games-no']);
    echo '          ' . $contribSelect;
    echo <<<'FECWCAL'
        </div>
        <div class="wc-field" id="fContribCustom" hidden>
          <label for="inContribCustom">Custom contribution <small>(%)</small></label>
          <input type="number" id="inContribCustom" min="1" max="100" step="1" placeholder="e.g. 75" inputmode="numeric">
        </div>
      </div>

FECWCAL;
    echo $contribHint;
    echo <<<'FECWCAL'
      <p class="wc-hint">Contribution is the share of each bet that counts toward wagering.
      Always confirm the table in the bonus T&amp;C — it varies by casino.</p>

      <span class="wc-sub">Limits in the terms <small>(optional)</small></span>
      <div class="wc-grid2">
        <div class="wc-field">
          <label for="inMaxBet">Max bet while wagering</label>
          <input type="number" id="inMaxBet" min="0" step="0.5" placeholder="e.g. 5" inputmode="decimal">
        </div>
        <div class="wc-field">
          <label for="inMaxCashout">Max cashout</label>
          <div style="display:flex;gap:6px">
            <input type="number" id="inMaxCashout" min="0" step="1" placeholder="e.g. 100" inputmode="decimal" style="flex:1">
            <select id="inMaxCashoutKind" style="width:auto" aria-label="Max cashout unit">
              <option value="abs">€/$</option>
              <option value="x_dep">× dep.</option>
              <option value="x_bonus">× bonus</option>
            </select>
          </div>
        </div>
        <div class="wc-field">
          <label for="inCashable">Bonus type at withdrawal</label>
          <select id="inCashable">
            <option value="yes">Cashable — bonus is yours</option>
            <option value="no">Sticky — bonus removed at withdrawal</option>
          </select>
        </div>
        <div class="wc-field">
          <label for="inDays">Days left to complete <small>(optional)</small></label>
          <input type="number" id="inDays" min="1" step="1" placeholder="e.g. 14" inputmode="numeric">
        </div>
      </div>

      <span class="wc-sub">Your progress</span>
      <div class="wc-grid2">
        <div class="wc-field">
          <label for="inWagered">Wagered so far <small>(total bets on the selected game)</small></label>
          <input type="number" id="inWagered" min="0" step="1" placeholder="0" inputmode="decimal">
        </div>
        <div class="wc-field">
          <label for="inName">Save as <small>(name, optional)</small></label>
          <input type="text" id="inName" maxlength="60" placeholder="e.g. MyCasino 200% July">
        </div>
      </div>

      <div class="wc-actions">
        <button type="button" class="wc-btn" id="btnSave">Save bonus</button>
        <button type="button" class="wc-btn ghost" id="btnReset">Reset form</button>
        <button type="button" class="wc-btn ghost" id="btnTg" disabled
                title="Enter the days left to complete wagering to enable the reminder">
          <svg class="wc-ico" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          Remind me on Telegram
        </button>
      </div>
    </section>

    <!-- ================= RESULTS ================= -->
    <aside class="wc-card wc-results" aria-labelledby="h-results" aria-live="polite">
      <h2 id="h-results">What it really takes</h2>

      <div class="wc-big">
        <div class="n" id="outRemaining">—</div>
        <div class="l">left to wager on the selected game before you can withdraw</div>
      </div>

      <div class="wc-chips" id="outChips" aria-hidden="true"></div>
      <div class="wc-progress-label">
        <span id="outProgressPct">0%</span>
        <span id="outProgressAbs">— / —</span>
      </div>

      <span class="wc-sub">Breakdown</span>
      <div class="wc-bonusline"><span class="k">Bonus credited</span><span class="v" id="outBonus">—</span></div>
      <div class="wc-bonusline"><span class="k">Wagering base</span><span class="v" id="outBase">—</span></div>
      <div class="wc-bonusline"><span class="k">Required turnover (100% games)</span><span class="v" id="outTurnover">—</span></div>
      <div class="wc-bonusline"><span class="k">Required on selected game</span><span class="v" id="outTurnoverEff">—</span></div>
      <div class="wc-bonusline"><span class="k">Turnover vs your deposit</span><span class="v" id="outVsDep">—</span></div>
      <div class="wc-bonusline" id="rowPace" hidden><span class="k">Daily pace needed</span><span class="v" id="outPace">—</span></div>
      <div class="wc-bonusline" id="rowCap" hidden><span class="k">Withdrawal ceiling</span><span class="v" id="outCap">—</span></div>

      <div id="outFlags"></div>
    </aside>

    <!-- ================= SAVED ================= -->
    <section class="wc-card wc-saved" aria-labelledby="h-saved">
      <h2 id="h-saved">Saved bonuses</h2>
      <p class="wc-hint" style="margin:0 0 12px">Stored only in this browser. Update
      “wagered so far” any time — the calculator remembers where you left off.</p>
      <ul id="savedList"></ul>
      <p class="wc-empty" id="savedEmpty">Nothing saved yet.</p>
    </section>

    <p class="wc-foot">This tool models the arithmetic of bonus terms; it does not predict
    game outcomes and it is not gambling advice. Terms change — the casino’s current
    T&amp;C always prevail. 18+ · Play responsibly.</p>
  
FECWCAL;
    echo "\n</div>\n";
  }
}