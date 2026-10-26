/* com_authors — Blog desk (V2.7.66)
 * Word count, HTML/plain indicator, image preview, unsaved-text guard and a
 * localStorage backup of the text (restored after a crash or a lost session).
 * No dependencies. */
(function () {
  'use strict';
  var form = document.getElementById('bdk-form');
  if (!form) return;

  var body = document.getElementById('bdk-body');
  var title = document.getElementById('bdk-title');
  var words = document.getElementById('bdk-words');
  var modeLabel = document.getElementById('bdk-mode-label');
  var idField = form.querySelector('input[name="id"]');
  var key = 'fec-blogdesk-' + (idField ? idField.value : '0');
  var initial = (title ? title.value : '') + '\u0000' + (body ? body.value : '');
  var dirty = false;
  var submitting = false;

  function looksLikeHtml(s) {
    return /<(p|h[1-6]|div|br|ul|ol|li|blockquote|table|tr|section|article)\b[^>]*>/i.test(s);
  }
  function countWords(s) {
    s = s.replace(/<[^>]+>/g, ' ');
    var m = s.match(/[A-Za-z0-9À-ɏЀ-ӿ']+/g);
    return m ? m.length : 0;
  }
  function refresh() {
    if (!body) return;
    var v = body.value;
    var html = looksLikeHtml(v);
    if (words) words.textContent = countWords(v).toLocaleString();
    if (modeLabel) modeLabel.textContent = v.trim() === '' ? '—' : (html ? 'HTML' : 'plain text');
    body.classList.toggle('bdk-is-html', html);
  }
  function snapshot() {
    return (title ? title.value : '') + '\u0000' + (body ? body.value : '');
  }
  function backup() {
    try {
      var snap = snapshot();
      if (snap === initial) { localStorage.removeItem(key); return; }
      localStorage.setItem(key, JSON.stringify({ t: title ? title.value : '', b: body ? body.value : '', at: Date.now() }));
    } catch (e) { /* storage may be unavailable */ }
  }

  // restore a backup newer than what the page shows
  try {
    var raw = localStorage.getItem(key);
    if (raw) {
      var saved = JSON.parse(raw);
      var differs = saved && ((title && saved.t !== title.value) || (body && saved.b !== body.value));
      if (differs && saved.b && saved.b.trim() !== '') {
        var bar = document.createElement('div');
        bar.className = 'bdk-msg bdk-msg-info';
        var when = new Date(saved.at);
        bar.innerHTML = 'Unsaved text from ' + when.toLocaleString() + ' was found in this browser. ' +
          '<a href="#" id="bdk-restore">Restore it</a> <a href="#" id="bdk-discard">Discard</a>';
        form.parentNode.insertBefore(bar, form);
        document.getElementById('bdk-restore').addEventListener('click', function (e) {
          e.preventDefault();
          if (title) title.value = saved.t;
          if (body) body.value = saved.b;
          dirty = true; refresh(); bar.parentNode.removeChild(bar);
        });
        document.getElementById('bdk-discard').addEventListener('click', function (e) {
          e.preventDefault();
          try { localStorage.removeItem(key); } catch (x) {}
          bar.parentNode.removeChild(bar);
        });
      } else if (!differs) {
        localStorage.removeItem(key);
      }
    }
  } catch (e) { /* ignore */ }

  var timer = null;
  function onInput() {
    dirty = (snapshot() !== initial);
    refresh();
    clearTimeout(timer);
    timer = setTimeout(backup, 800);
  }
  if (body) body.addEventListener('input', onInput);
  if (title) title.addEventListener('input', onInput);
  refresh();

  // image preview
  var file = document.getElementById('bdk-image');
  var preview = document.getElementById('bdk-image-preview');
  var box = document.getElementById('bdk-image-box');
  var empty = document.getElementById('bdk-image-empty');
  if (file && preview) {
    file.addEventListener('change', function () {
      var f = file.files && file.files[0];
      if (!f || !/^image\//.test(f.type)) return;
      var url = URL.createObjectURL(f);
      preview.src = url; preview.hidden = false;
      if (empty) empty.hidden = true;
      if (box) box.classList.remove('bdk-image--empty');
      dirty = true;
    });
  }

  // leaving with unsaved text
  window.addEventListener('beforeunload', function (e) {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
  });
  form.addEventListener('submit', function () {
    submitting = true;
    try { localStorage.removeItem(key); } catch (e) {}
    var btns = form.querySelectorAll('button[type=submit]');
    for (var i = 0; i < btns.length; i++) { btns[i].disabled = false; btns[i].style.opacity = '.7'; }
  });

  // Draft checkbox drives the button label (2.7.70)
  var draftBox = document.getElementById('bdk-draft-box');
  var saveBtn = document.getElementById('bdk-publish');
  function refreshLabel() {
    if (!draftBox || !saveBtn) return;
    saveBtn.textContent = draftBox.checked ? (saveBtn.getAttribute('data-label-draft') || 'Save draft') : (saveBtn.getAttribute('data-label-publish') || 'Publish');
  }
  if (draftBox) draftBox.addEventListener('change', refreshLabel);
  refreshLabel();

  // Ctrl/Cmd+Enter saves
  if (body) body.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      if (saveBtn) saveBtn.click();
    }
  });
})();
