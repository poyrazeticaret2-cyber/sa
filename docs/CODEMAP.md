# CODEMAP
_Son güncelleme: 2026-10-31_

## Teknoloji
PHP 8.2 (framework yok) / MySQL-MariaDB (PDO) / Composer-npm yok; Plesk-Apache üzerinde çalışır. Ek: `urun-bot/` Python 3.10+ (yalnız stdlib) ürün araştırma botu.

## Klasör yapısı
| Klasör | Ne içerir |
|---|---|
| `/` (kök) | Tüm PHP sayfaları ve kütüphaneleri (düz yapı) |
| `assets/css`, `assets/js`, `assets/img` | Stil (main/learning/auth/admin), quiz/review/app JS, favicon |
| `docs/` | Bu harita |
| `urun-bot/` | AliExpress/Alibaba → Türkiye fırsat ürün botu (Python, ayrı proje) |

## Neyin ayarı nerede
| Konu | Dosya | Fonksiyon / anahtar / satır | Not |
|---|---|---|---|
| Veritabanı bağlantısı | `config.php` | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `config.local.php` varsa onu kullanır (gitignore) |
| PDO katmanı | `db.php` | `db()`, `db_query()`, `db_all()`, `db_transaction()` | |
| Uygulama sabitleri | `config.php` | `APP_*`, `MASTERY_*`, `SESSION_NAME`, `REMEMBER_DAYS` | |
| Debug | `config.php` | env `ALMANCAPRO_DEBUG=1` → `APP_DEBUG` | |
| Önyükleme / hata yakalama | `bootstrap.php` | `app_boot()`, `app_handle_throwable()`, `fatal_page()` | Her sayfanın ilk satırı |
| Site ayarları (DB) | `functions.php` | `setting()`, `setting_set()`, `settings_all()` | Tablo `site_settings` |
| Auth (kullanıcı) | `auth.php`, `login.php`, `register.php`, `verify.php` | | Remember token, rate limit |
| Auth (admin) | `admin-auth.php`, `admin-login.php` | | Varsayılan admin: `config.php` `DEFAULT_ADMIN_*` |
| CSRF / güvenlik başlıkları | `functions.php` | `csrf_*()`, `send_security_headers()` | |
| Rate limit / kilit | `functions.php` | `rate_limit_hit()`, `acquire_lock()` | |
| Şema / migration | `schema.php`, `installer.php` | `almancapro_migrations()`, `almancapro_run_install()` | 34 adımlı parça kurulum |
| İçerik yükleme | `seed.php`, `content-*.php` | `seed_curriculum()`, `seed_vocabulary()` | A0–B1 içerik dosyaları |
| Kurulum sihirbazı | `install.php` | | |
| Tema / renk / font | `assets/css/main.css`, `layout.php` | `render_head()` | |
| Menü | `layout.php` | `app_nav_items()` | |
| E-posta (SMTP) | `mailer.php`, `admin-smtp.php` | `send_mail()`, ayar `smtp_*`, `mail_from` | |
| Telegram | `telegram-api.php`, `telegram-webhook.php`, `admin-telegram.php` | ayar `telegram_bot_token`, `telegram_webhook_url` | |
| Bildirim kuyruğu | `notify.php` | `notify_enqueue()`, `notify_process_queue()` | |
| Cron | `cron.php` | ayar `cron_secret` | CLI veya `?secret=` |
| AI öğretmen | `ai.php`, `admin-ai.php` | ayar `ai_api_key`, `ai_endpoint`, `ai_model` | Opsiyonel |
| Bağış | `admin-donations.php`, `donation-notify.php` | ayar `donation_iban`, `donation_*` | |
| Loglama | `functions.php` | `app_log()`, `admin_log()`, `scrub_secrets()` | `storage/` |
| SEO / robots | `robots.txt`, `layout.php` | | |
| Apache kuralları | `.htaccess` | | |
| Ürün botu CSV içe aktarma (Alibaba vb.) | `urun-bot/urun_bot/sources/csv_veri.py` | `oku()` | Sütun listesi dosya başında |
| Ürün botu ayarları | `urun-bot/urun_bot/data/categories.json`, `urun-bot/urun_bot/data/customs_rules.json` | | Vergi oranları, izinler, anahtar kelimeler |

## Ana akışlar
### Öğrenme döngüsü
1. `lesson.php` → ders gösterimi, kilit kontrolü (`planner.php` `next_available_lesson()`)
2. `exercise.php` / `quiz.php` → cevap gönderimi
3. `learning.php` → `evaluate_answer()`, `skill_mastery()`, SRS güncellemesi
4. `review.php` → tekrar kuyruğu

### Günlük plan
1. `dashboard.php` → `planner.php` `daily_plan()`, `next_activity()`
2. `intensive.php` → 30 günlük program

### Kurulum
1. `index.php` → `installer.php` `almancapro_run_install()` (parça parça)
2. `schema.php` → tablolar, `seed.php` → içerik

### Bildirim
1. `cron.php` → `notify_plan_daily()` → `notify_process_queue()` → `mailer.php` / `telegram-api.php`

### Ürün botu
1. `urun-bot/urun_bot/cli.py` `main()` → `sources/` (aliexpress, trendyol, trends, csv_veri, tcmb) sinyalleri toplar
2. `urun-bot/urun_bot/scoring.py` `score_category()` → `customs.py` `ticari_maliyet()` ile vergi/varış maliyeti
3. `urun-bot/urun_bot/report.py` → `raporlar/` altına HTML + CSV; `notify.py` → Telegram

## Önemli fonksiyon / component / sınıflar
| İsim | Dosya | Ne yapar |
|---|---|---|
| `evaluate_answer()` | `learning.php` | Cevabı değerlendirir, hata kategorisi atar |
| `daily_plan()` | `planner.php` | Günlük planı üretir |
| `render_app_start()` | `layout.php` | Uygulama sayfa iskeleti |
| `telegram_call()` | `telegram-api.php` | Bot API çağrısı |
| `ticari_maliyet()`, `posta_maliyet()` | `urun-bot/urun_bot/customs.py` | Türkiye varış maliyeti (beyanname / posta %60) |
| `score_category()` | `urun-bot/urun_bot/scoring.py` | Fırsat skoru |

## Veri modeli
| Tablo / model | Dosya | Önemli alanlar / ilişkiler |
|---|---|---|
| `users`, `admins` | `schema.php` | Kullanıcı, yönetici |
| `modules` → `lessons` → `lesson_sections` | `schema.php` | Müfredat |
| `skills`, `lesson_skills`, `lesson_prerequisites` | `schema.php` | Beceri ve önkoşul |
| `vocabulary`, `grammar_topics`, `exercises` | `schema.php` | İçerik |
| `user_*_mastery`, `exercise_attempts` | `schema.php` | İlerleme ve SRS |
| `daily_plans`, `study_sessions` | `schema.php` | Plan / oturum |
| `telegram_*`, `notification_*`, `mail_log` | `schema.php` | Bildirim |
| `donations`, `site_settings` | `schema.php` | Bağış, ayarlar |

## Dış servisler
| Servis | Kullanıldığı dosya | Env anahtarı |
|---|---|---|
| SMTP | `mailer.php` | DB ayarı `smtp_*` |
| Telegram Bot API | `telegram-api.php` | DB ayarı `telegram_bot_token` |
| AI API | `ai.php` | DB ayarı `ai_api_key` |
| AliExpress Affiliate API | `urun-bot/urun_bot/sources/aliexpress.py` | `ALIEXPRESS_APP_KEY`, `ALIEXPRESS_APP_SECRET` |
| Google Trends (pytrends, ops.) | `urun-bot/urun_bot/sources/trends.py` | — |
| TCMB kur | `urun-bot/urun_bot/sources/tcmb.py` | `USD_TRY` (yedek) |
| Telegram (bot özeti) | `urun-bot/urun_bot/notify.py` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` |
| Trendyol arama | `urun-bot/urun_bot/sources/trendyol.py` | — |

## Dokunma
- `database.sql` (büyük dump), `AlmancaPro.zip` (paket), `config.local.php`, `storage/`, `.install.lock`
