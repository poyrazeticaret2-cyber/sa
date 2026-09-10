# AlmancaPro

**Almancayı ezberleme. Gerçekten öğren.**

A0'dan B1'e kadar, öğrenmeden ilerlemene izin vermeyen **ücretsiz** Almanca eğitim sistemi.
Türkçe konuşan, Almanya'ya iş/eğitim/yaşam için giden veya gitmeyi planlayan yetişkinler için tasarlandı.

---

## 1. AlmancaPro nedir?

AlmancaPro klasik bir "dersi oku → ileri" uygulaması **değildir**. Sistem, bir konuyu
gerçekten öğrendiğini kanıtlamadan bir sonraki konuya geçmene izin vermez.

Öğrenme döngüsü:

```
ÖĞREN → ANLA → UYGULA → TEST ET → HATAYI ANALİZ ET → TEKRAR ET
      → FARKLI BAĞLAMDA TEST → GECİKMELİ HATIRLAMA → MASTERED → SONRAKİ KONU
```

Öne çıkanlar:

- **Öğrenmeden geçiş yok.** Ders kilidi sunucu tarafında zorlanır; adres çubuğundan
  (`lesson.php?id=999`) atlamak mümkün değildir.
- **Çok sinyalli hakimiyet (mastery) motoru.** Tek doğru cevap "öğrendim" saymaz.
  Tanıma, Türkçe→Almanca aktif hatırlama, Almanca→Türkçe, artikel, çoğul, üretim,
  yazım, gecikmeli hatırlama ve farklı bağlam ayrı ayrı ölçülür.
- **Gerçek aralıklı tekrar (SRS).** Doğru cevap aralığı uzatır, yanlış cevap kısaltır,
  zayıflığı artırır ve konuyu telafi kuyruğuna alır.
- **16 başlıklı hata taksonomisi.** Artikel, cinsiyet, hâl, çekim, kelime sırası, yazım,
  büyük harf, çoğul, kelime hatırlama, edat, yardımcı fiil, Partizip II, ayrılabilir fiil,
  zamir, sıfat takısı ve anlam hataları ayrı ayrı sayılır.
- **Kelime öğretimi standardı.** Hiçbir isim "Tisch = masa" diye öğretilmez.
  Her zaman `der Tisch – die Tische / masa` biçiminde, artikel + çoğul + örnek cümle ile.
- **Telegram entegrasyonu.** Hatırlatmalar ve Telegram üzerinden mini quiz; Telegram'daki
  cevaplar web ile **aynı** öğrenme verisine yazılır.
- **30 günlük yoğun program.** Kalan güne, hakimiyete ve zayıf alanlara göre uyarlanır.
  (B1 seviyesi 30 günde **garanti edilmez**; sistem bunu asla vaat etmez.)
- **İş Almancası ve günlük hayat modülleri.** İşe giriş, vardiya, hastalık bildirimi,
  izin talebi, Anmeldung, Bürgeramt, Krankenkasse, banka, market, tren, doktor…
  (Dil öğretilir; **hukuki tavsiye verilmez**.)
- **Tamamen ücretsiz.** Abonelik, paket, ödeme sistemi, kart bilgisi yoktur.

---

## 2. Sunucu gereksinimleri

| Gereksinim | Değer |
|---|---|
| PHP | **8.2** veya üzeri |
| PHP eklentileri | `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl` (zorunlu) · `curl` (Telegram/AI için) |
| Veritabanı | MySQL 5.7+ veya MariaDB 10.4+ (InnoDB, utf8mb4) |
| Web sunucusu | Apache, Plesk (Apache+nginx) veya nginx |
| Diğer | Composer, npm, Node.js, Docker, SSH **gerekmez** |

Framework kullanılmaz. Kurulum için terminale ihtiyaç yoktur.

---

## 3. Plesk'e kurulum

1. **Dosyaları yükleyin.**
   Plesk > *Dosyalar* > `httpdocs` klasörünü açın.
   `AlmancaPro.zip` dosyasını yükleyip **"Ayıkla"** deyin.
   Ayıklama sonrası `index.php`, `login.php`, `admin-login.php` ve `assets/`
   klasörü doğrudan `httpdocs` içinde olmalıdır (fazladan bir `AlmancaPro/` klasörü olmamalıdır).

2. **PHP sürümünü ayarlayın.**
   Plesk > *PHP Ayarları* > PHP **8.2** (veya üzeri), handler: FPM.

3. **Siteyi açın.**
   `https://alanadiniz.com` adresine gidin.
   AlmancaPro veritabanı bağlantısını test eder, **tabloları otomatik oluşturur**,
   müfredatı ve kelime hazinesini yükler, varsayılan yönetici hesabını açar.
   Bu işlem idempotenttir: sayfayı ikinci kez açmak veri tekrarına yol açmaz.

4. **Kurulum sihirbazını tamamlayın (isteğe bağlı).**
   `https://alanadiniz.com/install.php` adresinden site adresi, SMTP, Telegram,
   cron anahtarı ve AI ayarlarını girebilirsiniz. **Hepsi isteğe bağlıdır.**
   Boş bırakırsanız site tam olarak çalışır; ilgili özellikler yönetim panelinde
   "yapılandırılmadı" olarak görünür ve sonradan tamamlanabilir.

5. **Yönetici şifresini değiştirin.**
   `https://alanadiniz.com/admin-login.php` → Kullanıcı adı `Admin`, ilk şifre `Admin12345!`
   Sistem ilk girişte şifre değiştirmenizi zorunlu kılar.

6. **HTTPS'i açın.**
   Plesk > *SSL/TLS Sertifikaları* > Let's Encrypt sertifikası alın ve
   "HTTPS'e yönlendir" seçeneğini işaretleyin.

---

## 4. Veritabanı

Veritabanı bağlantı bilgileri sunucu tarafında `config.php` içinde sabittir ve
kurulum sırasında **sorulmaz**. Bu bilgiler hiçbir HTML sayfasında, JavaScript
dosyasında, JSON yanıtında, hata ekranında, logda, kullanıcı panelinde veya
yönetim panelinde görünmez.

| Ayar | Değer |
|---|---|
| Sunucu | `localhost` |
| Port | `3306` |
| Veritabanı | `lxsadauz_almanca` |
| Kullanıcı | `lxsadauz_almanca` |
| Karakter seti | `utf8mb4` |

Bağlantı PDO ile kurulur; `ERRMODE_EXCEPTION`, `FETCH_ASSOC` ve
`EMULATE_PREPARES = false` ayarlanır. Bütün sorgular hazırlanmış ifadelerle çalışır.

### Otomatik kurulum

İlk sayfa açılışında sırasıyla:

1. Bağlantı test edilir
2. Şema kontrol edilir, eksik tablolar/indeksler/foreign key'ler oluşturulur
3. Hata kategorileri, skill tanımları ve ön koşul ilişkileri yüklenir
4. Gerçek A0–B1 müfredatı, kelime hazinesi, dilbilgisi konuları ve alıştırmalar yüklenir
5. Senaryolar ve bilgi bankası yüklenir
6. Varsayılan yönetici hesabı ve site ayarları oluşturulur
7. Kurulum kilidi (`.install.lock`) yazılır

### database.sql

`database.sql` **yedek ve referans** dosyasıdır; normal kurulumda içe aktarmanız
gerekmez. Şemanın tamamını ve gerçek eğitim içeriğini (dersler, bölümler, kelimeler,
alıştırmalar, dilbilgisi, senaryolar) içerir. Kullanıcı verisi, oturum verisi,
loglar ve sırlar bu dosyada **yer almaz**.

---

## 5. E-posta (SMTP) ayarları

E-posta gönderimi Composer'sız, saf PHP ile (soket + STARTTLS) yapılır.

Doğrulama kodları ve şifre sıfırlama bağlantıları **`noreply@alanadiniz.com`**
adresinden gönderilir. Kurulum sırasında sunucu, port, kullanıcı adı ve gönderen
adresi **alan adınıza göre otomatik doldurulur**; sizin girmeniz gereken tek değer
posta kutusunun şifresidir.

**Adım adım:**

1. Plesk > *Mail* > **Create Email Address** ile `noreply@alanadiniz.com` kutusunu oluşturun.
2. Bir şifre belirleyin.
3. Yönetim paneli > **SMTP Ayarları** sayfasını açın; alanların dolu geldiğini göreceksiniz.
4. Yalnızca **Şifre** alanına o posta kutusunun şifresini yazıp kaydedin.
5. **Test E-postası Gönder** ile doğrulayın.

Otomatik doldurulan değerler (Plesk'in *Mail Client Setup* ekranındakilerle aynıdır):

| Alan | Değer |
|---|---|
| SMTP sunucusu | `alanadiniz.com` |
| Port | `465` |
| Şifreleme | SSL/TLS |
| Kullanıcı adı | `noreply@alanadiniz.com` (tam adres) |
| Şifre | (yalnızca yazılır, panelde okunamaz) |
| Gönderen e-posta | `noreply@alanadiniz.com` |
| Gönderen adı | `AlmancaPro` |

> Sunucunuz 465 yerine STARTTLS istiyorsa portu `587`, şifrelemeyi *STARTTLS* yapın.

Kaydettikten sonra **Test E-postası Gönder** düğmesiyle gerçek bir gönderim yapabilirsiniz.
Sonuç `mail_log` tablosuna yazılır. SMTP hataları loglanır; **SMTP şifresi asla loglanmaz**.

SMTP yapılandırılmazsa site çalışmaya devam eder; doğrulama kodu ve şifre sıfırlama
e-postaları gönderilemez ve kullanıcıya bu durum açıkça bildirilir.

---

## 6. Telegram botu kurulumu

Telegram botları, kullanıcının telefon numarasına veya `@kullanıcıadı`na kendiliğinden
mesaj **gönderemez**. AlmancaPro bu yüzden doğru akışı kullanır ve kullanıcıdan
**asla chat_id istemez**.

### 6.1 Botu oluşturun

1. Telegram'da **@BotFather**'ı açın.
2. `/newbot` yazın.
3. Bota bir ad verin (örnek: `AlmancaPro`).
4. Bir kullanıcı adı verin; `bot` ile bitmelidir (örnek: `AlmancaProBot`).
5. BotFather size `123456789:AA...` biçiminde bir **token** verir.

### 6.2 Panele girin

6. Yönetim paneli > **Telegram** sayfasını açın.
7. Bot kullanıcı adını (@ olmadan) ve token'ı girip **Kaydet** deyin.
8. **Webhook'u Ayarla** düğmesine basın. (Site HTTPS olmalıdır.)

Aynı sayfada bot durumunu, webhook durumunu, bağlı kullanıcıları, gelen update'leri,
mesaj kuyruğunu ve başarısız gönderimleri görebilirsiniz. Bot token'ı panelde
**tam metin olarak gösterilmez**; yalnızca maskeli özeti görünür ve değiştirmek için
mevcut değeri okumanız gerekmez.

### 6.3 Kullanıcı hesabını nasıl bağlar?

1. Kullanıcı `Telegram` sayfasında **"Telegram'ı Bağla"** düğmesine basar.
2. Sunucu tek kullanımlık, kriptografik olarak güvenli bir token üretir (30 dakika geçerli).
3. Kullanıcı `https://t.me/BOT_KULLANICI_ADI?start=TOKEN` bağlantısına yönlendirilir.
4. Kullanıcı Telegram'da **Start**'a basar.
5. Webhook token'ı doğrular, süresini kontrol eder, web hesabıyla eşler,
   Telegram kullanıcı kimliğini ve chat_id'yi kaydeder, token'ı kullanıldı olarak işaretler.
6. Bot şu mesajı gönderir: *"AlmancaPro hesabın başarıyla bağlandı."*

### 6.4 Bot komutları

```
/start     Hesabı bağla / karşılama
/ders      Sıradaki dersi göster
/quiz      Mini quiz başlat
/tekrar    Zamanı gelen tekrarları çöz
/durum     Seviye, hakimiyet ve ilerleme özeti
/seri      Çalışma serisi
/hatirlat  Hatırlatma zamanlarını ayarla
/ayarlar   Bildirim tercihleri
/durdur    Bildirimleri durdur
/yardim    Komut listesi
```

Telegram quizleri satır içi (inline) düğmelerle çalışır. Verilen cevaplar web'deki
hakimiyet motoruna yazılır: yanlış cevaplar hata kaydı, hakimiyet düşüşü ve
tekrar kuyruğu kaydı oluşturur.

---

## 7. Zamanlanmış görev (cron)

Cron; zamanı gelen tekrarları işler, bildirim kuyruğunu gönderir, Telegram ve e-posta
gönderimlerini yapar, günlük planları oluşturur, çalışma serisini günceller ve
süresi dolmuş tokenları temizler.

Aynı anda iki kez çalışsa bile kilit (mutex) mekanizması sayesinde **çift gönderim olmaz**.

### Plesk > Zamanlanmış Görevler

**Komut satırı (önerilen):**

```
/opt/plesk/php/8.2/bin/php /var/www/vhosts/alanadiniz.com/httpdocs/cron.php
```

**Alternatif — HTTP (URL getir):**

```
https://alanadiniz.com/cron.php?secret=CRON_ANAHTARINIZ
```

Cron anahtarını yönetim paneli > **Site Ayarları** sayfasında bulabilir ve
oradan yenileyebilirsiniz. Anahtar kurulumda otomatik olarak rastgele üretilir.

Önerilen çalışma sıklığı: **5 dakikada bir**.

---

## 8. Yönetici girişi

| | |
|---|---|
| Adres | `/admin-login.php` |
| Kullanıcı adı | `Admin` |
| İlk şifre | `Admin12345!` |

> **İlk girişten sonra şifreyi mutlaka değiştirin.** Sistem bunu zorunlu kılar ve
> varsayılan şifrenin yeniden kullanılmasına izin vermez.

Yönetici oturumu normal kullanıcı oturumundan tamamen ayrıdır. `$_SESSION['user_id']`
hiçbir koşulda yönetici yetkisi vermez; yönetim sayfaları yalnızca
`$_SESSION['admin_id']` ve `$_SESSION['admin_authenticated']` ile açılır.

Yönetim paneli bölümleri: Dashboard, Kullanıcılar, Dersler, Müfredat, Kelime Hazinesi,
Dilbilgisi, Skills, Alıştırmalar, Quizler, Telegram, Bildirimler, Kullanıcı Soruları,
Bağışlar, AI Ayarları, SMTP Ayarları, Site Ayarları, Sistem Durumu, Loglar,
Profil, Şifre Değiştir, Çıkış.

**Yönetici hiçbir kullanıcının şifresini göremez.** Şifreler yalnızca hash olarak saklanır.

---

## 9. AI desteği (isteğe bağlı)

**AlmancaPro, AI olmadan da tam çalışır.** AI kapalıyken "Öğretmene Sor" sayfası
dilbilgisi veritabanı, ders içerikleri ve bilgi bankasından anahtar kelime eşleşmesiyle
cevap üretir.

AI eklemek için yönetim paneli > **AI Ayarları**:

- API adresi (chat completions uyumlu, HTTPS)
- Model adı
- API anahtarı
- Sistem talimatı (Türkçe açıklama, Almanca örnek, artikel + çoğul, fiil üç hâli,
  hâl/edat ilişkisi, seviyeye uygunluk ve "kural uydurma" yasağı burada tanımlıdır)
- Maksimum token ve kullanıcı başına günlük soru limiti

Bütün AI çağrıları **sunucu tarafında cURL ile** yapılır.
**API anahtarı hiçbir koşulda tarayıcıya gönderilmez.**

---

## 10. Yedekleme

**Dosyalar:** Plesk > *Yedekleme Yöneticisi* veya `httpdocs` klasörünün kopyası.

**Veritabanı:** Plesk > *Veritabanları* > phpMyAdmin > *Dışa Aktar*, ya da:

```
mysqldump -u KULLANICI -p VERITABANI > yedek.sql
```

Geri yükleme:

```
mysql -u KULLANICI -p VERITABANI < yedek.sql
```

Yedeklenmesi gereken kritik tablolar: `users`, `user_skill_mastery`,
`user_vocabulary_mastery`, `user_lesson_progress`, `exercise_attempts`,
`telegram_connections`, `site_settings`.

---

## 11. Güvenlik

Uygulamada uygulanan önlemler:

- Bütün sorgular **PDO hazırlanmış ifadeler** ile (SQL injection koruması)
- Bütün çıktılar kaçırılır (XSS koruması), `Content-Security-Policy` başlığı gönderilir
- Durum değiştiren bütün istekler **CSRF** token'ı ile korunur
- Oturum sabitleme koruması ve girişte `session_regenerate_id(true)`
- Çerezler `HttpOnly`, `SameSite=Lax`, HTTPS'te `Secure`
- Şifreler `password_hash()` ile; **Argon2id** destekleniyorsa o, değilse `PASSWORD_DEFAULT`
- Giriş, kayıt, doğrulama, şifre sıfırlama ve yönetici girişinde **hız sınırı**
- Yönetici girişinde kaba kuvvet koruması ve geçici hesap kilidi
- Kullanıcı adı sızdırmayan genel hata mesajları
- Kaynak sahipliği doğrulaması (IDOR koruması)
- 6 haneli doğrulama kodu CSPRNG ile üretilir, **hash olarak** saklanır,
  10 dakika geçerlidir, tek kullanımlıktır ve deneme sayısı sınırlıdır
- Şifre sıfırlama token'ı güvenli rastgele, hash'lenmiş, süreli ve tek kullanımlıktır
- Üretimde hata ayrıntıları kullanıcıya gösterilmez
- Güvenlik başlıkları: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, HTTPS'te `Strict-Transport-Security`

**Asla loglanmayan değerler:** şifreler, doğrulama kodları, şifre sıfırlama token'ları,
veritabanı şifresi, SMTP şifresi, Telegram bot token'ı, AI API anahtarı, cron anahtarı.
Loglara yazılan metinler ayrıca otomatik olarak taranıp temizlenir.

Panelde gösterilen sırlar **maskelenir**; değiştirmek için mevcut değeri okumaya gerek yoktur.

### nginx kullanıyorsanız

`.htaccess` yalnızca Apache'de geçerlidir. Sadece nginx kullanan bir sunucuda uygulama
yine çalışır; aşağıdaki korumaları nginx yapılandırmasına ekleyin:

```nginx
location ~* ^/(config|config\.local|db|schema|seed|installer|functions|bootstrap|layout|auth|admin-auth|learning|planner|mailer|notify|telegram-api|ai|content-[a-z0-9-]+)\.php$ {
    deny all;
}
location ~* \.(sql|log|ini|conf|bak|old|orig|md|yml|sh)$ { deny all; }
location ~ /\.            { deny all; }
autoindex off;
error_page 404 /404.php;
```

---

## 12. Sorun giderme

### Önce buraya bakın: `500 Internal Server Error`

Sitede 500 hatası görüyorsanız **ilk yapılacak şey** tarayıcıdan şu adresi açmaktır:

```
https://alanadiniz.com/tani.php
```

Bu sayfa kasıtlı olarak eski PHP söz dizimiyle yazılmıştır; sunucudaki PHP sürümü
yetersiz olsa bile çalışır ve sorunu adı adına söyler: PHP sürümü, eksik eklentiler,
eksik dosyalar, `.htaccess` riskleri, zaman aşımı limitleri ve veritabanı bağlantısı.
**Sorun çözülünce `tani.php` dosyasını sunucudan silin.**

AlmancaPro artık hiçbir sayfada ham 500 göstermez: beklenmeyen bir hata olursa
ne olduğunu anlatan bir sayfa ve kısa bir **hata referansı** çıkar. Ayrıntı
(dosya, satır, yığın izi) yalnızca sunucudaki `storage/almancapro-log.php`
dosyasına yazılır ve `tani.php` sayfasında listelenir. Bu dosya web'den
okunamaz; ilk satırı `<?php exit;` olduğu için doğrudan açılsa bile boş döner.

Yönetici olarak giriş yaptıysanız hata ayrıntısı ekranda da gösterilir.

**Sık karşılaşılan durum — bazı sayfalar açılıyor, bazıları 500 veriyor:**
Bu neredeyse her zaman *yarım kalmış kurulum* demektir. Ana sayfa veritabanı
okumalarını yedekli yaptığı için açılır; `support.php`, `register.php` gibi
gerçekten tablo sorgulayan sayfalar hata verir. Çözüm: `/install.php` adresini
açıp kurulumu tamamlayın. `tani.php` eksik tabloları adıyla listeler ve
veritabanı kullanıcısının **tablo oluşturma yetkisi** olup olmadığını canlı test eder
(Plesk'te bu yetki eksikse kurulum sessizce yarım kalır).

`tani.php` de açılmıyorsa 500'ün nedeni Apache yapılandırmasıdır. Sırayla deneyin:

1. **PHP sürümü.** Plesk > *Websites & Domains* > alan adı > *PHP Settings* >
   **PHP 8.2 veya üzeri**, *Run PHP as*: **FPM application served by Apache**.
   En sık neden budur. (AlmancaPro'nun `index.php` dosyası bu durumu yakalayıp
   500 yerine açıklayıcı bir sayfa gösterir; yine de 500 görüyorsanız neden başkadır.)
2. **`.htaccess`.** Dosya yöneticisinde adını geçici olarak `.htaccess.bak` yapın ve
   siteyi yenileyin. Hata kayboluyorsa sunucunuz `AllowOverride` ile bazı satırları
   yasaklıyor demektir. Bu paketteki `.htaccess` riskli `Options` ve `php_flag`
   satırlarını zaten içermez; özelleştirdiyseniz onları geri alın.
3. **Dosya konumu.** `index.php` doğrudan `httpdocs` altında olmalıdır. ZIP ayıklanırken
   fazladan bir `AlmancaPro/` klasörü oluştuysa dosyaları bir üst dizine taşıyın.
4. **Dosya izinleri.** Klasörler `755`, dosyalar `644` olmalıdır.
5. **Sunucu hata günlüğü.** Plesk > *Logs* > `error_log` son satırları kesin nedeni verir.

### İlk açılış uzun sürüyor veya zaman aşımına uğruyor

İlk açılışta yaklaşık 10.000 satır yazılır (müfredat, kelimeler, alıştırmalar).
Paylaşımlı sunucuda varsayılan `max_execution_time` buna yetmeyebilir.

AlmancaPro bunu kendisi yönetir: süre limitini yükseltmeyi dener, yükseltemezse
kurulumu ana sayfada yapmak yerine `/install.php` adresine yönlendirir ve orada
zaman aşımı olmadan tamamlar. Yine de takılırsanız doğrudan `https://alanadiniz.com/install.php`
adresini açın.

### "Veritabanına bağlanılamadı"

**"Veritabanına bağlanılamadı"**
Plesk'te veritabanı kullanıcısının aktif olduğundan ve veritabanı adının
`lxsadauz_almanca` olduğundan emin olun. Bağlantı bilgileri güvenlik gereği
ekranda gösterilmez; ayrıntılı hata sunucu loguna yazılır.

**Sayfa boş geliyor**
PHP sürümünü kontrol edin (8.2+ olmalı). Plesk > *Loglar* > `error_log` dosyasına bakın.

**Türkçe/Almanca karakterler bozuk**
Veritabanının `utf8mb4` / `utf8mb4_unicode_ci` olduğunu doğrulayın.
Yönetim paneli > *Sistem Durumu* sayfası bunu kontrol eder.

**Doğrulama e-postası gelmiyor**
SMTP ayarlarını girin ve *Test E-postası Gönder* ile deneyin. Sonucu
yönetim paneli > *SMTP Ayarları* > "Son gönderimler" listesinde görebilirsiniz.
SMTP yoksa doğrulama kodunu yönetim panelinden kullanıcıyı elle onaylayarak da geçebilirsiniz.

**Telegram mesajları gelmiyor**
1. Site HTTPS mi? 2. Bot token doğru mu? 3. Webhook ayarlandı mı?
Yönetim paneli > *Telegram* > "Telegram'a bağlanıp doğrula" düğmesi bu üçünü de kontrol eder.

**Cron çalışmıyor**
Yönetim paneli > *Site Ayarları* > "Son çalışma" satırına bakın.
Boşsa Plesk zamanlanmış görevini ekleyin. HTTP tetikleyici kullanıyorsanız
adresteki `secret` değerinin güncel olduğundan emin olun.

**Ders kilitli görünüyor ama açılmalı**
Kilit her zaman sunucuda hesaplanır. `/course.php?locked=DERS_ID#lock` sayfası hangi
skill'in kaç puanda olduğunu ve eşiğin ne olduğunu gösterir.
Eşikleri yönetim paneli > *Müfredat* sayfasından değiştirebilirsiniz.

**404 sayfası çalışmıyor**
`.htaccess` içindeki `ErrorDocument 404 /404.php` satırı Apache gerektirir.
nginx'te `error_page 404 /404.php;` tanımlayın.

---

## 13. Dizin yapısı

Bütün çalıştırılabilir PHP dosyaları **kök dizindedir**. `/src/`, `/app/`,
`/controllers/`, `/includes/` gibi bir alt klasör mimarisi yoktur.

```
httpdocs/
├── index.php               Giriş noktası (PHP sürümünü kontrol eder)
├── home.php                Açılış sayfası gövdesi
├── tani.php                Kurulum teşhis aracı (sorun çözülünce silin)
├── storage/                Çalışma anında oluşur; hata günlüğü (web'e kapalı)
├── register.php            Kayıt
├── verify.php              E-posta doğrulama
├── login.php  logout.php   Giriş / çıkış
├── forgot-password.php     Şifremi unuttum
├── reset-password.php      Şifre sıfırlama
├── onboarding.php          Tanışma akışı
├── placement-test.php      Seviye testi
├── dashboard.php           "Bugün ne yapmalıyım?"
├── course.php  path.php    Öğrenme yolu (A0 → B1)
├── lesson.php              Ders
├── quiz.php                Quiz / tekrar / telafi / checkpoint motoru
├── exercise.php            Tekil alıştırma ve telafi oturumu
├── review.php              Aralıklı tekrar
├── checkpoint.php          Modül ve seviye kontrol noktaları
├── vocabulary.php          Kelime hazinem
├── grammar.php             Dilbilgisi kütüphanesi
├── progress.php            İlerleme
├── weak-areas.php          Zayıf alanlar
├── intensive.php program.php   30 günlük yoğun program
├── work-german.php workplace.php   İş Almancası
├── scenario.php            Rol yapma senaryoları
├── ask.php                 Öğretmene Sor
├── profile.php settings.php    Profil ve ayarlar
├── telegram.php            Telegram bağlantısı
├── delete-account.php      Hesap silme
├── privacy.php terms.php   Gizlilik ve kullanım koşulları
├── support.php  donation-notify.php   Gönüllü destek
├── telegram-webhook.php    Telegram webhook
├── cron.php                Zamanlanmış görevler
├── install.php             Kurulum sihirbazı
├── 404.php                 Hata sayfası
├── admin-*.php             Yönetim paneli (24 sayfa)
├── config.php db.php schema.php seed.php installer.php   Altyapı
├── functions.php bootstrap.php layout.php                Ortak yardımcılar
├── auth.php admin-auth.php mailer.php                    Kimlik ve e-posta
├── learning.php planner.php notify.php ai.php telegram-api.php   Motorlar
├── content-*.php           Gerçek müfredat ve kelime içeriği
├── database.sql            Şema + içerik yedeği
├── .htaccess robots.txt README.md
└── assets/
    ├── css/  main.css  learning.css  auth.css  admin.css
    ├── js/   app.js  quiz.js  review.js
    └── img/  favicon.svg
```

---

## 14. Önemli notlar

- **B1 garantisi yoktur.** 30 günlük yoğun program hızlandırır, ancak hiçbir süre için
  seviye garantisi verilmez.
- **Uygulama içi seviye değerlendirmesi resmî sertifika değildir.** B1 kontrol noktası
  sonucu, Goethe, telc veya ÖSD tarafından verilen resmî bir B1 sertifikası yerine geçmez.
- **Hukuki tavsiye verilmez.** İş Almancası ve resmî işlem modülleri dil öğretir;
  iş hukuku, oturum veya sağlık sigortası konularında hukuki danışmanlık yerine geçmez.
- **Zamana bağlı bilgiler değişebilir.** Kurum adları, süreçler ve ücretler zamanla
  değişir; içerik dil öğretimi amaçlıdır.
- **Uygulama tamamen ücretsizdir.** Destek sayfasındaki bağış tamamen gönüllüdür ve
  hiçbir dersi, özelliği veya içeriği açmaz.
