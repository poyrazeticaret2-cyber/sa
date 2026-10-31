"""Çıktılar: konsol tablosu, CSV ve tek dosyalık HTML rapor."""
from __future__ import annotations

import csv
import html
from datetime import datetime
from pathlib import Path

from .scoring import Sonuc

RISK_ETIKET = {"dusuk": "Düşük", "orta": "Orta", "yuksek": "Yüksek", "yasak": "Yasak"}


def _f(x, ek: str = "", ondalik: int = 0) -> str:
    if x is None:
        return "–"
    return f"{x:,.{ondalik}f}{ek}".replace(",", "X").replace(".", ",").replace("X", ".")


def konsol(sonuclar: list[Sonuc], adet: int = 15) -> str:
    satirlar = [f"{'#':>2}  {'Puan':>4}  {'Güven':>5}  {'Vergi':>6}  {'Risk':<6}  Kategori",
                "-" * 92]
    for i, r in enumerate(sonuclar[:adet], 1):
        satirlar.append(f"{i:>2}  {r.toplam:>4.0f}  {r.guven:>4.0f}%  {_f(r.vergi_yuku_pct, '%', 1):>6}  "
                        f"{RISK_ETIKET[r.kategori['risk']]:<6}  {r.kategori['ad'][:62]}")
    return "\n".join(satirlar)


CSV_ALANLARI = ["sira", "kategori_id", "kategori", "toplam_puan", "guven_pct", "talep", "bosluk",
                "vergi", "mevzuat", "marj", "vergi_yuku_pct", "risk", "izinler", "gtip",
                "dunya_siparis", "dunya_fiyat_usd", "alibaba_fiyat_usd", "tr_ilan_sayisi",
                "tr_fiyat_try", "varis_maliyeti_usd", "marj_pct", "uyarilar"]


def csv_yaz(sonuclar: list[Sonuc], yol: Path) -> None:
    with open(yol, "w", encoding="utf-8-sig", newline="") as f:
        w = csv.DictWriter(f, fieldnames=CSV_ALANLARI, delimiter=";")
        w.writeheader()
        for i, r in enumerate(sonuclar, 1):
            s, k = r.sinyaller, r.kategori
            w.writerow({
                "sira": i, "kategori_id": k["id"], "kategori": k["ad"],
                "toplam_puan": round(r.toplam, 1), "guven_pct": round(r.guven),
                **{a: r.puanlar.get(a) for a in ("talep", "bosluk", "vergi", "mevzuat", "marj")},
                "vergi_yuku_pct": round(r.vergi_yuku_pct, 1), "risk": k["risk"],
                "izinler": ", ".join(k.get("izinler", [])), "gtip": k.get("gtip"),
                "dunya_siparis": s.get("dunya_siparis"), "dunya_fiyat_usd": s.get("dunya_fiyat_usd"),
                "alibaba_fiyat_usd": s.get("alibaba_fiyat_usd"), "tr_ilan_sayisi": s.get("tr_ilan_sayisi"),
                "tr_fiyat_try": s.get("tr_fiyat_try"),
                "varis_maliyeti_usd": (r.maliyet or {}).get("varis_maliyeti_usd"),
                "marj_pct": round(r.marj_pct, 1) if r.marj_pct is not None else None,
                "uyarilar": " | ".join(r.uyarilar),
            })


def _cubuk(deger) -> str:
    if deger is None:
        return '<span class="yok">veri yok</span>'
    return (f'<span class="cubuk"><i style="width:{deger:.0f}%"></i></span>'
            f'<span class="sayi">{deger:.0f}</span>')


def html_yaz(sonuclar: list[Sonuc], yol: Path, kurallar: dict, meta: dict) -> None:
    e = html.escape
    satirlar = []
    for i, r in enumerate(sonuclar, 1):
        k, s, m = r.kategori, r.sinyaller, r.maliyet or {}
        izinler = "".join(
            f'<span class="etiket" title="{e(kurallar["izinler"].get(x, {}).get("ad", x))}">{e(x)}</span>'
            for x in k.get("izinler", [])) or '<span class="yok">yok</span>'
        ornekler = "".join(
            f'<li><a href="{e(o["url"] or "#")}" rel="noopener" target="_blank">{e(o["baslik"])}</a> '
            f'– {_f(o["siparis"])} sipariş</li>' for o in s.get("ornek_urunler", []) if o.get("url"))
        maliyet = ""
        if m:
            maliyet = (f'<p>Birim toptan {_f(m["fob_usd"], " $", 2)} → CIF {_f(m["cif_usd"], " $", 2)} → '
                       f'GV {_f(m["gv_usd"], " $", 2)} + İGV {_f(m["igv_usd"], " $", 2)} + ÖTV {_f(m["otv_usd"], " $", 2)} '
                       f'→ <b>varış {_f(m["varis_maliyeti_usd"], " $", 2)}</b> (KDV {_f(m["kdv_usd"], " $", 2)} ayrıca, indirilebilir)</p>')
        uyarilar = "".join(f"<li>{e(u)}</li>" for u in r.uyarilar)
        satirlar.append(f"""
<details class="kart risk-{k['risk']}">
  <summary>
    <span class="sira">{i}</span>
    <span class="ad">{e(k['ad'])}</span>
    <span class="puan">{r.toplam:.0f}</span>
  </summary>
  <div class="ic">
    <div class="izgara">
      <div><small>Dünya talebi</small>{_cubuk(r.puanlar['talep'])}</div>
      <div><small>Türkiye boşluğu</small>{_cubuk(r.puanlar['bosluk'])}</div>
      <div><small>Vergi uygunluğu</small>{_cubuk(r.puanlar['vergi'])}</div>
      <div><small>Mevzuat kolaylığı</small>{_cubuk(r.puanlar['mevzuat'])}</div>
      <div><small>Marj</small>{_cubuk(r.puanlar['marj'])}</div>
    </div>
    <table>
      <tr><th>GTİP (örnek)</th><td>{e(str(k.get('gtip')))}</td><th>Risk</th><td>{RISK_ETIKET[k['risk']]}</td></tr>
      <tr><th>Tahmini vergi yükü</th><td>%{_f(r.vergi_yuku_pct, '', 1)} (GV %{_f(k.get('gv'), '', 1)}, İGV %{_f(k.get('igv'), '', 1)}, ÖTV %{_f(k.get('otv'), '', 1)})</td>
          <th>Gereken belgeler</th><td>{izinler}</td></tr>
      <tr><th>Dünya siparişi (ilk sayfalar)</th><td>{_f(s.get('dunya_siparis'))}</td><th>Trendyol ilan sayısı</th><td>{_f(s.get('tr_ilan_sayisi'))}</td></tr>
      <tr><th>AliExpress medyan fiyat</th><td>{_f(s.get('dunya_fiyat_usd'), ' $', 2)}</td><th>Trendyol medyan fiyat</th><td>{_f(s.get('tr_fiyat_try'), ' ₺')}</td></tr>
      <tr><th>Tahmini brüt marj</th><td>{_f(r.marj_pct, '%')}</td><th>Veri güveni</th><td>%{r.guven:.0f}</td></tr>
    </table>
    {maliyet}
    <p class="not">{e(k.get('not', ''))}</p>
    {f'<ul class="uyari">{uyarilar}</ul>' if uyarilar else ''}
    {f'<p><small>Çok satan örnekler</small></p><ul>{ornekler}</ul>' if ornekler else ''}
  </div>
</details>""")

    kaynaklar = "".join(f"<li>{e(x)}</li>" for x in kurallar.get("resmi_kaynaklar", []))
    posta = kurallar["posta_hizli_kargo"]
    sayfa = f"""<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ürün Fırsat Raporu</title>
<style>
:root{{--zemin:#f7f7f5;--kart:#fff;--yazi:#1d1d1b;--soluk:#6b6b66;--cizgi:#e4e3de;--vurgu:#2f6f5e;--uyari:#a3521b}}
@media (prefers-color-scheme:dark){{:root{{--zemin:#161615;--kart:#1f1f1d;--yazi:#ecebe6;--soluk:#a3a29c;--cizgi:#34332f;--vurgu:#6cc3a7;--uyari:#e59a62}}}}
*{{box-sizing:border-box}}body{{margin:0;background:var(--zemin);color:var(--yazi);font:15px/1.5 system-ui,sans-serif}}
main{{max-width:980px;margin:0 auto;padding:24px 16px}}h1{{margin:0 0 4px;font-size:24px}}
.meta{{color:var(--soluk);margin-bottom:16px}}.bant{{background:var(--kart);border:1px solid var(--cizgi);border-left:4px solid var(--uyari);padding:12px 16px;border-radius:8px;margin-bottom:16px}}
.kart{{background:var(--kart);border:1px solid var(--cizgi);border-radius:10px;margin-bottom:10px}}
summary{{display:flex;gap:12px;align-items:center;padding:12px 16px;cursor:pointer;list-style:none}}
summary::-webkit-details-marker{{display:none}}.sira{{color:var(--soluk);width:24px}}.ad{{flex:1;font-weight:600}}
.puan{{font-size:20px;font-weight:700;color:var(--vurgu);min-width:40px;text-align:right}}
.ic{{padding:0 16px 16px}}.izgara{{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:12px}}
.izgara small{{display:block;color:var(--soluk)}}.cubuk{{display:inline-block;width:70%;height:8px;background:var(--cizgi);border-radius:4px;vertical-align:middle;margin-right:6px}}
.cubuk i{{display:block;height:100%;background:var(--vurgu);border-radius:4px}}.sayi{{font-variant-numeric:tabular-nums}}
table{{width:100%;border-collapse:collapse;font-size:14px}}th,td{{text-align:left;padding:6px 8px;border-top:1px solid var(--cizgi);vertical-align:top}}
th{{color:var(--soluk);font-weight:500;width:20%}}.etiket{{display:inline-block;border:1px solid var(--cizgi);border-radius:4px;padding:0 6px;margin:0 4px 4px 0;font-size:12px}}
.yok{{color:var(--soluk)}}.not{{color:var(--soluk)}}.uyari{{color:var(--uyari)}}.risk-orta .ad::after{{content:" · orta risk";color:var(--uyari);font-weight:400}}
.risk-yuksek .ad::after{{content:" · yüksek risk";color:var(--uyari);font-weight:400}}
a{{color:var(--vurgu)}}@media(max-width:600px){{th{{width:auto}}table,tr,td,th{{display:block}}}}
</style></head><body><main>
<h1>Ürün Fırsat Raporu</h1>
<div class="meta">{e(meta.get('tarih', ''))} · {len(sonuclar)} kategori · kaynaklar: {e(meta.get('kaynaklar', ''))} · USD/TRY: {_f(meta.get('usd_try'), '', 2)}</div>
{'<div class="bant"><b>DEMO verisi.</b> Bu rapordaki sayılar örnektir, gerçek pazar verisi değildir.</div>' if meta.get('demo') else ''}
<div class="bant">Vergi oranları <b>tahminidir</b> (kural dosyası son kontrol: {e(kurallar.get('son_kontrol', ''))}). Sipariş vermeden önce GTİP'i gümrük müşavirinizle ve TARA'dan doğrulayın.
Bireysel posta/hızlı kargoda (≤ {posta['ust_limit_eur']} €) AB dışı menşeli eşyaya <b>%{posta['ab_disi_oran_pct']}</b> maktu vergi uygulanır; bu nedenle hesap ticari ithalata göre yapılmıştır.</div>
{''.join(satirlar)}
<h2>Doğrulama kaynakları</h2><ul>{kaynaklar}</ul>
</main></body></html>"""
    yol.write_text(sayfa, encoding="utf-8")


def dosya_adi(dizin: Path, uzanti: str) -> Path:
    dizin.mkdir(parents=True, exist_ok=True)
    return dizin / f"firsatlar-{datetime.now():%Y%m%d-%H%M}.{uzanti}"
