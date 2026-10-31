"""Trendyol arama sonucu – Türkiye'deki ilan sayısı ve medyan fiyat (doygunluk sinyali).

Resmî bir API değildir; site yapısı değişirse None döner ve bot diğer sinyallerle devam eder.
İstekler arasında bekleme yapılır ve sonuçlar 24 saat önbellekte tutulur.
"""
from __future__ import annotations

import statistics

from ..http import getir

UC_NOKTA = "https://public.trendyol.com/discovery-web-searchgw-service/v2/api/infinite-scroll/sr"


def coz(yanit: dict) -> tuple[int | None, list[float]]:
    sonuc = yanit.get("result") or {}
    toplam = sonuc.get("totalCount")
    fiyatlar = []
    for u in sonuc.get("products") or []:
        fiyat = (u.get("price") or {}).get("sellingPrice")
        if isinstance(fiyat, dict):
            fiyat = fiyat.get("value")
        try:
            fiyatlar.append(float(fiyat))
        except (TypeError, ValueError):
            pass
    return (int(toplam) if toplam is not None else None), fiyatlar


def topla(kategori: dict) -> dict:
    sayilar, fiyatlar = [], []
    for kelime in kategori.get("anahtar_tr", [])[:3]:
        try:
            toplam, f = coz(getir(UC_NOKTA, {"q": kelime, "pi": 1}, aralik=3.0,
                                  basliklar={"Accept": "application/json"}))
        except Exception as hata:
            print(f"  [trendyol] {kelime!r}: {hata}")
            continue
        if toplam is not None:
            sayilar.append(toplam)
        fiyatlar.extend(f)
    if not sayilar:
        return {}
    return {
        "tr_ilan_sayisi": round(statistics.mean(sayilar)),
        "tr_fiyat_try": round(statistics.median(fiyatlar), 2) if fiyatlar else None,
    }
