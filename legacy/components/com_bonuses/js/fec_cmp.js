(function () {
  "use strict";

  function fecCmpInit() {
    var root = document.getElementById("fecCmp");
    if (!root) { return; }

    var els = {
      inputs: [document.getElementById("cmpS1"), document.getElementById("cmpS2"), document.getElementById("cmpS3")],
      cc: document.getElementById("cmpCc"),
      list: document.getElementById("cmpList"),
      samop: document.getElementById("cmpSamop"),
      status: document.getElementById("cmpStatus"),
      table: root.querySelector(".cmp-table"),
      head: document.getElementById("cmpHead"),
      body: document.getElementById("cmpBody")
    };

    var DATA = [];
    var byName = {}, bySlug = {};

    var COUNTRIES = ["US","GB","IT","DE","NL","FR","ES","PT","SE","NO","FI","DK","PL","CZ","AT","CH","IE","GR","RO","HU","CA","AU","NZ","BR","MX","AR","JP","IN","ZA"];

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (ch) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
      });
    }

    /* ---------- data ---------- */
    function load() {
      var src = root.getAttribute("data-src");
      fetch(src, { credentials: "omit" })
        .then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(function (j) {
          DATA = j.casinos || [];
          DATA.forEach(function (c) {
            byName[c.name.toLowerCase()] = c;
            bySlug[c.slug] = c;
          });
          fillUi();
          applyUrlParams();
          detectCountry();
          els.status.hidden = true;
          render();
        })
        .catch(function (e) {
          els.status.textContent = "Could not load the casino dataset. Reload the page to retry.";
        });
    }

    function fillUi() {
      var frag = document.createDocumentFragment();
      DATA.forEach(function (c) {
        var o = document.createElement("option");
        o.value = c.name;
        frag.appendChild(o);
      });
      els.list.appendChild(frag);
      COUNTRIES.forEach(function (cc) {
        var o = document.createElement("option");
        o.value = cc; o.textContent = cc;
        els.cc.appendChild(o);
      });
    }

    /* ---------- URL state: /tools/casino-comparator?c=slug1,slug2 ---------- */
    function applyUrlParams() {
      var m = /[?&]c=([^&]+)/.exec(window.location.search);
      if (!m) return;
      var slugs = decodeURIComponent(m[1]).split(",").slice(0, 3);
      slugs.forEach(function (slug, i) {
        var c = bySlug[slug.trim()];
        if (c && els.inputs[i]) els.inputs[i].value = c.name;
      });
    }

    function syncUrl(sel) {
      if (!window.history || !window.history.replaceState) return;
      var base = window.location.pathname;
      var qs = sel.length ? "?c=" + sel.map(function (c) { return encodeURIComponent(c.slug); }).join(",") : "";
      window.history.replaceState(null, "", base + qs);
    }

    /* ---------- country via Cloudflare trace (client side, cache safe) ---------- */
    function detectCountry() {
      if (els.cc.value !== "") return;
      fetch("/cdn-cgi/trace", { credentials: "omit" })
        .then(function (r) { return r.text(); })
        .then(function (t) {
          var m = /(?:^|\n)loc=([A-Z]{2})(?:\n|$)/.exec(t);
          if (!m) return;
          if (els.cc.value !== "") return; // user chose meanwhile
          var has = false;
          for (var i = 0; i < els.cc.options.length; i++) {
            if (els.cc.options[i].value === m[1]) { has = true; break; }
          }
          if (!has) {
            var o = document.createElement("option");
            o.value = m[1]; o.textContent = m[1];
            els.cc.appendChild(o);
          }
          els.cc.value = m[1];
          render();
        })
        .catch(function () { /* silent: country stays manual */ });
    }

    /* ---------- selection ---------- */
    function selected() {
      var out = [];
      els.inputs.forEach(function (inp) {
        var c = byName[(inp.value || "").trim().toLowerCase()];
        if (c && out.indexOf(c) === -1) out.push(c);
      });
      return out;
    }

    /* ---------- rendering ---------- */
    function statusPill(c) {
      if (c.status === "ok") return '<span class="pill ok">No active warnings</span>';
      var label = c.status.charAt(0).toUpperCase() + c.status.slice(1);
      var cls = c.status === "flagged" ? "warn" : "no";
      var out = '<span class="pill ' + cls + '">' + esc(label) + "</span>";
      if (c.flag_reason) out += '<div class="cmp-muted">' + esc(c.flag_reason) + "</div>";
      return out;
    }

    function playPill(c, cc) {
      if (!cc) return '<span class="pill neutral">Set your country</span>';
      var blocked = c.restricted.indexOf(cc.toLowerCase()) !== -1;
      return blocked
        ? '<span class="pill no">Restricted for ' + esc(cc) + "</span>"
        : '<span class="pill ok">' + esc(cc) + " not in the restricted list</span>";
    }

    function chips(arr, max, forceCollapse) {
      if (!arr.length) return '<span class="cmp-muted">&mdash;</span>';
      if (!forceCollapse && arr.length <= max) {
        return '<div class="chips">' + arr.map(function (x) {
          return '<span class="chip">' + esc(x) + "</span>";
        }).join("") + "</div>";
      }
      return "<details><summary>" + arr.length + " total</summary>" +
        '<div class="chips">' + arr.map(function (x) {
          return '<span class="chip">' + esc(x) + "</span>";
        }).join("") + "</div></details>";
    }

    function cell(v) {
      return v && String(v).trim() !== "" ? esc(v) : '<span class="cmp-muted">&mdash;</span>';
    }

    function render() {
      var sel = selected();
      var cc = els.cc.value;
      syncUrl(sel);

      if (sel.length < 2) {
        els.table.hidden = true;
        els.samop.className = "cmp-samop";
        els.status.hidden = false;
        els.status.textContent = DATA.length
          ? "Pick at least two casinos to compare."
          : els.status.textContent;
        return;
      }
      els.status.hidden = true;
      els.table.hidden = false;

      // same-operator detection: EXACT jr_owner match by design.
      // jr_owner is the certified operating entity, one per brand,
      // canonicalized via the Operator Atlas norm_key - no fuzzy matching.
      var byOwner = {};
      sel.forEach(function (c) {
        if (!byOwner[c.owner]) byOwner[c.owner] = [];
        byOwner[c.owner].push(c.name);
      });
      var dupes = Object.keys(byOwner).filter(function (k) { return k && byOwner[k].length > 1; });
      if (dupes.length) {
        els.samop.innerHTML = "<span aria-hidden=\"true\">&#9888;&#65039;</span><span><b>Same operator.</b> " +
          esc(byOwner[dupes[0]].join(" and ")) + " are run by the same operating company (" +
          esc(dupes[0]) + "). Opening accounts at both does not diversify counterparty risk, " +
          "and shared bonus-abuse rules may treat them as one venue.</span>";
        els.samop.className = "cmp-samop show";
      } else {
        els.samop.className = "cmp-samop";
      }

      // simmetria per riga: se UN casino supera la soglia, collassa la riga per tutti
      function over(field, max) {
        return sel.some(function (c) { return (c[field] || []).length > max; });
      }

      var h = '<th class="cmp-rowlabel"></th>';
      sel.forEach(function (c) {
        h += "<th>" + esc(c.name) +
          ' <a class="cmp-review" href="/online-casinos/' + encodeURIComponent(c.slug) + '">Read review</a></th>';
      });
      els.head.innerHTML = h;

      function row(label, fn) {
        return '<tr><th scope="row" class="cmp-rowlabel">' + label + "</th>" +
          sel.map(function (c) { return "<td>" + fn(c) + "</td>"; }).join("") + "</tr>";
      }

      els.body.innerHTML =
        row("Can you play?", function (c) { return playPill(c, cc); }) +
        row("Status", statusPill) +
        row("Operator", function (c) {
          var same = dupes.indexOf(c.owner) !== -1;
          return '<span class="' + (same ? "cmp-sameowner" : "") + '">' + cell(c.owner) + "</span>";
        }) +
        row("Licence", function (c) { return c.licence.length ? esc(c.licence.join(", ")) : cell(""); }) +
        row("Established", function (c) { return cell(c.established); }) +
        row("Software", function (c) { return chips(c.software, 6, over("software", 6)); }) +
        row("Live chat", function (c) {
          return c.livechat ? '<span class="pill ok">Yes</span>' : '<span class="pill neutral">No / unverified</span>';
        }) +
        row("Languages", function (c) { return chips(c.languages, 8, over("languages", 8)); }) +
        row("Currencies", function (c) { return chips(c.currencies, 8, over("currencies", 8)); }) +
        row("Banking methods", function (c) { return chips(c.banking, 6, over("banking", 6)); }) +
        row("Restricted countries", function (c) {
          return c.restricted.length + " listed" +
            "<details><summary>show</summary>" + '<div class="chips">' +
            c.restricted.map(function (x) { return '<span class="chip">' + esc(x) + "</span>"; }).join("") +
            "</div></details>";
        }) +
        row("Withdrawal pending period", function (c) { return cell(c.pending); });
    }

    root.addEventListener("change", render);
    root.addEventListener("input", function (e) {
      // datalist selections fire input; re-render only on exact matches or clears
      var t = e.target;
      if (t && t.tagName === "INPUT") {
        var v = (t.value || "").trim().toLowerCase();
        if (v === "" || byName[v]) render();
      }
    });

    load();
  }

  /* ==========================================================
     Launcher mode (F3): compact "Compare with..." band on casino
     review pages. Bare container:  <div id="fecCmpLaunch"></div>
     Current casino slug is derived from the /online-casinos/<alias>
     path - the jReviews template passes no variables. If the slug
     is not in the dataset (builder filter), nothing renders.
     ========================================================== */
  function fecCmpLauncherInit() {
    var box = document.getElementById("fecCmpLaunch");
    if (!box) { return; }

    var m = /\/online-casinos\/([^\/?#]+)/.exec(window.location.pathname);
    if (!m) { return; }
    var curSlug = decodeURIComponent(m[1]);

    var DATA_URL = box.getAttribute("data-src") || "/components/com_bonuses/data/cmp_casinos.json";
    var TOOL_URL = "/tools/casino-comparator";

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (ch) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
      });
    }

    fetch(DATA_URL, { credentials: "omit" })
      .then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
      .then(function (json) {
        var DATA = json.casinos || [];
        var cur = null, byName = {};
        DATA.forEach(function (c) {
          byName[c.name.toLowerCase()] = c;
          if (c.slug === curSlug) cur = c;
        });
        if (!cur) { return; } // casino not in the comparator dataset

        var sisters = DATA.filter(function (c) {
          return c.slug !== cur.slug && c.owner && c.owner === cur.owner;
        }).slice(0, 3);

        var html =
          '<div class="cmpl-head">Compare ' + esc(cur.name) + ' with&hellip;</div>' +
          '<div class="cmpl-row">' +
          '<input type="text" id="cmplPick" list="cmplList" placeholder="Type a casino name" autocomplete="off" aria-label="Casino to compare with">' +
          '<button type="button" id="cmplGo" class="cmpl-btn" disabled>Compare</button>' +
          '</div><datalist id="cmplList"></datalist>';
        if (sisters.length) {
          html += '<div class="cmpl-sisters"><span class="cmpl-sis-label">Same operator:</span>' +
            sisters.map(function (s) {
              return '<a class="cmpl-sis" href="' + TOOL_URL + "?c=" +
                encodeURIComponent(cur.slug) + "," + encodeURIComponent(s.slug) + '">vs ' + esc(s.name) + "</a>";
            }).join("") + "</div>";
        }
        box.innerHTML = html;
        box.className = "fec-cmpl";

        var list = document.getElementById("cmplList");
        var frag = document.createDocumentFragment();
        DATA.forEach(function (c) {
          if (c.slug === cur.slug) return;
          var o = document.createElement("option");
          o.value = c.name;
          frag.appendChild(o);
        });
        list.appendChild(frag);

        var pick = document.getElementById("cmplPick");
        var go = document.getElementById("cmplGo");
        function target() {
          var c = byName[(pick.value || "").trim().toLowerCase()];
          return (c && c.slug !== cur.slug) ? c : null;
        }
        function navigate() {
          var c = target();
          if (!c) return;
          window.location.href = TOOL_URL + "?c=" +
            encodeURIComponent(cur.slug) + "," + encodeURIComponent(c.slug);
        }
        pick.addEventListener("input", function () { go.disabled = !target(); });
        pick.addEventListener("keydown", function (e) { if (e.key === "Enter") navigate(); });
        go.addEventListener("click", navigate);
      })
      .catch(function () { /* silent: the band simply does not render */ });
  }

  function fecCmpBoot() {
    fecCmpInit();
    fecCmpLauncherInit();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fecCmpBoot);
  } else {
    fecCmpBoot();
  }
})();