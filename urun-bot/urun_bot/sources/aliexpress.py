"""AliExpress Affiliate (Open Platform) API – dünya geneli satış hacmi ve fiyat.

Anahtar almak için: https://openservice.aliexpress.com  → Affiliate uygulaması oluşturun.
Ortam değişkenleri: ALIEXPRESS_APP_KEY, ALIEXPRESS_APP_SECRET, (ops.) ALIEXPRESS_TRACKING_ID
"""
from __future__ import annotations

import hashlib
import hmac
import os
import statistics
import time

from ..http import getir, gizle

UC_NOKTA = "https://api-sg.aliexpress.com/sync"
METOD = "aliexpress.affiliate.product.query"


def yapilandirildi() -> bool:
    return bool(os.environ.get("ALIEXPRESS_APP_KEY") and os.environ.get("ALIEXPRESS_APP_SECRET"))


def imzala(params: dict, secret: str) -> str:
    """IOP 'sync' imzası: anahtara göre sıralı key+value birleşimi, HMAC-SHA256, büyük harf hex."""
    metin = "".join(f"{k}{params[k]}" for k in sorted(params) if k != "sign")
    return hmac.new(secret.encode(), metin.encode(), hashlib.sha256).hexdigest().upper()


def _istek(is_parametreleri: dict) -> dict:
    params = {
        "app_key": os.environ["ALIEXPRESS_APP_KEY"],
        "method": METOD,
        "sign_method": "sha256",
        "timestamp": str(int(time.time() * 1000)),
        **{k: str(v) for k, v in is_parametreleri.items()},
    }
    tracking = os.environ.get("ALIEXPRESS_TRACKING_ID")
    if tracking:
        params["tracking_id"] = tracking
    params["sign"] = imzala(params, os.environ["ALIEXPRESS_APP_SECRET"])
    # timestamp değiştiği için önbellek anahtarı her seferinde farklı olur; önbelleği kapatıyoruz
    return getir(UC_NOKTA, params, onbellek_saat=0, aralik=1.0)


def urunleri_coz(yanit: dict) -> list[dict]:
    kok = yanit.get("aliexpress_affiliate_product_query_response", {})
    sonuc = kok.get("resp_result", {}) or {}
    if str(sonuc.get("resp_code", "200")) != "200":
        raise RuntimeError(f"AliExpress hata: {sonuc.get('resp_msg')}")
    urunler = (((sonuc.get("result") or {}).get("products") or {}).get("product")) or []
    return urunler if isinstance(urunler, list) else [urunler]


def _fiyat(u: dict) -> float | None:
    for alan in ("target_sale_price", "sale_price", "target_app_sale_price"):
        try:
            return float(str(u[alan]).replace(",", ""))
        except (KeyError, ValueError, TypeError):
            continue
    return None


def ozetle(urunler: list[dict]) -> dict:
    siparis = sum(int(u.get("lastest_volume") or 0) for u in urunler)
    fiyatlar = [f for f in (_fiyat(u) for u in urunler) if f]
    ornekler = [{"baslik": u.get("product_title", "")[:90],
                 "siparis": int(u.get("lastest_volume") or 0),
                 "fiyat_usd": _fiyat(u),
                 "url": u.get("product_detail_url") or u.get("promotion_link")}
                for u in sorted(urunler, key=lambda x: int(x.get("lastest_volume") or 0), reverse=True)[:3]]
    return {
        "dunya_siparis": siparis,
        "dunya_fiyat_usd": round(statistics.median(fiyatlar), 2) if fiyatlar else None,
        "ornek_urunler": ornekler,
    }


def topla(kategori: dict, sayfa_boyutu: int = 50) -> dict:
    if not yapilandirildi():
        return {}
    tum: list[dict] = []
    for kelime in kategori.get("anahtar_en", [])[:3]:
        try:
            yanit = _istek({"keywords": kelime, "page_no": 1, "page_size": sayfa_boyutu,
                            "sort": "LAST_VOLUME_DESC", "target_currency": "USD",
                            "target_language": "EN"})
            tum.extend(urunleri_coz(yanit))
        except Exception as hata:  # ağ / API hatası botu durdurmasın
            print(f"  [aliexpress] {kelime!r}: {gizle(str(hata))}")
    return ozetle(tum) if tum else {}
