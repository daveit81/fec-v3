/* ============================================================================
   com_authors V2.4 — dashboard.js (v2.4.2)
   No frameworks, vanilla JS. Targets Joomla 2.5/3.x runtime.
   Expects window.FXDashboard { endpoint, token, imageBase } set inline.

   v2.4.1 — Joomla script ordering: defer init to DOMContentLoaded so
            window.FXDashboard (emitted by addScriptDeclaration AFTER
            external scripts) is reliably present.

   v2.4.2 — Wayback archive: switched from server-side cURL (rate-limited
            by hosting IPs) to browser-side flow. Click "Archive ↗" now:
              1) opens https://web.archive.org/save/<url> in a new tab
                 (user's residential IP triggers the save),
              2) polls archive.org/wayback/available (CORS) every 5s for
                 90s, auto-filling the archived URL field when a fresh
                 snapshot appears,
              3) falls back to manual copy/paste from the Wayback tab.
   ============================================================================ */
(function () {
  'use strict';

  function init() {
    if (!window.FXDashboard) {
      console.error('[dashx] FXDashboard config not found (still)');
      return;
    }

    var ENDPOINT   = window.FXDashboard.endpoint;
    var TOKEN      = window.FXDashboard.token;
    var IMAGE_BASE = window.FXDashboard.imageBase || '/images/rtp/';

    var panel      = document.getElementById('dashx-panel');
    var panelBody  = document.getElementById('dashx-panel-body');
    var panelTitle = document.getElementById('dashx-panel-title');
    var obsModal   = document.getElementById('dashx-obs-modal');
    var obsForm    = document.getElementById('dashx-obs-form');
    var obsPreview = document.getElementById('dashx-obs-preview');
    var toastEl    = document.getElementById('dashx-toast');
    var listEl     = document.getElementById('dashx-list');

    // Belt-and-braces: if any required element is missing, log it loudly
    // instead of throwing a generic null TypeError on the first listener.
    var missing = [];
    if (!panel)      missing.push('dashx-panel');
    if (!panelBody)  missing.push('dashx-panel-body');
    if (!panelTitle) missing.push('dashx-panel-title');
    if (!obsModal)   missing.push('dashx-obs-modal');
    if (!obsForm)    missing.push('dashx-obs-form');
    if (!obsPreview) missing.push('dashx-obs-preview');
    if (!toastEl)    missing.push('dashx-toast');
    if (!listEl)     missing.push('dashx-list');
    if (missing.length) {
      console.error('[dashx] missing DOM elements:', missing.join(', '));
      return;
    }

    // Current slot context (set when panel opens)
    var current = { gid: 0, name: '', slot: null, observations: [], casinos: [], pendingScreenshot: null };

    // ----------- helpers ----------------------------------------------------

    function esc(s) {
      if (s === null || s === undefined) return '';
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    // Render <select> for verified_by_author_id using window.FXDashboard.authors
    // (passed inline from PHP via addScriptDeclaration). Marks the current
    // selection if it matches. Defined at module scope so accesses window.*
    // directly rather than the FXD alias (which is only in scope inside
    // the DOMContentLoaded init closure).
    function renderAuthorSelect(selectedId) {
      var cfg = window.FXDashboard || {};
      var authors = (cfg.authors && cfg.authors.length) ? cfg.authors : [];
      var sel = selectedId !== null && selectedId !== undefined ? String(selectedId) : '';
      var html = '<select name="verified_by_author_id">';
      html += '<option value="">— Select author —</option>';
      for (var i = 0; i < authors.length; i++) {
        var a = authors[i];
        var aid = String(a.id);
        html += '<option value="' + esc(aid) + '"' + (aid === sel ? ' selected' : '') + '>' + esc(a.name) + '</option>';
      }
      html += '</select>';
      return html;
    }

    function toast(msg, isError) {
      toastEl.textContent = msg;
      toastEl.classList.toggle('is-error', !!isError);
      toastEl.classList.add('is-show');
      clearTimeout(toast._t);
      toast._t = setTimeout(function () { toastEl.classList.remove('is-show'); }, 2800);
    }

    /**
     * Joomla AJAX POST helper. Adds task + token. Returns parsed JSON.
     * For multipart, pass a FormData; otherwise an object literal.
     */
    function ajax(task, data) {
      var url = ENDPOINT + (ENDPOINT.indexOf('?') === -1 ? '?' : '&')
        + 'task=' + encodeURIComponent('dashboard.' + task);
      var body, headers = {};

      if (data instanceof FormData) {
        data.append(TOKEN, '1');
        body = data;
      } else {
        body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) {
          var v = data[k];
          if (v === null || v === undefined) return;
          body.append(k, v);
        });
        body.append(TOKEN, '1');
        headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
      }

      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: body
      }).then(function (r) {
        return r.text().then(function (txt) {
          try { return JSON.parse(txt); }
          catch (e) {
            var m = txt.match(/\{[\s\S]*\}/);
            if (m) { try { return JSON.parse(m[0]); } catch (e2) {} }
            console.error('[dashx] non-JSON response from', task, txt.substring(0, 400));
            throw new Error('Bad JSON');
          }
        });
      });
    }

    // ----------- slot detail open/close -------------------------------------

    function openPanel(gid, name) {
      current.gid = gid;
      current.name = name;
      current.pendingScreenshot = null;
      panelTitle.textContent = name;
      panelBody.innerHTML = '<p class="dashx-loading">Loading…</p>';
      panel.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      ajax('fetchSlot', { gid: gid })
        .then(function (res) {
          if (!res.ok) {
            panelBody.innerHTML = '<p class="dashx-loading">Error: ' + esc(res.error) + '</p>';
            return;
          }
          current.slot         = res.slot;
          current.observations = res.observations || [];
          current.casinos      = res.casinos || [];
          renderPanel();
        })
        .catch(function (err) {
          console.error('fetchSlot failed:', err);
          panelBody.innerHTML = '<p class="dashx-loading">Network error (see console)</p>';
        });
    }

    function closePanel() {
      panel.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    function renderPanel() {
      var s = current.slot;
      var variantsStr = '';
      if (s.rtp_variants_json) {
        try {
          var arr = JSON.parse(s.rtp_variants_json);
          if (Array.isArray(arr)) variantsStr = arr.join(', ');
          else variantsStr = s.rtp_variants_json;
        } catch (e) { variantsStr = s.rtp_variants_json; }
      }
      var isPublished = parseInt(s.published, 10) === 1;
      var isRetired = parseInt(s.retired, 10) === 1;
      var publicUrl = s.slug ? ('/slots/' + encodeURIComponent(s.slug)) : '';
      var html = '' +
        '<div class="dashx-context-meta">' +
          '<div class="label">' + esc(s.provider_name || '') + ' · gid #' + esc(s.gid) + '</div>' +
          '<div>' + esc(s.active_bonus_count || 0) + ' active bonuses</div>' +
        '</div>' +

        '<div class="dashx-publish-row">' +
          '<label class="dashx-publish-toggle">' +
            '<input type="checkbox" id="dashx-publish-cb"' + (isPublished ? ' checked' : '') + '>' +
            '<span>Publish to <code>/slots/' + esc(s.slug || '&lt;slug&gt;') + '</code></span>' +
          '</label>' +
          (isPublished && publicUrl ? ' <a class="dashx-view-public" href="' + esc(publicUrl) + '" target="_blank" rel="noopener">View public page ↗</a>' : '') +
        '</div>' +

        '<div class="dashx-retired-row' + (isRetired ? ' is-on' : '') + '">' +
          '<label class="dashx-retired-toggle">' +
            '<input type="checkbox" id="dashx-retired-cb"' + (isRetired ? ' checked' : '') + '>' +
            '<span>Mark as <b>retired</b> (banner + noindex on public page)</span>' +
          '</label>' +
        '</div>' +

        '<form class="dashx-rtp-form" id="dashx-rtp-form">' +
          '<input type="hidden" name="gid" value="' + esc(s.gid) + '">' +
          '<input type="hidden" name="published" id="dashx-published-input" value="' + (isPublished ? '1' : '0') + '">' +
          '<input type="hidden" name="retired" id="dashx-retired-input" value="' + (isRetired ? '1' : '0') + '">' +

          '<label class="dashx-retired-reason' + (isRetired ? '' : ' is-hidden') + '" id="dashx-retired-reason-wrap">' +
            '<span>Retired reason <small class="dashx-hint">(brief — shown to readers, e.g. "Acquired by Evolution, delisted from US market")</small></span>' +
            '<input type="text" name="retired_reason" maxlength="255" value="' + esc(s.retired_reason || '') + '">' +
          '</label>' +

          '<label><span>URL slug <small class="dashx-hint">(auto-generated from game name; edit if needed)</small></span>' +
            '<input type="text" name="slug" value="' + esc(s.slug || '') + '" pattern="[a-z0-9-]+" placeholder="auto">' +
          '</label>' +

          '<div class="row-2">' +
            '<label><span>RTP max (%)</span>' +
              '<input type="number" name="rtp_theoretical_max" step="0.01" min="50" max="100" inputmode="decimal" placeholder="96.50" value="' + esc(s.rtp_theoretical_max || '') + '"></label>' +
            '<label><span>RTP min (%)</span>' +
              '<input type="number" name="rtp_theoretical_min" step="0.01" min="50" max="100" inputmode="decimal" placeholder="88.12" value="' + esc(s.rtp_theoretical_min || '') + '"></label>' +
          '</div>' +

          '<label><span>RTP variants (tier list)</span>' +
            '<input type="text" name="rtp_variants_json" placeholder="96.50, 95.50, 94.50, 88.12" value="' + esc(variantsStr) + '">' +
            '<small class="dashx-hint">Comma list or JSON array. Pragmatic/some providers ship multiple tiers.</small>' +
          '</label>' +

          '<div class="row-2">' +
            '<label><span>Volatility</span>' +
              '<select name="volatility">' +
                '<option value="">—</option>' +
                ['low','low-med','medium','med-high','high','very-high','extreme'].map(function (v) {
                  return '<option value="' + v + '"' + (s.volatility === v ? ' selected' : '') + '>' + v + '</option>';
                }).join('') +
              '</select></label>' +
            '<label><span>Max win (×)</span>' +
              '<input type="number" name="max_win_x" inputmode="numeric" placeholder="5000" value="' + esc(s.max_win_x || '') + '"></label>' +
          '</div>' +

          '<div class="row-2">' +
            '<label><span>Reels</span>' +
              '<input type="number" name="reels" inputmode="numeric" placeholder="5" value="' + esc(s.reels || '') + '"></label>' +
            '<label><span>Paylines</span>' +
              '<input type="text" name="paylines" placeholder="20 / Ways / Cluster" value="' + esc(s.paylines || '') + '"></label>' +
          '</div>' +

          '<label><span>Release year</span>' +
            '<input type="number" name="release_year" inputmode="numeric" placeholder="2012" value="' + esc(s.release_year || '') + '"></label>' +
          '</label>' +

          '<label><span>Primary source URL <small class="dashx-hint">(provider game page)</small></span>' +
            '<div class="url-row">' +
              '<input type="url" name="primary_source_url" placeholder="https://provider.com/games/..." value="' + esc(s.primary_source_url || '') + '">' +
              '<button type="button" class="dashx-btn-archive" data-action="archive-source">Archive ↗</button>' +
            '</div>' +
          '</label>' +

          '<label><span>Archived URL (Wayback)</span>' +
            '<input type="url" name="primary_source_archived_url" placeholder="https://web.archive.org/web/..." value="' + esc(s.primary_source_archived_url || '') + '">' +
          '</label>' +

          '<label><span>Certificate URL <small class="dashx-hint">(GLI / eCOGRA / iTechLabs if any)</small></span>' +
            '<input type="url" name="certificate_url" placeholder="https://..." value="' + esc(s.certificate_url || '') + '">' +
          '</label>' +

          '<label><span>Last verified</span>' +
            '<input type="date" name="last_verified_date" value="' + esc(s.last_verified_date && s.last_verified_date !== '0000-00-00' ? s.last_verified_date : '') + '">' +
          '</label>' +

          '<label><span>Verified by</span>' +
            renderAuthorSelect(s.verified_by_author_id) +
          '</label>' +

          '<label><span>Notes</span>' +
            '<textarea name="notes" rows="2" placeholder="e.g. Pragmatic offers tier-low 88.12% — verify with operator">' + esc(s.notes || '') + '</textarea>' +
          '</label>' +

          '<div class="dashx-form-actions">' +
            '<button type="button" class="dashx-btn-ghost" data-close="panel">Close</button>' +
            '<button type="submit" class="dashx-btn-primary">Save RTP data</button>' +
          '</div>' +
        '</form>' +

        '<h3 class="dashx-section-head">Per-casino observations <span class="count">' + current.observations.length + '</span></h3>' +
        '<ul class="dashx-obs-list" id="dashx-obs-list">' +
          (current.observations.length === 0
            ? '<li class="dashx-empty">No observations yet.</li>'
            : current.observations.map(renderObsRow).join('')) +
        '</ul>' +

        '<button type="button" class="dashx-btn-add" data-action="open-obs-modal">+ Add observation</button>';

      panelBody.innerHTML = html;
    }

    function renderObsRow(o) {
      var d = '';
      if (o.observed_date && o.observed_date !== '0000-00-00') d = o.observed_date;
      var img = o.screenshot_url
        ? '<img src="' + esc(o.screenshot_url) + '" alt="">'
        : '<div style="width:48px;height:48px;background:#eee;border-radius:6px;flex:0 0 auto"></div>';
      return '' +
        '<li class="dashx-obs-row" data-obs-id="' + esc(o.id) + '">' +
          img +
          '<div class="obs-main">' +
            '<div class="obs-casino">' + esc(o.casino_name || ('casino #' + o.cid)) + '</div>' +
            '<div class="obs-meta">' +
              '<span class="obs-rtp">' + esc(o.rtp_observed || '?') + '%</span> · ' +
              esc(o.evidence_type || '') + ' · ' + esc(d) +
            '</div>' +
          '</div>' +
          '<button type="button" class="obs-delete" data-action="delete-obs" aria-label="Delete observation">×</button>' +
        '</li>';
    }

    // ----------- observation modal ------------------------------------------

    function openObsModal() {
      if (!current.gid) return;
      obsForm.reset();
      obsForm.querySelector('input[name="gid"]').value = current.gid;
      obsForm.querySelector('input[name="observed_date"]').value = new Date().toISOString().slice(0, 10);
      obsPreview.hidden = true;
      current.pendingScreenshot = null;

      var sel = obsForm.querySelector('select[name="cid"]');
      sel.innerHTML = '<option value="">Select casino…</option>' +
        current.casinos.map(function (c) {
          return '<option value="' + esc(c.cid) + '">' + esc(c.name) + '</option>';
        }).join('');

      obsModal.setAttribute('aria-hidden', 'false');
    }

    function closeObsModal() {
      obsModal.setAttribute('aria-hidden', 'true');
      current.pendingScreenshot = null;
    }

    // ----------- screenshot client-side resize ------------------------------

    function resizeImageClientSide(file, maxLong) {
      return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onerror = reject;
        reader.onload = function (e) {
          var img = new Image();
          img.onerror = reject;
          img.onload = function () {
            var w = img.width;
            var h = img.height;
            var long = Math.max(w, h);
            var ratio = long > maxLong ? maxLong / long : 1;
            var nw = Math.round(w * ratio);
            var nh = Math.round(h * ratio);
            var canvas = document.createElement('canvas');
            canvas.width = nw; canvas.height = nh;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, nw, nh);
            ctx.drawImage(img, 0, 0, nw, nh);
            canvas.toBlob(function (blob) {
              if (!blob) { reject(new Error('toBlob failed')); return; }
              resolve(blob);
            }, 'image/jpeg', 0.85);
          };
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });
    }

    function handleScreenshotChange(input) {
      var file = input.files && input.files[0];
      if (!file) return;
      if (!/^image\//.test(file.type)) {
        toast('Not an image file', true);
        input.value = '';
        return;
      }
      resizeImageClientSide(file, 1600)
        .then(function (blob) {
          current.pendingScreenshot = blob;
          var url = URL.createObjectURL(blob);
          obsPreview.querySelector('img').src = url;
          obsPreview.hidden = false;
        })
        .catch(function (err) {
          toast('Could not process image', true);
          console.error(err);
        });
    }

    function clearScreenshot() {
      current.pendingScreenshot = null;
      obsPreview.hidden = true;
      var inp = obsForm.querySelector('input[name="screenshot"]');
      if (inp) inp.value = '';
    }

    // ----------- save handlers ----------------------------------------------

    function handleRtpSubmit(form) {
      var data = {};
      new FormData(form).forEach(function (v, k) { data[k] = v; });
      return ajax('saveRtp', data).then(function (res) {
        if (!res.ok) {
          toast('Save failed: ' + (res.error || 'unknown'), true);
          return;
        }
        toast('RTP data saved');
        updateListRow(current.gid, {
          rtp_theoretical_max: data.rtp_theoretical_max,
          last_verified_date: res.last_verified_date || data.last_verified_date
        });
      });
    }

    function handleObsSubmit(form) {
      var data = {};
      new FormData(form).forEach(function (v, k) {
        if (k !== 'screenshot') data[k] = v;
      });
      var uploadStep = Promise.resolve({ ok: true });
      if (current.pendingScreenshot) {
        var fd = new FormData();
        fd.append('gid', data.gid);
        fd.append('cid', data.cid);
        fd.append('screenshot', current.pendingScreenshot, 'screenshot.jpg');
        uploadStep = ajax('uploadScreenshot', fd);
      }
      return uploadStep.then(function (up) {
        if (current.pendingScreenshot && !up.ok) {
          toast('Upload failed: ' + (up.error || 'unknown'), true);
          return;
        }
        if (up.ok && up.screenshot_path) data.screenshot_path = up.screenshot_path;
        return ajax('saveObservation', data);
      }).then(function (res) {
        if (!res || !res.ok) {
          toast('Save failed: ' + (res && res.error ? res.error : 'unknown'), true);
          return;
        }
        toast('Observation saved');
        closeObsModal();
        openPanel(current.gid, current.name);
      });
    }

    /**
     * Archive button — browser-side flow (v2.4.2).
     *
     * Server-side save via cURL hits Wayback rate-limits from hosting IPs.
     * Instead we:
     *   1. Open https://web.archive.org/save/<url> in a new tab. The user's
     *      residential browser triggers the save (no rate-limit).
     *   2. Poll the public availability API (CORS-enabled) every 5s for up
     *      to 90s, looking for a snapshot taken in the last 5 min.
     *   3. When found, auto-fill the archived URL field. Otherwise the user
     *      can copy/paste the URL from the still-open Wayback tab.
     */
    function handleArchiveClick(btn) {
      var form = btn.closest('form');
      var srcField = form.querySelector('input[name="primary_source_url"]');
      var dstField = form.querySelector('input[name="primary_source_archived_url"]');
      var url = (srcField.value || '').trim();
      if (!url) {
        toast('Enter a primary source URL first', true);
        return;
      }

      // 1. Open the save page in a new tab so the browser triggers the
      //    archive from the user's IP. 'noopener' for safety.
      var saveUrl = 'https://web.archive.org/save/' + url;
      window.open(saveUrl, '_blank', 'noopener');
      toast('Wayback opened in new tab — polling for fresh snapshot…');

      // 2. Poll availability API. archive.org returns CORS-enabled JSON.
      btn.disabled = true;
      var originalLabel = btn.textContent;
      var attempts = 0;
      var maxAttempts = 18;      // 18 × 5s = 90s
      var pollIntervalMs = 5000;
      var freshWindowSec = 600;  // accept snapshots up to 10 min old

      function tick() {
        attempts++;
        btn.textContent = 'Polling ' + attempts + '/' + maxAttempts + '…';

        if (attempts > maxAttempts) {
          btn.disabled = false;
          btn.textContent = originalLabel;
          toast('No fresh snapshot detected. Copy the URL from the Wayback tab and paste it manually.', true);
          return;
        }

        fetch('https://archive.org/wayback/available?url=' + encodeURIComponent(url), {
          credentials: 'omit',
          cache: 'no-store'
        })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            var c = json && json.archived_snapshots && json.archived_snapshots.closest;
            if (!c || !c.url || !c.timestamp || c.timestamp.length < 14) {
              setTimeout(tick, pollIntervalMs);
              return;
            }
            // Parse YYYYMMDDHHMMSS as UTC
            var ts = c.timestamp;
            var snapDate = new Date(Date.UTC(
              parseInt(ts.substr(0, 4), 10),
              parseInt(ts.substr(4, 2), 10) - 1,
              parseInt(ts.substr(6, 2), 10),
              parseInt(ts.substr(8, 2), 10),
              parseInt(ts.substr(10, 2), 10),
              parseInt(ts.substr(12, 2), 10)
            ));
            var ageSec = (Date.now() - snapDate.getTime()) / 1000;
            if (ageSec >= 0 && ageSec < freshWindowSec) {
              // Fresh snapshot ready
              dstField.value = String(c.url).replace(/^http:/, 'https:');
              btn.disabled = false;
              btn.textContent = originalLabel;
              toast('Snapshot auto-filled (' + Math.round(ageSec) + 's old)');
              return;
            }
            setTimeout(tick, pollIntervalMs);
          })
          .catch(function (err) {
            // CORS or transient — keep trying
            setTimeout(tick, pollIntervalMs);
          });
      }
      // Wait 5s before first poll (give Wayback time to start saving)
      setTimeout(tick, pollIntervalMs);
    }

    function handleDeleteObs(btn) {
      var row = btn.closest('.dashx-obs-row');
      var id = row && row.getAttribute('data-obs-id');
      if (!id) return;
      if (!confirm('Delete this observation? The screenshot will also be removed.')) return;
      ajax('deleteObservation', { id: id }).then(function (res) {
        if (!res.ok) { toast('Delete failed', true); return; }
        toast('Observation deleted');
        openPanel(current.gid, current.name);
      });
    }

    function updateListRow(gid, patch) {
      var item = listEl.querySelector('.dashx-item[data-gid="' + gid + '"]');
      if (!item) return;
      var status = item.querySelector('.dashx-status');
      if (status && patch.rtp_theoretical_max) {
        status.className = 'dashx-status st-ok';
        status.textContent = '✓';
        status.title = 'Verified';
      }
    }

    // ----------- event delegation -------------------------------------------

    listEl.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.dashx-item-tap');
      if (!btn) return;
      var li = btn.closest('.dashx-item');
      if (!li) return;
      var gid = parseInt(li.getAttribute('data-gid'), 10);
      var name = li.getAttribute('data-name') || '';
      openPanel(gid, name);
    });

    panel.addEventListener('click', function (e) {
      var closeAttr = e.target.getAttribute && e.target.getAttribute('data-close');
      if (closeAttr === 'panel' || e.target === panel) { closePanel(); return; }
      var action = e.target.getAttribute && e.target.getAttribute('data-action');
      if (action === 'archive-source') { handleArchiveClick(e.target); return; }
      if (action === 'open-obs-modal') { openObsModal(); return; }
      if (action === 'delete-obs') { handleDeleteObs(e.target); return; }
    });

    // ============ ADD-SLOT MODAL ============
    var addBtn       = document.getElementById('dashx-add-btn');
    var addModal     = document.getElementById('dashx-add-modal');
    var addSearchEl  = document.getElementById('dashx-add-search');
    var addResultsEl = document.getElementById('dashx-add-results');

    function openAddModal() {
      if (!addModal) return;
      addModal.hidden = false;
      addModal.setAttribute('aria-hidden', 'false');
      if (addResultsEl) addResultsEl.innerHTML = '';
      if (addSearchEl) {
        addSearchEl.value = '';
        // Slight delay so iOS Safari focuses correctly after the modal paints
        setTimeout(function () { addSearchEl.focus(); }, 50);
      }
    }
    function closeAddModal() {
      if (!addModal) return;
      addModal.hidden = true;
      addModal.setAttribute('aria-hidden', 'true');
    }

    // Debounced search
    var addSearchTimer = null;
    function scheduleAddSearch() {
      clearTimeout(addSearchTimer);
      addSearchTimer = setTimeout(runAddSearch, 250);
    }
    function runAddSearch() {
      var q = (addSearchEl && addSearchEl.value || '').trim();
      if (q.length < 2) { if (addResultsEl) addResultsEl.innerHTML = ''; return; }
      addResultsEl.innerHTML = '<li class="dashx-add-loading">Searching…</li>';
      ajax('searchGames', { q: q })
        .then(function (res) {
          if (!res.ok) {
            addResultsEl.innerHTML = '<li class="dashx-add-empty">Error: ' + esc(res.error || 'unknown') + '</li>'
                                  + buildCreateGameCta(q);
            return;
          }
          if (!res.results || !res.results.length) {
            addResultsEl.innerHTML = '<li class="dashx-add-empty">No matching slot found (or already documented).</li>'
                                  + buildCreateGameCta(q);
            return;
          }
          var html = '';
          for (var i = 0; i < res.results.length; i++) {
            var g = res.results[i];
            html += '<li class="dashx-add-result" data-gid="' + esc(g.gid) + '" data-name="' + esc(g.game_name) + '">'
                  +   '<div class="dashx-add-result-main">'
                  +     '<span class="dashx-add-result-name">' + esc(g.game_name) + '</span>'
                  +     '<span class="dashx-add-result-provider">' + esc(g.provider_name || '') + '</span>'
                  +   '</div>'
                  +   '<div class="dashx-add-result-meta">'
                  +     '<span>' + esc(g.bonus_count) + ' active bonus' + (parseInt(g.bonus_count,10) === 1 ? '' : 'es') + '</span>'
                  +     '<span class="dashx-add-result-add">+ Add</span>'
                  +   '</div>'
                  + '</li>';
          }
          html += buildCreateGameCta(q);
          addResultsEl.innerHTML = html;
        })
        .catch(function (err) {
          console.error('searchGames failed:', err);
          addResultsEl.innerHTML = '<li class="dashx-add-empty">Network error (see console)</li>';
        });
    }

    // v2.7.10 — "Create new game" CTA + inline form -----------------------
    var providersCache = null; // {provider_value, provider_name, game_count}[]

    function buildCreateGameCta(prefillName) {
      return '<li class="dashx-add-newgame-cta" data-prefill="' + esc(prefillName || '') + '">'
           +   '<span class="dashx-add-result-add">+ Create new game</span>'
           +   '<small>Game not in jos_games yet? Add it here.</small>'
           + '</li>';
    }

    function loadProviders() {
      if (providersCache) return Promise.resolve(providersCache);
      return ajax('listProviders', {}).then(function (res) {
        providersCache = (res && res.ok && res.providers) ? res.providers : [];
        return providersCache;
      });
    }

    function openCreateGameForm(prefillName) {
      addResultsEl.innerHTML = '<li class="dashx-add-loading">Loading providers…</li>';
      loadProviders().then(function (providers) {
        var opts = '<option value="">— Select provider —</option>';
        for (var i = 0; i < providers.length; i++) {
          var p = providers[i];
          // v2.7.88: the list also carries providers with no game yet
          // (site vocabulary); the count prints only when there is one.
          var n = parseInt(p.game_count, 10) || 0;
          opts += '<option value="' + esc(p.provider_value) + '" data-name="' + esc(p.provider_name) + '">'
                + esc(p.provider_name) + (n > 0 ? ' (' + n + ')' : '')
                + '</option>';
        }
        opts += '<option value="__NEW__">+ Add a new provider…</option>';

        var html = '<li class="dashx-add-newgame-form">'
                 +   '<form class="dashx-newgame-form" autocomplete="off">'
                 +     '<label><span>Game name</span>'
                 +       '<input type="text" name="game_name" required value="' + esc(prefillName || '') + '" placeholder="e.g. Reactoonz">'
                 +     '</label>'
                 +     '<label><span>Provider</span>'
                 +       '<select name="provider_select" required>' + opts + '</select>'
                 +     '</label>'
                 +     '<div class="dashx-newgame-new-provider" hidden>'
                 +       '<label><span>New provider name</span>'
                 +         '<input type="text" name="provider_name_new" placeholder="e.g. Big Time Gaming">'
                 +       '</label>'
                 +     '</div>'
                 +     '<label><span>Type</span>'
                 +       '<select name="type">'
                 +         '<option value="slot" selected>slot</option>'
                 +         '<option value="scratchcards">scratchcards</option>'
                 +       '</select>'
                 +     '</label>'
                 +     '<div class="dashx-newgame-actions">'
                 +       '<button type="button" class="dashx-btn-link" data-action="cancel">Cancel</button>'
                 +       '<button type="submit" class="dashx-btn-primary">Create + Open</button>'
                 +     '</div>'
                 +   '</form>'
                 + '</li>';
        addResultsEl.innerHTML = html;
      }).catch(function (err) {
        console.error('loadProviders failed:', err);
        addResultsEl.innerHTML = '<li class="dashx-add-empty">Failed to load providers (see console)</li>';
      });
    }

    function handleNewGameSelectChange(sel) {
      var newProviderBlock = addResultsEl.querySelector('.dashx-newgame-new-provider');
      if (!newProviderBlock) return;
      if (sel.value === '__NEW__') {
        newProviderBlock.hidden = false;
        var inp = newProviderBlock.querySelector('input[name="provider_name_new"]');
        if (inp) { inp.required = true; inp.focus(); }
      } else {
        newProviderBlock.hidden = true;
        var inp2 = newProviderBlock.querySelector('input[name="provider_name_new"]');
        if (inp2) inp2.required = false;
      }
    }

    function handleNewGameSubmit(form) {
      var fd = new FormData(form);
      var providerSel = form.querySelector('select[name="provider_select"]');
      var selectedOpt = providerSel.options[providerSel.selectedIndex];
      var providerValue = providerSel.value;
      var providerName = '';

      if (providerValue === '__NEW__') {
        providerName = (form.querySelector('input[name="provider_name_new"]').value || '').trim();
        if (!providerName) { toast('Please enter the new provider name', true); return; }
        providerValue = providerName; // backend normalises to *slug*
      } else {
        providerName = selectedOpt ? (selectedOpt.getAttribute('data-name') || '') : '';
        if (!providerValue || !providerName) { toast('Please select a provider', true); return; }
      }

      var gameName = (fd.get('game_name') || '').toString().trim();
      if (!gameName) { toast('Game name required', true); return; }

      var payload = {
        game_name:      gameName,
        type:           (fd.get('type') || 'slot').toString(),
        provider_value: providerValue,
        provider_name:  providerName
      };

      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; btn.textContent = 'Creating…'; }

      ajax('createGame', payload).then(function (res) {
        if (!res || !res.ok) {
          toast('Create failed: ' + ((res && res.error) || 'unknown'), true);
          if (btn) { btn.disabled = false; btn.textContent = 'Create + Open'; }
          return;
        }
        toast('Game created — opening editor', false);
        // Invalidate providers cache (new provider may have been added)
        providersCache = null;
        closeAddModal();
        openPanel(res.gid, res.name || gameName);
        setTimeout(function () {
          // refresh dashboard list after panel opens
          location.reload();
        }, 800);
      }).catch(function (err) {
        console.error('createGame failed:', err);
        toast('Network error (see console)', true);
        if (btn) { btn.disabled = false; btn.textContent = 'Create + Open'; }
      });
    }

    function handleAddResultClick(li) {
      var gid = parseInt(li.getAttribute('data-gid'), 10);
      var name = li.getAttribute('data-name') || '';
      if (!gid) return;
      li.classList.add('is-loading');
      ajax('addSlot', { gid: gid })
        .then(function (res) {
          if (!res.ok) {
            toast('Add failed: ' + (res.error || 'unknown'), true);
            li.classList.remove('is-loading');
            return;
          }
          toast('Slot added — opening editor', false);
          closeAddModal();
          // Open the new slot directly in the panel
          openPanel(gid, name);
          // Refresh the dashboard list in the background so the new slot
          // appears in the visible roster. Simplest path: reload after a
          // short delay so the user sees the panel first.
          setTimeout(function () {
            // Preserve current filter/search params in the URL
            window.location.reload();
          }, 1500);
        })
        .catch(function (err) {
          console.error('addSlot failed:', err);
          toast('Network error (see console)', true);
          li.classList.remove('is-loading');
        });
    }

    if (addBtn) {
      addBtn.addEventListener('click', openAddModal);
    }
    if (addModal) {
      addModal.addEventListener('click', function (e) {
        var closeAttr = e.target.getAttribute && e.target.getAttribute('data-close');
        if (closeAttr === 'add-modal' || e.target.classList.contains('dashx-add-backdrop')) {
          closeAddModal();
          return;
        }
        // v2.7.10 — new-game CTA + form actions
        var ctaLi = e.target.closest && e.target.closest('.dashx-add-newgame-cta');
        if (ctaLi) {
          openCreateGameForm(ctaLi.getAttribute('data-prefill') || '');
          return;
        }
        var cancelBtn = e.target.closest && e.target.closest('[data-action="cancel"]');
        if (cancelBtn && cancelBtn.closest('.dashx-newgame-form')) {
          runAddSearch(); // re-render results
          return;
        }
        var li = e.target.closest && e.target.closest('.dashx-add-result');
        if (li) handleAddResultClick(li);
      });
      // submit + change delegates for the new-game form
      addModal.addEventListener('submit', function (e) {
        var form = e.target.closest && e.target.closest('.dashx-newgame-form');
        if (form) {
          e.preventDefault();
          handleNewGameSubmit(form);
        }
      });
      addModal.addEventListener('change', function (e) {
        if (e.target && e.target.matches && e.target.matches('select[name="provider_select"]')) {
          handleNewGameSelectChange(e.target);
        }
      });
    }
    if (addSearchEl) {
      addSearchEl.addEventListener('input', scheduleAddSearch);
    }
    // ============ END ADD-SLOT MODAL ============

    // Sync publish checkbox to hidden form field
    panel.addEventListener('change', function (e) {
      if (e.target && e.target.id === 'dashx-publish-cb') {
        var hidden = document.getElementById('dashx-published-input');
        if (hidden) hidden.value = e.target.checked ? '1' : '0';
      }
      if (e.target && e.target.id === 'dashx-retired-cb') {
        var hidR = document.getElementById('dashx-retired-input');
        if (hidR) hidR.value = e.target.checked ? '1' : '0';
        // Toggle reason field visibility
        var wrap = document.getElementById('dashx-retired-reason-wrap');
        var row  = e.target.closest('.dashx-retired-row');
        if (wrap) wrap.classList.toggle('is-hidden', !e.target.checked);
        if (row)  row.classList.toggle('is-on', e.target.checked);
      }
    });

    panel.addEventListener('submit', function (e) {
      if (e.target.id === 'dashx-rtp-form') {
        e.preventDefault();
        handleRtpSubmit(e.target);
      }
    });

    obsModal.addEventListener('click', function (e) {
      var closeAttr = e.target.getAttribute && e.target.getAttribute('data-close');
      if (closeAttr === 'obs-modal' || e.target === obsModal) { closeObsModal(); return; }
      var action = e.target.getAttribute && e.target.getAttribute('data-action');
      if (action === 'clear-screenshot') { clearScreenshot(); return; }
    });

    obsModal.addEventListener('change', function (e) {
      if (e.target.name === 'screenshot') handleScreenshotChange(e.target);
    });

    obsForm.addEventListener('submit', function (e) {
      e.preventDefault();
      handleObsSubmit(obsForm);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (addModal && addModal.getAttribute('aria-hidden') === 'false') { closeAddModal(); return; }
      if (obsModal.getAttribute('aria-hidden') === 'false') { closeObsModal(); return; }
      if (panel.getAttribute('aria-hidden') === 'false') { closePanel(); return; }
    });

    console.log('[dashx] ready');
  }

  // Joomla emits external scripts (this file) BEFORE inline declarations
  // (window.FXDashboard = {...}). Defer init until DOM is ready so the
  // inline declaration has been executed by then.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
