(function () {
  "use strict";

  function fecDnInit() {
    var root = document.getElementById("fecDn");
    if (!root) { return; }

    var d = root.dataset;
    // core fields required; the band self-hides otherwise
    var wr = parseFloat(d.wr), pct = parseFloat(d.bonus);
    // wr = 0 is a legitimate value (wager-free), only NaN/negative hides the band
    if (!isFinite(wr) || wr < 0 || !isFinite(pct) || pct <= 0) { return; }

    var rec = {
      type: d.type === "nd" ? "nd" : "match", // fs handled as match on winnings? no: fs skipped server side
      bonus: pct,
      max_bonus: parseFloat(d.maxBonus) || null,
      min_dep: parseFloat(d.minDep) || null,
      wr: wr,
      wr_meth: d.wrMeth === "bd" ? "bd" : "b",
      cashable: d.cashable !== "no",
      max_cashout: parseFloat(d.maxCashout) || null,
      cap_kind: d.maxCashoutKind || "",
      max_bet: parseFloat(d.maxBet) || null,
      op_status: d.opStatus || "ok"
    };

    var LS = "fec_dnprofile_v1";
    var profile = null;
    try {
      var saved = JSON.parse(localStorage.getItem(LS));
      if (saved && typeof saved === "object") profile = saved;
    } catch (e) {}

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (ch) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
      });
    }
    function eur(n) { return "\u20AC" + Math.round(n).toLocaleString("en-US"); }

    /* ---------- deterministic engine, rule table v1.0 ----------
       R0 operator override (flagged caps at caution, susp/bl force skip)
       R1 min deposit vs bankroll  R2 turnover ratio 120/300
       R3 verified cap 2x/5x (max-win)  R4 sticky (max-win)
       R5 fast clearing: bd base, wr>30  R6 max-bet friction note */
    function decide(b, p) {
      var D = b.type === "nd" ? 0 : p.bankroll;
      var reasons = [], severity = 0;
      function raise(level, text) { severity = Math.max(severity, level); reasons.push(text); }

      if (b.min_dep && D > 0 && b.min_dep > D) {
        raise(2, "Minimum deposit " + eur(b.min_dep) + " is above your ~" + eur(D) + " bankroll.");
      }

      var B;
      if (b.type === "nd") { B = b.bonus; }
      else {
        B = D * b.bonus / 100;
        if (b.max_bonus) B = Math.min(B, b.max_bonus);
      }
      var base = b.wr_meth === "bd" ? D + B : B;
      var T = b.wr * base;
      var contrib = p.game / 100;
      var Teff = T / contrib;

      if (b.wr === 0) {
        reasons.push("Bonus credited: <b>" + eur(B) + "</b>. <b>No wagering requirement</b> \u2014 winnings are withdrawable subject to the remaining terms.");
      } else {
        reasons.push("Bonus credited: <b>" + eur(B) + "</b>. Required stakes on your game: <b>" +
          eur(Teff) + "</b> (" + b.wr + "\u00D7 " + (b.wr_meth === "bd" ? "deposit+bonus" : "bonus") +
          (contrib < 1 ? ", at " + p.game + "% contribution" : "") + ").");
      }

      if (D > 0) {
        var ratio = Teff / D;
        if (ratio > 300) raise(2, "That is <b>" + Math.round(ratio) + "\u00D7 your deposit</b> \u2014 not a realistic clearing volume for this bankroll (expected clearing cost above 12\u00D7 your deposit at typical slot RTP).");
        else if (ratio > 120) raise(1, "That is <b>" + Math.round(ratio) + "\u00D7 your deposit</b> \u2014 heavy for this bankroll (expected clearing cost around 5\u00D7 your deposit at typical slot RTP).");
      }

      if (b.max_cashout) {
        if (b.cap_kind === "abs" || b.cap_kind === "x_dep" || b.cap_kind === "x_bonus") {
          var capAbs = b.cap_kind === "x_dep" ? b.max_cashout * D
                     : b.cap_kind === "x_bonus" ? b.max_cashout * B
                     : b.max_cashout;
          var capR = D > 0 ? capAbs / D : null;
          if (p.priority === "maxwin" && capR !== null && capR < 2) {
            raise(2, "Verified max cashout <b>" + eur(capAbs) + "</b> = " + capR.toFixed(1) + "\u00D7 your deposit \u2014 the ceiling defeats a max-win goal.");
          } else if (p.priority === "maxwin" && capR !== null && capR < 5) {
            raise(1, "Verified max cashout <b>" + eur(capAbs) + "</b> caps any win at " + capR.toFixed(1) + "\u00D7 your deposit.");
          } else {
            reasons.push("Max cashout on record: <b>" + eur(capAbs) + "</b>.");
          }
        } else {
          reasons.push("A max cashout of <b>" + b.max_cashout + "</b> is on record but its unit is not yet verified (amount or multiple) \u2014 treated as unknown, check the T&amp;C.");
        }
      }

      if (!b.cashable && p.priority === "maxwin") {
        raise(1, "Sticky bonus: the " + eur(B) + " is removed from your balance at withdrawal.");
      }
      if (p.priority === "fastclear" && b.wr > 0) {
        if (b.wr_meth === "bd") raise(1, "Wagering applies to deposit+bonus \u2014 the slowest base to clear.");
        if (b.wr > 30) raise(1, b.wr + "\u00D7 is a slow multiplier for a fast-clearing goal.");
      }
      if (b.max_bet && Teff / b.max_bet > 5000) {
        reasons.push("Max bet " + eur(b.max_bet) + " forces a minimum of <b>" +
          Math.round(Teff / b.max_bet).toLocaleString("en-US") + " bets</b>.");
      }

      if (b.op_status === "flagged") {
        if (severity < 1) severity = 1;
        reasons.unshift("<b>Operator warning on record.</b> Editorial flag active \u2014 see the review before depositing. This caps the verdict regardless of profile.");
      } else if (b.op_status === "suspended" || b.op_status === "blacklisted") {
        severity = 2;
        reasons.unshift("<b>Operator " + esc(b.op_status) + ".</b> No bonus terms outrank an operator-level warning.");
      }

      return { severity: severity, reasons: reasons };
    }

    /* ---------- ui ---------- */
    var Q_BANKROLL =
      '<div class="dn-q">How much do you plan to deposit?</div>' +
      '<div class="dn-seg" data-k="bankroll">' +
        '<button type="button" data-v="25">~\u20AC25</button>' +
        '<button type="button" data-v="75">~\u20AC75</button>' +
        '<button type="button" data-v="200">~\u20AC200</button>' +
        '<button type="button" data-v="500">\u20AC500+</button></div>';
    var Q_REST =
      '<div class="dn-q">What do you mostly play?</div>' +
      '<div class="dn-seg" data-k="game">' +
        '<button type="button" data-v="100">Slots</button>' +
        '<button type="button" data-v="10">Table games</button>' +
        '<button type="button" data-v="5">Live casino</button></div>' +
      '<div class="dn-q">What matters most to you?</div>' +
      '<div class="dn-seg" data-k="priority">' +
        '<button type="button" data-v="maxwin">Max win potential</button>' +
        '<button type="button" data-v="lowrisk">Low risk</button>' +
        '<button type="button" data-v="fastclear">Fast clearing</button></div>';

    // nd pages need no bankroll: question 1 is skipped there and the
    // verdict starts from game+priority. A deposit page later reuses the
    // saved answers and asks only what is missing.
    function neededKeys() {
      return rec.type === "nd" ? ["game", "priority"] : ["bankroll", "game", "priority"];
    }
    function profileComplete() {
      if (!profile) return false;
      var need = neededKeys();
      for (var i = 0; i < need.length; i++) {
        if (profile[need[i]] === undefined || profile[need[i]] === null || profile[need[i]] === "") return false;
      }
      return true;
    }
    function questionsHtml() {
      return (rec.type === "nd" ? "" : Q_BANKROLL) + Q_REST;
    }

    function labelProfile(p) {
      return (rec.type === "nd" ? "" : "~" + eur(p.bankroll) + " \u00B7 ") +
        (p.game == 100 ? "slots" : p.game == 10 ? "table games" : "live casino") +
        " \u00B7 " + esc(String(p.priority).replace("maxwin", "max win").replace("fastclear", "fast clearing").replace("lowrisk", "low risk"));
    }

    function render() {
      var html = '<div class="dn-head">Is this bonus for you?</div>';
      if (!profileComplete()) {
        html += '<p class="dn-sub">' + (rec.type === "nd" ? "Two" : "Three") + ' quick questions \u2014 the verdict is computed from the verified terms of this bonus. Your answers are remembered on every bonus page.</p>' +
          '<div class="dn-questions">' + questionsHtml() + '</div>';
      } else {
        var r = decide(rec, profile);
        var cls = ["worth", "caution", "skip"][r.severity];
        var title = ["Worth claiming for your profile", "Claimable with caution", "Skip for your profile"][r.severity];
        html +=
          '<div class="dn-verdict ' + cls + '">' +
          '<div class="dn-vlabel">For your profile \u00B7 ' + labelProfile(profile) +
          ' \u00B7 <button type="button" class="dn-edit" id="dnEdit">change</button></div>' +
          '<div class="dn-vtitle">' + title + "</div>" +
          "<ul>" + r.reasons.map(function (x) { return "<li>" + x + "</li>"; }).join("") + "</ul>" +
          '<div class="dn-vnote">Computed from the verified record (<a href="/methodology/decision-engine">rule table v1.0</a>). The signed decision note below remains the editorial judgment; this verdict scopes it to your profile. ' +
          '<a href="#calculator">Run the full numbers</a>.</div>' +
          "</div>" +
          '<div class="dn-questions" hidden>' + questionsHtml() + "</div>";
      }
      root.innerHTML = html;

      // mark current answers (also for a partial profile in question mode)
      if (profile) {
        root.querySelectorAll(".dn-seg").forEach(function (seg) {
          var k = seg.getAttribute("data-k");
          seg.querySelectorAll("button").forEach(function (btn) {
            btn.setAttribute("aria-pressed", String(btn.getAttribute("data-v")) === String(profile[k]) ? "true" : "false");
          });
        });
        var edit = document.getElementById("dnEdit");
        if (edit) edit.addEventListener("click", function () {
          root.querySelector(".dn-questions").hidden = false;
          edit.disabled = true;
        });
      }
    }

    root.addEventListener("click", function (e) {
      var btn = e.target.closest ? e.target.closest(".dn-seg button") : null;
      if (!btn) return;
      var seg = btn.parentNode;
      seg.querySelectorAll("button").forEach(function (x) { x.setAttribute("aria-pressed", "false"); });
      btn.setAttribute("aria-pressed", "true");
      if (!profile) profile = {};
      var k = seg.getAttribute("data-k"), v = btn.getAttribute("data-v");
      profile[k] = (k === "priority") ? v : parseInt(v, 10);
      try { localStorage.setItem(LS, JSON.stringify(profile)); } catch (err) {}
      if (profileComplete()) render();
    });

    render();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fecDnInit);
  } else {
    fecDnInit();
  }
})();