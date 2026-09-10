<?php
/**
 * AlmancaPro - B1 kelime hazinesi.
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_vocab_b1(): array
{
    return [
        /* --- Bağlaçlar ve yapı --- */
        ['g'=>'bevor','tr'=>'-den önce','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Bevor ich anfange, lese ich die Anweisung.','ext'=>'Başlamadan önce talimatı okuyorum.','note'=>'Yan cümle kurar; fiil sona gider.'],
        ['g'=>'nachdem','tr'=>'-den sonra','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Nachdem ich gegessen hatte, ging ich zur Arbeit.','ext'=>'Yemek yedikten sonra işe gittim.','note'=>'Genellikle iki farklı zaman kullanılır: Plusquamperfekt + Präteritum.'],
        ['g'=>'während','tr'=>'-iken; sırasında','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Während ich arbeite, höre ich Radio.','ext'=>'Çalışırken radyo dinliyorum.'],
        ['g'=>'seitdem','tr'=>'-den beri','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Seitdem ich hier wohne, spreche ich besser Deutsch.','ext'=>'Burada yaşadığımdan beri daha iyi Almanca konuşuyorum.'],
        ['g'=>'bis','tr'=>'-e kadar','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Warte bitte, bis ich fertig bin.','ext'=>'Lütfen ben bitirene kadar bekle.'],
        ['g'=>'sobald','tr'=>'-er ermez','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Sobald ich ankomme, rufe ich an.','ext'=>'Varır varmaz arayacağım.'],
        ['g'=>'damit','tr'=>'-mesi için','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Ich spreche langsam, damit alle mich verstehen.','ext'=>'Herkes beni anlasın diye yavaş konuşuyorum.','sim'=>'Özne aynıysa "um ... zu", farklıysa "damit" kullanılır.'],
        ['g'=>'um ... zu','tr'=>'-mek için','pos'=>'phrase','top'=>'infinitiv','ex'=>'Ich lerne Deutsch, um in Deutschland zu arbeiten.','ext'=>'Almanya\'da çalışmak için Almanca öğreniyorum.','note'=>'Her iki cümlenin öznesi aynı olmalıdır.'],
        ['g'=>'ohne ... zu','tr'=>'-meden','pos'=>'phrase','top'=>'infinitiv','ex'=>'Er ging, ohne etwas zu sagen.','ext'=>'Hiçbir şey söylemeden gitti.'],
        ['g'=>'statt ... zu','tr'=>'-mek yerine','pos'=>'phrase','top'=>'infinitiv','ex'=>'Statt zu warten, rief er an.','ext'=>'Beklemek yerine telefon etti.'],
        ['g'=>'obwohl','tr'=>'-e rağmen','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Obwohl er krank war, kam er zur Arbeit.','ext'=>'Hasta olmasına rağmen işe geldi.'],
        ['g'=>'falls','tr'=>'şayet, eğer','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Falls es Probleme gibt, melden Sie sich bitte.','ext'=>'Sorun olursa lütfen haber verin.'],

        /* --- da-/wo- bileşikleri --- */
        ['g'=>'darauf','tr'=>'onun üzerine; ona (o şeye)','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Ich freue mich darauf.','ext'=>'Onu dört gözle bekliyorum.','note'=>'Nesne bir şey ise edat + zamir yerine da(r)- bileşiği kullanılır.'],
        ['g'=>'darüber','tr'=>'onun hakkında','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Wir sprechen darüber morgen.','ext'=>'Bunun hakkında yarın konuşuruz.'],
        ['g'=>'davon','tr'=>'ondan, o konudan','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Ich habe schon davon gehört.','ext'=>'Bunu daha önce duydum.'],
        ['g'=>'damit','tr'=>'onunla','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Bist du damit einverstanden?','ext'=>'Bununla hemfikir misin?'],
        ['g'=>'womit','tr'=>'ne ile','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Womit kann ich Ihnen helfen?','ext'=>'Size ne konuda yardımcı olabilirim?'],
        ['g'=>'worauf','tr'=>'ne üzerine, neyi','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Worauf wartest du?','ext'=>'Neyi bekliyorsun?'],
        ['g'=>'worüber','tr'=>'ne hakkında','pos'=>'adverb','top'=>'pronominaladverb','ex'=>'Worüber sprecht ihr?','ext'=>'Ne hakkında konuşuyorsunuz?'],

        /* --- Fiil + edat --- */
        ['g'=>'sich interessieren','tr'=>'ilgilenmek','pos'=>'verb','top'=>'verb_praeposition','p3'=>'interessiert sich','pret'=>'interessierte sich','p2'=>'interessiert','aux'=>'haben','refl'=>1,'prep'=>'für + Akkusativ','ex'=>'Ich interessiere mich für Technik.','ext'=>'Teknikle ilgileniyorum.'],
        ['g'=>'sich kümmern','tr'=>'ilgilenmek, bakmak','pos'=>'verb','top'=>'verb_praeposition','p3'=>'kümmert sich','pret'=>'kümmerte sich','p2'=>'gekümmert','aux'=>'haben','refl'=>1,'prep'=>'um + Akkusativ','ex'=>'Ich kümmere mich um das Problem.','ext'=>'Sorunla ben ilgileneceğim.'],
        ['g'=>'denken','tr'=>'düşünmek','pos'=>'verb','top'=>'verb_praeposition','p3'=>'denkt','pret'=>'dachte','p2'=>'gedacht','aux'=>'haben','irr'=>1,'prep'=>'an + Akkusativ','ex'=>'Ich denke an meine Familie.','ext'=>'Ailemi düşünüyorum.'],
        ['g'=>'sprechen über','tr'=>'hakkında konuşmak','pos'=>'phrase','top'=>'verb_praeposition','prep'=>'über + Akkusativ','ex'=>'Wir sprechen über den Vertrag.','ext'=>'Sözleşme hakkında konuşuyoruz.'],
        ['g'=>'teilnehmen','tr'=>'katılmak','pos'=>'verb','top'=>'verb_praeposition','p3'=>'nimmt teil','pret'=>'nahm teil','p2'=>'teilgenommen','aux'=>'haben','irr'=>1,'sep'=>'teil','prep'=>'an + Dativ','ex'=>'Ich nehme an der Besprechung teil.','ext'=>'Toplantıya katılıyorum.'],
        ['g'=>'sich beschweren','tr'=>'şikayet etmek','pos'=>'verb','top'=>'verb_praeposition','p3'=>'beschwert sich','pret'=>'beschwerte sich','p2'=>'beschwert','aux'=>'haben','refl'=>1,'prep'=>'über + Akkusativ','ex'=>'Ich möchte mich über den Lärm beschweren.','ext'=>'Gürültüden şikayet etmek istiyorum.'],
        ['g'=>'sich bedanken','tr'=>'teşekkür etmek','pos'=>'verb','top'=>'verb_praeposition','p3'=>'bedankt sich','pret'=>'bedankte sich','p2'=>'bedankt','aux'=>'haben','refl'=>1,'prep'=>'für + Akkusativ','ex'=>'Ich bedanke mich für Ihre Hilfe.','ext'=>'Yardımınız için teşekkür ederim.'],
        ['g'=>'achten','tr'=>'dikkat etmek','pos'=>'verb','top'=>'verb_praeposition','p3'=>'achtet','pret'=>'achtete','p2'=>'geachtet','aux'=>'haben','prep'=>'auf + Akkusativ','ex'=>'Achten Sie auf die Sicherheit.','ext'=>'Güvenliğe dikkat edin.'],
        ['g'=>'abhängen','tr'=>'bağlı olmak','pos'=>'verb','top'=>'verb_praeposition','p3'=>'hängt ab','pret'=>'hing ab','p2'=>'abgehangen','aux'=>'haben','irr'=>1,'sep'=>'ab','prep'=>'von + Dativ','ex'=>'Das hängt von der Situation ab.','ext'=>'Bu duruma bağlı.'],

        /* --- Konjunktiv II ve kibarlık --- */
        ['g'=>'würde','tr'=>'-ecekti, -erdi','pos'=>'verb','top'=>'konjunktiv','ex'=>'Ich würde gern früher anfangen.','ext'=>'Daha erken başlamak isterdim.','note'=>'würde + Infinitiv en yaygın Konjunktiv II biçimidir.'],
        ['g'=>'hätte','tr'=>'sahip olurdu/olsaydı','pos'=>'verb','top'=>'konjunktiv','ex'=>'Wenn ich mehr Zeit hätte, würde ich mehr lernen.','ext'=>'Daha çok vaktim olsaydı daha çok çalışırdım.'],
        ['g'=>'wäre','tr'=>'olurdu/olsaydı','pos'=>'verb','top'=>'konjunktiv','ex'=>'Das wäre sehr nett.','ext'=>'Bu çok nazik olurdu.'],
        ['g'=>'könnte','tr'=>'-ebilirdi','pos'=>'verb','top'=>'konjunktiv','ex'=>'Könnten Sie mir bitte helfen?','ext'=>'Bana yardım edebilir misiniz?','note'=>'Kibar ricanın standart biçimidir.'],
        ['g'=>'sollte','tr'=>'-meli','pos'=>'verb','top'=>'konjunktiv','ex'=>'Du solltest zum Arzt gehen.','ext'=>'Doktora gitmelisin.'],
        ['g'=>'müsste','tr'=>'-mesi gerekirdi','pos'=>'verb','top'=>'konjunktiv','ex'=>'Ich müsste eigentlich arbeiten.','ext'=>'Aslında çalışmam gerekirdi.'],

        /* --- Passiv ve resmi dil --- */
        ['g'=>'werden (Passiv)','tr'=>'edilgen yapı yardımcı fiili','pos'=>'verb','top'=>'passiv','ex'=>'Die Maschine wird jeden Tag geprüft.','ext'=>'Makine her gün kontrol ediliyor.','note'=>'Passiv: werden + Partizip II.'],
        ['g'=>'geprüft','tr'=>'kontrol edilmiş','pos'=>'adjective','top'=>'passiv','ex'=>'Alle Geräte werden regelmäßig geprüft.','ext'=>'Bütün cihazlar düzenli kontrol ediliyor.'],
        ['g'=>'hergestellt','tr'=>'üretilmiş','pos'=>'adjective','top'=>'passiv','ex'=>'Das Produkt wird in Deutschland hergestellt.','ext'=>'Ürün Almanya\'da üretiliyor.'],
        ['g'=>'lassen','tr'=>'bırakmak; yaptırmak','pos'=>'verb','top'=>'grammatik','p3'=>'lässt','pret'=>'ließ','p2'=>'gelassen','aux'=>'haben','irr'=>1,'ex'=>'Ich lasse das Auto reparieren.','ext'=>'Arabayı tamir ettiriyorum.','note'=>'"lassen + Infinitiv" birine bir işi yaptırmayı anlatır.'],

        /* --- İş dünyası --- */
        ['g'=>'Betriebsrat','a'=>'der','pl'=>'die Betriebsräte','tr'=>'işçi temsil kurulu','pos'=>'noun','top'=>'arbeit','ex'=>'Der Betriebsrat vertritt die Mitarbeiter.','ext'=>'İşçi kurulu çalışanları temsil eder.','note'=>'Bu bir dil bilgisidir; hukuki tavsiye için uzmana danışılmalıdır.'],
        ['g'=>'Arbeitsunfähigkeitsbescheinigung','a'=>'die','pl'=>'die Arbeitsunfähigkeitsbescheinigungen','tr'=>'iş göremezlik raporu','pos'=>'noun','top'=>'arbeit','ex'=>'Bitte reichen Sie die Arbeitsunfähigkeitsbescheinigung ein.','ext'=>'Lütfen iş göremezlik raporunu teslim edin.','tip'=>'Günlük dilde kısaca "AU" veya "Krankschreibung" denir.'],
        ['g'=>'Lohnabrechnung','a'=>'die','pl'=>'die Lohnabrechnungen','tr'=>'maaş bordrosu','pos'=>'noun','top'=>'arbeit','ex'=>'Auf der Lohnabrechnung steht der Bruttolohn.','ext'=>'Bordroda brüt ücret yazıyor.'],
        ['g'=>'brutto','tr'=>'brüt','pos'=>'adjective','top'=>'arbeit','ex'=>'Das Gehalt ist brutto 3000 Euro.','ext'=>'Maaş brüt 3000 euro.'],
        ['g'=>'netto','tr'=>'net','pos'=>'adjective','top'=>'arbeit','ex'=>'Netto bleiben etwa 2000 Euro.','ext'=>'Net olarak yaklaşık 2000 euro kalıyor.'],
        ['g'=>'Probezeit','a'=>'die','pl'=>'die Probezeiten','tr'=>'deneme süresi','pos'=>'noun','top'=>'arbeit','ex'=>'Die Probezeit dauert sechs Monate.','ext'=>'Deneme süresi altı ay sürüyor.'],
        ['g'=>'Kündigungsfrist','a'=>'die','pl'=>'die Kündigungsfristen','tr'=>'fesih ihbar süresi','pos'=>'noun','top'=>'arbeit','ex'=>'Die Kündigungsfrist steht im Vertrag.','ext'=>'Fesih süresi sözleşmede yazıyor.'],
        ['g'=>'Schichtplan','a'=>'der','pl'=>'die Schichtpläne','tr'=>'vardiya planı','pos'=>'noun','top'=>'arbeit','ex'=>'Der Schichtplan hängt am schwarzen Brett.','ext'=>'Vardiya planı ilan panosunda asılı.'],
        ['g'=>'Vorgesetzte','a'=>'der','pl'=>'die Vorgesetzten','tr'=>'üst amir','pos'=>'noun','top'=>'arbeit','ex'=>'Sprechen Sie bitte mit Ihrem Vorgesetzten.','ext'=>'Lütfen üst amirinizle konuşun.'],
        ['g'=>'Mitarbeiter','a'=>'der','pl'=>'die Mitarbeiter','tr'=>'çalışan','pos'=>'noun','top'=>'arbeit','ex'=>'Wir suchen neue Mitarbeiter.','ext'=>'Yeni çalışanlar arıyoruz.'],
        ['g'=>'Frist','a'=>'die','pl'=>'die Fristen','tr'=>'süre, son tarih','pos'=>'noun','top'=>'arbeit','ex'=>'Die Frist läuft am Freitag ab.','ext'=>'Süre cuma günü doluyor.'],
        ['g'=>'Verantwortung','a'=>'die','pl'=>'die Verantwortungen','tr'=>'sorumluluk','pos'=>'noun','top'=>'arbeit','ex'=>'Ich übernehme die Verantwortung.','ext'=>'Sorumluluğu üstleniyorum.'],
        ['g'=>'Vorschlag','a'=>'der','pl'=>'die Vorschläge','tr'=>'öneri','pos'=>'noun','top'=>'meeting','ex'=>'Ich habe einen Vorschlag.','ext'=>'Bir önerim var.'],
        ['g'=>'Ergebnis','a'=>'das','pl'=>'die Ergebnisse','tr'=>'sonuç','pos'=>'noun','top'=>'meeting','ex'=>'Das Ergebnis ist gut.','ext'=>'Sonuç iyi.'],
        ['g'=>'Entscheidung','a'=>'die','pl'=>'die Entscheidungen','tr'=>'karar','pos'=>'noun','top'=>'meeting','ex'=>'Wir treffen die Entscheidung morgen.','ext'=>'Kararı yarın veriyoruz.','note'=>'"eine Entscheidung treffen" = karar vermek.'],
        ['g'=>'Problem','a'=>'das','pl'=>'die Probleme','tr'=>'sorun','pos'=>'noun','top'=>'meeting','ex'=>'Es gibt ein Problem mit der Maschine.','ext'=>'Makinede bir sorun var.'],
        ['g'=>'Lösung','a'=>'die','pl'=>'die Lösungen','tr'=>'çözüm','pos'=>'noun','top'=>'meeting','ex'=>'Wir suchen eine Lösung.','ext'=>'Bir çözüm arıyoruz.'],
        ['g'=>'Termin verschieben','tr'=>'randevuyu ertelemek','pos'=>'phrase','top'=>'meeting','ex'=>'Können wir den Termin verschieben?','ext'=>'Randevuyu erteleyebilir miyiz?'],

        /* --- Görüş bildirme --- */
        ['g'=>'meiner Meinung nach','tr'=>'bence','pos'=>'phrase','top'=>'meinung','ex'=>'Meiner Meinung nach ist das zu teuer.','ext'=>'Bence bu çok pahalı.','note'=>'"nach" burada isimden sonra gelir.'],
        ['g'=>'Ich bin der Meinung, dass','tr'=>'... olduğu görüşündeyim','pos'=>'phrase','top'=>'meinung','ex'=>'Ich bin der Meinung, dass wir mehr Zeit brauchen.','ext'=>'Daha çok vakte ihtiyacımız olduğu görüşündeyim.'],
        ['g'=>'Ich stimme zu','tr'=>'katılıyorum','pos'=>'phrase','top'=>'meinung','ex'=>'Da stimme ich Ihnen zu.','ext'=>'Bu konuda size katılıyorum.'],
        ['g'=>'Ich sehe das anders','tr'=>'ben farklı görüyorum','pos'=>'phrase','top'=>'meinung','ex'=>'Entschuldigung, ich sehe das anders.','ext'=>'Affedersiniz, ben farklı düşünüyorum.','note'=>'Karşı çıkarken kibar ve profesyonel bir kalıptır.'],
        ['g'=>'einerseits ... andererseits','tr'=>'bir yandan ... öte yandan','pos'=>'phrase','top'=>'meinung','ex'=>'Einerseits ist es teuer, andererseits ist es sicher.','ext'=>'Bir yandan pahalı, öte yandan güvenli.'],
        ['g'=>'Es kommt darauf an','tr'=>'duruma bağlı','pos'=>'phrase','top'=>'meinung','ex'=>'Es kommt darauf an, wie viel Zeit wir haben.','ext'=>'Ne kadar vaktimiz olduğuna bağlı.'],

        /* --- Genel B1 kelimeleri --- */
        ['g'=>'Erfahrung','a'=>'die','pl'=>'die Erfahrungen','tr'=>'deneyim','pos'=>'noun','top'=>'allgemein','ex'=>'Ich habe fünf Jahre Erfahrung.','ext'=>'Beş yıllık deneyimim var.'],
        ['g'=>'Möglichkeit','a'=>'die','pl'=>'die Möglichkeiten','tr'=>'olanak','pos'=>'noun','top'=>'allgemein','ex'=>'Gibt es eine Möglichkeit, früher zu gehen?','ext'=>'Daha erken gitme olanağı var mı?','tip'=>'-keit ve -heit ile biten isimler daima "die" alır.'],
        ['g'=>'Bedingung','a'=>'die','pl'=>'die Bedingungen','tr'=>'koşul','pos'=>'noun','top'=>'allgemein','ex'=>'Die Bedingungen sind fair.','ext'=>'Koşullar adil.'],
        ['g'=>'Unterschied','a'=>'der','pl'=>'die Unterschiede','tr'=>'fark','pos'=>'noun','top'=>'allgemein','ex'=>'Was ist der Unterschied zwischen den beiden?','ext'=>'İkisi arasındaki fark nedir?'],
        ['g'=>'Vorteil','a'=>'der','pl'=>'die Vorteile','tr'=>'avantaj','pos'=>'noun','top'=>'allgemein','ex'=>'Das hat viele Vorteile.','ext'=>'Bunun birçok avantajı var.'],
        ['g'=>'Nachteil','a'=>'der','pl'=>'die Nachteile','tr'=>'dezavantaj','pos'=>'noun','top'=>'allgemein','ex'=>'Der Nachteil ist der Preis.','ext'=>'Dezavantajı fiyatı.'],
        ['g'=>'Zusammenhang','a'=>'der','pl'=>'die Zusammenhänge','tr'=>'bağlam, ilişki','pos'=>'noun','top'=>'allgemein','ex'=>'In diesem Zusammenhang ist das wichtig.','ext'=>'Bu bağlamda bu önemli.'],
        ['g'=>'Entwicklung','a'=>'die','pl'=>'die Entwicklungen','tr'=>'gelişme','pos'=>'noun','top'=>'allgemein','ex'=>'Die Entwicklung ist positiv.','ext'=>'Gelişme olumlu.'],
        ['g'=>'Umgebung','a'=>'die','pl'=>'die Umgebungen','tr'=>'çevre','pos'=>'noun','top'=>'allgemein','ex'=>'Die Umgebung ist ruhig.','ext'=>'Çevre sakin.'],
        ['g'=>'Gesellschaft','a'=>'die','pl'=>'die Gesellschaften','tr'=>'toplum; şirket','pos'=>'noun','top'=>'allgemein','ex'=>'Die Gesellschaft verändert sich.','ext'=>'Toplum değişiyor.'],
        ['g'=>'Verhalten','a'=>'das','tr'=>'davranış','pos'=>'noun','top'=>'allgemein','ex'=>'Sein Verhalten war korrekt.','ext'=>'Davranışı düzgündü.'],
        ['g'=>'Auswirkung','a'=>'die','pl'=>'die Auswirkungen','tr'=>'etki','pos'=>'noun','top'=>'allgemein','ex'=>'Das hat Auswirkungen auf den Zeitplan.','ext'=>'Bunun zaman planına etkisi var.'],
        ['g'=>'zuverlässig','tr'=>'güvenilir','pos'=>'adjective','top'=>'allgemein','ex'=>'Er ist ein zuverlässiger Kollege.','ext'=>'O güvenilir bir iş arkadaşı.'],
        ['g'=>'selbstständig','tr'=>'bağımsız, kendi başına','pos'=>'adjective','top'=>'allgemein','ex'=>'Sie arbeitet sehr selbstständig.','ext'=>'O çok bağımsız çalışıyor.'],
        ['g'=>'notwendig','tr'=>'gerekli','pos'=>'adjective','top'=>'allgemein','ex'=>'Eine Genehmigung ist notwendig.','ext'=>'Bir izin gerekli.'],
        ['g'=>'möglich','tr'=>'mümkün','pos'=>'adjective','top'=>'allgemein','ex'=>'Ist das möglich?','ext'=>'Bu mümkün mü?'],
        ['g'=>'schwierig','tr'=>'zor','pos'=>'adjective','top'=>'allgemein','ex'=>'Die Situation ist schwierig.','ext'=>'Durum zor.'],
        ['g'=>'deutlich','tr'=>'net, belirgin','pos'=>'adjective','top'=>'allgemein','ex'=>'Sprechen Sie bitte deutlich.','ext'=>'Lütfen net konuşun.'],
        ['g'=>'ausführlich','tr'=>'ayrıntılı','pos'=>'adjective','top'=>'allgemein','ex'=>'Er hat es ausführlich erklärt.','ext'=>'Bunu ayrıntılı açıkladı.'],
        ['g'=>'vermeiden','tr'=>'kaçınmak','pos'=>'verb','top'=>'allgemein','p3'=>'vermeidet','pret'=>'vermied','p2'=>'vermieden','aux'=>'haben','irr'=>1,'case'=>'akkusativ','ex'=>'Wir wollen Fehler vermeiden.','ext'=>'Hatalardan kaçınmak istiyoruz.'],
        ['g'=>'verbessern','tr'=>'iyileştirmek','pos'=>'verb','top'=>'allgemein','p3'=>'verbessert','pret'=>'verbesserte','p2'=>'verbessert','aux'=>'haben','case'=>'akkusativ','ex'=>'Ich möchte mein Deutsch verbessern.','ext'=>'Almancamı geliştirmek istiyorum.'],
        ['g'=>'erreichen','tr'=>'ulaşmak, erişmek','pos'=>'verb','top'=>'allgemein','p3'=>'erreicht','pret'=>'erreichte','p2'=>'erreicht','aux'=>'haben','case'=>'akkusativ','ex'=>'Sie erreichen mich unter dieser Nummer.','ext'=>'Bana bu numaradan ulaşabilirsiniz.'],
        ['g'=>'beantragen','tr'=>'başvuruda bulunmak, talep etmek','pos'=>'verb','top'=>'behoerde','p3'=>'beantragt','pret'=>'beantragte','p2'=>'beantragt','aux'=>'haben','case'=>'akkusativ','ex'=>'Ich möchte einen Aufenthaltstitel beantragen.','ext'=>'Oturma izni başvurusu yapmak istiyorum.'],
        ['g'=>'genehmigen','tr'=>'onaylamak','pos'=>'verb','top'=>'behoerde','p3'=>'genehmigt','pret'=>'genehmigte','p2'=>'genehmigt','aux'=>'haben','case'=>'akkusativ','ex'=>'Der Urlaub wurde genehmigt.','ext'=>'İzin onaylandı.'],
        ['g'=>'bestätigen','tr'=>'teyit etmek','pos'=>'verb','top'=>'behoerde','p3'=>'bestätigt','pret'=>'bestätigte','p2'=>'bestätigt','aux'=>'haben','case'=>'akkusativ','ex'=>'Bitte bestätigen Sie den Termin.','ext'=>'Lütfen randevuyu teyit edin.'],
        ['g'=>'Bescheid','a'=>'der','pl'=>'die Bescheide','tr'=>'resmi bildirim, karar yazısı','pos'=>'noun','top'=>'behoerde','ex'=>'Der Bescheid kommt per Post.','ext'=>'Bildirim postayla geliyor.','note'=>'"Bescheid geben" = haber vermek.'],
        ['g'=>'Aufenthaltstitel','a'=>'der','pl'=>'die Aufenthaltstitel','tr'=>'oturma izni belgesi','pos'=>'noun','top'=>'behoerde','ex'=>'Mein Aufenthaltstitel ist zwei Jahre gültig.','ext'=>'Oturma iznim iki yıl geçerli.','note'=>'Kurallar zamanla değişebilir; güncel bilgiyi ilgili kurumdan doğrula.'],
        ['g'=>'gültig','tr'=>'geçerli','pos'=>'adjective','top'=>'behoerde','ex'=>'Der Ausweis ist bis 2030 gültig.','ext'=>'Kimlik 2030\'a kadar geçerli.'],

        /* --- n-Deklination örnekleri --- */
        ['g'=>'Mensch','a'=>'der','pl'=>'die Menschen','tr'=>'insan','pos'=>'noun','top'=>'n_deklination','ex'=>'Ich kenne diesen Menschen nicht.','ext'=>'Bu insanı tanımıyorum.','note'=>'n-Deklination: Nominativ dışında -en alır.'],
        ['g'=>'Kunde','a'=>'der','pl'=>'die Kunden','tr'=>'müşteri','pos'=>'noun','top'=>'n_deklination','ex'=>'Wir helfen dem Kunden.','ext'=>'Müşteriye yardım ediyoruz.'],
        ['g'=>'Praktikant','a'=>'der','pl'=>'die Praktikanten','tr'=>'stajyer','pos'=>'noun','top'=>'n_deklination','ex'=>'Der Praktikant lernt schnell.','ext'=>'Stajyer hızlı öğreniyor.'],
        ['g'=>'Experte','a'=>'der','pl'=>'die Experten','tr'=>'uzman','pos'=>'noun','top'=>'n_deklination','ex'=>'Fragen Sie einen Experten.','ext'=>'Bir uzmana sorun.'],
    ];
}
