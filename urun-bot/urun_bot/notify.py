"""Telegram özeti (opsiyonel). Ortam: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID."""
from __future__ import annotations

import json
import os
import urllib.request

from .scoring import Sonuc


def telegram_gonder(sonuclar: list[Sonuc], adet: int = 10, demo: bool = False) -> bool:
    token, sohbet = os.environ.get("TELEGRAM_BOT_TOKEN"), os.environ.get("TELEGRAM_CHAT_ID")
    if not (token and sohbet):
        print("Telegram atlandı: TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID tanımlı değil.")
        return False
    satirlar = ["🔎 Ürün fırsatları" + (" (DEMO)" if demo else "")]
    for i, r in enumerate(sonuclar[:adet], 1):
        satirlar.append(f"{i}. {r.kategori['ad'][:60]} — puan {r.toplam:.0f}, vergi ~%{r.vergi_yuku_pct:.0f}")
    satirlar.append("Vergiler tahminidir; GTİP'i doğrulayın.")
    veri = json.dumps({"chat_id": sohbet, "text": "\n".join(satirlar)}).encode()
    istek = urllib.request.Request(f"https://api.telegram.org/bot{token}/sendMessage", data=veri,
                                   headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(istek, timeout=15) as yanit:
            return yanit.status == 200
    except Exception as hata:
        print(f"Telegram gönderilemedi: {type(hata).__name__}")
        return False
