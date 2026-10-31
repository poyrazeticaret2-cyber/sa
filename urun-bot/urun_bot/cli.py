"""Komut satırı: python -m urun_bot [seçenekler]"""
from __future__ import annotations

import argparse
from datetime import datetime
from pathlib import Path

from . import config
from .report import csv_yaz, dosya_adi, html_yaz, konsol
from .scoring import score_category, sirala
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
    sirali = sirala(sonuclar, a.riskli_dahil, a.min_puan)

    print()
    print(konsol(sirali, a.ilk))
    disarida = [r for r in sonuclar if r not in sirali]
    if disarida:
        print(f"\nElenen {len(disarida)} kategori (yüksek risk / yasak / düşük puan): "
              + ", ".join(r.kategori["id"] for r in disarida))

    dizin = Path(a.cikti)
    csv_yolu, html_yolu = dosya_adi(dizin, "csv"), dosya_adi(dizin, "html")
    csv_yaz(sirali, csv_yolu)
    html_yaz(sirali, html_yolu, kurallar, {
        "tarih": f"{datetime.now():%d.%m.%Y %H:%M}", "kaynaklar": ", ".join(kullanilan) or "yok",
        "usd_try": usd_try, "demo": a.demo})
    print(f"\nRapor: {html_yolu}\nCSV:   {csv_yolu}")

    if a.telegram:
        from .notify import telegram_gonder
        telegram_gonder(sirali, demo=a.demo)
    return 0
