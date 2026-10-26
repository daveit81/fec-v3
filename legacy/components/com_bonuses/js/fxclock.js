/* FXClock widget (FEC Tools No. 04) v1.1.0
 * Path: components/com_bonuses/js/fxclock.js
 * Hydrates #fxclock rendered by fxclock_widget.php. ES5 + fetch, no deps.
 */
(function () {
  'use strict';

  // Homepage band: refresh the lead and the tiles from the cached feed every minute.
  function homeBand() {
    var home = document.getElementById('fxcHome');
    if (!home || !window.fetch) return;
    var api = home.getAttribute('data-api') || '/fxclock/api';
    var methods = { crypto: 'Crypto', card: 'Card', ewallet: 'E-wallet', bank: 'Bank transfer' };
    var grades = { A: 'Stop time locked on-chain', B: 'Deposit verified', C: 'Contact verified', D: 'Anonymous' };
    var rn = null;
    try { if (window.Intl && Intl.DisplayNames) rn = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { rn = null; }
    function cname(cc) { if (!cc) return ''; try { return rn ? (rn.of(cc) || cc) : cc; } catch (e) { return cc; } }
    function fmt(m) { m = Math.max(0, Math.floor(m)); if (m < 60) return m + 'm'; var h = Math.floor(m / 60); if (h < 24) return h + 'h ' + (m % 60 < 10 ? '0' : '') + (m % 60) + 'm'; var d = Math.floor(h / 24); return d + 'd ' + (h % 24 < 10 ? '0' : '') + (h % 24) + 'h'; }
    function esc(x) { return String(x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    var ccs = home.querySelectorAll('.fxc-cc[data-cc]');
    for (var i = 0; i < ccs.length; i++) ccs[i].textContent = cname(ccs[i].getAttribute('data-cc'));
    function render(f) {
      var lead = document.getElementById('fxcHomeLead'), tiles = document.getElementById('fxcHomeTiles');
      if (!lead || !tiles) return;
      if (f.running_count === 0 && f.stopped_total === 0) return; // keep the server invitation text
      var parts = [];
      if (f.running_count > 0) parts.push('<b>' + f.running_count + '</b> clock' + (f.running_count === 1 ? '' : 's') + ' running now across <b>' + f.running_brands + '</b> casino' + (f.running_brands === 1 ? '' : 's'));
      if (f.stopped_total > 0) parts.push('<b>' + f.stopped_total + '</b> withdrawal' + (f.stopped_total === 1 ? '' : 's') + ' timed to the minute');
      if (f.brands_with_median > 0) parts.push('<b>' + f.brands_with_median + '</b> casino' + (f.brands_with_median === 1 ? '' : 's') + ' with a player-timed median');
      lead.innerHTML = 'Players are timing their withdrawals: ' + parts.join(', ') + '.';
      var runWrap = document.getElementById('fxcHomeRunningWrap'), run = document.getElementById('fxcHomeRunning');
      if (runWrap && run) {
        var rh = '';
        for (var k = 0; k < (f.running || []).length; k++) {
          var r = f.running[k];
          var rhref = r.page_path ? '/' + r.page_path.replace(/^\//, '') + '#fxclock' : '/online-casinos';
          rh += '<a class="fxc-home-tile fxc-home-tile--running" href="' + esc(rhref) + '" data-start="' + parseInt(r.oldest_started_at, 10) + '">'
              + '<span class="fxc-home-tile-time"><span class="fxc-dot" aria-hidden="true"></span><span class="fxc-time">' + esc(fmt((Date.now() / 1000 - r.oldest_started_at) / 60)) + '</span></span>'
              + '<span class="fxc-home-tile-brand">' + esc(r.title) + '</span>'
              + '<span class="fxc-home-tile-sub">' + r.count + ' clock' + (r.count === 1 ? '' : 's') + ' running' + (r.count > 1 ? ', longest shown' : '') + '</span></a>';
        }
        run.innerHTML = rh;
        runWrap.hidden = !(f.running && f.running.length);
      }
      var latestWrap = document.getElementById('fxcHomeLatestWrap');
      if (latestWrap) latestWrap.hidden = !(f.latest && f.latest.length);
      var html = '';
      for (var j = 0; j < f.latest.length; j++) {
        var t = f.latest[j];
        var href = t.page_path ? '/' + t.page_path.replace(/^\//, '') + '#fxclock' : '/online-casinos';
        html += '<a class="fxc-home-tile" href="' + esc(href) + '"><span class="fxc-home-tile-time">' + esc(fmt(t.duration_min)) + '</span>'
              + '<span class="fxc-home-tile-brand">' + esc(t.title) + '</span>'
              + '<span class="fxc-home-tile-sub">' + esc(methods[t.method] || t.method) + (t.cc ? ', from ' + esc(cname(t.cc)) : '') + '</span>'
              + '<span class="fxc-grade" title="' + esc(grades[t.grade] || '') + '">' + esc(t.grade) + '</span></a>';
      }
      tiles.innerHTML = html;
    }
    function refreshHome() {
      fetch(api + '/feed', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.ok) render(j.data); }).catch(function () {});
    }
    function tickHome() {
      var tiles = home.querySelectorAll('.fxc-home-tile--running[data-start]');
      var now = Date.now() / 1000;
      for (var i = 0; i < tiles.length; i++) {
        var t = tiles[i].querySelector('.fxc-time');
        if (t) t.textContent = fmt((now - parseInt(tiles[i].getAttribute('data-start'), 10)) / 60);
      }
    }
    setInterval(tickHome, 30000);
    setInterval(refreshHome, 60000);
    refreshHome();
  }

  function main() {
  homeBand();
  var root = document.getElementById('fxclock');
  if (!root || !window.fetch) return;

  var API = root.getAttribute('data-api') || '/fxclock/api';
  var BRAND = root.getAttribute('data-brand');
  var NAME = root.getAttribute('data-name') || BRAND;
  var THRESHOLD = parseInt(root.getAttribute('data-threshold') || '10', 10);
  var LS_KEY = 'fxc_clocks_v1';

  var L = {
    method: { crypto: 'Crypto', card: 'Card', ewallet: 'E-wallet', bank: 'Bank transfer' },
    amount: { lt100: 'Under 100', b100_500: '100\u2013500', b500_2000: '500\u20132,000', gt2000: 'Over 2,000' },
    when: { now: 'Just now', today: 'Earlier today', yesterday: 'Yesterday' },
    contact: { telegram: 'Telegram', email: 'Email', none: "I'll come back" },
    kyc: { not_asked: 'Not asked', deposit: 'At deposit', withdrawal: 'At withdrawal', after_win: 'After a win' },
    docs: { none: 'None', address: 'Proof of address', source_of_funds: 'Source of funds', selfie: 'Selfie or video' },
    paid: { full: 'Full amount', reduced: 'Less than requested', split: 'Split in parts' },
    band: { lt24h: 'Under 24h', d1_3: '1\u20133 days', d4_7: '4\u20137 days', gt7d: 'Over a week' },
    chain: { btc: 'BTC', eth: 'ETH / ERC-20', tron: 'TRON / TRC-20', ltc: 'LTC' },
    grade: { A: 'Stop time locked on-chain', B: 'Deposit verified', C: 'Contact verified', D: 'Anonymous' }
  };

  var state = { payload: null, csrf: null, mine: loadMine(), panel: null, active: null };

  // ------------------------------------------------------------------
  // utils
  // ------------------------------------------------------------------
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  // Analytics: one dataLayer push per event, consumed by GTM (Custom Event trigger ^fxc_).
  function track(name, params) {
    try {
      var o = { event: name, fxc_brand: BRAND };
      if (params) { for (var k in params) { if (params.hasOwnProperty(k)) o[k] = params[k]; } }
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push(o);
    } catch (e) {}
  }

  var regionNames = null;
  try { if (window.Intl && Intl.DisplayNames) regionNames = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { regionNames = null; }
  function countryName(cc) {
    if (!cc) return '';
    try { return regionNames ? (regionNames.of(cc) || cc) : cc; } catch (e) { return cc; }
  }
  function rowSub(c, extra) {
    var s = (L.method[c.method] || c.method) + ', ' + (c.amount_band ? L.amount[c.amount_band] : 'amount not given');
    if (c.cc) s += ', from ' + countryName(c.cc);
    return s + (extra || '');
  }

  function fmtSec(s) {
    s = Math.max(0, Math.floor(s));
    if (s < 3600) return Math.floor(s / 60) + 'm ' + pad(s % 60) + 's';
    var h = Math.floor(s / 3600);
    if (h < 24) return h + 'h ' + pad(Math.floor((s % 3600) / 60)) + 'm';
    var d = Math.floor(h / 24);
    return d + 'd ' + pad(h % 24) + 'h';
  }
  function fmtMin(m) { return fmtSec(m * 60); }

  function el(tag, attrs, children) {
    var e = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (!attrs.hasOwnProperty(k)) continue;
        if (k === 'class') e.className = attrs[k];
        else if (k === 'text') e.textContent = attrs[k];
        else if (k === 'html') e.innerHTML = attrs[k];
        else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2), attrs[k]);
        else if (attrs[k] === true) e.setAttribute(k, '');
        else if (attrs[k] !== false && attrs[k] !== null && attrs[k] !== undefined) e.setAttribute(k, attrs[k]);
      }
    }
    if (children) {
      for (var i = 0; i < children.length; i++) {
        var c = children[i];
        if (c === null || c === undefined) continue;
        e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      }
    }
    return e;
  }

  function svg(paths, size) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', size || 18); s.setAttribute('height', size || 18);
    s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2');
    s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < paths.length; i++) {
      var p = document.createElementNS('http://www.w3.org/2000/svg', paths[i][0]);
      for (var k in paths[i][1]) { if (paths[i][1].hasOwnProperty(k)) p.setAttribute(k, paths[i][1][k]); }
      s.appendChild(p);
    }
    return s;
  }
  var ICON = {
    check: function () { return svg([['path', { d: 'M5 12l4 4L19 6' }]]); },
    back: function () { return svg([['path', { d: 'M15 6l-6 6 6 6' }]]); },
    share: function () { return svg([['path', { d: 'M12 3v12' }], ['path', { d: 'M8 7l4-4 4 4' }], ['path', { d: 'M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7' }]]); },
    clock: function () { return svg([['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M12 7v5l3 2' }]], 16); }
  };

  function loadMine() {
    try {
      var raw = window.localStorage ? localStorage.getItem(LS_KEY) : null;
      var o = raw ? JSON.parse(raw) : {};
      return (o && typeof o === 'object') ? o : {};
    } catch (e) { return {}; }
  }
  function saveMine() {
    try { if (window.localStorage) localStorage.setItem(LS_KEY, JSON.stringify(state.mine)); } catch (e) {}
  }
  function mineForBrand() {
    var out = [];
    for (var id in state.mine) {
      if (state.mine.hasOwnProperty(id) && state.mine[id].brand === BRAND) out.push(state.mine[id]);
    }
    return out;
  }

  // ------------------------------------------------------------------
  // API
  // ------------------------------------------------------------------
  function getCsrf() {
    if (state.csrf) return Promise.resolve(state.csrf);
    return fetch(API + '/token', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) { state.csrf = j && j.ok ? j.data.csrf : null; return state.csrf; });
  }

  function post(path, body, retried) {
    return getCsrf().then(function (tok) {
      return fetch(API + path, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-FXC-Token': tok || '' },
        body: JSON.stringify(body || {})
      });
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && !j.ok && j.error === 'csrf' && !retried) { state.csrf = null; return post(path, body, true); }
      return j;
    });
  }

  function refresh() {
    return fetch(API + '/brand/' + encodeURIComponent(BRAND), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.ok) { state.payload = j.data; renderAll(); } })
      .catch(function () {});
  }

  // ------------------------------------------------------------------
  // render: stats + running
  // ------------------------------------------------------------------
  function renderStat() {
    var p = state.payload, host = document.getElementById('fxcStat');
    if (!p || !host) return;
    host.innerHTML = '';
    host.appendChild(el('div', { class: 'fxc-label', text: 'Median payout, timed by players' }));
    if (p.median_min !== null && p.median_min !== undefined) {
      host.appendChild(el('div', { class: 'fxc-big', text: fmtMin(p.median_min) }));
      host.appendChild(el('div', { class: 'fxc-muted', text: 'from ' + p.stopped_count + ' stopped clocks' }));
      var max = 1, k;
      for (k in p.buckets) { if (p.buckets.hasOwnProperty(k) && p.buckets[k] > max) max = p.buckets[k]; }
      var bars = el('div', { class: 'fxc-bars' });
      var order = ['lt24h', 'd1_3', 'd4_7', 'gt7d'];
      for (var i = 0; i < order.length; i++) {
        k = order[i];
        var v = p.buckets[k] || 0;
        var fill = el('span', { class: 'fxc-bar-f' }); fill.style.width = Math.round(v / max * 100) + '%';
        bars.appendChild(el('div', { class: 'fxc-bar' }, [
          el('span', { class: 'fxc-bar-l', text: L.band[k] }),
          el('span', { class: 'fxc-bar-t' }, [fill]),
          el('span', { class: 'fxc-bar-n', text: String(v) })
        ]));
      }
      host.appendChild(bars);
    } else {
      host.appendChild(el('div', { class: 'fxc-big fxc-big-empty', text: '\u2014' }));
      host.appendChild(el('div', { class: 'fxc-muted', text: 'Not enough stopped clocks yet to show a median. Needs ' + THRESHOLD + ', has ' + p.stopped_count + '.' }));
    }
    var counts = el('div', { class: 'fxc-counts' });
    counts.appendChild(el('div', { text: p.unresolved_count + ' clocks never stopped, closed as unresolved after 30 days.' }));
    if (p.refused_count > 0) counts.appendChild(el('div', { class: 'fxc-brick', text: p.refused_count + ' closed as refused.' }));
    if (p.backdated_count > 0) counts.appendChild(el('div', { text: p.backdated_count + ' past payouts logged at grade D, kept out of the median.' }));
    if (p.hidden_count > 0) counts.appendChild(el('div', { text: p.hidden_count + ' excluded by automatic checks.' }));
    host.appendChild(counts);
  }

  function renderRunning() {
    var p = state.payload, host = document.getElementById('fxcRunning');
    if (!p || !host) return;
    host.innerHTML = '';
    host.appendChild(el('div', { class: 'fxc-label', text: 'Running now' }));

    // clocks this browser owns but that are not in the (capped) public list
    var listed = {};
    var i;
    for (i = 0; i < p.running.length; i++) listed[p.running[i].public_id] = true;
    var mine = mineForBrand();
    var extra = [];
    var changed = false;
    for (i = 0; i < mine.length; i++) {
      if (listed[mine[i].public_id]) continue;
      if (p.running_more > 0) {
        // list is capped: the clock may simply be beyond the cap
        extra.push({ public_id: mine[i].public_id, started_at: mine[i].started_at, method: mine[i].method, amount_band: mine[i].amount_band, grade: mine[i].grade || 'D', cc: mine[i].cc || null, unlisted: true });
      } else if ((state.active && state.active.public_id === mine[i].public_id) || (mine[i].local_ts && Date.now() - mine[i].local_ts < 120000)) {
        // being handled right now, or saved less than two minutes ago (the brand endpoint is edge-cached 60 s): keep it
        extra.push({ public_id: mine[i].public_id, started_at: mine[i].started_at, method: mine[i].method, amount_band: mine[i].amount_band, grade: mine[i].grade || 'D', cc: mine[i].cc || null, unlisted: true });
      } else {
        // list is complete and the clock is not in it: closed elsewhere (Telegram, email link, reset)
        delete state.mine[mine[i].public_id]; changed = true;
      }
    }
    if (changed) saveMine();
    var rows = extra.concat(p.running);

    if (!rows.length) {
      host.appendChild(el('p', { class: 'fxc-muted fxc-empty', text: 'No clocks running. Start one the moment you request a withdrawal.' }));
      return;
    }
    var ul = el('ul', { class: 'fxc-list' });
    for (i = 0; i < rows.length; i++) {
      var c = rows[i];
      var isMine = !!state.mine[c.public_id];
      var li = el('li', { class: 'fxc-row' + (isMine ? ' fxc-row-mine' : ''), 'data-id': c.public_id, 'data-start': c.started_at });
      li.appendChild(el('span', { class: 'fxc-dot', 'aria-hidden': 'true' }));
      li.appendChild(el('span', { class: 'fxc-row-main' }, [
        el('span', { class: 'fxc-time', text: fmtSec(Date.now() / 1000 - c.started_at) }),
        el('span', { class: 'fxc-row-sub', text: rowSub(c, isMine ? ' \u00b7 yours' : '') })
      ]));
      li.appendChild(el('span', { class: 'fxc-grade', title: L.grade[c.grade] || '', text: c.grade }));
      if (isMine) {
        li.appendChild(el('button', { type: 'button', class: 'fxc-received', 'data-id': c.public_id, text: 'Received', onclick: onReceived }));
      }
      ul.appendChild(li);
    }
    host.appendChild(ul);
    if (p.running_more > 0) host.appendChild(el('p', { class: 'fxc-muted', text: '+' + p.running_more + ' more running.' }));
  }

  function renderAll() { renderStat(); renderRunning(); }

  function tick() {
    var rows = root.querySelectorAll('.fxc-row[data-start]');
    var now = Date.now() / 1000;
    for (var i = 0; i < rows.length; i++) {
      var t = rows[i].querySelector('.fxc-time');
      if (t) t.textContent = fmtSec(now - parseInt(rows[i].getAttribute('data-start'), 10));
    }
    if (state.active && state.panel === 'started') {
      var big = document.getElementById('fxcLive');
      if (big) big.textContent = fmtSec(now - state.active.started_at);
    }
  }

  // ------------------------------------------------------------------
  // panels
  // ------------------------------------------------------------------
  var panel = document.getElementById('fxcPanel');
  var actions = document.getElementById('fxcActions');

  function openPanel(name, node) {
    state.panel = name;
    panel.innerHTML = '';
    panel.appendChild(node);
    panel.hidden = false;
    actions.hidden = true;
    var top = panel.getBoundingClientRect().top + window.pageYOffset - 24;
    if (top < window.pageYOffset || top > window.pageYOffset + window.innerHeight * 0.6) window.scrollTo({ top: top, behavior: 'smooth' });
  }
  function closePanel() {
    state.panel = null; state.active = null;
    panel.hidden = true; panel.innerHTML = '';
    actions.hidden = false;
  }

  function chips(name, map, keys, value, onChange) {
    var wrap = el('div', { class: 'fxc-chips', role: 'group', 'aria-label': name });
    keys.forEach(function (k) {
      var b = el('button', { type: 'button', class: 'fxc-chip' + (value === k ? ' is-on' : ''), 'data-v': k, 'aria-pressed': value === k ? 'true' : 'false', text: map[k] });
      b.addEventListener('click', function () {
        var all = wrap.querySelectorAll('.fxc-chip');
        for (var i = 0; i < all.length; i++) { all[i].classList.remove('is-on'); all[i].setAttribute('aria-pressed', 'false'); }
        b.classList.add('is-on'); b.setAttribute('aria-pressed', 'true');
        onChange(k);
      });
      wrap.appendChild(b);
    });
    return wrap;
  }
  function q(label, hint, node) {
    var d = el('div', { class: 'fxc-q' }, [el('div', { class: 'fxc-q-l', text: label })]);
    if (hint) d.appendChild(el('div', { class: 'fxc-q-h', text: hint }));
    d.appendChild(node);
    return d;
  }
  function back(label, fn) {
    return el('button', { type: 'button', class: 'fxc-back', onclick: fn }, [ICON.back(), document.createTextNode(label)]);
  }
  function errorLine(msg) { return el('p', { class: 'fxc-error', role: 'alert', text: msg }); }

  // --- start ---------------------------------------------------------
  function openStart() {
    var d = { method: null, amount_band: null, when: 'now', contact_kind: null, contact: '', fec_code_claimed: 0 };
    var box = el('div', { class: 'fxc-form' });
    box.appendChild(back('Back', closePanel));
    box.appendChild(el('h3', { class: 'fxc-h', text: 'Start a clock' }));
    box.appendChild(el('p', { class: 'fxc-muted', text: 'Tap this the moment you request the withdrawal. Nothing to write.' }));
    box.appendChild(q('How are you withdrawing?', null, chips('method', L.method, ['crypto', 'card', 'ewallet', 'bank'], null, function (v) { d.method = v; sync(); })));
    box.appendChild(q('Amount, in your currency', 'Optional.', chips('amount', L.amount, ['lt100', 'b100_500', 'b500_2000', 'gt2000'], null, function (v) { d.amount_band = v; })));
    box.appendChild(q('When did you request it?', null, chips('when', L.when, ['now', 'today', 'yesterday'], 'now', function (v) { d.when = v; })));
    var emailWrap = el('div', { class: 'fxc-email', hidden: true });
    var email = el('input', { type: 'email', class: 'fxc-input', placeholder: 'you@example.com', autocomplete: 'email', 'aria-label': 'Email address' });
    email.addEventListener('input', function () { d.contact = email.value.trim(); sync(); });
    emailWrap.appendChild(email);
    box.appendChild(q('How should we ask whether it arrived?', null, chips('contact', L.contact, ['telegram', 'email', 'none'], null, function (v) { d.contact_kind = v; emailWrap.hidden = v !== 'email'; sync(); })));
    box.appendChild(emailWrap);
    var code = el('label', { class: 'fxc-check' }, [el('input', { type: 'checkbox' }), document.createTextNode(' I used an FEC bonus code at this casino')]);
    code.querySelector('input').addEventListener('change', function (e) { d.fec_code_claimed = e.target.checked ? 1 : 0; });
    box.appendChild(code);
    box.appendChild(el('p', { class: 'fxc-muted fxc-consent', text: 'We keep only what is needed to remind you and to show the time. No name, no account.' }));
    var err = el('div');
    box.appendChild(err);
    var go = el('button', { type: 'button', class: 'btn btn-primary fxc-btn', text: 'Start clock', disabled: true });
    box.appendChild(el('div', { class: 'fxc-actions' }, [go]));
    function sync() {
      var ok = d.method && d.contact_kind && (d.contact_kind !== 'email' || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.contact));
      if (ok) go.removeAttribute('disabled'); else go.setAttribute('disabled', '');
    }
    go.addEventListener('click', function () {
      go.setAttribute('disabled', ''); err.innerHTML = '';
      var body = { brand: BRAND, method: d.method, amount_band: d.amount_band, when: d.when, contact_kind: d.contact_kind, fec_code_claimed: d.fec_code_claimed };
      if (d.contact_kind === 'email') body.contact = d.contact;
      post('/clock', body).then(function (j) {
        if (!j || !j.ok) { err.appendChild(errorLine(j && j.message ? j.message : 'Could not start the clock. Try again.')); sync(); return; }
        var c = { public_id: j.data.public_id, token: j.data.token, brand: BRAND, started_at: j.data.started_at, method: d.method, amount_band: d.amount_band, grade: j.data.grade, cc: j.data.cc || null, contact_kind: d.contact_kind, telegram_link: j.data.telegram_link || null, local_ts: Date.now() };
        state.mine[c.public_id] = c; saveMine();
        track('fxc_start', { fxc_method: d.method, fxc_contact: d.contact_kind, fxc_when: d.when });
        openStarted(c);
        refresh();
      }).catch(function () { err.appendChild(errorLine('Network error. Try again.')); sync(); });
    });
    openPanel('start', box);
  }

  function openStarted(c) {
    state.active = c;
    var box = el('div', { class: 'fxc-form' });
    box.appendChild(el('div', { class: 'fxc-live-l' }, [el('span', { class: 'fxc-dot', 'aria-hidden': 'true' }), document.createTextNode(' Running on ' + NAME)]));
    box.appendChild(el('div', { class: 'fxc-big', id: 'fxcLive', text: fmtSec(Date.now() / 1000 - c.started_at) }));
    box.appendChild(el('div', { class: 'fxc-muted', text: rowSub(c) }));
    var msg;
    if (c.contact_kind === 'telegram') msg = 'Open the FEC Telegram bot once to link this clock. We will then ask on Telegram whether it arrived. Tap Received when the money lands.';
    else if (c.contact_kind === 'email') msg = 'We will email you a link on day 3, 7 and 15. Tap Received when the money lands.';
    else msg = 'Come back to this page on this device and tap Received when the money lands.';
    box.appendChild(el('p', { class: 'fxc-p', text: msg }));
    var acts = el('div', { class: 'fxc-actions' });
    if (c.telegram_link) acts.appendChild(el('a', { class: 'btn btn-primary fxc-btn', href: c.telegram_link, target: '_blank', rel: 'noopener', text: 'Link on Telegram' }));
    acts.appendChild(el('button', { type: 'button', class: 'btn fxc-btn fxc-btn-ghost', text: 'Received', onclick: function () { openStop(c); } }));
    acts.appendChild(el('button', { type: 'button', class: 'btn fxc-btn fxc-btn-ghost', text: 'Back to the page', onclick: closePanel }));
    box.appendChild(acts);
    openPanel('started', box);
  }

  // --- stop ----------------------------------------------------------
  function onReceived(e) {
    var id = e.currentTarget.getAttribute('data-id');
    var c = state.mine[id];
    if (c) openStop(c);
  }

  function openStop(c) {
    state.active = c;
    var a = { kyc: null, docs: null, paid: null, chain: null, txid: '', note: '' };
    var frozen = Date.now() / 1000;
    var box = el('div', { class: 'fxc-form' });
    box.appendChild(back('Keep it running', closePanel));
    box.appendChild(el('div', { class: 'fxc-live-l fxc-green' }, [ICON.check(), document.createTextNode(' Stopped')]));
    box.appendChild(el('div', { class: 'fxc-big', text: fmtSec(frozen - c.started_at) }));
    box.appendChild(el('div', { class: 'fxc-muted', text: rowSub(c) }));
    box.appendChild(el('p', { class: 'fxc-p', text: "Three taps while it's fresh." }));
    box.appendChild(q('Did they ask for KYC?', null, chips('kyc', L.kyc, ['not_asked', 'deposit', 'withdrawal', 'after_win'], null, function (v) { a.kyc = v; sync(); })));
    box.appendChild(q('Documents beyond your ID?', null, chips('docs', L.docs, ['none', 'address', 'source_of_funds', 'selfie'], null, function (v) { a.docs = v; sync(); })));
    box.appendChild(q('What arrived?', null, chips('paid', L.paid, ['full', 'reduced', 'split'], null, function (v) { a.paid = v; sync(); })));
    if (c.method === 'crypto') {
      var chainSel = chips('chain', L.chain, ['btc', 'eth', 'tron', 'ltc'], null, function (v) { a.chain = v; });
      var tx = el('input', { type: 'text', class: 'fxc-input', placeholder: 'Paste the transaction id', autocomplete: 'off', spellcheck: 'false', 'aria-label': 'Transaction id' });
      tx.addEventListener('input', function () { a.txid = tx.value.trim(); });
      box.appendChild(q('Transaction id', 'Optional. Locks the stop time to the chain timestamp and lifts this clock to grade A.', el('div', {}, [chainSel, tx])));
    }
    var note = el('textarea', { class: 'fxc-input', rows: 2, maxlength: 200, 'aria-label': 'One line for a friend' });
    var cnt = el('div', { class: 'fxc-count', text: '0/200' });
    note.addEventListener('input', function () { a.note = note.value.slice(0, 200); cnt.textContent = a.note.length + '/200'; });
    box.appendChild(q('One line for a friend', 'Optional. Shown after moderation, never counted.', el('div', {}, [note, cnt])));
    var err = el('div');
    box.appendChild(err);
    var go = el('button', { type: 'button', class: 'btn btn-primary fxc-btn', text: 'Stop clock', disabled: true });
    var refuse = el('button', { type: 'button', class: 'fxc-link fxc-brick', text: 'They refused the withdrawal, close this clock' });
    box.appendChild(el('div', { class: 'fxc-actions' }, [go]));
    box.appendChild(refuse);
    function sync() { if (a.kyc && a.docs && a.paid) go.removeAttribute('disabled'); else go.setAttribute('disabled', ''); }
    go.addEventListener('click', function () {
      go.setAttribute('disabled', ''); err.innerHTML = '';
      var body = { kyc: a.kyc, docs: a.docs, paid: a.paid, note: a.note };
      if (c.method === 'crypto' && a.txid) {
        if (!a.chain) { err.appendChild(errorLine('Pick the chain for that transaction id, or clear the id.')); sync(); return; }
        body.chain = a.chain; body.txid = a.txid;
      }
      post('/clock/' + c.token + '/stop', body).then(function (j) {
        if (!j || !j.ok) {
          if (j && (j.error === 'clock_closed' || j.error === 'token_invalid')) { delete state.mine[c.public_id]; saveMine(); closePanel(); refresh(); return; }
          err.appendChild(errorLine(j && j.message ? j.message : 'Could not stop the clock. Try again.')); sync(); return;
        }
        delete state.mine[c.public_id]; saveMine();
        track('fxc_stop', { fxc_method: c.method, fxc_grade: j.data.grade, fxc_counted: j.data.counted ? 1 : 0, fxc_txid: j.data.txid_status !== 'none' ? 1 : 0 });
        openReceipt(j.data, c);
        refresh();
      }).catch(function () { err.appendChild(errorLine('Network error. Try again.')); sync(); });
    });
    refuse.addEventListener('click', function () {
      if (!window.confirm('Close this clock as refused? This cannot be undone.')) return;
      post('/clock/' + c.token + '/refuse', {}).then(function (j) {
        delete state.mine[c.public_id]; saveMine();
        track('fxc_refuse', { fxc_method: c.method });
        closePanel(); refresh();
      }).catch(function () {});
    });
    openPanel('stop', box);
  }

  // --- receipt -------------------------------------------------------
  function openReceipt(r, c) {
    state.active = null;
    var box = el('div', { class: 'fxc-form' });
    var card = el('div', { class: 'fxc-receipt' });
    card.appendChild(el('div', { class: 'fxc-live-l fxc-green' }, [ICON.check(), document.createTextNode(' Received')]));
    card.appendChild(el('div', { class: 'fxc-big', text: fmtMin(r.duration_min) }));
    card.appendChild(el('div', { class: 'fxc-p', text: NAME }));
    card.appendChild(el('div', { class: 'fxc-muted', text: rowSub(c) }));
    card.appendChild(el('div', { class: 'fxc-grade-line' }, [el('span', { class: 'fxc-grade', text: r.grade }), document.createTextNode(' ' + (L.grade[r.grade] || ''))]));
    if (r.txid_status === 'pending') card.appendChild(el('div', { class: 'fxc-muted', text: 'Transaction id queued for verification. Grade A once it checks out.' }));
    card.appendChild(el('div', { class: 'fxc-receipt-foot', text: 'Timed by FEC, ' + new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }));
    box.appendChild(card);

    var shift;
    if (!r.counted) shift = 'Recorded, but excluded from the numbers by automatic checks.';
    else if (r.median_before !== null && r.median_after !== null) shift = 'Your clock moved the median from ' + fmtMin(r.median_before) + ' to ' + fmtMin(r.median_after) + ', now from ' + r.stopped_count + ' stopped clocks.';
    else if (r.median_after !== null) shift = 'Your clock completed the set: the median now shows, ' + fmtMin(r.median_after) + ' from ' + r.stopped_count + ' stopped clocks.';
    else shift = 'Counted. ' + r.stopped_count + ' of ' + THRESHOLD + ' clocks needed before the median shows.';
    box.appendChild(el('div', { class: 'fxc-shift' }, [el('div', { class: 'fxc-shift-t', text: 'Your clock moved the number.' }), el('div', { text: shift })]));

    var receiptUrl = r.receipt_url || (location.href.split('#')[0] + '#fxclock');
    var shareText = NAME + ': ' + (L.method[c.method] || c.method).toLowerCase() + ' payout received in ' + fmtMin(r.duration_min) + ', timed by FEC (grade ' + r.grade + '). ' + receiptUrl;
    var share = el('button', { type: 'button', class: 'btn btn-primary fxc-btn' }, [ICON.share(), document.createTextNode(' Share receipt')]);
    share.addEventListener('click', function () {
      track('fxc_share', { fxc_grade: r.grade });
      if (navigator.share) { navigator.share({ title: 'FXClock receipt', text: shareText }).catch(function () {}); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(shareText).then(function () { share.textContent = 'Copied'; }).catch(function () {});
    });
    var acts = [share];
    if (r.receipt_url) acts.push(el('a', { class: 'btn fxc-btn fxc-btn-ghost', href: r.receipt_url, text: 'Open receipt page' }));
    acts.push(el('button', { type: 'button', class: 'btn fxc-btn fxc-btn-ghost', text: 'Back to the page', onclick: closePanel }));
    box.appendChild(el('div', { class: 'fxc-actions' }, acts));
    openPanel('receipt', box);
  }

  // --- backdated -----------------------------------------------------
  function openPast() {
    var d = { method: null, duration_band: null };
    var box = el('div', { class: 'fxc-form' });
    box.appendChild(back('Back', closePanel));
    box.appendChild(el('h3', { class: 'fxc-h', text: 'Log a past payout' }));
    box.appendChild(el('p', { class: 'fxc-muted', text: 'Past payouts count at grade D and stay out of the headline median.' }));
    box.appendChild(q('How did you withdraw?', null, chips('method', L.method, ['crypto', 'card', 'ewallet', 'bank'], null, function (v) { d.method = v; sync(); })));
    box.appendChild(q('How long did it take?', null, chips('band', L.band, ['lt24h', 'd1_3', 'd4_7', 'gt7d'], null, function (v) { d.duration_band = v; sync(); })));
    var err = el('div');
    box.appendChild(err);
    var go = el('button', { type: 'button', class: 'btn btn-primary fxc-btn', text: 'Log payout', disabled: true });
    box.appendChild(el('div', { class: 'fxc-actions' }, [go]));
    function sync() { if (d.method && d.duration_band) go.removeAttribute('disabled'); else go.setAttribute('disabled', ''); }
    go.addEventListener('click', function () {
      go.setAttribute('disabled', ''); err.innerHTML = '';
      post('/clock/backdated', { brand: BRAND, method: d.method, duration_band: d.duration_band }).then(function (j) {
        if (!j || !j.ok) { err.appendChild(errorLine(j && j.message ? j.message : 'Could not log the payout. Try again.')); sync(); return; }
        track('fxc_backdated', { fxc_method: d.method, fxc_band: d.duration_band });
        closePanel(); refresh();
      }).catch(function () { err.appendChild(errorLine('Network error. Try again.')); sync(); });
    });
    openPanel('past', box);
  }

  // ------------------------------------------------------------------
  // boot
  // ------------------------------------------------------------------
  var ccs = root.querySelectorAll('.fxc-cc[data-cc]');
  for (var ci = 0; ci < ccs.length; ci++) ccs[ci].textContent = countryName(ccs[ci].getAttribute('data-cc'));
  try { state.payload = JSON.parse(root.getAttribute('data-payload') || 'null'); } catch (e) { state.payload = null; }
  if (state.payload) renderAll();

  actions.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-fxc]') : null;
    if (!b) return;
    if (b.getAttribute('data-fxc') === 'start') { track('fxc_start_open', { fxc_source: 'section' }); openStart(); }
    if (b.getAttribute('data-fxc') === 'past') { track('fxc_past_open', { fxc_source: 'section' }); openPast(); }
  });

  var viewed = false;
  function markViewed() { if (viewed) return; viewed = true; track('fxc_view', { fxc_stopped: state.payload ? state.payload.stopped_count : null }); }
  if (window.IntersectionObserver) {
    var io = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) { if (entries[i].isIntersecting) { markViewed(); io.disconnect(); break; } }
    }, { threshold: 0.3 });
    io.observe(root);
  } else {
    markViewed();
  }

  setInterval(tick, 1000);
  setInterval(refresh, 60000);
  refresh();

  // Ribbon deep link: #fxc-start scrolls to the section and opens the start panel.
  function startFromHash(source) {
    if (location.hash !== '#fxc-start') return false;
    if (window.history && history.replaceState) history.replaceState(null, '', location.pathname + location.search + '#fxclock');
    var top = root.getBoundingClientRect().top + window.pageYOffset - 16;
    window.scrollTo({ top: top, behavior: 'smooth' });
    track('fxc_start_open', { fxc_source: source || 'hash' });
    openStart();
    return true;
  }
  var ribbon = document.getElementById('fxcRibbon');
  if (ribbon) {
    ribbon.addEventListener('click', function (ev) {
      var a = ev.target.closest ? ev.target.closest('a[href="#fxc-start"]') : null;
      if (!a) return;
      ev.preventDefault();
      location.hash = '#fxc-start';
      startFromHash('ribbon');
    });
  }
  // the hero CTA "Time your withdrawal" links to #fxc-start too: report it as hero when the hash is set by a click on it
  var heroCta = document.querySelector('a[href="#fxc-start"]:not(.fxc-ribbon-link)');
  if (heroCta) heroCta.addEventListener('click', function (ev) { ev.preventDefault(); location.hash = '#fxc-start'; startFromHash('hero'); });
  startFromHash('hash');

  // Arrival via magic link: /fxclock/r/<token> redirects here with #fxc=<token>.
  // Adopt the clock on this device and open the stop panel; the hash never reaches the server.
  var hm = /(?:^|[#&])fxc=([A-Za-z0-9_-]{40,50})(?:&|$)/.exec(location.hash || '');
  if (hm) {
    var tok = hm[1];
    if (window.history && history.replaceState) history.replaceState(null, '', location.pathname + location.search + '#fxclock');
    fetch(API + '/clock/' + tok, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok || j.data.brand !== BRAND) return;
        var cd = j.data;
        if (cd.status === 'received' && cd.receipt_url) { location.href = cd.receipt_url; return; }
        if (cd.status !== 'running' || cd.kind !== 'live') return;
        var c = { public_id: cd.public_id, token: tok, brand: BRAND, started_at: cd.started_at, method: cd.method, amount_band: cd.amount_band, grade: cd.grade, cc: cd.cc || null, contact_kind: cd.contact_kind, local_ts: Date.now() };
        state.mine[c.public_id] = c; saveMine();
        track('fxc_link_arrival', { fxc_contact: cd.contact_kind });
        renderRunning();
        openStop(c);
      }).catch(function () {});
  }
  }

  // The script is injected in <head> by JDocument::addScript, so wait for the DOM.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', main);
  } else {
    main();
  }
})();