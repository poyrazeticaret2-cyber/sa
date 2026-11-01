"""Komut satırı: python -m urun_bot [seçenekler]"""
from __future__ import annotations

import argparse
from datetime import datetime
from pathlib import Path

from . import config
from .report import csv_yaz, dosya_adi, html_yaz, konsol
from .customs import ticari_maliyet
from .scoring import basabas_fiyat, satis_basina_kar, score_category, sirala
from .sources import aliexpress, csv_veri, tcmb, trendyol, trends

TUM_KAYNAKLAR = ("aliexpress", "trendyol", "trends")
VARSAYILAN_KUR = 42.0


def arguman_ayristir(argv=None) -> argparse.Namespace:
    p = argparse.ArgumentParser(
        prog="urun_bot",
        description="Dünyada satan, Türkiye'de az bulunan ve düşük vergili ürün gruplarını bulur.")
    p.add_argument("--demo", action="store_true", help="Ağa çıkmadan örnek veriyle çalış")
    p.add_argument("--kaynak", default=",".join(TUM_KAYNAKLAR),
                   help="Virgülle: aliexpress,trendyol,trends (varsayılan: hepsi)")
    p.add_argument("--csv", action="append", default=[], metavar="DOSYA",
                   help="Ek veri CSV'si (Alibaba fiyatları vb.); birden fazla verilebilir")
    p.add_argument("--kategori", help="Yalnız bu kategori id'leri (virgülle)")
    p.add_argument("--segment", choices=["tuketici", "hobi", "b2b"],
                   help="Yalnız bu segment: tuketici (son kullanıcı), hobi, b2b")
    p.add_argument("--hesap", nargs=2, type=float, metavar=("ALIS_USD", "SATIS_TL"),
                   help="Tek ürün kâr hesabı: Alibaba birim fiyatı ($) ve Trendyol KDV dahil fiyatı (TL). "
                        "--kategori ile tek kategori verin")
    p.add_argument("--adet", type=int, help="Hesapta parti adedi (varsayılan kural dosyasından)")
    p.add_argument("--cok-satanlar", action="store_true",
                   help="AliExpress'te çok satan ve çok beğenilen ürün listesi (Alibaba/Trendyol linkleriyle)")
    p.add_argument("--kelime", action="append", default=[], metavar="EN_KELIME",
                   help="--cok-satanlar için ek İngilizce arama kelimesi (birden fazla verilebilir)")
    p.add_argument("--min-olumlu", type=float, default=95, help="En düşük olumlu değerlendirme %% (varsayılan 95)")
    p.add_argument("--min-satis", type=int, default=500, help="En düşük son dönem satış (varsayılan 500)")
    p.add_argument("--riskli-dahil", action="store_true", help="Yüksek riskli kategorileri de listele")
    p.add_argument("--min-puan", type=float, default=0)
    p.add_argument("--ilk", type=int, default=15, help="Konsolda gösterilecek satır")
    p.add_argument("--cikti", default="raporlar", help="Rapor klasörü")
    p.add_argument("--usd-try", type=float, help="Kur (verilmezse TCMB'den alınır)")
    p.add_argument("--navlun", type=float, help="Navlun USD/kg (varsayılan kural dosyasından)")
    p.add_argument("--telegram", action="store_true", help="Özeti Telegram'a gönder")
    p.add_argument("--listele", action="store_true", help="Kategori id'lerini yazdır ve çık")
    return p.parse_args(argv)


def main(argv=None) -> int:
    a = arguman_ayristir(argv)
    config.env_yukle()
    kurallar = config.gumruk_kurallari()
    kategoriler = config.kategoriler()

    if a.listele:
        for k in kategoriler:
            print(f"{k['id']:<22} {k['risk']:<7} {k['ad']}")
        return 0

    if a.hesap:
        return tek_urun_hesabi(a, kurallar, kategoriler)

    if a.cok_satanlar:
        return cok_satanlar_listesi(a, kategoriler)

    if a.kategori:
        secim = {x.strip() for x in a.kategori.split(",")}
        kategoriler = [k for k in kategoriler if k["id"] in secim]
        if not kategoriler:
            print("Eşleşen kategori yok. --listele ile id'lere bakın.")
            return 2

    # Riskli kategoriler istenmiyorsa ağ isteği harcamayalım
    taranacak = [k for k in kategoriler if k["risk"] != "yasak" and (a.riskli_dahil or k["risk"] != "yuksek")]

    sinyaller: dict[str, dict] = {k["id"]: {} for k in kategoriler}
    kullanilan: list[str] = []

    if a.demo:
        demo = config.json_oku("demo_signals.json")["sinyaller"]
        for kid, s in demo.items():
            if kid in sinyaller:
                sinyaller[kid].update(s)
        kullanilan.append("demo")
        usd_try = a.usd_try or VARSAYILAN_KUR
    else:
        istenen = {x.strip() for x in a.kaynak.split(",") if x.strip()}
        if "aliexpress" in istenen and not aliexpress.yapilandirildi():
            print("! AliExpress anahtarları yok (ALIEXPRESS_APP_KEY / ALIEXPRESS_APP_SECRET) – atlanıyor.")
            istenen.discard("aliexpress")
        if "trends" in istenen and not trends.kullanilabilir():
            print("! pytrends kurulu değil (pip install pytrends) – Google Trends atlanıyor.")
            istenen.discard("trends")
        kaynaklar = {"aliexpress": aliexpress.topla, "trendyol": trendyol.topla, "trends": trends.topla}
        for k in taranacak:
            print(f"- {k['ad'][:70]}")
            for ad in TUM_KAYNAKLAR:
                if ad in istenen:
                    sinyaller[k["id"]].update(kaynaklar[ad](k))
        kullanilan.extend(x for x in TUM_KAYNAKLAR if x in istenen)
        usd_try = a.usd_try or tcmb.usd_try()
        if usd_try is None:
            usd_try = config.env_float("USD_TRY", VARSAYILAN_KUR)
            print(f"! Kur alınamadı, {usd_try} kullanılıyor (--usd-try ile verin).")

    for yol in a.csv:
        for kid, s in csv_veri.oku(yol).items():
            if kid in sinyaller:
                sinyaller[kid].update(s)
            else:
                print(f"! CSV'de bilinmeyen kategori_id: {kid}")
        kullanilan.append(f"csv:{Path(yol).name}")

    sonuclar = [score_category(k, sinyaller[k["id"]], kurallar, usd_try, a.navlun) for k in kategoriler]
    sirali = sirala(sonuclar, a.riskli_dahil, a.min_puan, a.segment)

    print()
    print(konsol(sirali, a.ilk))
    disarida = [r for r in sonuclar if r not in sirali]
    if disarida:
        print(f"\nElenen {len(disarida)} kategori (segment dışı / yüksek risk / yasak / düşük puan): "
              + ", ".join(r.kategori["id"] for r in disarida))

    dizin = Path(a.cikti)
    csv_yolu, html_yolu = dosya_adi(dizin, "csv"), dosya_adi(dizin, "html")
    csv_yaz(sirali, csv_yolu)
    html_yaz(sirali, html_yolu, kurallar, {
        "tarih": f"{datetime.now():%d.%m.%Y %H:%M}", "kaynaklar": ", ".join(kullanilan) or "yok",
        "usd_try": usd_try, "demo": a.demo, "segment": a.segment})
    print(f"\nRapor: {html_yolu}\nCSV:   {csv_yolu}")

    if a.telegram:
        from .notify import telegram_gonder
        telegram_gonder(sirali, demo=a.demo)
    return 0


def tek_urun_hesabi(a: argparse.Namespace, kurallar: dict, kategoriler: list[dict]) -> int:
    """Bir ürün için satış başına kâr dökümü (konsola)."""
    secili = [k for k in kategoriler if a.kategori and k["id"] == a.kategori.strip()]
    if not secili:
        print("--hesap için --kategori <id> verin (vergi oranları oradan alınır). --listele ile bakın.")
        return 2
    kat = secili[0]
    alis, satis = a.hesap
    if a.adet:
        kurallar["ticari_ithalat"]["parti_adet"] = a.adet
    kur = a.usd_try or tcmb.usd_try() or config.env_float("USD_TRY", VARSAYILAN_KUR)
    t, p = kurallar["ticari_ithalat"], kurallar["pazaryeri"]
    sabit_usd = t["parti_sabit_masraf_try"] / t["parti_adet"] / kur
    m = ticari_maliyet(alis, kat, kurallar, a.navlun, sabit_usd)
    kdv = float(kat.get("kdv", 20))
    maliyet_try = m.varis_maliyeti_usd * kur
    kar = satis_basina_kar(satis, maliyet_try, kurallar, kdv)
    tl = lambda usd: f"{usd * kur:>9.2f} TL"
    print(f"{kat['ad']}\nKur: {kur:.2f} TL/$ · parti: {t['parti_adet']} adet · GTİP {kat['gtip']}\n")
    print(f"  Alış (FOB)                 {tl(m.fob_usd)}")
    print(f"  Navlun + sigorta           {tl(m.navlun_usd + m.sigorta_usd)}")
    print(f"  GV %{kat['gv']} + İGV %{kat['igv']} + ÖTV     {tl(m.gv_usd + m.igv_usd + m.otv_usd)}")
    print(f"  Gümrük/yerel masraf payı   {tl(m.yerel_masraf_usd)}")
    print(f"  = Türkiye'ye geliş maliyeti {maliyet_try:>8.2f} TL   (ithalat KDV {m.kdv_usd * kur:.2f} TL ayrıca, indirilir)\n")
    kdv_haric = satis / (1 + kdv / 100)
    kom = satis * p["komisyon_pct"] / 100 / (1 + kdv / 100)
    sabit = p["kargo_try"] + p["platform_bedeli_try"] + p["ambalaj_try"]
    print(f"  Satış (KDV dahil)          {satis:>9.2f} TL")
    print(f"  − KDV %{kdv:.0f}                  {satis - kdv_haric:>9.2f} TL")
    print(f"  − Komisyon %{p['komisyon_pct']} (KDV hariç)  {kom:>9.2f} TL")
    print(f"  − Kargo+platform+ambalaj   {sabit:>9.2f} TL")
    print(f"  − Ürün maliyeti            {maliyet_try:>9.2f} TL")
    oncesi = kdv_haric - kom - sabit - maliyet_try
    print(f"  = Vergi öncesi kâr         {oncesi:>9.2f} TL")
    if oncesi > 0:
        print(f"  − Gelir/kurumlar vergisi %{p['gelir_vergisi_pct']} {oncesi - kar:>7.2f} TL")
    print(f"  = SATIŞ BAŞINA NET KÂR     {kar:>9.2f} TL  (satış fiyatının %{kar / satis * 100:.0f}'i)")
    print(f"\n  Başabaş satış fiyatı: {basabas_fiyat(maliyet_try, kurallar, kdv):.0f} TL")
    print(f"  {t['parti_adet']} adetin tamamı satılırsa: yatırım ≈ {(maliyet_try + m.kdv_usd * kur) * t['parti_adet']:,.0f} TL "
          f"(KDV dahil), net kâr ≈ {kar * t['parti_adet']:,.0f} TL")
    print("\n  Vergi oranları ve pazaryeri kesintileri tahminidir; customs_rules.json'dan güncelleyin.")
    return 0


def cok_satanlar_listesi(a: argparse.Namespace, kategoriler: list[dict]) -> int:
    from . import cok_satanlar
    from .report import dosya_adi

    if not aliexpress.yapilandirildi():
        print("Bu liste AliExpress API anahtarı ister: .env içine ALIEXPRESS_APP_KEY ve "
              "ALIEXPRESS_APP_SECRET yazın (README > AliExpress anahtarı).")
        return 2
    secili = [k for k in kategoriler if k["risk"] != "yasak"
              and (a.riskli_dahil or k["risk"] != "yuksek")
              and (not a.segment or a.segment in k.get("segment", []))
              and (not a.kategori or k["id"] in {x.strip() for x in a.kategori.split(",")})]
    if a.kelime and not a.kategori and not a.segment:
        secili = []  # yalnız verilen kelimelerle ara
    urunler = cok_satanlar.filtrele(cok_satanlar.topla(secili, a.kelime), a.min_olumlu, a.min_satis)
    print(f"\n{len(urunler)} ürün eşiği geçti (olumlu ≥ %{a.min_olumlu}, satış ≥ {a.min_satis}).\n")
    for i, u in enumerate(urunler[:a.ilk], 1):
        oran = "" if u.olumlu_pct is None else f"%{u.olumlu_pct:.1f}"
        print(f"{i:>3}. {u.satis:>7}  {oran:>6}  {u.baslik[:70]}")
    dizin = Path(a.cikti)
    csv_yolu = dosya_adi(dizin, "csv").with_name(dosya_adi(dizin, "csv").name.replace("firsatlar", "cok-satanlar"))
    html_yolu = csv_yolu.with_suffix(".html")
    cok_satanlar.csv_yaz(urunler, csv_yolu)
    cok_satanlar.html_yaz(urunler, html_yolu, {"tarih": f"{datetime.now():%d.%m.%Y %H:%M}",
                                               "min_olumlu": a.min_olumlu, "min_satis": a.min_satis})
    print(f"\nListe: {html_yolu}\nCSV:   {csv_yolu}")
    return 0
