"""Google Trends (pytrends, opsiyonel): dünya ilgisi ve Türkiye'nin göreli ilgisi.

Kurulum: pip install pytrends  — kurulu değilse bu kaynak sessizce atlanır.
Google sık istekte 429 döndürebilir; anahtar kelime sayısını düşük tutun.
"""
from __future__ import annotations

import time


def kullanilabilir() -> bool:
    try:
        import pytrends  # noqa: F401
        return True
    except ImportError:
        return False


def topla(kategori: dict) -> dict:
    if not kullanilabilir():
        return {}
    from pytrends.request import TrendReq

    kelime = (kategori.get("anahtar_en") or [None])[0]
    if not kelime:
        return {}
    try:
        tr = TrendReq(hl="en-US", tz=180, timeout=(10, 25))
        tr.build_payload([kelime], timeframe="today 12-m")
        zaman = tr.interest_over_time()
        dunya = float(zaman[kelime].mean()) if not zaman.empty else None
        bolge = tr.interest_by_region(resolution="COUNTRY", inc_low_vol=True)
        tr_deger = None
        for ad in ("Turkey", "Türkiye", "Turkiye"):
            if ad in bolge.index:
                tr_deger = float(bolge.loc[ad, kelime])
                break
        time.sleep(2)
        return {k: v for k, v in {"trend_dunya": dunya, "trend_tr": tr_deger}.items() if v is not None}
    except Exception as hata:
        print(f"  [trends] {kelime!r}: {hata}")
        return {}
