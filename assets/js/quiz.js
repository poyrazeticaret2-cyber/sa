/* ==========================================================================
   AlmancaPro - Quiz / alıştırma oturumu
   Sunucu otoritedir: puanlama, mastery ve kilit kararları burada verilmez.
   JS kapalıysa form normal POST ile çalışır.
   ========================================================================== */
(function () {
  'use strict';

  var AP = window.AlmancaPro || {};
  window.AlmancaPro = AP;

  var root = document.querySelector('[data-quiz]');
  if (!root) { return; }

  var form = root.querySelector('form[data-quiz-form]');
  var optionsWrap = root.querySelector('[data-quiz-options]');
  var textInput = root.querySelector('[data-quiz-input]');
  var submitBtn = root.querySelector('[data-quiz-submit]');
  var feedbackWrap = root.querySelector('[data-quiz-feedback]');
  var isSubmitting = false;
  var answered = false;
  var startedAt = Date.now();

  function escapeHtml(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function markOptions(userAnswer, correctAnswer) {
    if (!optionsWrap) { return; }
    optionsWrap.querySelectorAll('.quiz-option').forEach(function (btn) {
      var val = btn.getAttribute('data-value') || '';
      btn.disabled = true;
      var norm = function (x) { return String(x).trim().toLocaleLowerCase('de-DE'); };
      if (norm(val) === norm(correctAnswer)) {
        btn.classList.add('is-correct');
        var m1 = document.createElement('span');
        m1.className = 'quiz-option__mark';
        m1.textContent = '✓';
        btn.appendChild(m1);
      } else if (norm(val) === norm(userAnswer)) {
        btn.classList.add('is-wrong');
        var m2 = document.createElement('span');
        m2.className = 'quiz-option__mark';
        m2.textContent = '✕';
        btn.appendChild(m2);
      }
    });
  }

  function renderFeedback(d) {
    if (!feedbackWrap) { return; }
    var ok = !!d.correct;
    var html = '';
    html += '<div class="feedback ' + (ok ? 'feedback--ok' : 'feedback--bad') + '" tabindex="-1">';
    html += '<div class="feedback__head"><span aria-hidden="true">' + (ok ? '✓' : '✕') + '</span>';
    html += '<span>' + (ok ? 'Doğru' : 'Henüz değil') + '</span></div>';
    html += '<div class="feedback__body">';

    if (!ok) {
      html += '<div class="feedback__compare">';
      html += '<div class="feedback__cell feedback__cell--yours"><dt>Senin cevabın</dt><dd>' + escapeHtml(d.user_answer || '—') + '</dd></div>';
      html += '<div class="feedback__cell feedback__cell--right"><dt>Doğrusu</dt><dd>' + escapeHtml(d.correct_answer) + '</dd></div>';
      html += '</div>';
    }

    if (d.breakdown && d.breakdown.length > 1) {
      html += '<div class="breakdown">';
      d.breakdown.forEach(function (b) {
        html += '<div class="breakdown__row ' + (b.ok ? 'is-ok' : 'is-bad') + '">';
        html += '<span class="breakdown__mark" aria-hidden="true">' + (b.ok ? '✓' : '✕') + '</span>';
        html += '<span class="breakdown__label">' + escapeHtml(b.label) + '</span>';
        html += '<span class="breakdown__note">' + escapeHtml(b.note || '') + '</span>';
        html += '</div>';
      });
      html += '</div>';
    }

    if (d.explanation) {
      html += '<div class="feedback__label">Neden?</div><p>' + escapeHtml(d.explanation) + '</p>';
    }
    if (d.note) {
      html += '<p>' + escapeHtml(d.note) + '</p>';
    }
    if (d.memory_hint) {
      html += '<div class="feedback__label">Hatırlama</div><p>' + escapeHtml(d.memory_hint) + '</p>';
    }
    if (d.mastery_note) {
      html += '<div class="feedback__label">Mastery durumu</div><p>' + escapeHtml(d.mastery_note) + '</p>';
    }
    if (d.queue_note) {
      html += '<div class="feedback__queue">' + escapeHtml(d.queue_note) + '</div>';
    }
    html += '</div></div>';

    html += '<div class="quiz__submit-row">';
    html += '<a class="btn btn--lg" href="' + escapeHtml(d.next_url) + '" data-quiz-next>' + (ok ? 'DEVAM ET' : 'ANLADIM, DEVAM ET') + '</a>';
    html += '</div>';

    feedbackWrap.innerHTML = html;
    var fb = feedbackWrap.querySelector('.feedback');
    if (fb) { fb.focus(); }
    var next = feedbackWrap.querySelector('[data-quiz-next]');
    if (next) {
      setTimeout(function () { next.focus(); }, 60);
    }
  }

  function submitAnswer(answer) {
    if (isSubmitting || answered) { return; }
    if (!answer || !String(answer).trim()) {
      AP.toast('Önce bir cevap ver.', 'error');
      return;
    }
    isSubmitting = true;
    AP.setLoading(submitBtn, true);

    var payload = {
      action: 'answer',
      session_id: root.getAttribute('data-session') || '',
      exercise_id: root.getAttribute('data-exercise') || '',
      item_id: root.getAttribute('data-item') || '',
      answer: answer,
      response_ms: String(Date.now() - startedAt)
    };

    AP.post(root.getAttribute('data-endpoint') || window.location.pathname, payload)
      .then(function (res) {
        isSubmitting = false;
        AP.setLoading(submitBtn, false);
        if (!res.success) {
          AP.toast(res.message || 'Cevap kaydedilemedi. Tekrar dene.', 'error');
          return;
        }
        answered = true;
        var d = res.data || {};
        markOptions(answer, d.correct_answer);
        if (textInput) { textInput.readOnly = true; }
        if (submitBtn) { submitBtn.hidden = true; }
        d.user_answer = answer;
        renderFeedback(d);
      })
      .catch(function () {
        isSubmitting = false;
        AP.setLoading(submitBtn, false);
        AP.toast('Bağlantı koptu. Cevabın gönderilemedi, tekrar dene.', 'error');
      });
  }

  /* Seçenek tıklama: bütün kutu tıklanabilir */
  if (optionsWrap) {
    optionsWrap.querySelectorAll('.quiz-option').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (answered) { return; }
        optionsWrap.querySelectorAll('.quiz-option').forEach(function (b) { b.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
        submitAnswer(btn.getAttribute('data-value') || '');
      });
    });
  }

  /* Metin girişi: ENTER ve buton */
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (textInput) { submitAnswer(textInput.value); }
    });
  }
  if (textInput) {
    textInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitAnswer(textInput.value);
      }
    });
    textInput.focus();
  }

  /* Klavye kısayolu: 1-4 ile seçenek seçme */
  document.addEventListener('keydown', function (e) {
    if (answered || !optionsWrap) { return; }
    if (document.activeElement && document.activeElement.tagName === 'INPUT') { return; }
    var idx = ['1', '2', '3', '4', '5'].indexOf(e.key);
    if (idx === -1) { return; }
    var btns = optionsWrap.querySelectorAll('.quiz-option');
    if (btns[idx]) { btns[idx].click(); }
  });
})();
