/* ==========================================================================
   AlmancaPro - Genel arayüz davranışları
   Vanilla JS, harici bağımlılık yok. JS olmadan da kritik akışlar çalışır.
   ========================================================================== */
(function () {
  'use strict';

  var AP = window.AlmancaPro || {};
  window.AlmancaPro = AP;

  /* ---------------- CSRF ---------------- */
  AP.csrf = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  /* ---------------- JSON isteği ---------------- */
  AP.post = function (url, data) {
    var body = new URLSearchParams();
    Object.keys(data || {}).forEach(function (k) {
      if (data[k] !== null && data[k] !== undefined) { body.append(k, data[k]); }
    });
    body.append('csrf_token', AP.csrf());
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': AP.csrf()
      },
      credentials: 'same-origin',
      body: body.toString()
    }).then(function (res) {
      return res.json().catch(function () {
        return { success: false, message: 'Sunucu beklenmeyen bir yanıt verdi.', data: {}, errors: {} };
      });
    });
  };

  /* ---------------- Toast ---------------- */
  AP.toast = function (message, type) {
    var stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('role', 'status');
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    var el = document.createElement('div');
    el.className = 'toast' + (type ? ' toast--' + type : '');
    el.textContent = (type === 'success' ? '✓ ' : type === 'error' ? '✕ ' : '● ') + message;
    stack.appendChild(el);
    setTimeout(function () {
      if (el.parentNode) { el.parentNode.removeChild(el); }
    }, 5000);
  };

  /* ---------------- Buton yükleme durumu ---------------- */
  AP.setLoading = function (btn, loading) {
    if (!btn) { return; }
    if (loading) {
      if (!btn.dataset.originalText) { btn.dataset.originalText = btn.textContent; }
      btn.textContent = 'YÜKLENİYOR…';
      btn.disabled = true;
      btn.classList.add('is-loading');
    } else {
      if (btn.dataset.originalText) { btn.textContent = btn.dataset.originalText; }
      btn.disabled = false;
      btn.classList.remove('is-loading');
    }
  };

  /* ---------------- Çift gönderim koruması ---------------- */
  function guardForms() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (btn && !btn.disabled) {
          setTimeout(function () { AP.setLoading(btn, true); }, 0);
        }
      });
    });
  }

  /* ---------------- Şifre göster / gizle ---------------- */
  function passwordToggles() {
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var field = btn.closest('.pw-field');
        if (!field) { return; }
        var input = field.querySelector('input');
        if (!input) { return; }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
        btn.textContent = show ? 'GİZLE' : 'GÖSTER';
      });
    });
  }

  /* ---------------- Şifre gücü ---------------- */
  function passwordStrength() {
    document.querySelectorAll('[data-strength-for]').forEach(function (widget) {
      var input = document.getElementById(widget.getAttribute('data-strength-for'));
      if (!input) { return; }
      var segs = widget.querySelectorAll('.pw-strength__seg');
      var label = widget.querySelector('.pw-strength__label');

      function score(v) {
        var s = 0;
        if (v.length >= 8) { s++; }
        if (v.length >= 12) { s++; }
        if (/[a-zçğıöşü]/.test(v) && /[A-ZÇĞİÖŞÜ]/.test(v)) { s++; }
        if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) { s++; }
        if (v.length === 0) { return 0; }
        return Math.max(1, Math.min(4, s));
      }

      function render() {
        var s = score(input.value);
        var names = ['', 'ZAYIF', 'ORTA', 'GÜÇLÜ', 'ÇOK GÜÇLÜ'];
        var cls = ['', 'is-on-weak', 'is-on-medium', 'is-on-strong', 'is-on-strong'];
        segs.forEach(function (seg, i) {
          seg.className = 'pw-strength__seg' + (i < s ? ' ' + cls[s] : '');
        });
        if (label) { label.textContent = names[s]; }
      }
      input.addEventListener('input', render);
      render();
    });
  }

  /* ---------------- Şifre eşleşme ---------------- */
  function passwordMatch() {
    document.querySelectorAll('[data-match-for]').forEach(function (out) {
      var a = document.getElementById(out.getAttribute('data-match-for'));
      var b = document.getElementById(out.getAttribute('data-match-with'));
      if (!a || !b) { return; }
      function render() {
        if (!b.value) { out.textContent = ''; out.className = 'hint'; return; }
        var ok = a.value === b.value;
        out.textContent = ok ? '✓ Şifreler eşleşiyor' : '✕ Şifreler eşleşmiyor';
        out.className = 'hint ' + (ok ? 'text-success' : 'text-danger');
      }
      a.addEventListener('input', render);
      b.addEventListener('input', render);
    });
  }

  /* ---------------- Modal / alt panel ---------------- */
  var lastFocused = null;

  AP.openModal = function (id) {
    var modal = document.getElementById(id);
    if (!modal) { return; }
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    var focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable) { focusable.focus(); }
  };

  AP.closeModal = function (id) {
    var modal = id ? document.getElementById(id) : document.querySelector('.modal-backdrop:not([hidden])');
    if (!modal) { return; }
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
  };

  function modals() {
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        AP.openModal(btn.getAttribute('data-modal-open'));
      });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        AP.closeModal(btn.getAttribute('data-modal-close') || null);
      });
    });
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('mousedown', function (e) {
        if (e.target === backdrop) { AP.closeModal(backdrop.id); }
      });
    });
    document.addEventListener('keydown', function (e) {
      var open = document.querySelector('.modal-backdrop:not([hidden])');
      if (!open) { return; }
      if (e.key === 'Escape') { AP.closeModal(open.id); return; }
      if (e.key !== 'Tab') { return; }
      var items = open.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])');
      if (!items.length) { return; }
      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  /* ---------------- Kopyala butonları ---------------- */
  function copyButtons() {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy');
        var done = function () {
          var original = btn.dataset.copyLabel || btn.textContent;
          btn.dataset.copyLabel = original;
          btn.textContent = '✓ KOPYALANDI';
          setTimeout(function () { btn.textContent = original; }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { AP.toast('Kopyalanamadı. Metni elle seçebilirsin.', 'error'); });
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (err) { AP.toast('Kopyalanamadı.', 'error'); }
          document.body.removeChild(ta);
        }
      });
    });
  }

  /* ---------------- Almanca telaffuz (Web Speech API) ---------------- */
  AP.speak = function (text) {
    if (!('speechSynthesis' in window)) {
      AP.toast('Tarayıcın sesli okumayı desteklemiyor.', 'error');
      return false;
    }
    try {
      window.speechSynthesis.cancel();
      var utt = new SpeechSynthesisUtterance(text);
      utt.lang = 'de-DE';
      utt.rate = 0.9;
      window.speechSynthesis.speak(utt);
      return true;
    } catch (e) {
      return false;
    }
  };

  function speakButtons() {
    if (!('speechSynthesis' in window)) {
      document.querySelectorAll('[data-speak]').forEach(function (btn) {
        btn.hidden = true;
      });
      return;
    }
    document.querySelectorAll('[data-speak]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        AP.speak(btn.getAttribute('data-speak'));
      });
    });
  }

  /* ---------------- Kapatılabilir satırlar (destek hatırlatması) ---------------- */
  function dismissables() {
    document.querySelectorAll('[data-dismiss-key]').forEach(function (el) {
      var key = 'ap_dismiss_' + el.getAttribute('data-dismiss-key');
      var days = parseInt(el.getAttribute('data-dismiss-days') || '60', 10);
      var until = null;
      try { until = window.localStorage.getItem(key); } catch (e) { until = null; }
      if (until && parseInt(until, 10) > Date.now()) {
        el.hidden = true;
        return;
      }
      el.hidden = false;
      var btn = el.querySelector('[data-dismiss-btn]');
      if (btn) {
        btn.addEventListener('click', function () {
          try { window.localStorage.setItem(key, String(Date.now() + days * 86400000)); } catch (e) { /* yoksay */ }
          el.hidden = true;
        });
      }
    });
  }

  /* ---------------- Onay gerektiren işlemler ---------------- */
  function confirmActions() {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!window.confirm(el.getAttribute('data-confirm'))) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    });
  }

  /* ---------------- Otomatik gönderilen filtreler ---------------- */
  function autoSubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
      el.addEventListener('change', function () {
        var form = el.closest('form');
        if (form) { form.submit(); }
      });
    });
  }

  /* ---------------- Hazır soru şablonları ---------------- */
  function fillTargets() {
    document.querySelectorAll('[data-fill-target]').forEach(function (el) {
      el.addEventListener('click', function () {
        var target = document.getElementById(el.getAttribute('data-fill-target'));
        if (!target) { return; }
        target.value = el.getAttribute('data-q') || el.textContent.trim();
        target.focus();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    guardForms();
    passwordToggles();
    passwordStrength();
    passwordMatch();
    modals();
    copyButtons();
    speakButtons();
    dismissables();
    confirmActions();
    autoSubmit();
    fillTargets();
  });
})();
