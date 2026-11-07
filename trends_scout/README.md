# Trends Scout

Google Trends (Türkiye, Alışveriş kategorisi) ile ürün trendi keşfi:
en çok aranan ürünler, yeni yükselenler, 5 yılda düşüşe geçenler.

## Kurulum ve çalıştırma
    pip install -r requirements.txt
    python scout.py --demo      # sentetik veriyle deneme (internet gerekmez)
    python scout.py             # gerçek veri; ~40 ürün için ~10-15 dk sürer
Çıktı: `out/trends.csv`, `out/report.html`

## Nasıl çalışır
1. `seeds.txt` tohum ürünlerinden Google'ın "ilgili / yükselen sorgularıyla" aday listesi genişler.
2. Adaylar 4'lü gruplar halinde, her grupta ortak bir referans kelimeyle (`--anchor`, varsayılan "kargo") 5 yıllık haftalık seri olarak çekilir; referans ortalaması = 100 olacak şekilde ölçeklenir, böylece gruplar birbiriyle kıyaslanır.
3. Etiketler: **Yükselen** (son 52 hf ≥ önceki yıl +%50, son 12 hf ivmeli), **Düşen** (52 hf ortalaması zirvenin ≤ yarısı, zirve ≥1 yıl önce, eğim negatif), aksi **Kararlı**. 52 haftalık ortalama kullanıldığı için mevsimsel ürünler yanlış düşüş sayılmaz.

## Sınırlar
- Resmi Google Trends API'si yok; `pytrends` resmi olmayan ve bakımı durmuş bir kütüphane. 429 / engel alırsan `--sleep` değerini artır, sonuçlar `.cache/` içine kaydedilir, tekrar çalıştırınca kaldığı yerden devam eder.
- Değerler göreli ilgidir, gerçek arama sayısı değildir. Referans kelimeden çok daha az aranan ürünlerde değerler 0'a yuvarlanabilir; `--anchor` değerini daha düşük hacimli bir kelimeyle değiştirmeyi dene.
- Google "en çok aranan ürün listesi" vermez; liste tohumlara ve ilgili sorgulara bağlıdır. Daha iyi kapsam için `seeds.txt`'yi genişlet.
