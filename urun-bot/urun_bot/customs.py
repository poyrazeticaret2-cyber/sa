"""Türkiye'ye varış maliyeti ve vergi yükü hesabı.

Ticari ithalat (beyanname ile):
    CIF  = FOB + navlun + sigorta
    GV   = CIF x gv%
    İGV  = CIF x igv%
    ÖTV  = (CIF + GV + İGV) x otv%
    Yerel = CIF x yerel% + parti sabit masrafı / adet
    KDV  = (CIF + GV + İGV + ÖTV + yerel masraf) x kdv%   -> şirket için indirilebilir

Posta / hızlı kargo (bireysel, <= 1500 €): CIF x maktu oran (AB dışı %60).
"""
from __future__ import annotations

from dataclasses import dataclass, asdict


@dataclass
class Maliyet:
    rota: str
    fob_usd: float
    navlun_usd: float
    sigorta_usd: float
    cif_usd: float
    gv_usd: float
    igv_usd: float
    otv_usd: float
    maktu_usd: float
    yerel_masraf_usd: float
    kdv_usd: float
    vergi_yuku_pct: float      # KDV hariç vergilerin CIF'e oranı
    varis_maliyeti_usd: float  # KDV hariç birim maliyet (şirket için gerçek maliyet)

    def sozluk(self) -> dict:
        return {k: round(v, 4) if isinstance(v, float) else v for k, v in asdict(self).items()}


def ticari_maliyet(fob_usd: float, kategori: dict, kurallar: dict,
                   navlun_usd_kg: float | None = None, sabit_usd: float = 0.0) -> Maliyet:
    """sabit_usd: parti başına sabit masrafın (müşavir, beyanname) birime düşen payı."""
    t = kurallar["ticari_ithalat"]
    navlun_kg = t["navlun_usd_kg"] if navlun_usd_kg is None else navlun_usd_kg
    navlun = float(kategori.get("birim_kg", 0.5)) * navlun_kg
    sigorta = fob_usd * t["sigorta_orani_pct"] / 100
    cif = fob_usd + navlun + sigorta
    gv = cif * float(kategori.get("gv", 0)) / 100
    igv = cif * float(kategori.get("igv", 0)) / 100
    otv = (cif + gv + igv) * float(kategori.get("otv", 0)) / 100
    yerel = cif * t["yerel_masraf_pct"] / 100 + sabit_usd
    kdv_oran = float(kategori.get("kdv", kurallar.get("kdv_varsayilan", 20)))
    kdv = (cif + gv + igv + otv + yerel) * kdv_oran / 100
    vergi = gv + igv + otv
    return Maliyet("ticari", fob_usd, navlun, sigorta, cif, gv, igv, otv, 0.0, yerel, kdv,
                   (vergi / cif * 100) if cif else 0.0, cif + vergi + yerel)


def posta_maliyet(fob_usd: float, kargo_usd: float, kategori: dict, kurallar: dict,
                  ab_menseli: bool = False) -> Maliyet:
    p = kurallar["posta_hizli_kargo"]
    oran = p["ab_oran_pct"] if ab_menseli else p["ab_disi_oran_pct"]
    if float(kategori.get("otv", 0)) > 0:
        oran += p["otv_listesi_ek_pct"]
    cif = fob_usd + kargo_usd
    maktu = cif * oran / 100
    return Maliyet("posta", fob_usd, kargo_usd, 0.0, cif, 0.0, 0.0, 0.0, maktu, 0.0, 0.0,
                   oran, cif + maktu)


def tahmini_vergi_yuku_pct(kategori: dict) -> float:
    """Fiyat bilgisi yokken kategori oranlarından KDV hariç vergi yükü (CIF'e oran)."""
    gv = float(kategori.get("gv", 0))
    igv = float(kategori.get("igv", 0))
    otv = float(kategori.get("otv", 0))
    return gv + igv + (100 + gv + igv) * otv / 100
