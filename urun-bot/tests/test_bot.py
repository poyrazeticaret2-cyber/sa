import hashlib
import hmac
import json
import tempfile
import unittest
from pathlib import Path

from urun_bot import config
from urun_bot.customs import posta_maliyet, ticari_maliyet
from urun_bot.scoring import basabas_fiyat, satis_basina_kar, score_category, sirala
from urun_bot.sources import aliexpress, csv_veri, trendyol

KURALLAR = config.gumruk_kurallari()


def kat(**kw):
    taban = {"id": "x", "ad": "X", "gv": 0, "igv": 0, "otv": 0, "kdv": 20,
             "izinler": [], "risk": "dusuk", "birim_kg": 0.5}
    taban.update(kw)
    return taban


class GumrukTest(unittest.TestCase):
    def test_ticari_hesap(self):
        m = ticari_maliyet(100, kat(gv=10, igv=20, birim_kg=1), KURALLAR, navlun_usd_kg=5)
        cif = 100 + 5 + 0.5
        self.assertAlmostEqual(m.cif_usd, cif)
        self.assertAlmostEqual(m.gv_usd, cif * 0.10)
        self.assertAlmostEqual(m.igv_usd, cif * 0.20)
        self.assertAlmostEqual(m.vergi_yuku_pct, 30)
        yerel = cif * 0.01
        self.assertAlmostEqual(m.kdv_usd, (cif * 1.3 + yerel) * 0.20)
        self.assertAlmostEqual(m.varis_maliyeti_usd, cif * 1.3 + yerel)

    def test_otv_matrahi(self):
        m = ticari_maliyet(100, kat(gv=10, otv=10, birim_kg=0), KURALLAR, navlun_usd_kg=0)
        self.assertAlmostEqual(m.otv_usd, (100.5 * 1.1) * 0.10)

    def test_posta_maktu_ab_disi_60(self):
        m = posta_maliyet(20, 5, kat(), KURALLAR)
        self.assertAlmostEqual(m.maktu_usd, 25 * 0.60)
        self.assertAlmostEqual(posta_maliyet(20, 5, kat(otv=20), KURALLAR).maktu_usd, 25 * 0.80)


class SkorTest(unittest.TestCase):
    def test_bos_turkiye_pazari_daha_yuksek_puan(self):
        bos = score_category(kat(), {"dunya_siparis": 50000, "tr_ilan_sayisi": 100}, KURALLAR, 40)
        dolu = score_category(kat(), {"dunya_siparis": 50000, "tr_ilan_sayisi": 150000}, KURALLAR, 40)
        self.assertGreater(bos.toplam, dolu.toplam)

    def test_yuksek_vergi_cezalandirilir(self):
        s = {"dunya_siparis": 50000, "tr_ilan_sayisi": 1000}
        self.assertGreater(score_category(kat(), s, KURALLAR, 40).toplam,
                           score_category(kat(gv=17, igv=30), s, KURALLAR, 40).toplam)

    def test_yasak_ve_yuksek_risk_elenir(self):
        s = {"dunya_siparis": 50000}
        sonuc = [score_category(kat(id="a"), s, KURALLAR, 40),
                 score_category(kat(id="b", risk="yuksek"), s, KURALLAR, 40),
                 score_category(kat(id="c", risk="yasak"), s, KURALLAR, 40)]
        self.assertEqual([r.kategori["id"] for r in sirala(sonuc)], ["a"])
        self.assertEqual([r.kategori["id"] for r in sirala(sonuc, riskli_dahil=True)], ["a", "b"])

    def test_marj(self):
        r = score_category(kat(birim_kg=0), {"alibaba_fiyat_usd": 10, "tr_fiyat_try": 1200}, KURALLAR, 40)
        self.assertIsNotNone(r.birim_kar_try)
        self.assertAlmostEqual(r.marj_pct, 18.9, places=1)  # 10 $ x 40 TL ürün, 1200 TL satış

    def test_satis_basina_kar_elle_hesapla_ayni(self):
        # 1200 TL satış, 92,4 TL maliyet: KDV 200, komisyon 200, sabit 75 -> 632,6; %25 vergi -> 474,45
        self.assertAlmostEqual(satis_basina_kar(1200, 92.4, KURALLAR), 474.45, places=2)
        self.assertAlmostEqual(satis_basina_kar(129, 93.9, KURALLAR), 129 / 1.2 - 25.8 / 1.2 - 75 - 93.9)

    def test_basabas(self):
        f = basabas_fiyat(92.4, KURALLAR)
        self.assertAlmostEqual(satis_basina_kar(f, 92.4, KURALLAR), 0, places=6)

    def test_segment_filtresi(self):
        s = {"dunya_siparis": 50000}
        sonuc = [score_category(kat(id="a", segment=["tuketici"]), s, KURALLAR, 40),
                 score_category(kat(id="b", segment=["b2b"]), s, KURALLAR, 40)]
        self.assertEqual([r.kategori["id"] for r in sirala(sonuc, segment="tuketici")], ["a"])

    def test_veri_yoksa_guven_dusuk(self):
        r = score_category(kat(), {}, KURALLAR, 40)
        self.assertLess(r.guven, 50)
        self.assertIn("Dünya talep verisi yok.", r.uyarilar)


class KaynakTest(unittest.TestCase):
    def test_aliexpress_imza(self):
        p = {"app_key": "123", "method": "m", "timestamp": "1", "sign_method": "sha256"}
        beklenen = hmac.new(b"sir", b"app_key123methodmsign_methodsha256timestamp1",
                            hashlib.sha256).hexdigest().upper()
        self.assertEqual(aliexpress.imzala(p, "sir"), beklenen)

    def test_aliexpress_coz(self):
        yanit = {"aliexpress_affiliate_product_query_response": {"resp_result": {
            "resp_code": 200, "result": {"products": {"product": [
                {"lastest_volume": 120, "target_sale_price": "9.90", "product_title": "A",
                 "product_detail_url": "u1"},
                {"lastest_volume": 30, "target_sale_price": "5.10", "product_title": "B"}]}}}}}
        o = aliexpress.ozetle(aliexpress.urunleri_coz(yanit))
        self.assertEqual(o["dunya_siparis"], 150)
        self.assertAlmostEqual(o["dunya_fiyat_usd"], 7.5)

    def test_trendyol_coz(self):
        toplam, fiyat = trendyol.coz({"result": {"totalCount": 812, "products": [
            {"price": {"sellingPrice": 100}}, {"price": {"sellingPrice": {"value": 300}}}]}})
        self.assertEqual(toplam, 812)
        self.assertEqual(fiyat, [100.0, 300.0])

    def test_csv(self):
        with tempfile.TemporaryDirectory() as d:
            yol = Path(d) / "a.csv"
            yol.write_text("kategori_id;alibaba_fiyat_usd;tr_ilan_sayisi\ncnc-uc;3,2;900\n", encoding="utf-8")
            self.assertEqual(csv_veri.oku(yol), {"cnc-uc": {"alibaba_fiyat_usd": 3.2, "tr_ilan_sayisi": 900.0}})


class VeriDosyasiTest(unittest.TestCase):
    def test_kategoriler_gecerli(self):
        izinler = set(KURALLAR["izinler"])
        ids = set()
        for k in config.kategoriler():
            self.assertNotIn(k["id"], ids)
            ids.add(k["id"])
            self.assertIn(k["risk"], KURALLAR["risk_taban_puani"])
            self.assertTrue(set(k["izinler"]) <= izinler, k["id"])
            self.assertTrue(k["anahtar_en"] and k["anahtar_tr"], k["id"])
            self.assertTrue(set(k["segment"]) <= {"tuketici", "hobi", "b2b"}, k["id"])
        demo = config.json_oku("demo_signals.json")["sinyaller"]
        self.assertTrue(set(demo) <= ids)


class UctanUcaTest(unittest.TestCase):
    def test_demo_rapor(self):
        from urun_bot.cli import main
        with tempfile.TemporaryDirectory() as d:
            self.assertEqual(main(["--demo", "--cikti", d]), 0)
            html = next(Path(d).glob("*.html")).read_text(encoding="utf-8")
            self.assertIn("DEMO verisi", html)
            csv = next(Path(d).glob("*.csv")).read_text(encoding="utf-8-sig")
            self.assertNotIn("ayakkabi", csv)

    def test_hesap_komutu(self):
        from urun_bot.cli import main
        self.assertEqual(main(["--hesap", "1.24", "1200", "--kategori", "cnc-uc", "--usd-try", "48"]), 0)
        self.assertEqual(main(["--hesap", "1.24", "1200"]), 2)


if __name__ == "__main__":
    unittest.main()
