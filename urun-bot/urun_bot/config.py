"""Ayar ve veri dosyası yükleme. Gizli anahtarlar yalnız ortam değişkeninden / .env'den okunur."""
from __future__ import annotations

import json
import os
from pathlib import Path

PAKET_DIZINI = Path(__file__).resolve().parent
VERI_DIZINI = PAKET_DIZINI / "data"
PROJE_DIZINI = PAKET_DIZINI.parent


def env_yukle(yol: Path | None = None) -> None:
    """Basit .env okuyucu (KEY=VALUE). Var olan ortam değişkenlerini ezmez."""
    yol = yol or (PROJE_DIZINI / ".env")
    if not yol.exists():
        return
    for satir in yol.read_text(encoding="utf-8").splitlines():
        satir = satir.strip()
        if not satir or satir.startswith("#") or "=" not in satir:
            continue
        anahtar, deger = satir.split("=", 1)
        os.environ.setdefault(anahtar.strip(), deger.strip().strip('"').strip("'"))


def json_oku(ad: str) -> dict:
    with open(VERI_DIZINI / ad, encoding="utf-8") as f:
        return json.load(f)


def kategoriler() -> list[dict]:
    return json_oku("categories.json")["kategoriler"]


def gumruk_kurallari() -> dict:
    return json_oku("customs_rules.json")


def env_float(ad: str, varsayilan: float | None = None) -> float | None:
    deger = os.environ.get(ad)
    if deger in (None, ""):
        return varsayilan
    try:
        return float(deger.replace(",", "."))
    except ValueError:
        return varsayilan
