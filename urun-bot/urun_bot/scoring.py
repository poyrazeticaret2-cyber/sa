"""Fırsat skoru: dünya talebi x Türkiye boşluğu x vergi x mevzuat x marj."""
from __future__ import annotations

import math
from dataclasses import dataclass, field

from .customs import ticari_maliyet, tahmini_vergi_yuku_pct

AGIRLIKLAR = {"talep": 0.25, "bosluk": 0.30, "vergi": 0.20, "mevzuat": 0.15, "marj": 0.10}
TOPTAN_INDIRIM = 0.70  # Alibaba fiyatı yoksa AliExpress perakende fiyatının ~%70'i toptan kabul edilir


def _sinirla(x: float, alt: float = 0.0, ust: float = 100.0) -> float:
    return max(alt, min(ust, x))


def _log_puan(deger: float, tavan: float) -> float:
    """0 -> 0, tavan -> 100, logaritmik."""
    if deger <= 0:
        return 0.0
    return _sinirla(math.log10(deger + 1) / math.log10(tavan + 1) * 100)


def satis_basina_kar(satis_kdv_dahil_try: float, urun_maliyeti_try: float, kurallar: dict,
                     kdv_pct: float = 20.0) -> float:
    """Pazaryerinde bir satıştan kalan vergi sonrası net kâr (TL).

    KDV düşülür (ithalat KDV'si indirilebildiği için maliyet değildir), komisyon KDV dahil fiyattan
    hesaplanır ve kendi KDV'si indirilir; kargo/platform/ambalaj sipariş başına sabit masraftır.
    """
    p = kurallar["pazaryeri"]
    kdv_haric = satis_kdv_dahil_try / (1 + kdv_pct / 100)
    komisyon = satis_kdv_dahil_try * p["komisyon_pct"] / 100
    if p.get("komisyon_kdv_dahil_fiyattan", True):
        komisyon /= (1 + kdv_pct / 100)
    sabit = p["kargo_try"] + p["platform_bedeli_try"] + p["ambalaj_try"]
    vergi_oncesi = kdv_haric - komisyon - sabit - urun_maliyeti_try
    if vergi_oncesi > 0:
        return vergi_oncesi * (1 - p["gelir_vergisi_pct"] / 100)
    return vergi_oncesi


def basabas_fiyat(urun_maliyeti_try: float, kurallar: dict, kdv_pct: float = 20.0) -> float:
    """Zarar etmemek için gereken en düşük KDV dahil satış fiyatı."""
    p = kurallar["pazaryeri"]
    kom = p["komisyon_pct"] / 100
    pay = (1 - kom) / (1 + kdv_pct / 100) if p.get("komisyon_kdv_dahil_fiyattan", True) \
        else 1 / (1 + kdv_pct / 100) - kom
    return (p["kargo_try"] + p["platform_bedeli_try"] + p["ambalaj_try"] + urun_maliyeti_try) / pay


@dataclass
class Sonuc:
    kategori: dict
    sinyaller: dict
    puanlar: dict = field(default_factory=dict)
    toplam: float = 0.0
    guven: float = 0.0
    vergi_yuku_pct: float = 0.0
    maliyet: dict | None = None
    marj_pct: float | None = None      # net kâr / KDV dahil satış fiyatı
    birim_kar_try: float | None = None  # satış başına vergi sonrası net kâr
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

    t = kurallar["ticari_ithalat"]
    sabit_usd = (t.get("parti_sabit_masraf_try", 0) / max(t.get("parti_adet", 1), 1) / usd_try
                 if usd_try else 0.0)
    if toptan:
        m = ticari_maliyet(float(toptan), kategori, kurallar, navlun_usd_kg, sabit_usd)
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
        r.birim_kar_try = satis_basina_kar(float(s["tr_fiyat_try"]), r.maliyet["varis_maliyeti_usd"] * usd_try,
                                           kurallar, kdv)
        r.marj_pct = r.birim_kar_try / float(s["tr_fiyat_try"]) * 100
        puan["marj"] = _sinirla(r.marj_pct * 100 / 30)  # %30 net marj = 100 puan
        if r.marj_pct < 10:
            r.uyarilar.append(f"Satış başına net kâr düşük ({r.birim_kar_try:.0f} TL, %{r.marj_pct:.0f}). "
                              "Paket/set satışıyla sepet tutarını büyütün.")

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


def sirala(sonuclar: list[Sonuc], riskli_dahil: bool = False, min_puan: float = 0,
          segment: str | None = None) -> list[Sonuc]:
    secilen = [x for x in sonuclar
               if x.kategori["risk"] != "yasak"
               and (not segment or segment in x.kategori.get("segment", []))
               and (riskli_dahil or x.kategori["risk"] != "yuksek")
               and x.toplam >= min_puan]
    return sorted(secilen, key=lambda x: x.toplam, reverse=True)
