# AlmancaPro
Türkçe konuşanlar için A0–B1 ücretsiz Almanca öğrenme sitesi. PHP 8.2, framework yok, MySQL/MariaDB (PDO), Plesk/Apache'de terminalsiz kurulum.
Ayrıca `urun-bot/` altında bağımsız bir Python ürün araştırma botu var (AliExpress/Alibaba → Türkiye fırsatları).

## Komutlar
- PHP sözdizimi kontrolü: `php -l <dosya>.php`
- Site: Apache/Plesk altında çalışır; ilk açılışta `installer.php` kurulumu yapar.
- Cron: `php cron.php`
- Ürün botu: `cd urun-bot && python3 -m urun_bot --demo` · test: `python3 -m unittest discover -s tests`

## Kod kuralları
- Her sayfa `bootstrap.php` ile başlar; `declare(strict_types=1)`.
- Çıktıyı `e()`/`eattr()` ile kaçır, formlarda `csrf_field()` + `csrf_require()`.
- DB erişimi yalnız `db.php` yardımcılarıyla (hazır ifadeler).
- Gizli değerler (`smtp_password`, `telegram_bot_token`, `ai_api_key`) loglanmaz, tarayıcıya gönderilmez.
- Arayüz metinleri Türkçe.

## Dosya bulma kuralı
- Görevde ÖNCE docs/CODEMAP.md'ye bak, ilgili dosyayı doğrudan aç. Tüm projeyi tarama.
- Haritada yoksa hedefli ara, bulunca CODEMAP.md'ye ekle.
- Dosya ekler/taşır/silersen CODEMAP.md'yi aynı görevde güncelle.
