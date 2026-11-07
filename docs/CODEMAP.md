# CODEMAP
_Son güncelleme: 2026-11-07_

## Teknoloji
- Ana proje: AlmancaPro — düz PHP + MySQL (Türkçe Almanca eğitim sitesi), framework yok, `.htaccess` ile Apache.
- `landing/`: bağımsız statik HTML/CSS/JS ürün satış sayfası (Chelsea bot). PHP uygulamasından bağımsız.

## Klasör yapısı
| Klasör | Ne içerir |
|---|---|
| `/` (kök) | PHP sayfaları (`admin-*.php`, `lesson.php`, `quiz.php`…), `content-*.php` ders içerikleri, `database.sql` |
| `assets/` | AlmancaPro css/js/img |
| `landing/` | Chelsea bot satış sayfası (`index.html`) |
| `landing/assets/` | `bot-*.jpg` orijinal fotoğraflar, `cut-*.webp` arka plansız kesimler |
| `docs/` | Bu harita |

## Neyin ayarı nerede
| Konu | Dosya | Anahtar / satır | Not |
|---|---|---|---|
| Landing renk/font | `landing/index.html` | `:root` bloğu (`--dark`, `--accent`, `--paper`) | Marka renkleri gelince burası |
| Landing logo | `landing/index.html` | `.logo` ("MARKA") — header + footer | `<img class="logo-img">` ile değiştir |
| Landing metinler/CTA | `landing/index.html` | `<section>` blokları; CTA'lar `#satin-al` | Satın alma linki `#buy` / `.btn` href |
| Landing ürün görselleri | `landing/assets/cut-*.webp` | — | Orijinal: `bot-*.jpg` |
| AlmancaPro config | `config.php`, `bootstrap.php`, `db.php` | — | Landing ile ilgisiz |
| Yardımcı fonksiyonlar | `functions.php` | `e()`, `setting()`, `csrf_*` | PHP uygulaması |

## Ana akışlar
### Landing sayfası
1. `landing/index.html` → hero, en iyi çift (görünüm seçici JS), konfor, tek çift her plan, kampanya, footer.

## Dokunma
`database.sql`, `AlmancaPro.zip`, `content-*.php` (büyük içerik dosyaları).
