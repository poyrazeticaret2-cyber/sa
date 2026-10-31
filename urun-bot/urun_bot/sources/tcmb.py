"""TCMB günlük kur (USD/TRY döviz satış)."""
from __future__ import annotations

import xml.etree.ElementTree as ET

from ..http import getir

UC_NOKTA = "https://www.tcmb.gov.tr/kurlar/today.xml"


def usd_try() -> float | None:
    try:
        xml = getir(UC_NOKTA, json_mu=False, onbellek_saat=6)
        kok = ET.fromstring(xml)
        for doviz in kok.findall("Currency"):
            if doviz.get("CurrencyCode") == "USD":
                return float(doviz.findtext("ForexSelling"))
    except Exception as hata:
        print(f"  [tcmb] kur alınamadı: {hata}")
    return None
