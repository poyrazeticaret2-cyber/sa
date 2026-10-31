# Ürün Fırsat Botu

AliExpress ve Alibaba'da **dünya genelinde çok satan**, **Türkiye'de az bulunan** ve
**Türkiye'ye düşük vergiyle / sorunsuz girebilecek** ürün gruplarını bulup puanlar.

> ⚠️ Vergi oranları ve mevzuat bilgileri **tahminidir**. Sipariş vermeden önce GTİP'i
> gümrük müşavirinizle ve Ticaret Bakanlığı **TARA** sorgusundan doğrulayın.

## Nasıl çalışır?

Her kategori için 5 puan (0–100) hesaplanır ve ağırlıklı toplanır:

| Puan | Ağırlık | Kaynak |
|---|---|---|
| **Dünya talebi** | %25 | AliExpress Affiliate API (son satış adedi), Google Trends |
| **Türkiye boşluğu** | %30 | Trendyol ilan sayısı (az ilan = boşluk), Google Trends'te Türkiye'nin payı |
| **Vergi uygunluğu** | %20 | GV + İGV + ÖTV yükü (KDV indirilebilir, dahil edilmez) |
| **Mevzuat kolaylığı** | %15 | Gereken belgeler: TAREKS, BTK, TSE, TİTCK, damping… |
| **Marj** | %10 | Satış başına net kâr: Trendyol fiyatı − KDV − komisyon − kargo/platform/ambalaj − geliş maliyeti − gelir vergisi |

**Ucuz ürün tuzağı:** Trendyol'da her siparişte komisyona ek ~75 TL sabit masraf (kargo, platform, ambalaj) var.
Bu yüzden 250 TL'nin altındaki ürünlerde kâr kalmaz; set/paket satışıyla sepet tutarını büyütün.

Veri gelmeyen puanlar hesaba katılmaz; rapordaki **güven** yüzdesi bunu gösterir.
Yasak (e-sigara, cep telefonu) ve yüksek riskli (ayakkabı, giyim, oyuncak, kozmetik,
dampingli ürünler…) kategoriler varsayılan olarak elenir.

### 2026 için önemli not: AliExpress paketleri artık pahalı
10813 sayılı Karar ile posta/hızlı kargodaki **30 € muafiyeti kaldırıldı** (Şubat 2026).
AB dışından gelen ≤1500 € gönderilere **%60 maktu vergi** uygulanıyor. Bu yüzden bot
maliyeti **ticari ithalat** (şirket adına beyanname, hava/deniz kargo) üzerinden hesaplar:

```
CIF = toptan fiyat + navlun (kg × $/kg) + sigorta
Vergi = CIF × (GV% + İGV%)  [+ ÖTV]       → "vergi yükü"
Varış maliyeti = CIF + vergiler + yerel masraf (%3)
KDV ayrıca gösterilir (şirket için indirilebilir)
```

## Kurulum

Python 3.10+ yeterli; ek paket gerekmez.

```bash
cd urun-bot
cp .env.example .env          # anahtarları doldurun
pip install pytrends           # opsiyonel: Google Trends sinyali
```

### AliExpress anahtarı
1. https://openservice.aliexpress.com adresinde geliştirici hesabı açın.
2. **Affiliate** tipinde uygulama oluşturun → `App Key` ve `App Secret` alın.
3. `.env` içine `ALIEXPRESS_APP_KEY` ve `ALIEXPRESS_APP_SECRET` yazın.

### Alibaba verisi
Alibaba.com'un herkese açık ürün arama API'si yok. Alibaba'da baktığınız toptan
fiyatları bir CSV'ye yazıp bota verin (örnek: `ornek_alibaba.csv`):

```
kategori_id;alibaba_fiyat_usd;alibaba_moq;alibaba_tedarikci
olcum-test;41;10;320
```

Aynı CSV ile elle topladığınız Hepsiburada/N11 ilan sayılarını (`tr_ilan_sayisi`) veya
fiyatları (`tr_fiyat_try`) da ekleyebilirsiniz.

## Kullanım

```bash
python3 -m urun_bot --demo                         # ağa çıkmadan örnek veriyle dene
python3 -m urun_bot                                # tüm kaynaklarla gerçek tarama
python3 -m urun_bot --csv alibaba.csv              # Alibaba fiyatlarıyla marj hesabı
python3 -m urun_bot --kategori olcum-test,cnc-uc   # sadece seçilen kategoriler
python3 -m urun_bot --segment tuketici             # sadece son kullanıcı ürünleri (hobi, b2b da var)
python3 -m urun_bot --riskli-dahil                 # yüksek riskli grupları da göster
python3 -m urun_bot --listele                      # kategori id'leri
python3 -m urun_bot --telegram                     # özeti Telegram'a gönder
```

### Tek ürün kâr hesabı
Alibaba birim fiyatı ($) ve Trendyol KDV dahil satış fiyatı (TL) ile satış başına net kârı hesaplar:

```bash
python3 -m urun_bot --hesap 1.24 1200 --kategori cnc-uc --adet 300
```

Döküm: alış → navlun → gümrük vergileri → gümrük masrafı payı → geliş maliyeti; satıştan KDV,
komisyon, kargo/platform/ambalaj ve gelir vergisi düşülür; başabaş fiyatı ve parti toplamı gösterilir.

Çıktı: `raporlar/firsatlar-YYYYMMDD-HHMM.html` (açılır kartlı rapor) ve `.csv` (Excel'de açılır).

### Haftalık otomatik çalıştırma (cron)
```
0 7 * * 1  cd /yol/urun-bot && /usr/bin/python3 -m urun_bot --telegram >> bot.log 2>&1
```

## Kategori ekleme / vergi güncelleme

- `urun_bot/data/categories.json` → kategori, anahtar kelimeler (EN: AliExpress/Trends, TR: Trendyol),
  GTİP, `gv`, `igv`, `otv`, `kdv`, `izinler`, `risk` (`dusuk`/`orta`/`yuksek`/`yasak`), `birim_kg`.
- `urun_bot/data/customs_rules.json` → navlun $/kg, parti sabit gümrük masrafı, posta maktu oranları, izin cezaları
  ve `pazaryeri` bölümü (komisyon %, sipariş başına kargo/platform/ambalaj TL, gelir vergisi %).
- Her kategoride `segment`: `tuketici` (son kullanıcı), `hobi`, `b2b`.

İGV oranları sık değişir (ör. 10791 ve 11508 sayılı Kararlar). Değişiklikte bu iki dosyayı güncelleyin.

## Sınırlar

- Trendyol sonuçları resmî API değildir; yapı değişirse o sinyal boş gelir, bot diğerleriyle devam eder.
  İstekler yavaşlatılır ve 24 saat önbellekte tutulur.
- Google Trends sık istekte geçici olarak engelleyebilir.
- Marka/logo taşıyan ürünler (taklit) gümrükte el konulur; yalnız markasız/kendi markanızı düşünün.

## Test

```bash
python3 -m unittest discover -s tests -t .
```
