"""Fırsat skoru: dünya talebi x Türkiye boşluğu x vergi x mevzuat x marj."""
from __future__ import annotations

import math
from dataclasses import dataclass, field

from .customs import ticari_maliyet, tahmini_vergi_yuku_pct

AGIRLIKLAR = {"talep": 0.25, "bosluk": 0.30, "vergi": 0.20, "mevzuat": 0.15, "marj": 0.10}
PAZARYERI_KOMISYON_PCT = 15.0
TOPTAN_INDIRIM = 0.70  # Alibaba fiyatı yoksa AliExpress perakende fiyatının ~%70'i toptan kabul edilir


def _sinirla(x: float, alt: float = 0.0, ust: float = 100.0) -> float:
    return max(alt, min(ust, x))


def _log_puan(deger: float, tavan: float) -> float:
    """0 -> 0, tavan -> 100, logaritmik."""
    if deger <= 0:
        return 0.0
    return _sinirla(math.log10(deger + 1) / math.log10(tavan + 1) * 100)


@dataclass
class Sonuc:
    kategori: dict
    sinyaller: dict
    puanlar: dict = field(default_factory=dict)
    toplam: float = 0.0
    guven: float = 0.0
    vergi_yuku_pct: float = 0.0
    maliyet: dict | None = None
    marj_pct: float | None = None
    uyarilar: list[str] = field(default_factory=list)

    @property
    def uygun(self) -> bool:
        return self.kategori["risk"] in ("dusuk", "orta") and self.vergi_yuku_pct <= 15


def talep_puani(s: dict) -> float | None:
    parcalar = []
    if s.get("dunya_siparis") is not None:
        parcalar.append(_log_puan(float(s["dunya_siparis"]), 100_000))
    if s.get("trend_dunya") is not None:
        parcalar.append(_sinirla(float(s["trend_dunya"])))
    return sum(parcalar) / len(parcalar) if parcalar else None


def bosluk_puani(s: dict) -> float | None:
    parcalar = []
    if s.get("tr_ilan_sayisi") is not None:
        parcalar.append(100 - _log_puan(float(s["tr_ilan_sayisi"]), 200_000))
    if s.get("trend_tr") is not None:
        parcalar.append(100 - _sinirla(float(s["trend_tr"])))
    return sum(parcalar) / len(parcalar) if parcalar else None


def mevzuat_puani(kategori: dict, kurallar: dict) -> float:
    taban = kurallar["risk_taban_puani"].get(kategori["risk"], 50)
    ceza = sum(kurallar["izinler"].get(i, {}).get("ceza", 10) for i in kategori.get("izinler", []))
    return _sinirla(taban - ceza)


def score_category(kategori: dict, sinyaller: dict, kurallar: dict, usd_try: float | None,
                   navlun_usd_kg: float | None = None) -> Sonuc:
    s = sinyaller or {}
    r = Sonuc(kategori=kategori, sinyaller=s)

    toptan = s.get("alibaba_fiyat_usd")
    if toptan is None and s.get("dunya_fiyat_usd") is not None:
        toptan = float(s["dunya_fiyat_usd"]) * TOPTAN_INDIRIM
        r.uyarilar.append("Toptan fiyat AliExpress fiyatından tahmin edildi (Alibaba verisi yok).")

    if toptan:
        m = ticari_maliyet(float(toptan), kategori, kurallar, navlun_usd_kg)
        r.maliyet = m.sozluk()
        r.vergi_yuku_pct = m.vergi_yuku_pct
    else:
        r.vergi_yuku_pct = tahmini_vergi_yuku_pct(kategori)

    puan = {
        "talep": talep_puani(s),
        "bosluk": bosluk_puani(s),
        "vergi": _sinirla(100 - r.vergi_yuku_pct * 2),
        "mevzuat": mevzuat_puani(kategori, kurallar),
        "marj": None,
    }

    if r.maliyet and s.get("tr_fiyat_try") and usd_try:
        kdv = float(kategori.get("kdv", kurallar.get("kdv_varsayilan", 20)))
        net = float(s["tr_fiyat_try"]) / (1 + kdv / 100) * (1 - PAZARYERI_KOMISYON_PCT / 100)
        maliyet_try = r.maliyet["varis_maliyeti_usd"] * usd_try
        r.marj_pct = (net - maliyet_try) / net * 100 if net > 0 else None
        if r.marj_pct is not None:
            puan["marj"] = _sinirla(r.marj_pct * 2)
            if r.marj_pct < 15:
                r.uyarilar.append(f"Brüt marj düşük (%{r.marj_pct:.0f}).")

    agirlik_toplam = sum(AGIRLIKLAR[k] for k, v in puan.items() if v is not None)
    r.toplam = (sum(AGIRLIKLAR[k] * v for k, v in puan.items() if v is not None) / agirlik_toplam
                if agirlik_toplam else 0.0)
    r.guven = agirlik_toplam * 100
    r.puanlar = {k: (round(v, 1) if v is not None else None) for k, v in puan.items()}

    if kategori["risk"] == "yasak":
        r.toplam = 0.0
        r.uyarilar.append("Türkiye'ye ithalatı yasak veya fiilen uygun değil.")
    if kategori.get("vergi_guveni") == "dusuk":
        r.uyarilar.append("Vergi oranları düşük güvenli tahmin: GTİP'i TARA'dan doğrulayın.")
    if puan["talep"] is None:
        r.uyarilar.append("Dünya talep verisi yok.")
    if puan["bosluk"] is None:
        r.uyarilar.append("Türkiye pazar verisi yok.")
    return r


def sirala(sonuclar: list[Sonuc], riskli_dahil: bool = False, min_puan: float = 0) -> list[Sonuc]:
    secilen = [x for x in sonuclar
               if x.kategori["risk"] != "yasak"
               and (riskli_dahil or x.kategori["risk"] != "yuksek")
               and x.toplam >= min_puan]
    return sorted(secilen, key=lambda x: x.toplam, reverse=True)
