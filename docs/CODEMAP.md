# CODEMAP
_Son güncelleme: 2026-11-07_

## Teknoloji
PHP 8.2+ (framework yok, düz PHP + PDO), MySQL/MariaDB utf8mb4, Apache/Plesk (.htaccess), Composer/npm yok. Vanilla JS/CSS.

## Klasör yapısı
| Klasör / dosya | Ne içerir |
|---|---|
| `/` (kök) | Tüm sayfalar (her .php bir URL), yardımcı kütüphaneler, içerik dosyaları |
| `assets/css` | main, auth, learning, admin .css |
| `assets/js` | app.js, quiz.js, review.js |
| `database.sql` | Tam veritabanı dökümü (büyük, okuma) |
| `AlmancaPro.zip` | Dağıtım paketi (dokunma) |
| `storage/` | Çalışma zamanı log (gitignore) |

## Neyin ayarı nerede
| Konu | Dosya | Anahtar / fonksiyon | Not |
|---|---|---|---|
| Sabitler (ad, oturum, mastery eşiği) | `config.php` | APP_NAME, MASTERY_* , SESSION_NAME | `config.local.php` yerel override (gitignore) |
| Veritabanı | `db.php` | db(), db_row/all/value/insert/exec, db_transaction | PDO sarmalayıcı |
| Ayarlar (DB'den) | `functions.php` | setting(), setting_set(), settings_all() | tablo `site_settings`; varsayılanlar `installer.php` almancapro_seed_settings |
| Açılış / hata yakalama | `bootstrap.php` | app_boot(), app_handle_throwable(), fatal_page() | |
| Kurulum | `install.php`, `installer.php`, `tani.php` | almancapro_run_install (34 adım), almancapro_install_plan | tani.php = sunucu teşhis |
| Şema | `schema.php` | almancapro_schema_statements() | ~55 tablo |
| Oturum / CSRF / güvenlik başlıkları | `functions.php` | app_session_start, csrf_*, send_security_headers | |
| Kullanıcı girişi | `auth.php` | auth_login_user, require_login, remember token, doğrulama kodu, şifre sıfırlama | sayfalar: login/register/verify/forgot-password/reset-password |
| Admin girişi | `admin-auth.php`, `admin-login.php` | | |
| Sayfa iskeleti / menü | `layout.php` | render_head, render_app_start, render_admin_start, app_nav_items, admin_nav_items | |
| E-posta (SMTP) | `mailer.php` | send_mail, smtp_send_raw, mail_send_* | ayar: `admin-smtp.php` |
| Telegram | `telegram-api.php`, `telegram-webhook.php`, `telegram.php` | telegram_call, handle_message, cmd_* | ayar: `admin-telegram.php` |
| Bildirimler | `notify.php` | notify_plan_daily, notify_enqueue, notify_process_queue | tablolar notification_* |
| Cron | `cron.php` | CLI veya `?secret=` | kilitli (app_locks) |
| AI asistan | `ai.php`, `ask.php` | ai_ask, ai_build_context, ai_fallback_answer | cURL; ayar `admin-ai.php` |
| Yönlendirmeler | `.htaccess`, `program.php`, `workplace.php`, `path.php` | | kısa yönlendirme dosyaları |
| SEO | `robots.txt`, `404.php` | | |

## Ana akışlar
### Öğrenme döngüsü
1. `exercise.php` / `quiz.php` / `review.php` → oturum (`planner.php` create_session, build_*_quiz)
2. `learning.php` evaluate_answer → cevap değerlendirme, hata kategorisi
3. `learning.php` record_attempt → update_skill_mastery / update_vocab_mastery (srs_next)
4. `learning.php` lesson_completion_state, lesson_lock_state → ders kilidi sunucu tarafı

### Günlük plan
1. `dashboard.php` → `planner.php` daily_plan / next_activity / build_plan_items
2. `intensive.php` 30 günlük yoğun program; `cron.php` → notify_plan_daily → Telegram/e-posta

### Kayıt → kullanım
register.php → verify.php → onboarding.php → placement-test.php → dashboard.php

### Telegram
telegram-webhook.php → handle_message/cmd_* → aynı öğrenme verisine yazar

## Önemli fonksiyonlar
| İsim | Dosya | Ne yapar |
|---|---|---|
| evaluate_answer | learning.php | Cevabı puanlar |
| compute_skill_mastery / compute_vocab_mastery | learning.php | Hakimiyet yüzdesi |
| srs_next | learning.php | Aralıklı tekrar |
| learning_path / level_summary | planner.php | Öğrenme yolu |
| build_checkpoint / build_remediation | planner.php | Kontrol/telafi quizleri |
| json_response, redirect, flash, input* | functions.php | Genel yardımcılar |

## İçerik dosyaları (büyük, gerekmedikçe açma)
`content-a0/a1/a2/b1.php` dersler · `content-vocab-*.php` kelimeler · `content-scenarios.php` · `content-skills.php` · `content-kb.php` bilgi tabanı · `content-placement.php` seviye testi · `seed.php` tohumlama

## Sayfalar
Kullanıcı: home, dashboard, course, lesson, grammar, vocabulary, scenario, work-german, weak-areas, progress, profile, settings, support, donation-notify, privacy, terms, delete-account.
Admin: `admin*.php` (users, lessons, exercises, vocabulary, grammar, questions, quizzes, skills, curriculum, donations, notifications, logs, health, settings, smtp, telegram, ai, profile, password).

## Veri modeli (schema.php)
Kullanıcı: users, email_verifications, password_resets, remember_tokens, login_attempts · İçerik: modules, skills, lessons, lesson_sections, grammar_*, vocabulary*, exercises*, scenarios* · İlerleme: user_lesson_progress, user_skill_mastery, user_vocabulary_mastery, study_sessions, session_items, exercise_attempts, daily_plans · Bildirim: telegram_*, notification_*, mail_log · Diğer: site_settings, admins, admin_logs, app_locks, rate_limits, questions/answers, knowledge_base, donations*.

## Dış servisler
| Servis | Dosya | Anahtar |
|---|---|---|
| Telegram Bot API | telegram-api.php | DB ayarı (admin-telegram) |
| SMTP | mailer.php | DB ayarı (admin-smtp) |
| AI API | ai.php | DB ayarı (admin-ai) |
| ALMANCAPRO_DEBUG | config.php | env |

## Dokunma
`database.sql`, `AlmancaPro.zip`, `content-*.php` (devasa), `storage/`.
