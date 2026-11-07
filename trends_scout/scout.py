#!/usr/bin/env python3
"""Trends Scout - Google Trends ile ürün trendi keşfi (Türkiye varsayılan).

Bulur: en çok aranan ürünler, yeni yükselenler, 5 yıllık düşüştekiler.
Çıktı: out/trends.csv ve out/report.html

  python scout.py --demo            # internetsiz sentetik veriyle deneme
  python scout.py                   # gerçek Google Trends (kendi bilgisayarında)
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import html
import json
import math
import random
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
CACHE_DIR = HERE / ".cache"
OUT_DIR = HERE / "out"
SHOPPING_CAT = 18  # Google Trends "Alışveriş" kategorisi


# --------------------------------------------------------------------------
# Veri kaynağı: gerçek (pytrends) ve demo
# --------------------------------------------------------------------------
class RealSource:
    def __init__(self, geo, cat, sleep):
        from pytrends.request import TrendReq  # geç import: demo için gerekmez
        # retries=0: pytrends'in urllib3 Retry uyumsuzluğunu tetiklemesin, yeniden denemeyi biz yapıyoruz
        self.pt = TrendReq(hl="tr-TR", tz=-180, timeout=(10, 30))
        self.geo, self.cat, self.sleep = geo, cat, sleep

    def _retry(self, fn):
        for attempt in range(5):
            try:
                out = fn()
                time.sleep(self.sleep + random.random())
                return out
            except Exception as exc:  # 429 vb.
                wait = min(120, 10 * 2 ** attempt)
                print(f"  ! {exc.__class__.__name__}: {wait}s bekleniyor ({attempt + 1}/5)", file=sys.stderr)
                time.sleep(wait)
        raise RuntimeError("Google Trends yanıt vermedi (muhtemelen 429 / engel). Daha sonra tekrar dene.")

    def interest(self, kws, timeframe):
        def go():
            self.pt.build_payload(kws, cat=self.cat, timeframe=timeframe, geo=self.geo)
            return self.pt.interest_over_time()
        df = self._retry(go)
        if df is None or df.empty:
            return {k: [] for k in kws}
        return {k: [float(v) for v in df[k].tolist()] for k in kws if k in df}

    def related(self, kw):
        def go():
            self.pt.build_payload([kw], cat=self.cat, timeframe="today 12-m", geo=self.geo)
            return self.pt.related_queries()
        data = self._retry(go).get(kw) or {}
        top = data.get("top")
        rising = data.get("rising")
        top_l = [] if top is None else [str(q) for q in top["query"].tolist()]
        rise_l = [] if rising is None else [str(q) for q in rising["query"].tolist()]
        return top_l, rise_l


class DemoSource:
    """Gerçek veri değil: mantığı internetsiz denemek için sentetik eğriler."""

    def __init__(self, *_a, **_k):
        pass

    @staticmethod
    def _rng(key):
        return random.Random(int(hashlib.sha1(key.encode()).hexdigest(), 16))

    def _curve(self, kw, n=261):
        r = self._rng(kw)
        shape = r.choice(["rise", "fall", "flat", "season", "spike_fall"])
        base = r.uniform(8, 90)
        out = []
        for i in range(n):
            t = i / (n - 1)
            if shape == "rise":
                v = base * (0.15 + 1.6 * t ** 2)
            elif shape == "fall":
                v = base * (1.5 - 1.3 * t)
            elif shape == "season":
                v = base * (1 + 0.4 * math.sin(i / 52 * 2 * math.pi))
            elif shape == "spike_fall":
                v = base * 2.5 * math.exp(-((t - 0.25) ** 2) / 0.01) + base * 0.15
            else:
                v = base
            out.append(max(0.0, v + r.gauss(0, base * 0.06)))
        return out

    def interest(self, kws, timeframe):
        curves = {k: self._curve(k) for k in kws}
        peak = max(max(c) for c in curves.values()) or 1
        return {k: [round(v / peak * 100) for v in c] for k, c in curves.items()}

    def related(self, kw):
        words = ["fiyat", "yorumları", "modelleri", "en iyi", "ucuz"]
        r = self._rng("rel" + kw)
        pick = r.sample(words, 4)
        return [f"{kw} {w}" for w in pick[:2]], [f"{kw} {w}" for w in pick[2:]]


# --------------------------------------------------------------------------
# Önbellek (aynı isteği tekrar tekrar Google'a göndermemek için)
# --------------------------------------------------------------------------
def cached(namespace, key, fn, enabled=True):
    if not enabled:
        return fn()
    CACHE_DIR.mkdir(exist_ok=True)
    path = CACHE_DIR / f"{namespace}-{hashlib.sha1(key.encode()).hexdigest()[:16]}.json"
    if path.exists():
        return json.loads(path.read_text())
    val = fn()
    path.write_text(json.dumps(val, ensure_ascii=False))
    return val


# --------------------------------------------------------------------------
# Analiz
# --------------------------------------------------------------------------
def mean(xs):
    return sum(xs) / len(xs) if xs else 0.0


def moving_avg(xs, w=8):
    return [mean(xs[max(0, i - w + 1): i + 1]) for i in range(len(xs))]


def slope_per_week(xs):
    n = len(xs)
    mx, my = (n - 1) / 2, mean(xs)
    den = sum((i - mx) ** 2 for i in range(n))
    return sum((i - mx) * (x - my) for i, x in enumerate(xs)) / den if den else 0.0


def analyze(name, s, min_level):
    """s: anchor'a göre normalize haftalık seri (anchor ortalaması = 100)."""
    n = len(s)
    if n < 120:
        return None
    last52, prev52 = mean(s[-52:]), mean(s[-104:-52])
    last12 = mean(s[-12:])
    # Mevsimselliği yanlış "düşüş" saymamak için 52 haftalık kayan ortalama ile kıyasla
    roll = [mean(s[i - 51:i + 1]) for i in range(51, n)]
    peak = max(roll)
    weeks_since_peak = len(roll) - 1 - roll.index(peak)
    now = roll[-1]
    m = mean(s) or 1e-9
    trend_pct_year = slope_per_week(s) * 52 / m * 100  # 5 yıllık eğim, yıllık % (ortalamaya göre)
    yoy = (last52 + 1) / (prev52 + 1) - 1
    momentum = (last12 + 1) / (last52 + 1)
    from_peak = now / peak if peak else 0

    label = "Kararlı"
    if peak >= min_level:
        if yoy >= 0.5 and momentum >= 1.05 and trend_pct_year > 10:
            label = "Yükselen"
        elif weeks_since_peak >= 52 and from_peak <= 0.5 and trend_pct_year < -10:
            label = "Düşen"
    return {
        "urun": name,
        "etiket": label,
        "seviye_son52h": round(last52, 1),
        "seviye_onceki52h": round(prev52, 1),
        "yillik_degisim_pct": round(yoy * 100, 1),
        "ivme_12h_vs_52h": round(momentum, 2),
        "zirveden_oran": round(from_peak, 2),
        "zirve_kac_hafta_once": weeks_since_peak,
        "egim_pct_yil": round(trend_pct_year, 1),
        "seri": [round(v, 1) for v in s],
    }


# --------------------------------------------------------------------------
# Akış
# --------------------------------------------------------------------------
def read_seeds(path):
    return [l.strip() for l in Path(path).read_text(encoding="utf-8").splitlines()
            if l.strip() and not l.startswith("#")]


def discover(src, seeds, max_candidates, use_cache, geo, cat):
    cands, seen = [], set()
    rising_first = []
    for i, kw in enumerate(seeds, 1):
        print(f"[keşif {i}/{len(seeds)}] {kw}")
        top, rise = cached("rel", f"{geo}|{cat}|{kw}", lambda kw=kw: src.related(kw), use_cache)
        for q in rise + top:
            q = q.strip().lower()
            if q and q not in seen and q not in {s.lower() for s in seeds}:
                seen.add(q)
                (rising_first if q in rise else cands).append(q)
    pool = list(dict.fromkeys([s.lower() for s in seeds] + rising_first + cands))
    return pool[:max_candidates]


def collect_series(src, kws, anchor, use_cache, geo, cat, timeframe):
    """5'erli gruplar halinde çeker; her grupta anchor var, değerler anchor'a göre ölçeklenir."""
    out = {}
    kws = [k for k in kws if k.lower() != anchor.lower()]
    for i in range(0, len(kws), 4):
        batch = kws[i:i + 4]
        group = [anchor] + batch
        print(f"[seri {i + 1}-{i + len(batch)}/{len(kws)}] {', '.join(batch)}")
        data = cached("ts", f"{geo}|{cat}|{timeframe}|{'|'.join(group)}",
                      lambda g=group: src.interest(g, timeframe), use_cache)
        a = data.get(anchor) or []
        am = mean(a)
        if am <= 0:
            print("  ! anchor boş döndü, grup atlandı")
            continue
        scale = 100.0 / am
        for k in batch:
            if data.get(k):
                out[k] = [v * scale for v in data[k]]
    return out


def write_csv(rows, path):
    cols = [c for c in rows[0] if c != "seri"]
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=cols, extrasaction="ignore")
        w.writeheader()
        w.writerows(rows)


def spark(series, w=140, h=32):
    if not series:
        return ""
    mx = max(series) or 1
    step = w / (len(series) - 1)
    pts = " ".join(f"{i * step:.1f},{h - v / mx * (h - 2) - 1:.1f}" for i, v in enumerate(series))
    return f'<svg width="{w}" height="{h}" viewBox="0 0 {w} {h}" aria-hidden="true"><polyline fill="none" stroke="currentColor" stroke-width="1.5" points="{pts}"/></svg>'


def section(title, note, rows):
    if not rows:
        return f"<h2>{html.escape(title)}</h2><p class=n>Bu gruba giren ürün çıkmadı.</p>"
    body = "".join(
        f"<tr><td>{html.escape(r['urun'])}</td><td class=num>{r['seviye_son52h']}</td>"
        f"<td class=num>{r['yillik_degisim_pct']:+}%</td><td class=num>%{round(r['zirveden_oran'] * 100)}</td>"
        f"<td>{spark(r['seri'])}</td></tr>" for r in rows)
    return (f"<h2>{html.escape(title)}</h2><p class=n>{html.escape(note)}</p>"
            "<div class=w><table><thead><tr><th>Ürün</th><th>Seviye (son 52 hf)</th><th>1 yıllık değişim</th>"
            f"<th>Zirveye göre şimdi</th><th>5 yıl</th></tr></thead><tbody>{body}</tbody></table></div>")


def write_html(rows, path, meta):
    top = sorted(rows, key=lambda r: -r["seviye_son52h"])[:25]
    rising = sorted([r for r in rows if r["etiket"] == "Yükselen"], key=lambda r: -r["yillik_degisim_pct"])[:25]
    falling = sorted([r for r in rows if r["etiket"] == "Düşen"], key=lambda r: r["zirveden_oran"])[:25]
    demo = '<p class=demo>DEMO: veriler sentetiktir, gerçek Google Trends verisi değildir.</p>' if meta["demo"] else ""
    doc = f"""<!doctype html><html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Ürün Trend Raporu</title>
<style>
:root{{--bg:#fff;--fg:#1c1b19;--mut:#6b6860;--line:#e3e0d8;--acc:#0b6e4f}}
@media (prefers-color-scheme:dark){{:root{{--bg:#16150f;--fg:#ecebe6;--mut:#a09d93;--line:#34322b;--acc:#4cc59a}}}}
body{{margin:0;padding:24px 16px;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,sans-serif}}
main{{max-width:960px;margin:0 auto}}h1{{font-size:24px}}h2{{margin-top:36px;font-size:19px}}
.n{{color:var(--mut);margin:4px 0 10px}}.demo{{background:#fde8c8;color:#5a3500;padding:8px 12px;border-radius:6px}}
.w{{overflow-x:auto}}table{{border-collapse:collapse;width:100%}}th,td{{padding:7px 10px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}}
th{{color:var(--mut);font-weight:600;font-size:13px}}.num{{text-align:right;font-variant-numeric:tabular-nums}}svg{{color:var(--acc)}}
</style></head><body><main>
<h1>Ürün Trend Raporu</h1>{demo}
<p class=n>Bölge: {html.escape(meta['geo'] or 'Dünya')} · Kategori: Alışveriş · Dönem: son 5 yıl (haftalık) · {meta['count']} ürün analiz edildi · Karşılaştırma: "{html.escape(meta['anchor'])}" ortalaması = 100</p>
{section('En çok aranan ürünler', 'Son 52 haftanın ortalama ilgisi (referans kelime = 100).', top)}
{section('Yeni yükselenler', 'Son 1 yılda önceki yıla göre ≥%50 artmış ve son 12 haftada ivmesi sürenler.', rising)}
{section('5 yılda düşüşe geçenler', 'Zirvesi en az 1 yıl önce kalmış, şimdi zirvenin yarısı veya altında ve genel eğimi negatif.', falling)}
<p class=n>Not: Google Trends değerleri mutlak arama sayısı değil, göreli ilgidir.</p>
</main></body></html>"""
    path.write_text(doc, encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--geo", default="TR", help="Ülke kodu (TR, DE, US; boş=dünya)")
    ap.add_argument("--seeds", default=str(HERE / "seeds.txt"))
    ap.add_argument("--anchor", default="kargo", help="Normalizasyon referans kelimesi (kararlı, yüksek hacimli olmalı)")
    ap.add_argument("--max-candidates", type=int, default=60)
    ap.add_argument("--min-level", type=float, default=8.0, help="Bu zirve seviyesinin altındakiler 'Kararlı' sayılır")
    ap.add_argument("--sleep", type=float, default=3.0, help="İstekler arası bekleme (sn)")
    ap.add_argument("--no-cache", action="store_true")
    ap.add_argument("--demo", action="store_true", help="Sentetik veriyle çalıştır (internet gerekmez)")
    a = ap.parse_args()

    src = DemoSource() if a.demo else RealSource(a.geo, SHOPPING_CAT, a.sleep)
    use_cache = not a.no_cache and not a.demo
    seeds = read_seeds(a.seeds)
    cands = discover(src, seeds, a.max_candidates, use_cache, a.geo, SHOPPING_CAT)
    series = collect_series(src, cands, a.anchor, use_cache, a.geo, SHOPPING_CAT, "today 5-y")

    rows = [r for k, s in series.items() if (r := analyze(k, s, a.min_level))]
    if not rows:
        sys.exit("Analiz edilecek veri çıkmadı.")
    OUT_DIR.mkdir(exist_ok=True)
    write_csv(sorted(rows, key=lambda r: -r["seviye_son52h"]), OUT_DIR / "trends.csv")
    write_html(rows, OUT_DIR / "report.html",
               {"geo": a.geo, "anchor": a.anchor, "count": len(rows), "demo": a.demo})
    counts = {l: sum(r["etiket"] == l for r in rows) for l in ("Yükselen", "Düşen", "Kararlı")}
    print(f"\nBitti: {len(rows)} ürün · {counts}\n→ {OUT_DIR / 'trends.csv'}\n→ {OUT_DIR / 'report.html'}")


if __name__ == "__main__":
    main()
