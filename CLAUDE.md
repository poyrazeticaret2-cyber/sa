# AlmancaPro
Türkçe konuşanlar için ücretsiz A0-B1 Almanca öğrenme platformu. Framework'süz PHP 8.2 + MySQL (PDO), Apache/Plesk. Mastery motoru, SRS, Telegram bildirimleri, admin paneli.

## Komutlar
- Derleme/paket yöneticisi yok. Sözdizimi: `php -l dosya.php`
- Kurulum web üzerinden: `install.php`; teşhis: `tani.php`

## Kod kuralları
- Her .php bir sayfa; ortak kod `functions.php`, `db.php`, `layout.php`.
- Çıktıyı `e()` ile kaçır, POST'larda `csrf_require()`, SQL'de PDO parametreleri.
- Ayarlar DB'de (`setting()`), sırlar koda yazılmaz.
- Metinler Türkçe; kullanıcıya ham 500 gösterme (`bootstrap.php`).

## Dosya bulma kuralı
- Görevde ÖNCE docs/CODEMAP.md'ye bak, ilgili dosyayı doğrudan aç. Tüm projeyi tarama.
- Haritada yoksa hedefli ara, bulunca CODEMAP.md'ye ekle.
- Dosya ekler/taşır/silersen CODEMAP.md'yi aynı görevde güncelle.
