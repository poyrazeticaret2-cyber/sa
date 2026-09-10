/* ==========================================================================
   AlmancaPro - Tekrar oturumu yardımcıları
   Kart kuyruğu ve yeniden sıraya alma sunucu tarafında yönetilir.
   ========================================================================== */
(function () {
  'use strict';

  var AP = window.AlmancaPro || {};
  window.AlmancaPro = AP;

  /* Tekrar başlatma butonu: çift tıklamayı engeller ve durum gösterir. */
  document.querySelectorAll('[data-review-start]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      AP.setLoading(btn, true);
    });
  });

  /* Oturum ilerleme sayacı: sunucudan gelen değerleri okur. */
  var bar = document.querySelector('[data-session-progress]');
  if (bar) {
    var done = parseInt(bar.getAttribute('data-done') || '0', 10);
    var total = parseInt(bar.getAttribute('data-total') || '0', 10);
    var fill = bar.querySelector('.progress__fill');
    if (fill && total > 0) {
      fill.style.width = Math.round((done / total) * 100) + '%';
    }
  }

  /* Oturumdan çıkışta uyarı: yarım kalan oturum sunucuda saklanır. */
  document.querySelectorAll('[data-session-exit]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var remaining = parseInt(link.getAttribute('data-remaining') || '0', 10);
      if (remaining > 0) {
        if (!window.confirm('Oturumda ' + remaining + ' soru kaldı. Çıkarsan kaldığın yerden devam edebilirsin. Çıkmak istiyor musun?')) {
          e.preventDefault();
        }
      }
    });
  });
})();
