(function () {
  "use strict";

  // Assets are added to <head> by fec_wcal_add_assets(); wait for the DOM.
  function fecWcalInit() {

  var LS_KEY = "fec_wcal_v1";
  var root = document.getElementById("fecWcal");
  if (!root) { return; } // asset may be loaded on pages without the widget
  var $ = function (id) { return document.getElementById(id); };

  var els = {
    deposit: $("inDeposit"), pct: $("inPct"), maxBonus: $("inMaxBonus"),
    fixed: $("inFixed"), wr: $("inWr"), wrMeth: $("inWrMeth"),
    contrib: $("inContrib"), contribCustom: $("inContribCustom"),
    maxBet: $("inMaxBet"), maxCashout: $("inMaxCashout"),
    maxCashoutKind: $("inMaxCashoutKind"), cashable: $("inCashable"),
    days: $("inDays"), wagered: $("inWagered"), name: $("inName")
  };

  var state = { type: "match" };

  /* ---------- helpers ---------- */
  function num(el) {
    var v = parseFloat(el.value);
    return isFinite(v) && v >= 0 ? v : null;
  }
  function fmt(n) {
    if (n === null || !isFinite(n)) return "—";
    return "€" + n.toLocaleString("en-US", { maximumFractionDigits: n < 100 ? 2 : 0 });
  }
  function fmtX(n) {
    return isFinite(n) ? (Math.round(n * 10) / 10).toLocaleString("en-US") + "×" : "—";
  }

  /* ---------- prefill from data-attributes (embed API, F3) ---------- */
  function prefill() {
    var d = root.dataset;
    if (d.type === "nd") setType("nd");
    else if (d.type === "fs") setType("fs");
    else setType("match");
    if (d.bonus && state.type === "match") els.pct.value = d.bonus;
    if (d.bonus && state.type === "nd" && !els.fixed.value) els.fixed.value = d.bonus;
    if (d.validityDays && !els.days.value) els.days.value = d.validityDays;
    if (d.maxBonus) els.maxBonus.value = d.maxBonus;
    if (d.minDep && !els.deposit.value) els.deposit.placeholder = "min. " + d.minDep;
    if (d.wr) els.wr.value = d.wr;
    if (d.wrMeth === "bd" || d.wrMeth === "b") els.wrMeth.value = d.wrMeth;
    if (d.cashable === "no") els.cashable.value = "no";
    if (d.maxCashout) els.maxCashout.value = d.maxCashout;
    if (d.maxCashoutKind) els.maxCashoutKind.value = d.maxCashoutKind;
    if (d.maxBet) els.maxBet.value = d.maxBet;
    if (d.brand && !els.name.value) {
      els.name.placeholder = d.brand + (d.label ? " — " + d.label : "");
    }
  }

  /* ---------- bonus type segmented control ---------- */
  function setType(t) {
    state.type = t;
    var btns = document.querySelectorAll("#segType button");
    for (var i = 0; i < btns.length; i++) {
      btns[i].setAttribute("aria-pressed", btns[i].getAttribute("data-v") === t ? "true" : "false");
    }
    var isMatch = t === "match";
    $("fPct").hidden = !isMatch;
    $("fMaxBonus").hidden = !isMatch;
    $("fFixed").hidden = isMatch;
    $("fDeposit").hidden = t === "nd";
    $("lblFixed").textContent = t === "fs" ? "Free spins winnings" : "Bonus amount";
    var fsHint = $("fsHint");
    if (fsHint) {
      var spins = root.dataset.bonus || "";
      if (t === "fs" && spins) {
        els.fixed.placeholder = "e.g. winnings from " + spins + " spins";
        fsHint.textContent = "This bonus grants " + spins + " free spins. Wagering applies to what the spins actually pay out - enter that amount once your spins have played through.";
        fsHint.hidden = false;
      } else {
        els.fixed.placeholder = t === "fs" ? "e.g. 50" : els.fixed.placeholder;
        fsHint.hidden = true;
      }
    }
    compute();
  }
  document.getElementById("segType").addEventListener("click", function (e) {
    var b = e.target.closest ? e.target.closest("button") : null;
    if (b) setType(b.getAttribute("data-v"));
  });

  /* ---------- engine ---------- */
  function readModel() {
    var D = state.type === "nd" ? 0 : (num(els.deposit) || 0);
    var B;
    if (state.type === "match") {
      var pct = num(els.pct);
      B = pct !== null ? D * pct / 100 : null;
      var cap = num(els.maxBonus);
      if (B !== null && cap !== null && cap > 0) B = Math.min(B, cap);
    } else {
      B = num(els.fixed);
    }
    var wr = num(els.wr);
    var meth = els.wrMeth.value; // 'b' | 'bd'
    var c = els.contrib.value === "custom" ? num(els.contribCustom) : parseFloat(els.contrib.value);
    if (c === null || !isFinite(c) || c <= 0) c = 100;
    if (c > 100) c = 100;

    var T = (B !== null && wr !== null) ? wr * (meth === "bd" ? D + B : B) : null;
    var Teff = T !== null ? T / (c / 100) : null;

    var wagered = num(els.wagered) || 0;
    var counted = wagered * (c / 100);
    var remainingEff = Teff !== null ? Math.max(0, Teff - wagered) : null;
    var progress = (T !== null && T > 0) ? Math.min(1, counted / T) : 0;

    var days = num(els.days);
    var pace = (remainingEff !== null && days) ? remainingEff / days : null;

    var capVal = num(els.maxCashout), capKind = els.maxCashoutKind.value, capAbs = null;
    if (capVal !== null && capVal > 0) {
      capAbs = capKind === "x_dep" ? capVal * D : capKind === "x_bonus" ? capVal * (B || 0) : capVal;
    }

    return {
      D: D, B: B, wr: wr, meth: meth, c: c, T: T, Teff: Teff,
      wagered: wagered, counted: counted, remainingEff: remainingEff,
      progress: progress, pace: pace, days: days,
      capAbs: capAbs, sticky: els.cashable.value === "no",
      maxBet: num(els.maxBet)
    };
  }

  function compute() {
    var m = readModel();

    $("outBonus").textContent = fmt(m.B);
    $("outBase").textContent = m.B === null ? "—" :
      (m.meth === "bd" ? fmt(m.D + m.B) + " (D+B)" : fmt(m.B) + " (B)");
    $("outTurnover").textContent = fmt(m.T);
    $("outTurnoverEff").textContent = m.Teff === null ? "—" :
      fmt(m.Teff) + (m.c < 100 ? " @ " + m.c + "%" : "");
    $("outRemaining").textContent = fmt(m.remainingEff);
    $("outVsDep").textContent = (m.T !== null && m.D > 0) ? fmtX(m.T / m.D) + " your deposit" : "—";

    $("rowPace").hidden = m.pace === null;
    if (m.pace !== null) $("outPace").textContent = fmt(m.pace) + " / day";

    $("rowCap").hidden = m.capAbs === null;
    if (m.capAbs !== null) $("outCap").textContent = fmt(m.capAbs);

    /* chip-stack progress */
    var chips = $("outChips");
    if (!chips.childElementCount) {
      for (var i = 0; i < 20; i++) chips.appendChild(document.createElement("span"));
    }
    var lit = Math.floor(m.progress * 20 + 1e-9);
    for (var j = 0; j < 20; j++) {
      var cls = j < lit ? (m.progress >= 1 ? "done" : "on") : "";
      chips.children[j].className = cls;
    }
    $("outProgressPct").textContent = Math.floor(m.progress * 100) + "%";
    $("outProgressAbs").textContent = (m.T === null) ? "— / —" :
      fmt(m.counted) + " / " + fmt(m.T) + " counted";

    /* flags */
    var f = [];
    if (m.progress >= 1 && m.T !== null) {
      f.push(flag("info", "Wagering complete.", "Based on your numbers the requirement is met. Verify in the casino cashier before withdrawing."));
    }
    if (m.sticky && m.B) {
      f.push(flag("warn", "Sticky bonus.", "The " + fmt(m.B) + " bonus is removed from your balance at withdrawal — only winnings above it are yours."));
    }
    if (m.capAbs !== null) {
      var capNote = "Anything above " + fmt(m.capAbs) + " is forfeited at withdrawal.";
      if (m.T !== null && m.capAbs < m.T * 0.02) {
        capNote += " That is a very low ceiling relative to the required turnover.";
      }
      f.push(flag("warn", "Max cashout applies.", capNote));
    }
    if (m.maxBet !== null && m.maxBet > 0) {
      f.push(flag("warn", "Max bet " + fmt(m.maxBet) + " while wagering.", "A single bet above this limit typically voids the bonus and winnings."));
    }
    if (m.pace !== null && m.remainingEff > 0 && m.pace > 0) {
      f.push(flag("info", "Pace check.", "You need to put through " + fmt(m.pace) + " per day for " + m.days + " more day" + (m.days > 1 ? "s" : "") + " to finish in time."));
    }
    $("outFlags").innerHTML = f.join("");

    updateTgButton(m);
  }

  /* ---------- Telegram reminder (F4) ---------- */
  function updateTgButton(m) {
    var btn = $("btnTg");
    if (!btn) return;
    var bot = (root.dataset.tgBot || "").replace(/^@/, "");
    if (!bot) {
      btn.disabled = true;
      btn.title = "Telegram reminders are not configured";
      return;
    }
    var days = num(els.days);
    if (!days || days < 1) {
      btn.disabled = true;
      btn.title = "Enter the days left to complete wagering to enable the reminder";
      return;
    }
    btn.disabled = false;
    btn.title = "Get a Telegram reminder 48 hours before your wagering deadline";
  }

  function openTgReminder() {
    var bot = (root.dataset.tgBot || "").replace(/^@/, "");
    var days = num(els.days);
    if (!bot || !days || days < 1) return;
    var bid = parseInt(root.dataset.bid, 10);
    if (!isFinite(bid) || bid < 1) bid = 0;
    var payload = "wcal_" + bid + "_" + Math.floor(days);
    window.open("https://t.me/" + encodeURIComponent(bot) + "?start=" + payload, "_blank", "noopener");
  }

  function flag(kind, title, body) {
    return '<div class="wc-flag ' + kind + '"><span class="ico">' +
      (kind === "warn" ? "⚠️" : "✔️") + '</span><span><b>' +
      esc(title) + '</b> ' + esc(body) + '</span></div>';
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
    });
  }

  /* ---------- persistence ---------- */
  function loadStore() {
    try { return JSON.parse(localStorage.getItem(LS_KEY)) || []; }
    catch (e) { return []; }
  }
  function saveStore(list) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(list)); } catch (e) {}
  }
  function snapshot() {
    return {
      id: Date.now(),
      name: els.name.value.trim() || els.name.placeholder || "Unnamed bonus",
      type: state.type,
      deposit: els.deposit.value, pct: els.pct.value, maxBonus: els.maxBonus.value,
      fixed: els.fixed.value, wr: els.wr.value, wrMeth: els.wrMeth.value,
      contrib: els.contrib.value, contribCustom: els.contribCustom.value,
      maxBet: els.maxBet.value, maxCashout: els.maxCashout.value,
      maxCashoutKind: els.maxCashoutKind.value, cashable: els.cashable.value,
      days: els.days.value, wagered: els.wagered.value,
      savedAt: new Date().toISOString().slice(0, 10)
    };
  }
  function restore(s) {
    setType(s.type || "match");
    els.deposit.value = s.deposit || ""; els.pct.value = s.pct || "";
    els.maxBonus.value = s.maxBonus || ""; els.fixed.value = s.fixed || "";
    els.wr.value = s.wr || ""; els.wrMeth.value = s.wrMeth || "b";
    els.contrib.value = s.contrib || "100";
    $("fContribCustom").hidden = els.contrib.value !== "custom";
    els.contribCustom.value = s.contribCustom || "";
    els.maxBet.value = s.maxBet || ""; els.maxCashout.value = s.maxCashout || "";
    els.maxCashoutKind.value = s.maxCashoutKind || "abs";
    els.cashable.value = s.cashable || "yes";
    els.days.value = s.days || ""; els.wagered.value = s.wagered || "";
    els.name.value = s.name || "";
    compute();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
  function renderSaved() {
    var list = loadStore();
    var ul = $("savedList");
    ul.innerHTML = "";
    $("savedEmpty").hidden = list.length > 0;
    list.forEach(function (s) {
      var li = document.createElement("li");
      var wr = s.wr ? s.wr + "×" + (s.wrMeth === "bd" ? " D+B" : " B") : "WR n/a";
      li.innerHTML =
        '<span class="meta"><span class="t">' + esc(s.name) + '</span><br>' +
        '<span class="s">' + wr + ' · wagered ' + esc(s.wagered || "0") +
        ' · saved ' + esc(s.savedAt) + '</span></span>' +
        '<span class="act">' +
        '<button type="button" class="wc-btn ghost sm" data-a="open" data-id="' + s.id + '">Open</button>' +
        '<button type="button" class="wc-btn ghost sm" data-a="del" data-id="' + s.id + '">Delete</button>' +
        '</span>';
      ul.appendChild(li);
    });
  }
  $("savedList").addEventListener("click", function (e) {
    var b = e.target.closest ? e.target.closest("button") : null;
    if (!b) return;
    var id = parseInt(b.getAttribute("data-id"), 10);
    var list = loadStore();
    if (b.getAttribute("data-a") === "del") {
      saveStore(list.filter(function (s) { return s.id !== id; }));
      renderSaved();
    } else {
      var s = list.filter(function (x) { return x.id === id; })[0];
      if (s) restore(s);
    }
  });
  $("btnSave").addEventListener("click", function () {
    var list = loadStore();
    list.unshift(snapshot());
    if (list.length > 30) list = list.slice(0, 30);
    saveStore(list);
    renderSaved();
  });
  $("btnReset").addEventListener("click", function () {
    restore({ type: "match" });
    els.name.value = "";
  });

  /* ---------- wire up ---------- */
  root.addEventListener("input", compute);
  $("btnTg").addEventListener("click", openTgReminder);
  els.contrib.addEventListener("change", function () {
    $("fContribCustom").hidden = els.contrib.value !== "custom";
    compute();
  });

  prefill();
  renderSaved();
  compute();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fecWcalInit);
  } else {
    fecWcalInit();
  }
})();