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
| `landing/` | Chelsea bot satış sayfası: `index.html` (DERLENMİŞ çıktı), `build.py`, `base.css`, `old-index.html` (önceki tek dosya sürüm, yedek) |
| `landing/parts/` | Bölüm parçaları: `hero`, `pair`, `comfort`, `plan`, `final` (.html; her biri `<style>` + `<section data-sec>`) |
| `landing/assets/` | `bot-*.jpg` orijinal fotoğraflar, `cut-*.webp` arka plansız kesimler, `model.jpg` yaşam tarzı fotoğrafı |
| `docs/` | Bu harita |

## Neyin ayarı nerede
| Konu | Dosya | Anahtar / satır | Not |
|---|---|---|---|
| Landing renk/font | `landing/base.css` | `:root` (`--dark`, `--yellow`, `--gold`, `--paper`, `--serif`, `--sans`) | Marka renkleri gelince burası |
| Landing logo | `landing/base.css` + `parts/hero.html`, `parts/final.html` | `.logo` ("MARKA"), `.logo-img` | `<img class="logo-img">` ile değiştir |
| Landing tipografi/buton | `landing/base.css` | `.h-xl/.h-lg/.h-md/.h-sm`, `.btn`, `.ghost` | Ortak sınıflar |
| Landing metinler/CTA | `landing/parts/*.html` | Bölüm başına; CTA `.btn` href | index.html'i elle düzenleme, parçayı düzenle |
| Landing derleme | `landing/build.py` | `ORDER` listesi, `<head>` (font linki, title/description) | `python3 landing/build.py` → `index.html` |
| Landing ürün görselleri | `landing/assets/cut-*.webp`, `model.jpg` | — | Orijinal: `bot-*.jpg` |
| AlmancaPro config | `config.php`, `bootstrap.php`, `db.php` | — | Landing ile ilgisiz |
| Yardımcı fonksiyonlar | `functions.php` | `e()`, `setting()`, `csrf_*` | PHP uygulaması |

## Ana akışlar
### Landing sayfası
1. `landing/base.css` + `landing/parts/{hero,pair,comfort,plan,final}.html` → `landing/build.py` ile birleştirilir.
2. `landing/index.html` → derlenmiş tek dosya (hero, en iyi çift, konfor, tek çift her plan, kampanya/footer).

## Dokunma
`database.sql`, `AlmancaPro.zip`, `content-*.php` (büyük içerik dosyaları), `landing/index.html` (build.py üretir).
