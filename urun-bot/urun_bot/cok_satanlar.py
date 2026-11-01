"""Çok satan ve çok beğenilen ürün listesi.

Alibaba.com'un herkese açık satış/yorum verisi sunan bir API'si yoktur. Bu yüzden ürün düzeyindeki
satış adedi ve olumlu değerlendirme oranı resmî AliExpress Affiliate API'den alınır; her ürünün
yanına aynı ürünün Alibaba toptan araması ve Trendyol araması için link eklenir.
"""
from __future__ import annotations

import csv
import html
import urllib.parse
from dataclasses import dataclass
from pathlib import Path

from .sources import aliexpress


@dataclass
class Urun:
    urun_id: str
    baslik: str
    kategori_id: str
    kelime_en: str
    kelime_tr: str
    satis: int
    olumlu_pct: float | None
    fiyat_usd: float | None
    aliexpress_url: str
    gorsel: str

    @property
    def alibaba_url(self) -> str:
        return "https://www.alibaba.com/trade/search?" + urllib.parse.urlencode({"SearchText": self.kelime_en})

    @property
    def trendyol_url(self) -> str:
        return "https://www.trendyol.com/sr?" + urllib.parse.urlencode({"q": self.kelime_tr})


def olumlu_orani(deger) -> float | None:
    """'97.8%' / '97,8' / 97.8 -> 97.8"""
    if deger in (None, ""):
        return None
    try:
        return float(str(deger).replace("%", "").replace(",", ".").strip())
    except ValueError:
        return None


def urune_cevir(u: dict, kategori_id: str, kelime_en: str, kelime_tr: str) -> Urun:
    return Urun(
        urun_id=str(u.get("product_id") or u.get("product_detail_url") or u.get("product_title")),
        baslik=str(u.get("product_title") or "")[:140],
        kategori_id=kategori_id, kelime_en=kelime_en, kelime_tr=kelime_tr,
        satis=int(u.get("lastest_volume") or 0),
        olumlu_pct=olumlu_orani(u.get("evaluate_rate")),
        fiyat_usd=aliexpress._fiyat(u),
        aliexpress_url=str(u.get("product_detail_url") or u.get("promotion_link") or ""),
        gorsel=str(u.get("product_main_image_url") or ""),
    )


def filtrele(urunler: list[Urun], min_olumlu: float, min_satis: int) -> list[Urun]:
    """Tekrarları atar, eşiği geçenleri satışa göre sıralar."""
    tekil: dict[str, Urun] = {}
    for u in urunler:
        if u.satis < min_satis or (u.olumlu_pct is not None and u.olumlu_pct < min_olumlu):
            continue
        if u.urun_id not in tekil or tekil[u.urun_id].satis < u.satis:
            tekil[u.urun_id] = u
    return sorted(tekil.values(), key=lambda x: (x.satis, x.olumlu_pct or 0), reverse=True)


def topla(kategoriler: list[dict], ek_kelimeler: list[str], kelime_basi: int = 50) -> list[Urun]:
    """Her kategori için ilk İngilizce anahtar kelimeyle, ek kelimelerin her biriyle arar."""
    isler = [(k["id"], k["anahtar_en"][0], k["anahtar_tr"][0]) for k in kategoriler]
    isler += [("ozel", kelime, kelime) for kelime in ek_kelimeler]
    sonuc: list[Urun] = []
    for kid, en, tr in isler:
        print(f"- {en}")
        try:
            yanit = aliexpress._istek({"keywords": en, "page_no": 1, "page_size": kelime_basi,
                                       "sort": "LAST_VOLUME_DESC", "target_currency": "USD",
                                       "target_language": "EN"})
            sonuc.extend(urune_cevir(u, kid, en, tr) for u in aliexpress.urunleri_coz(yanit))
        except Exception as hata:
            print(f"  [aliexpress] {en!r}: {aliexpress.gizle(str(hata))}")
    return sonuc


CSV_ALANLARI = ["sira", "baslik", "kategori_id", "satis_son_donem", "olumlu_degerlendirme_pct",
                "fiyat_usd", "aliexpress_url", "alibaba_toptan_arama", "trendyol_arama"]


def csv_yaz(urunler: list[Urun], yol: Path) -> None:
    with open(yol, "w", encoding="utf-8-sig", newline="") as f:
        w = csv.writer(f, delimiter=";")
        w.writerow(CSV_ALANLARI)
        for i, u in enumerate(urunler, 1):
            w.writerow([i, u.baslik, u.kategori_id, u.satis, u.olumlu_pct, u.fiyat_usd,
                        u.aliexpress_url, u.alibaba_url, u.trendyol_url])


def html_yaz(urunler: list[Urun], yol: Path, meta: dict) -> None:
    e = html.escape
    satirlar = "".join(f"""
<tr>
  <td class="n">{i}</td>
  <td>{f'<img src="{e(u.gorsel)}" alt="" loading="lazy">' if u.gorsel.startswith("https://") else ''}</td>
  <td><a href="{e(u.aliexpress_url)}" target="_blank" rel="noopener">{e(u.baslik)}</a><small>{e(u.kategori_id)}</small></td>
  <td class="n">{f"{u.satis:,}".replace(",", ".")}</td>
  <td class="n">{'' if u.olumlu_pct is None else f'%{u.olumlu_pct:.1f}'}</td>
  <td class="n">{'' if u.fiyat_usd is None else f'{u.fiyat_usd:.2f} $'}</td>
  <td><a href="{e(u.alibaba_url)}" target="_blank" rel="noopener">Alibaba</a> · <a href="{e(u.trendyol_url)}" target="_blank" rel="noopener">Trendyol</a></td>
</tr>""" for i, u in enumerate(urunler, 1))
    yol.write_text(f"""<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Çok Satanlar</title>
<style>
:root{{--zemin:#f7f7f5;--kart:#fff;--yazi:#1d1d1b;--soluk:#6b6b66;--cizgi:#e4e3de;--vurgu:#2f6f5e}}
@media (prefers-color-scheme:dark){{:root{{--zemin:#161615;--kart:#1f1f1d;--yazi:#ecebe6;--soluk:#a3a29c;--cizgi:#34332f;--vurgu:#6cc3a7}}}}
body{{margin:0;background:var(--zemin);color:var(--yazi);font:14px/1.45 system-ui,sans-serif}}
main{{max-width:1100px;margin:0 auto;padding:24px 16px}}.meta{{color:var(--soluk);margin-bottom:12px}}
.kap{{overflow-x:auto;background:var(--kart);border:1px solid var(--cizgi);border-radius:10px}}
table{{border-collapse:collapse;width:100%}}th,td{{padding:8px 10px;border-top:1px solid var(--cizgi);text-align:left;vertical-align:top}}
th{{color:var(--soluk);font-weight:500;border-top:0}}.n{{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}}
td small{{display:block;color:var(--soluk)}}img{{width:56px;height:56px;object-fit:cover;border-radius:6px}}a{{color:var(--vurgu)}}
</style></head><body><main><h1>Çok Satan ve Çok Beğenilen Ürünler</h1>
<div class="meta">{e(meta.get('tarih', ''))} · {len(urunler)} ürün · olumlu değerlendirme ≥ %{meta.get('min_olumlu')} · satış ≥ {meta.get('min_satis')} ·
kaynak: AliExpress Affiliate API (satış = AliExpress'in "son dönem satış" sayısı)</div>
<div class="kap"><table><thead><tr><th>#</th><th></th><th>Ürün</th><th class="n">Satış</th><th class="n">Olumlu</th><th class="n">Fiyat</th><th>Toptan / TR</th></tr></thead>
<tbody>{satirlar}</tbody></table></div></main></body></html>""", encoding="utf-8")
