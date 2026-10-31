"""Elle / dışarıdan toplanan veriyi CSV ile içe aktarma (Alibaba, Hepsiburada, kendi araştırmanız).

Alibaba.com'un herkese açık bir ürün arama API'si yoktur. Alibaba'da bulduğunuz
toptan fiyatları ve tedarikçi sayılarını bu CSV ile bota verebilirsiniz.

Zorunlu sütun: kategori_id
İsteğe bağlı sütunlar (hangileri doluysa kullanılır):
  alibaba_fiyat_usd, alibaba_moq, alibaba_tedarikci, dunya_siparis, dunya_fiyat_usd,
  tr_ilan_sayisi, tr_fiyat_try, trend_dunya, trend_tr
"""
from __future__ import annotations

import csv
from pathlib import Path

SAYISAL = {"alibaba_fiyat_usd", "alibaba_moq", "alibaba_tedarikci", "dunya_siparis",
           "dunya_fiyat_usd", "tr_ilan_sayisi", "tr_fiyat_try", "trend_dunya", "trend_tr"}


def oku(yol: str | Path) -> dict[str, dict]:
    veri: dict[str, dict] = {}
    with open(yol, encoding="utf-8-sig", newline="") as f:
        ayirici = ";" if ";" in f.readline() else ","
        f.seek(0)
        for satir in csv.DictReader(f, delimiter=ayirici):
            kid = (satir.get("kategori_id") or "").strip()
            if not kid:
                continue
            kayit = veri.setdefault(kid, {})
            for ad in SAYISAL:
                deger = (satir.get(ad) or "").strip().replace(",", ".")
                if deger:
                    try:
                        kayit[ad] = float(deger)
                    except ValueError:
                        pass
    return veri
