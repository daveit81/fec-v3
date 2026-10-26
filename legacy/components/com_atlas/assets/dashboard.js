/* ============================================================================
   com_atlas v1.0 — dashboard.js
   No frameworks, vanilla JS. Targets Joomla 2.5/3.x runtime.
   Expects window.AtlasDashboard { endpoint, token, enums } set inline.

   Rule errors surfaced verbatim from the model:
     two_source_rule      — confirmed needs >= 2 linked sources (R2)
     operator_conflict    — one current operator per brand (R3)
     unverified_entity    — unverified entities cannot publish (R1)
     single_source_unpublishable — changelog items (R1)
   ============================================================================ */
(function () {
  'use strict';

  function init() {
    if (!window.AtlasDashboard) {
      console.error('[atlx] AtlasDashboard config not found');
      return;
    }

    var ENDPOINT = window.AtlasDashboard.endpoint;
    var TOKEN    = window.AtlasDashboard.token;
    var ENUMS    = window.AtlasDashboard.enums || {};

    var panel     = document.getElementById('atlx-panel');
    var panelBody = document.getElementById('atlx-panel-body');
    var panelTitle= document.getElementById('atlx-panel-title');
    var sheet     = document.getElementById('atlx-sheet');
    var sheetBody = document.getElementById('atlx-sheet-body');
    var sheetTitle= document.getElementById('atlx-sheet-title');
    var toastEl   = document.getElementById('atlx-toast');
    var listEl    = document.getElementById('atlx-list');

    var current = null; // last fetched entity payload

    // ----------- helpers ---------------------------------------------------

    function esc(s) {
      return String(s === null || s === undefined ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function toast(msg, isErr) {
      toastEl.textContent = msg;
      toastEl.className = 'atlx-toast' + (isErr ? ' err' : '');
      toastEl.hidden = false;
      clearTimeout(toastEl._t);
      toastEl._t = setTimeout(function () { toastEl.hidden = true; }, isErr ? 5200 : 2600);
    }

    /** Joomla AJAX POST helper. Adds task + token + cache-buster. */
    function ajax(task, data) {
      var url = ENDPOINT + (ENDPOINT.indexOf('?') === -1 ? '?' : '&')
        + 'task=' + encodeURIComponent('dashboard.' + task)
        + '&_=' + Date.now();
      var body = new URLSearchParams();
      Object.keys(data || {}).forEach(function (k) {
        var v = data[k];
        if (v === null || v === undefined) return;
        body.append(k, v);
      });
      body.append(TOKEN, '1');
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body
      }).then(function (r) {
        return r.text().then(function (txt) {
          try { return JSON.parse(txt); }
          catch (e) {
            var m = txt.match(/\{[\s\S]*\}/);
            if (m) { try { return JSON.parse(m[0]); } catch (e2) {} }
            console.error('[atlx] non-JSON response from', task, txt.substring(0, 400));
            throw new Error('Bad JSON');
          }
        });
      });
    }

    function ruleError(res) {
      switch (res.error) {
        case 'two_source_rule':
          return 'Two-source rule: confirmed needs at least 2 linked sources (this licence has ' + (res.sources || 0) + '). Link sources first.';
        case 'operator_conflict':
          return 'One current operator per brand: this review already has "' + (res.existing_entity || '?') + '" as current operator. Close that era first.';
        case 'unverified_entity':
          return 'Unverified entities cannot be published. Set a verified status first.';
        case 'single_source_unpublishable':
          return 'single_source items are never publishable (standing rule).';
        case 'relation_semantics':
          return 'Programs and commercial umbrellas cannot be operators: use the network relation. The operator link belongs to the certified operating company.';
        case 'slug_taken':      return 'Slug already in use.';
        case 'bad_slug':        return 'Slug must be lowercase a-z, 0-9 and hyphens.';
        case 'review_not_found':return 'No review with that contentid in the catalogue.';
        case 'invalid_token':   return 'Session token expired — reload the page.';
        case 'forbidden':       return 'Not authorized.';
        default: return 'Error: ' + (res.error || 'unknown');
      }
    }

    function badge(value, prefix) {
      return '<span class="atlx-badge ' + prefix + '-' + esc(value) + '">' + esc(value) + '</span>';
    }

    function selectHtml(name, options, selected, allowEmpty) {
      var h = '<select name="' + name + '">';
      if (allowEmpty) h += '<option value=""></option>';
      (options || []).forEach(function (o) {
        h += '<option value="' + esc(o) + '"' + (o === selected ? ' selected' : '') + '>' + esc(o) + '</option>';
      });
      return h + '</select>';
    }

    function openOverlay(el) { el.hidden = false; el.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
    function closeOverlay(el) { el.hidden = true; el.setAttribute('aria-hidden', 'true'); if (panel.hidden && sheet.hidden) document.body.style.overflow = ''; }

    document.addEventListener('click', function (ev) {
      var c = ev.target.closest ? ev.target.closest('[data-close]') : null;
      if (!c) return;
      if (c.getAttribute('data-close') === 'panel') closeOverlay(panel);
      if (c.getAttribute('data-close') === 'sheet') closeOverlay(sheet);
    });

    // ----------- entity detail ---------------------------------------------

    function openEntity(id) {
      panelTitle.textContent = 'Loading…';
      panelBody.innerHTML = '';
      openOverlay(panel);
      ajax('fetchEntity', { id: id }).then(function (res) {
        if (!res.ok) { toast(ruleError(res), true); closeOverlay(panel); return; }
        current = res;
        renderEntity();
      }).catch(function () { toast('Network error', true); });
    }

    function kv(label, value, rawHtml) {
      if (value === null || value === undefined || value === '') return '';
      return '<div><dt>' + esc(label) + '</dt><dd>' + (rawHtml ? value : esc(value)) + '</dd></div>';
    }

    function renderEntity() {
      var e = current.entity;
      panelTitle.textContent = e.name;

      var h = '';

      // --- identity card
      h += '<div class="atlx-card"><h3>Identity ' +
           '<span><button type="button" class="atlx-btn-sm" data-act="edit-entity">Edit</button> ' +
           '<button type="button" class="atlx-btn-sm ' + (Number(e.published) ? 'b-danger' : 'b-primary') + '" data-act="toggle-publish">' +
           (Number(e.published) ? 'Unpublish' : 'Publish') + '</button></span></h3>';
      h += '<dl class="atlx-kv">';
      h += kv('Slug', '/' + e.slug);
      h += kv('Type', badge(e.entity_type, 't'), true);
      h += kv('Status', badge(e.status, 's') + (e.status_date ? ' <span class="atlx-mini">' + esc(e.status_date) + '</span>' : ''), true);
      h += kv('Jurisdiction', e.jurisdiction);
      h += kv('Reg number', e.reg_number);
      h += kv('Address', e.reg_address);
      if (e.successor_name) h += kv('Successor', esc(e.successor_name) + ' <span class="atlx-mini">/' + esc(e.successor_slug) + '</span>', true);
      if (current.entity.predecessors && current.entity.predecessors.length) {
        h += kv('Predecessors', current.entity.predecessors.map(function (p) { return esc(p.name); }).join(', '), true);
      }
      h += kv('Published', Number(e.published) ? 'yes' : 'no');
      h += '</dl>';
      if (e.public_notes) h += '<p class="atlx-hint"><strong>Public:</strong> ' + esc(e.public_notes) + '</p>';
      if (e.editor_notes) h += '<p class="atlx-hint"><strong>Editor:</strong> ' + esc(e.editor_notes) + '</p>';
      h += '<div id="atlx-publish-warn"></div>';
      h += '</div>';

      // --- licences card
      h += '<div class="atlx-card"><h3>Licences <button type="button" class="atlx-btn-sm" data-act="add-licence">+ licence</button></h3>';
      if (!current.licences.length) h += '<p class="atlx-hint">None recorded.</p>';
      current.licences.forEach(function (l) {
        h += '<div class="atlx-item">' +
             '<div class="atlx-item-head"><span class="atlx-item-title">' + esc(l.regulator) + '</span> ' +
             (l.licence_number ? '<code>' + esc(l.licence_number) + '</code>' : '<span class="atlx-mini">(no number)</span>') + ' ' +
             badge(l.status, 'bs') + ' ' + badge(l.confidence, 'bc') +
             ' <span class="atlx-mini">' + Number(l.source_count) + ' src</span></div>';
        var sub = [];
        if (l.licence_type) sub.push(l.licence_type);
        if (l.valid_from) sub.push('from ' + l.valid_from);
        if (l.valid_to) sub.push('to ' + l.valid_to);
        if (l.last_verified) sub.push('verified ' + String(l.last_verified).substring(0, 10));
        if (sub.length) h += '<div class="atlx-item-sub">' + esc(sub.join(' · ')) + '</div>';
        if (l.editor_notes) h += '<div class="atlx-item-sub">' + esc(l.editor_notes) + '</div>';
        h += '<div class="atlx-item-actions">' +
             '<button type="button" class="atlx-btn-sm" data-act="edit-licence" data-id="' + l.id + '">Edit</button>' +
             '<button type="button" class="atlx-btn-sm" data-act="lic-sources" data-id="' + l.id + '">Sources</button>' +
             '<button type="button" class="atlx-btn-sm b-danger" data-act="del-licence" data-id="' + l.id + '">Delete</button>' +
             '</div></div>';
      });
      h += '</div>';

      // --- brand links card
      var currentLinks = current.brand_links.filter(function (b) { return !b.date_to; });
      var closedLinks  = current.brand_links.filter(function (b) { return !!b.date_to; });
      h += '<div class="atlx-card"><h3>Brand links <button type="button" class="atlx-btn-sm" data-act="add-link">+ brand link</button></h3>';
      if (!current.brand_links.length) h += '<p class="atlx-hint">None recorded.</p>';
      currentLinks.forEach(function (b) { h += brandLinkHtml(b, false); });
      if (closedLinks.length) {
        h += '<p class="atlx-hint" style="margin-top:10px"><strong>Closed eras</strong> (era firewall — history is never deleted)</p>';
        closedLinks.forEach(function (b) { h += brandLinkHtml(b, true); });
      }
      h += '</div>';

      // --- events card
      h += '<div class="atlx-card"><h3>Changelog events <button type="button" class="atlx-btn-sm" data-act="add-event">+ event</button></h3>';
      if (!current.events.length) h += '<p class="atlx-hint">None recorded.</p>';
      current.events.forEach(function (ev) {
        h += '<div class="atlx-item"><div class="atlx-item-head">' +
             '<span class="atlx-item-title">' + esc(ev.event_date) + '</span> ' +
             '<span class="atlx-mini">' + esc(ev.event_type) + '</span> ' +
             badge(ev.confidence, 'bc') +
             (Number(ev.published) ? ' <span class="atlx-pub">published</span>' : '') + '</div>' +
             '<div class="atlx-item-sub">' + esc(ev.title) + '</div>' +
             (ev.description ? '<div class="atlx-item-sub">' + esc(ev.description) + '</div>' : '') +
             '<div class="atlx-item-actions">' +
             '<button type="button" class="atlx-btn-sm" data-act="edit-event" data-id="' + ev.id + '">Edit</button>' +
             '<button type="button" class="atlx-btn-sm b-danger" data-act="del-event" data-id="' + ev.id + '">Delete</button>' +
             '</div></div>';
      });
      h += '</div>';

      // --- entity-level sources card
      h += '<div class="atlx-card"><h3>Entity sources <button type="button" class="atlx-btn-sm" data-act="link-entity-source">+ link source</button></h3>';
      if (!current.sources.length) h += '<p class="atlx-hint">None linked.</p>';
      current.sources.forEach(function (s) {
        h += '<div class="atlx-item"><div class="atlx-item-head"><span class="atlx-item-title">' + esc(s.description) + '</span> <span class="atlx-mini">' + esc(s.source_type) + '</span></div>' +
             (s.url ? '<div class="atlx-item-sub"><a href="' + esc(s.url) + '" target="_blank" rel="noopener">' + esc(s.url) + '</a></div>' : '') +
             '<div class="atlx-item-actions"><button type="button" class="atlx-btn-sm b-danger" data-act="unlink-source" data-id="' + s.link_id + '">Unlink</button></div></div>';
      });
      h += '</div>';

      panelBody.innerHTML = h;
    }

    function brandLinkHtml(b, closed) {
      return '<div class="atlx-item' + (closed ? ' atlx-era-closed' : '') + '">' +
        '<div class="atlx-item-head"><span class="atlx-item-title">' + esc(b.review_title || ('contentid ' + b.contentid)) + '</span> ' +
        '<span class="atlx-mini">' + esc(b.relation_type) + '</span> ' + badge(b.confidence, 'bc') + '</div>' +
        '<div class="atlx-item-sub">' +
        (b.date_from ? 'from ' + esc(b.date_from) + ' ' : '') +
        (b.date_to ? 'to ' + esc(b.date_to) : (closed ? '' : 'current era')) + '</div>' +
        (b.notes ? '<div class="atlx-item-sub">' + esc(b.notes) + '</div>' : '') +
        '<div class="atlx-item-actions">' +
        '<button type="button" class="atlx-btn-sm" data-act="edit-link" data-id="' + b.id + '">Edit</button>' +
        (!closed ? '<button type="button" class="atlx-btn-sm" data-act="close-link" data-id="' + b.id + '">Close era</button>' : '') +
        '<button type="button" class="atlx-btn-sm b-danger" data-act="del-link" data-id="' + b.id + '">Delete</button>' +
        '</div></div>';
    }

    // ----------- sheet forms ------------------------------------------------

    function openSheet(title, html, onSubmit) {
      sheetTitle.textContent = title;
      sheetBody.innerHTML = html;
      openOverlay(sheet);
      var form = sheetBody.querySelector('form');
      if (form && onSubmit) {
        form.addEventListener('submit', function (ev) {
          ev.preventDefault();
          onSubmit(form);
        });
      }
      return form;
    }

    function formError(form, msg) {
      var box = form.querySelector('.atlx-error');
      if (!box) {
        box = document.createElement('div');
        box.className = 'atlx-error';
        form.appendChild(box);
      }
      box.textContent = msg;
    }

    function formData(form) {
      var out = {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name) return;
        if (el.type === 'checkbox') { out[el.name] = el.checked ? 1 : 0; return; }
        out[el.name] = el.value;
      });
      return out;
    }

    function entityFormHtml(e) {
      e = e || {};
      return '<form class="atlx-form">' +
        '<input type="hidden" name="id" value="' + (e.id || 0) + '">' +
        '<label>Name</label><input type="text" name="name" required value="' + esc(e.name || '') + '">' +
        '<label>Slug <span class="atlx-hint">(empty = auto from name)</span></label><input type="text" name="slug" value="' + esc(e.slug || '') + '">' +
        '<div class="atlx-form-row"><div>' +
        '<label>Type</label>' + selectHtml('entity_type', ENUMS.entity_types, e.entity_type || 'operator') +
        '</div><div>' +
        '<label>Status</label>' + selectHtml('status', ENUMS.entity_status, e.status || 'unverified') +
        '</div></div>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Jurisdiction (ISO-2, lowercase)</label><input type="text" name="jurisdiction" maxlength="2" value="' + esc(e.jurisdiction || '') + '">' +
        '</div><div>' +
        '<label>Status date</label><input type="date" name="status_date" value="' + esc(e.status_date || '') + '">' +
        '</div></div>' +
        '<label>Reg number</label><input type="text" name="reg_number" value="' + esc(e.reg_number || '') + '">' +
        '<label>Registered address</label><input type="text" name="reg_address" value="' + esc(e.reg_address || '') + '">' +
        '<label>Successor entity id <span class="atlx-hint">(numeric id, 0 = none)</span></label>' +
        '<input type="text" name="successor_entity_id" value="' + (e.successor_entity_id || 0) + '">' +
        '<label>Editor notes (never rendered publicly)</label><textarea name="editor_notes">' + esc(e.editor_notes || '') + '</textarea>' +
        '<label>Public notes (rendered on the public page)</label><textarea name="public_notes">' + esc(e.public_notes || '') + '</textarea>' +
        '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Save</button></div>' +
        '</form>';
    }

    function submitEntity(form) {
      ajax('saveEntity', formData(form)).then(function (res) {
        if (!res.ok) { formError(form, ruleError(res)); return; }
        toast('Entity saved');
        closeOverlay(sheet);
        if (current && current.entity && Number(formData(form).id)) openEntity(current.entity.id);
        else location.reload();
      }).catch(function () { formError(form, 'Network error'); });
    }

    function licenceFormHtml(l) {
      l = l || {};
      return '<form class="atlx-form">' +
        '<input type="hidden" name="id" value="' + (l.id || 0) + '">' +
        '<input type="hidden" name="entity_id" value="' + current.entity.id + '">' +
        '<label>Regulator</label><input type="text" name="regulator" required value="' + esc(l.regulator || '') + '">' +
        '<div class="atlx-form-row"><div>' +
        '<label>Licence number</label><input type="text" name="licence_number" value="' + esc(l.licence_number || '') + '">' +
        '</div><div>' +
        '<label>Licence type</label><input type="text" name="licence_type" value="' + esc(l.licence_type || '') + '">' +
        '</div></div>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Status</label>' + selectHtml('status', ENUMS.lic_status, l.status || 'unverified') +
        '</div><div>' +
        '<label>Confidence</label>' + selectHtml('confidence', ENUMS.confidence, l.confidence || 'probable') +
        '</div></div>' +
        '<p class="atlx-hint">Upgrading to confirmed requires at least 2 linked sources (two-source rule). single_source never renders publicly.</p>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Valid from</label><input type="date" name="valid_from" value="' + esc(l.valid_from || '') + '">' +
        '</div><div>' +
        '<label>Valid to</label><input type="date" name="valid_to" value="' + esc(l.valid_to || '') + '">' +
        '</div></div>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Last verified</label><input type="date" name="last_verified" value="' + esc(String(l.last_verified || '').substring(0, 10)) + '">' +
        '</div><div>' +
        '<label>&nbsp;</label><button type="button" class="atlx-btn-sm" data-act="verify-today">Verified today</button>' +
        '</div></div>' +
        '<p class="atlx-hint">Feeds the Sentinel A2 staleness check (180 days). The button fills today; Save writes it.</p>' +
        '<label>Editor notes</label><textarea name="editor_notes">' + esc(l.editor_notes || '') + '</textarea>' +
        '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Save</button></div>' +
        '</form>';
    }

    function submitLicence(form) {
      ajax('saveLicence', formData(form)).then(function (res) {
        if (!res.ok) { formError(form, ruleError(res)); return; }
        toast('Licence saved');
        closeOverlay(sheet);
        openEntity(current.entity.id);
      }).catch(function () { formError(form, 'Network error'); });
    }

    function linkFormHtml(b) {
      b = b || {};
      var picker = !b.id;
      return '<form class="atlx-form">' +
        '<input type="hidden" name="id" value="' + (b.id || 0) + '">' +
        '<input type="hidden" name="entity_id" value="' + current.entity.id + '">' +
        (picker
          ? '<label>Review</label><input type="search" name="_review_q" placeholder="Search review title…" autocomplete="off">' +
            '<ul class="atlx-ac" id="atlx-review-ac"></ul>' +
            '<input type="hidden" name="contentid" value="0">' +
            '<p class="atlx-hint" id="atlx-review-picked">No review selected.</p>'
          : '<input type="hidden" name="contentid" value="' + b.contentid + '">' +
            '<p class="atlx-hint">Review: <strong>' + esc(b.review_title || ('contentid ' + b.contentid)) + '</strong></p>') +
        '<div class="atlx-form-row"><div>' +
        '<label>Relation</label>' + selectHtml('relation_type', ENUMS.relations, b.relation_type || 'operator') +
        '</div><div>' +
        '<label>Confidence</label>' + selectHtml('confidence', ENUMS.confidence, b.confidence || 'probable') +
        '</div></div>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Date from <span class="atlx-hint">(empty = unknown)</span></label><input type="date" name="date_from" value="' + esc(b.date_from || '') + '">' +
        '</div><div>' +
        '<label>Date to <span class="atlx-hint">(empty = CURRENT era)</span></label><input type="date" name="date_to" value="' + esc(b.date_to || '') + '">' +
        '</div></div>' +
        '<label>Notes</label><textarea name="notes">' + esc(b.notes || '') + '</textarea>' +
        '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Save</button></div>' +
        '</form>';
    }

    function wireReviewPicker(form) {
      var q = form.querySelector('[name="_review_q"]');
      if (!q) return;
      var ac = form.querySelector('#atlx-review-ac');
      var picked = form.querySelector('#atlx-review-picked');
      var t = null;
      q.addEventListener('input', function () {
        clearTimeout(t);
        var v = q.value.trim();
        if (v.length < 2) { ac.innerHTML = ''; return; }
        t = setTimeout(function () {
          ajax('searchReviews', { q: v }).then(function (res) {
            if (!res.ok) return;
            ac.innerHTML = res.results.map(function (r) {
              return '<li data-cid="' + r.contentid + '" data-title="' + esc(r.title) + '">' + esc(r.title) +
                (r.current_operator ? ' <span class="ac-op">— current: ' + esc(r.current_operator) + '</span>' : '') + '</li>';
            }).join('');
          });
        }, 250);
      });
      ac.addEventListener('click', function (ev) {
        var li = ev.target.closest ? ev.target.closest('li') : null;
        if (!li) return;
        form.querySelector('[name="contentid"]').value = li.getAttribute('data-cid');
        picked.innerHTML = 'Selected: <strong>' + esc(li.getAttribute('data-title')) + '</strong> (contentid ' + li.getAttribute('data-cid') + ')';
        ac.innerHTML = '';
      });
    }

    function submitLink(form) {
      var data = formData(form);
      delete data._review_q;
      if (!Number(data.contentid)) { formError(form, 'Pick a review first.'); return; }
      ajax('saveBrandLink', data).then(function (res) {
        if (!res.ok) { formError(form, ruleError(res)); return; }
        toast('Brand link saved');
        closeOverlay(sheet);
        openEntity(current.entity.id);
      }).catch(function () { formError(form, 'Network error'); });
    }

    function eventFormHtml(ev) {
      ev = ev || {};
      return '<form class="atlx-form">' +
        '<input type="hidden" name="id" value="' + (ev.id || 0) + '">' +
        '<input type="hidden" name="entity_id" value="' + current.entity.id + '">' +
        '<div class="atlx-form-row"><div>' +
        '<label>Date</label><input type="date" name="event_date" required value="' + esc(ev.event_date || '') + '">' +
        '</div><div>' +
        '<label>Type</label>' + selectHtml('event_type', ENUMS.event_types, ev.event_type || 'other') +
        '</div></div>' +
        '<label>Title</label><input type="text" name="title" required value="' + esc(ev.title || '') + '">' +
        '<label>Description</label><textarea name="description">' + esc(ev.description || '') + '</textarea>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Confidence</label>' + selectHtml('confidence', ENUMS.confidence, ev.confidence || 'probable') +
        '</div><div>' +
        '<label>Published</label><label><input type="checkbox" name="published"' + (Number(ev.published) ? ' checked' : '') + '> visible in public changelog</label>' +
        '</div></div>' +
        '<p class="atlx-hint">single_source events cannot be published (standing rule).</p>' +
        '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Save</button></div>' +
        '</form>';
    }

    function submitEvent(form) {
      ajax('saveEvent', formData(form)).then(function (res) {
        if (!res.ok) { formError(form, ruleError(res)); return; }
        toast('Event saved');
        closeOverlay(sheet);
        openEntity(current.entity.id);
      }).catch(function () { formError(form, 'Network error'); });
    }

    /** Licence sources sheet: list linked sources + search/link others. */
    function openLicenceSources(licId) {
      ajax('fetchLicenceSources', { id: licId }).then(function (res) {
        if (!res.ok) { toast(ruleError(res), true); return; }
        var h = '<div class="atlx-form">' +
          '<div id="atlx-lic-src-list">' + res.sources.map(function (s) {
            return '<div class="atlx-item"><div class="atlx-item-head"><span class="atlx-item-title">' + esc(s.description) + '</span></div>' +
              (s.url ? '<div class="atlx-item-sub">' + esc(s.url) + '</div>' : '') +
              '<div class="atlx-item-actions"><button type="button" class="atlx-btn-sm b-danger" data-unlink="' + s.link_id + '">Unlink</button></div></div>';
          }).join('') + (res.sources.length ? '' : '<p class="atlx-hint">No sources linked yet.</p>') + '</div>' +
          '<label>Link an existing source</label>' +
          '<input type="search" id="atlx-src-q" placeholder="Search sources…" autocomplete="off">' +
          '<ul class="atlx-ac" id="atlx-src-ac"></ul>' +
          '<p class="atlx-hint">Two linked sources unlock the confirmed upgrade.</p>' +
          '<label style="margin-top:14px">Or create a new evidence record</label>' +
          '<form class="atlx-form" id="atlx-lic-newsrc">' +
          '<label>Description</label><input type="text" name="description" required>' +
          '<div class="atlx-form-row"><div>' +
          '<label>Type</label><select name="source_type">' +
          ['registry_pdf','regulator_validator','tc_firstparty','site_footer','industry_desk','court_record','other'].map(function (o) { return '<option value="' + o + '">' + o + '</option>'; }).join('') +
          '</select></div><div>' +
          '<label>Captured</label><input type="date" name="captured_at">' +
          '</div></div>' +
          '<label>URL</label><input type="text" name="url">' +
          '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Create + link to this licence</button></div>' +
          '</form>' +
          '</div>';
        openSheet('Licence sources (' + res.sources.length + ')', h, null);

        var q = sheetBody.querySelector('#atlx-src-q');
        var ac = sheetBody.querySelector('#atlx-src-ac');
        var t = null;
        q.addEventListener('input', function () {
          clearTimeout(t);
          var v = q.value.trim();
          t = setTimeout(function () {
            ajax('listSources', { q: v }).then(function (r2) {
              if (!r2.ok) return;
              ac.innerHTML = r2.sources.map(function (s) {
                return '<li data-sid="' + s.id + '">' + esc(s.description) + ' <span class="ac-op">(' + Number(s.link_count) + ' links)</span></li>';
              }).join('');
            });
          }, 250);
        });
        ac.addEventListener('click', function (ev2) {
          var li = ev2.target.closest ? ev2.target.closest('li') : null;
          if (!li) return;
          ajax('linkSource', { source_id: li.getAttribute('data-sid'), ref_table: 'licences', ref_id: licId }).then(function (r3) {
            if (!r3.ok) { toast(ruleError(r3), true); return; }
            toast(r3.already ? 'Already linked' : 'Source linked');
            openLicenceSources(licId);
          });
        });
        sheetBody.addEventListener('click', function (ev3) {
          var b = ev3.target.closest ? ev3.target.closest('[data-unlink]') : null;
          if (!b) return;
          ajax('unlinkSource', { link_id: b.getAttribute('data-unlink') }).then(function (r4) {
            if (!r4.ok) { toast(ruleError(r4), true); return; }
            toast('Unlinked');
            openLicenceSources(licId);
          });
        });
        var lnf = sheetBody.querySelector('#atlx-lic-newsrc');
        if (lnf) {
          lnf.addEventListener('submit', function (ev4) {
            ev4.preventDefault();
            ajax('saveSource', formData(lnf)).then(function (r5) {
              if (!r5.ok) { formError(lnf, ruleError(r5)); return; }
              return ajax('linkSource', { source_id: r5.id, ref_table: 'licences', ref_id: licId }).then(function (r6) {
                if (!r6.ok) { formError(lnf, ruleError(r6)); return; }
                toast('Source created and linked');
                openLicenceSources(licId);
              });
            }).catch(function () { formError(lnf, 'Network error'); });
          });
        }
      });
    }

    function openEntitySourceLinker() {
      var h = '<div class="atlx-form">' +
        '<label>Search sources</label>' +
        '<input type="search" id="atlx-esrc-q" placeholder="Search sources…" autocomplete="off">' +
        '<ul class="atlx-ac" id="atlx-esrc-ac"></ul>' +
        '<label style="margin-top:18px">Or create a new evidence record</label>' +
        '<form class="atlx-form" id="atlx-newsrc">' +
        '<label>Description</label><input type="text" name="description" required>' +
        '<div class="atlx-form-row"><div>' +
        '<label>Type</label><select name="source_type">' +
        ['registry_pdf','regulator_validator','tc_firstparty','site_footer','industry_desk','court_record','other'].map(function (o) { return '<option value="' + o + '">' + o + '</option>'; }).join('') +
        '</select></div><div>' +
        '<label>Captured</label><input type="date" name="captured_at">' +
        '</div></div>' +
        '<label>URL</label><input type="text" name="url">' +
        '<div class="atlx-form-foot"><button type="submit" class="atlx-btn-sm b-primary">Create + link</button></div>' +
        '</form></div>';
      openSheet('Link a source to this entity', h, null);

      var q = sheetBody.querySelector('#atlx-esrc-q');
      var ac = sheetBody.querySelector('#atlx-esrc-ac');
      var t = null;
      q.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
          ajax('listSources', { q: q.value.trim() }).then(function (r) {
            if (!r.ok) return;
            ac.innerHTML = r.sources.map(function (s) {
              return '<li data-sid="' + s.id + '">' + esc(s.description) + ' <span class="ac-op">(' + Number(s.link_count) + ' links)</span></li>';
            }).join('');
          });
        }, 250);
      });
      ac.addEventListener('click', function (ev) {
        var li = ev.target.closest ? ev.target.closest('li') : null;
        if (!li) return;
        ajax('linkSource', { source_id: li.getAttribute('data-sid'), ref_table: 'entities', ref_id: current.entity.id }).then(function (r) {
          if (!r.ok) { toast(ruleError(r), true); return; }
          toast('Source linked');
          closeOverlay(sheet);
          openEntity(current.entity.id);
        });
      });
      var nf = sheetBody.querySelector('#atlx-newsrc');
      nf.addEventListener('submit', function (ev) {
        ev.preventDefault();
        ajax('saveSource', formData(nf)).then(function (r) {
          if (!r.ok) { formError(nf, ruleError(r)); return; }
          return ajax('linkSource', { source_id: r.id, ref_table: 'entities', ref_id: current.entity.id }).then(function (r2) {
            if (!r2.ok) { formError(nf, ruleError(r2)); return; }
            toast('Source created and linked');
            closeOverlay(sheet);
            openEntity(current.entity.id);
          });
        }).catch(function () { formError(nf, 'Network error'); });
      });
    }

    // ----------- verify-today (licence sheet) -------------------------------

    document.addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('[data-act="verify-today"]') : null;
      if (!b) return;
      var f = b.closest('form');
      if (!f) return;
      var inp = f.querySelector('[name="last_verified"]');
      if (!inp) return;
      var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
      inp.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    });

    // ----------- panel action dispatch --------------------------------------

    panelBody.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('[data-act]') : null;
      if (!btn || !current) return;
      var act = btn.getAttribute('data-act');
      var id  = Number(btn.getAttribute('data-id') || 0);

      if (act === 'edit-entity') {
        openSheet('Edit entity', entityFormHtml(current.entity), submitEntity);
      }
      if (act === 'toggle-publish') {
        var to = Number(current.entity.published) ? 0 : 1;
        ajax('setPublished', { id: current.entity.id, published: to }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast(to ? 'Published' : 'Unpublished');
          if (res.warnings && res.warnings.length) {
            var w = document.getElementById('atlx-publish-warn');
            if (w) w.innerHTML = '<ul class="atlx-warnlist"><li>' + res.warnings.map(esc).join('</li><li>') + '</li></ul>';
          }
          current.entity.published = to;
          renderEntity();
          if (res.warnings && res.warnings.length) {
            var w2 = document.getElementById('atlx-publish-warn');
            if (w2) w2.innerHTML = '<ul class="atlx-warnlist"><li>' + res.warnings.map(esc).join('</li><li>') + '</li></ul>';
          }
        });
      }
      if (act === 'add-licence')  openSheet('Add licence', licenceFormHtml(null), submitLicence);
      if (act === 'edit-licence') {
        var l = current.licences.filter(function (x) { return Number(x.id) === id; })[0];
        openSheet('Edit licence', licenceFormHtml(l), submitLicence);
      }
      if (act === 'lic-sources')  openLicenceSources(id);
      if (act === 'del-licence') {
        if (!window.confirm('Delete this licence record?')) return;
        ajax('deleteLicence', { id: id }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast('Licence deleted');
          openEntity(current.entity.id);
        });
      }
      if (act === 'add-link') {
        var f = openSheet('Add brand link', linkFormHtml(null), submitLink);
        wireReviewPicker(f);
      }
      if (act === 'edit-link') {
        var b = current.brand_links.filter(function (x) { return Number(x.id) === id; })[0];
        openSheet('Edit brand link', linkFormHtml(b), submitLink);
      }
      if (act === 'close-link') {
        var d = window.prompt('Close this era at date (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
        if (!d) return;
        ajax('closeBrandLink', { id: id, date_to: d }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast('Era closed');
          openEntity(current.entity.id);
        });
      }
      if (act === 'del-link') {
        if (!window.confirm('Delete this brand link? Prefer "Close era" unless it is a mistake.')) return;
        ajax('deleteBrandLink', { id: id }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast('Link deleted');
          openEntity(current.entity.id);
        });
      }
      if (act === 'add-event')  openSheet('Add changelog event', eventFormHtml(null), submitEvent);
      if (act === 'edit-event') {
        var e2 = current.events.filter(function (x) { return Number(x.id) === id; })[0];
        openSheet('Edit changelog event', eventFormHtml(e2), submitEvent);
      }
      if (act === 'del-event') {
        if (!window.confirm('Delete this event?')) return;
        ajax('deleteEvent', { id: id }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast('Event deleted');
          openEntity(current.entity.id);
        });
      }
      if (act === 'link-entity-source') openEntitySourceLinker();
      if (act === 'unlink-source') {
        ajax('unlinkSource', { link_id: id }).then(function (res) {
          if (!res.ok) { toast(ruleError(res), true); return; }
          toast('Unlinked');
          openEntity(current.entity.id);
        });
      }
    });

    // ----------- drift tab ---------------------------------------------------

    function driftRow(r, relation) {
      return '<div class="atlx-item" data-drift-row="1">' +
        '<div class="atlx-item-head"><span class="atlx-item-title">' + esc(r.title) + '</span> ' +
        '<span class="atlx-mini">#' + Number(r.contentid) + '</span></div>' +
        '<div class="atlx-item-sub">' + esc(r.group_label) + ' &rarr; <strong>' + esc(r.entity_name) + '</strong> <span class="atlx-mini">/' + esc(r.entity_slug) + '</span></div>' +
        '<div class="atlx-item-actions">' +
        '<button type="button" class="atlx-btn-sm b-primary" data-drift-act="link"' +
        ' data-contentid="' + Number(r.contentid) + '" data-entity="' + Number(r.entity_id) + '"' +
        ' data-relation="' + relation + '">Create ' + relation + ' link</button>' +
        '</div></div>';
    }

    function openDrift() {
      panelTitle.textContent = 'Drift — jReviews vs Atlas';
      panelBody.innerHTML = '<p class="atlx-hint">Running the comparison…</p>';
      openOverlay(panel);
      ajax('driftReport', {}).then(function (res) {
        if (!res.ok) { toast(ruleError(res), true); return; }
        var d = res.drift;
        var h = '';

        h += '<div class="atlx-card"><h3>Missing network links <span class="atlx-mini">' + d.missing_network.length + '</span></h3>';
        if (!d.missing_network.length) h += '<p class="atlx-hint">None — networks are in step.</p>';
        d.missing_network.forEach(function (r) { h += driftRow(r, 'network'); });
        h += '</div>';

        h += '<div class="atlx-card"><h3>Missing operator links <span class="atlx-mini">' + d.missing_operator.length + '</span></h3>';
        h += '<p class="atlx-hint">Careful with legacy attributions (Dama-style): the Atlas is the corrected register — verify before linking.</p>';
        if (!d.missing_operator.length) h += '<p class="atlx-hint">None — operators are in step.</p>';
        d.missing_operator.forEach(function (r) { h += driftRow(r, 'operator'); });
        h += '</div>';

        h += '<div class="atlx-card"><h3>Groups with no Atlas entity <span class="atlx-mini">' + d.no_entity.length + '</span></h3>';
        if (!d.no_entity.length) h += '<p class="atlx-hint">None.</p>';
        d.no_entity.forEach(function (r) {
          h += '<div class="atlx-item"><div class="atlx-item-head">' +
               '<span class="atlx-item-title">' + esc(r.group_label) + '</span> ' +
               '<span class="atlx-mini">' + Number(r.members) + ' member' + (Number(r.members) === 1 ? '' : 's') + '</span></div>' +
               '<div class="atlx-item-actions">' +
               '<button type="button" class="atlx-btn-sm" data-drift-act="entity" data-name="' + esc(r.group_label) + '">Add entity</button>' +
               '</div></div>';
        });
        h += '</div>';

        h += '<div class="atlx-card"><h3>Owner mismatches <span class="atlx-mini">' + d.owner_mismatch.length + '</span></h3>' +
             '<p class="atlx-hint">Informational: the current Atlas operator disagrees with jr_owner. Often the review side needs the update, not the Atlas.</p>';
        if (!d.owner_mismatch.length) h += '<p class="atlx-hint">None.</p>';
        d.owner_mismatch.forEach(function (r) {
          h += '<div class="atlx-item"><div class="atlx-item-head"><span class="atlx-item-title">' + esc(r.title) + '</span> <span class="atlx-mini">#' + Number(r.contentid) + '</span></div>' +
               '<div class="atlx-item-sub">jr_owner: ' + esc(r.jr_owner_value) + ' &middot; atlas: /' + esc(r.entity_slug) + '</div></div>';
        });
        h += '</div>';

        h += '<p class="atlx-hint" style="margin-top:10px"><button type="button" class="atlx-btn-sm" data-drift-act="refresh">Refresh report</button></p>';

        panelBody.innerHTML = h;
      }).catch(function () { toast('Network error', true); });
    }

    panelBody.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('[data-drift-act]') : null;
      if (!btn) return;
      var act = btn.getAttribute('data-drift-act');

      if (act === 'refresh') { openDrift(); return; }

      if (act === 'entity') {
        openSheet('Add entity', entityFormHtml({ name: btn.getAttribute('data-name') }), function (form) {
          ajax('saveEntity', formData(form)).then(function (res) {
            if (!res.ok) { formError(form, ruleError(res)); return; }
            toast('Entity created — refresh the report to see its missing links');
            closeOverlay(sheet);
          }).catch(function () { formError(form, 'Network error'); });
        });
        return;
      }

      if (act === 'link') {
        btn.disabled = true;
        ajax('saveBrandLink', {
          id: 0,
          contentid: btn.getAttribute('data-contentid'),
          entity_id: btn.getAttribute('data-entity'),
          relation_type: btn.getAttribute('data-relation'),
          confidence: 'probable',
          date_from: '',
          date_to: '',
          notes: 'Created from the Drift tab - synced from the review record'
        }).then(function (res) {
          if (!res.ok) { btn.disabled = false; toast(ruleError(res), true); return; }
          toast('Link created');
          var row = btn.closest ? btn.closest('[data-drift-row]') : null;
          if (row && row.parentNode) row.parentNode.removeChild(row);
        }).catch(function () { btn.disabled = false; toast('Network error', true); });
      }
    });

    // ----------- list + add entity ------------------------------------------

    if (listEl) {
      listEl.addEventListener('click', function (ev) {
        var row = ev.target.closest ? ev.target.closest('.atlx-row') : null;
        if (row) openEntity(Number(row.getAttribute('data-id')));
      });
      listEl.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var row = ev.target.closest ? ev.target.closest('.atlx-row') : null;
        if (row) openEntity(Number(row.getAttribute('data-id')));
      });
    }

    var driftBtn = document.getElementById('atlx-open-drift');
    if (driftBtn) driftBtn.addEventListener('click', openDrift);

    var addBtn = document.getElementById('atlx-add-entity');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        current = current || { entity: {} };
        openSheet('Add entity', entityFormHtml(null), function (form) {
          ajax('saveEntity', formData(form)).then(function (res) {
            if (!res.ok) { formError(form, ruleError(res)); return; }
            toast('Entity created');
            closeOverlay(sheet);
            location.reload();
          }).catch(function () { formError(form, 'Network error'); });
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
