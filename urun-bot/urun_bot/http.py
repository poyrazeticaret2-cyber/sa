"""Küçük HTTP yardımcıları: zaman aşımı, nazik bekleme, dosya önbelleği (yalnız stdlib)."""
from __future__ import annotations

import hashlib
import json
import time
import urllib.parse
import urllib.request
from pathlib import Path

from .config import PROJE_DIZINI

ONBELLEK = PROJE_DIZINI / ".cache"
TARAYICI_UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
               "(KHTML, like Gecko) Chrome/124.0 Safari/537.36")
_son_istek: dict[str, float] = {}


def _bekle(alan: str, aralik: float) -> None:
    gecen = time.monotonic() - _son_istek.get(alan, 0.0)
    if gecen < aralik:
        time.sleep(aralik - gecen)
    _son_istek[alan] = time.monotonic()


def getir(url: str, params: dict | None = None, *, basliklar: dict | None = None,
          zaman_asimi: float = 20, aralik: float = 1.5, onbellek_saat: float = 24,
          json_mu: bool = True):
    """GET isteği. Sonucu `.cache/` altında `onbellek_saat` boyunca saklar."""
    if params:
        url = f"{url}?{urllib.parse.urlencode(params)}"
    anahtar = hashlib.sha256(url.encode()).hexdigest()[:32]
    dosya = ONBELLEK / f"{anahtar}.json"
    if onbellek_saat > 0 and dosya.exists() and time.time() - dosya.stat().st_mtime < onbellek_saat * 3600:
        icerik = dosya.read_text(encoding="utf-8")
        return json.loads(icerik) if json_mu else icerik

    _bekle(urllib.parse.urlsplit(url).netloc, aralik)
    istek = urllib.request.Request(url, headers={"User-Agent": TARAYICI_UA, **(basliklar or {})})
    with urllib.request.urlopen(istek, timeout=zaman_asimi) as yanit:
        icerik = yanit.read().decode("utf-8", errors="replace")
    if onbellek_saat > 0:
        ONBELLEK.mkdir(exist_ok=True)
        dosya.write_text(icerik, encoding="utf-8")
    return json.loads(icerik) if json_mu else icerik


def gizle(url_veya_metin: str) -> str:
    """Hata mesajlarında imza/anahtar sızmasın."""
    for anahtar in ("app_key", "sign", "access_token"):
        url_veya_metin = _parametre_gizle(url_veya_metin, anahtar)
    return url_veya_metin


def _parametre_gizle(metin: str, ad: str) -> str:
    import re
    return re.sub(rf"({ad}=)[^&\s]+", r"\1***", metin)
