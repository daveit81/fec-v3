/* =========================================================
   FEC v2 — fecv2.js (clean, no duplicates)
   - User dropdown: click on touch, hover on desktop
   - Mobile nav burger: open/close + click outside + ESC
   - FXCheck tooltip: hover/focus
   - Toolbar: condensed on scroll + filters panel + chips -> querystring (pending) + apply/reset + sort
   - FAQ controls: open/close all + button state
   ========================================================= */


(function(){
  function qs(sel, root){ return (root || document).querySelector(sel); }

  /* ---------------------------------------------------------
     1) User dropdown (desktop hover / mobile click)
     --------------------------------------------------------- */
  (function(){
    var user = qs('[data-fec-dropdown]');
    if (!user) return;

    var btn = qs('.fec-user__btn', user);
    if (!btn) return;

    var isDesktopHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    function openUser(){
      user.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
    }
    function closeUser(){
      user.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }
    function toggleUser(){
      var open = user.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Always allow ESC to close
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeUser();
    });

    if (!isDesktopHover) {
      // Touch/mobile: click toggles
      btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        toggleUser();
      });

      // Click outside closes
      document.addEventListener('click', function(e){
        if (!user.contains(e.target)) closeUser();
      });
    } else {
      // Desktop: hover opens/closes (no "scappa")
      user.addEventListener('mouseenter', openUser);
      user.addEventListener('mouseleave', closeUser);

      // Also allow click to toggle (handy, but optional)
      btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        toggleUser();
      });

      document.addEventListener('click', function(e){
        if (!user.contains(e.target)) closeUser();
      });
    }
  })();


  /* ---------------------------------------------------------
     2) Mobile burger nav
     --------------------------------------------------------- */
  (function(){
    var burger = qs('.fec-burger');
    var nav    = qs('#fecNav');
    if (!burger || !nav) return;

    function setNav(open){
      document.body.classList.toggle('is-nav-open', !!open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    burger.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      var open = !document.body.classList.contains('is-nav-open');
      setNav(open);
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') setNav(false);
    });

    // Click outside closes only when open
    document.addEventListener('click', function(e){
      if (!document.body.classList.contains('is-nav-open')) return;
      var inside = nav.contains(e.target) || burger.contains(e.target);
      if (!inside) setNav(false);
    });
  })();


  /* ---------------------------------------------------------
     3) FXCheck tooltip (hover/focus)
     expects: .fec-card__fact--fec[data-tip="..."]
     --------------------------------------------------------- */
  (function(){
    function ensureTipEl(){
      var el = document.getElementById('fecTip');
      if (el) return el;

      el = document.createElement('div');
      el.id = 'fecTip';
      el.style.position = 'fixed';
      el.style.zIndex = '99999';
      el.style.pointerEvents = 'none';
      el.style.padding = '8px 10px';
      el.style.borderRadius = '12px';
      el.style.border = '1px solid rgba(0,0,0,.12)';
      el.style.background = 'rgba(255,255,255,.98)';
      el.style.boxShadow = '0 16px 50px rgba(0,0,0,.12)';
      el.style.color = 'rgba(0,0,0,.86)';
      el.style.fontSize = '12px';
      el.style.fontWeight = '800';
      el.style.maxWidth = '280px';
      el.style.display = 'none';
      document.body.appendChild(el);
      return el;
    }

    var tip = ensureTipEl();

    function showTip(text, x, y){
      if (!text) return;
      tip.textContent = text;
      tip.style.left = (x + 12) + 'px';
      tip.style.top  = (y + 12) + 'px';
      tip.style.display = 'block';
    }

    function hideTip(){ tip.style.display = 'none'; }

    document.addEventListener('mousemove', function(e){
      var t = e.target;
      if (!t) return;
      var host = t.closest ? t.closest('.fec-card__fact--fec[data-tip]') : null;
      if (!host) return hideTip();
      showTip(host.getAttribute('data-tip') || '', e.clientX, e.clientY);
    });

    document.addEventListener('mouseleave', hideTip, true);
    document.addEventListener('blur', hideTip, true);

    document.addEventListener('focusin', function(e){
      var host = e.target && e.target.closest ? e.target.closest('.fec-card__fact--fec[data-tip]') : null;
      if (!host) return;
      var r = host.getBoundingClientRect();
      showTip(host.getAttribute('data-tip') || '', r.left, r.bottom);
    });
  })();


  /* ---------------------------------------------------------
     4) Toolbar: condensed + filters panel + chips (pending) + apply/reset + sort
     Requirements in markup:
     - .fec-toolbar
     - button [data-fec-toggle="filters"]
     - panel  [data-fec-panel="filters"]
     - chips  [data-fec-chip="key"][data-value="val"]
     - (optional) badge count [data-fec-activecount]
     - (optional) sort select [data-fec-sort]
     - (optional) Apply/Reset buttons: [data-fec-action="apply"] / "reset"
     --------------------------------------------------------- */
  (function(){
    var toolbar = qs('.fec-toolbar');
    if (!toolbar) return;

    var toggleBtn      = qs('[data-fec-toggle="filters"]', toolbar);
    var panel          = qs('[data-fec-panel="filters"]', toolbar);
    var activeCountEl  = qs('[data-fec-activecount]', toolbar);
    var sortSel        = qs('[data-fec-sort]', toolbar);

    // Keys behavior
    var SINGLE = new Set(['fx','wr_max','cashout']); // one value at a time
    var TOGGLE = new Set(['code','crypto','cashable','hide_na','exclusive']);

    // Filter keys we "own" (for reset/count)
    var OWN_KEYS = ['fx','wr_max','cashout','cashable','code','crypto','hide_na','exclusive'];

    function getUrl(){
      return new URL(window.location.href);
    }

    function setParam(sp, key, value){
      if (TOGGLE.has(key) || SINGLE.has(key)) {
        if (sp.get(key) === value) sp.delete(key);
        else sp.set(key, value);
        return;
      }
      sp.set(key, value);
    }

    function isActive(sp, key, value){
      return sp.get(key) === value;
    }

    function countActive(sp){
      var n = 0;
      OWN_KEYS.forEach(function(k){ if (sp.has(k)) n++; });
      return n;
    }

    // Keep an internal pending URL in toolbar.dataset
    function getPendingUrl(){
      try {
        return toolbar.dataset.fecPendingUrl ? new URL(toolbar.dataset.fecPendingUrl) : null;
      } catch(e){
        return null;
      }
    }

    function setPendingUrl(url){
      toolbar.dataset.fecPendingUrl = url.toString();
    }

    function clearPendingUrl(){
      delete toolbar.dataset.fecPendingUrl;
    }

    // Render UI from "a given url" (not necessarily location)
    function syncUIFromUrl(url){
      var sp = url.searchParams;

      var chips = toolbar.querySelectorAll('[data-fec-chip][data-value]');
      chips.forEach(function(btn){
        var key = btn.getAttribute('data-fec-chip');
        var val = btn.getAttribute('data-value');
        var on  = isActive(sp, key, val);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      var n = countActive(sp);
      if (activeCountEl){
        activeCountEl.textContent = String(n);
        activeCountEl.hidden = (n === 0);
      }
      
      toolbar.classList.toggle('has-filters', n > 0);

      // Keep sort select in sync (optional)
      if (sortSel){
        var s = sp.get('sort') || 'best';
        // only set if option exists
        if ([...sortSel.options].some(function(o){ return o.value === s; })) {
          sortSel.value = s;
        }
      }
    }

    // Panel open/close
    function openPanel(open){
      if (!toggleBtn || !panel) return;
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.hidden = !open;
      toolbar.classList.toggle('is-filters-open', !!open);
    }

    // Init panel state (closed)
    if (toggleBtn && panel){
      // ensure deterministic initial state
      openPanel(false);

      toggleBtn.addEventListener('click', function(e){
        e.preventDefault();
        var isOpen = toggleBtn.getAttribute('aria-expanded') === 'true';
        openPanel(!isOpen);
      });

      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') openPanel(false);
      });
    }

    // Condensed on scroll (single handler)
    (function(){
      var last = null;
      function tick(){
        var condensed = window.scrollY > 80;
        if (condensed !== last){
          toolbar.classList.toggle('is-condensed', condensed);
          last = condensed;
        }
      }
      tick();
      window.addEventListener('scroll', tick, { passive: true });
    })();

    // Initial UI state: if we have a pending URL (rare), use it; else use current URL
    var initialUrl = getPendingUrl() || getUrl();
    syncUIFromUrl(initialUrl);

    // Click on any chip -> update pending URL (no reload)
    toolbar.addEventListener('click', function(e){
      var chip = e.target.closest && e.target.closest('[data-fec-chip][data-value]');
      if (!chip) return;

      e.preventDefault();

      var key = chip.getAttribute('data-fec-chip');
      var val = chip.getAttribute('data-value');

      var url = getPendingUrl() || getUrl();
      var sp  = url.searchParams;

      setParam(sp, key, val);

      // reset pagination
      if (sp.has('page')) sp.delete('page');

      setPendingUrl(url);
      syncUIFromUrl(url);
    });

    // Sort select -> immediate navigation (no pending)
    if (sortSel){
      sortSel.addEventListener('change', function(){
        var v = sortSel.value;
        var url = getUrl();
        var sp = url.searchParams;

        if (v === 'best') sp.delete('sort');
        else sp.set('sort', v);

        if (sp.has('page')) sp.delete('page');
        window.location.href = url.toString();
      });
    }

    // Apply / Reset buttons (optional)
    toolbar.addEventListener('click', function(e){
      var act = e.target.closest && e.target.closest('[data-fec-action]');
      if (!act) return;

      e.preventDefault();

      var action = act.getAttribute('data-fec-action');

      if (action === 'reset'){
        var url = getUrl();
        var sp  = url.searchParams;

        OWN_KEYS.forEach(function(k){ sp.delete(k); });
        if (sp.has('page')) sp.delete('page');

        setPendingUrl(url);
        syncUIFromUrl(url);
        return;
      }

      if (action === 'apply'){
        var targetUrl = getPendingUrl() ? getPendingUrl().toString() : window.location.href;
        window.location.href = targetUrl;
      }
    });

  })();


  /* ---------------------------------------------------------
     5) FAQ controls (open/close all + disable buttons)
     expects:
     - .fec-faq
     - .fec-faqctrl with buttons: [data-faq-action="open"] / "close"
     - items: .fec-faq__item (details)
     --------------------------------------------------------- */
  (function(){
    var faq  = qs('.fec-faq');
    var ctrl = qs('.fec-faqctrl');
    if(!faq || !ctrl) return;

    var items = Array.prototype.slice.call(faq.querySelectorAll('.fec-faq__item'));

    // show controls only if it makes sense
    ctrl.hidden = !(items.length >= 4);

    function updateState(){
      var openCount = items.filter(function(i){ return i.open; }).length;

      var btnOpen  = qs('[data-faq-action="open"]', ctrl);
      var btnClose = qs('[data-faq-action="close"]', ctrl);

      if (btnOpen)  btnOpen.disabled  = (openCount === items.length);
      if (btnClose) btnClose.disabled = (openCount === 0);
    }

    ctrl.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('[data-faq-action]');
      if(!btn) return;

      var action = btn.getAttribute('data-faq-action');
      var shouldOpen = (action === 'open');

      items.forEach(function(item){ item.open = shouldOpen; });
      updateState();
    });

    items.forEach(function(i){ i.addEventListener('toggle', updateState); });
    updateState();
  })();

})();

(function(){
  if (!document.body.classList.contains('is-free-spins')) return;
  if (window.innerWidth > 720) return;

  const scope = document.querySelector('.fec-scope');
  if (!scope) return;

  let lastY = window.scrollY;

  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    if (y > 20 && y > lastY){
      scope.classList.add('is-hidden');
    } else if (y < 10){
      scope.classList.remove('is-hidden');
    }
    lastY = y;
  }, { passive: true });
})();

