<?php
/**
 * AlmancaPro - A0 kelime hazinesi.
 * Kisaltmalar: g=Almanca, a=artikel, pl=cogul, tr=Turkce, pos=tur, top=konu,
 * ex/ext=ornek cumle, pr=okunus, tip=hatirlama, note=kullanim notu.
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_vocab_a0(): array
{
    return [
        /* --- Selamlaşma ve nezaket --- */
        ['g'=>'Hallo','tr'=>'merhaba','pos'=>'phrase','top'=>'begrussung','pr'=>'ha-LO','ex'=>'Hallo, ich heiße Emre.','ext'=>'Merhaba, benim adım Emre.','note'=>'Her saatte ve hem samimi hem yarı resmi kullanılır.'],
        ['g'=>'Guten Morgen','tr'=>'günaydın','pos'=>'phrase','top'=>'begrussung','pr'=>'GU-ten MOR-gın','ex'=>'Guten Morgen, Herr Wagner!','ext'=>'Günaydın, Bay Wagner!','note'=>'Sabah saat 11 civarına kadar kullanılır.'],
        ['g'=>'Guten Tag','tr'=>'iyi günler','pos'=>'phrase','top'=>'begrussung','pr'=>'GU-ten TAAK','ex'=>'Guten Tag, wie geht es Ihnen?','ext'=>'İyi günler, nasılsınız?','note'=>'Resmi ortamda en güvenli selamlaşmadır.'],
        ['g'=>'Guten Abend','tr'=>'iyi akşamlar','pos'=>'phrase','top'=>'begrussung','pr'=>'GU-ten AA-bınt','ex'=>'Guten Abend, mein Name ist Ayşe Yılmaz.','ext'=>'İyi akşamlar, adım Ayşe Yılmaz.'],
        ['g'=>'Gute Nacht','tr'=>'iyi geceler','pos'=>'phrase','top'=>'begrussung','pr'=>'GU-tı NAHT','ex'=>'Gute Nacht, bis morgen!','ext'=>'İyi geceler, yarın görüşürüz!','note'=>'Yalnızca yatmadan önce vedalaşırken kullanılır, selamlaşmak için değil.'],
        ['g'=>'Tschüss','tr'=>'hoşça kal','pos'=>'phrase','top'=>'begrussung','pr'=>'ÇÜS','ex'=>'Tschüss, bis später!','ext'=>'Hoşça kal, sonra görüşürüz!','note'=>'Samimi vedalaşma. Resmi ortamda "Auf Wiedersehen" tercih edilir.'],
        ['g'=>'Auf Wiedersehen','tr'=>'hoşça kalın, güle güle','pos'=>'phrase','top'=>'begrussung','pr'=>'auf VII-der-zee-ın','ex'=>'Auf Wiedersehen, Frau Meier.','ext'=>'Hoşça kalın, Bayan Meier.','note'=>'Resmi veda. Telefonda "Auf Wiederhören" denir.'],
        ['g'=>'Bis bald','tr'=>'yakında görüşürüz','pos'=>'phrase','top'=>'begrussung','pr'=>'bis BALT','ex'=>'Bis bald!','ext'=>'Yakında görüşürüz!'],
        ['g'=>'Bis morgen','tr'=>'yarın görüşürüz','pos'=>'phrase','top'=>'begrussung','ex'=>'Bis morgen im Büro!','ext'=>'Yarın ofiste görüşürüz!'],
        ['g'=>'bitte','tr'=>'lütfen; rica ederim','pos'=>'particle','top'=>'hoeflichkeit','pr'=>'Bİ-tı','ex'=>'Einen Kaffee, bitte.','ext'=>'Bir kahve, lütfen.','note'=>'Hem "lütfen" hem de teşekküre cevap olarak "rica ederim" anlamına gelir.'],
        ['g'=>'danke','tr'=>'teşekkürler','pos'=>'particle','top'=>'hoeflichkeit','pr'=>'DAN-kı','ex'=>'Danke für die Hilfe!','ext'=>'Yardım için teşekkürler!'],
        ['g'=>'Danke schön','tr'=>'çok teşekkürler','pos'=>'phrase','top'=>'hoeflichkeit','ex'=>'Danke schön, das ist sehr nett.','ext'=>'Çok teşekkürler, bu çok nazik.'],
        ['g'=>'Entschuldigung','a'=>'die','pl'=>'die Entschuldigungen','tr'=>'özür dilerim; affedersiniz','pos'=>'noun','top'=>'hoeflichkeit','pr'=>'ent-ŞUL-di-gung','ex'=>'Entschuldigung, wo ist der Bahnhof?','ext'=>'Affedersiniz, tren garı nerede?','tip'=>'Hem özür hem de birine seslenmek için kullanılır.'],
        ['g'=>'ja','tr'=>'evet','pos'=>'particle','top'=>'hoeflichkeit','ex'=>'Ja, das stimmt.','ext'=>'Evet, doğru.'],
        ['g'=>'nein','tr'=>'hayır','pos'=>'particle','top'=>'hoeflichkeit','ex'=>'Nein, danke.','ext'=>'Hayır, teşekkürler.'],
        ['g'=>'vielleicht','tr'=>'belki','pos'=>'adverb','top'=>'hoeflichkeit','pr'=>'fi-LAYHT','ex'=>'Vielleicht komme ich morgen.','ext'=>'Belki yarın gelirim.'],
        ['g'=>'gern','tr'=>'memnuniyetle, seve seve','pos'=>'adverb','top'=>'hoeflichkeit','ex'=>'Ja, gern!','ext'=>'Evet, memnuniyetle!'],

        /* --- Tanışma --- */
        ['g'=>'Name','a'=>'der','pl'=>'die Namen','tr'=>'isim, ad','pos'=>'noun','top'=>'vorstellung','pr'=>'NA-mı','ex'=>'Mein Name ist Emre.','ext'=>'Benim adım Emre.'],
        ['g'=>'Vorname','a'=>'der','pl'=>'die Vornamen','tr'=>'ön ad','pos'=>'noun','top'=>'vorstellung','ex'=>'Mein Vorname ist Ayşe.','ext'=>'Ön adım Ayşe.'],
        ['g'=>'Nachname','a'=>'der','pl'=>'die Nachnamen','tr'=>'soyad','pos'=>'noun','top'=>'vorstellung','ex'=>'Wie ist Ihr Nachname?','ext'=>'Soyadınız nedir?','sim'=>'Familienname ile aynı anlamdadır.'],
        ['g'=>'Herr','a'=>'der','pl'=>'die Herren','tr'=>'bay','pos'=>'noun','top'=>'vorstellung','ex'=>'Guten Tag, Herr Wagner.','ext'=>'İyi günler, Bay Wagner.','note'=>'Soyadla birlikte kullanılır: Herr Wagner.'],
        ['g'=>'Frau','a'=>'die','pl'=>'die Frauen','tr'=>'bayan; kadın','pos'=>'noun','top'=>'vorstellung','ex'=>'Frau Meier arbeitet hier.','ext'=>'Bayan Meier burada çalışıyor.','note'=>'Hem hitap (bayan) hem de "kadın" anlamına gelir.'],
        ['g'=>'Land','a'=>'das','pl'=>'die Länder','tr'=>'ülke','pos'=>'noun','top'=>'laender','ex'=>'Aus welchem Land kommst du?','ext'=>'Hangi ülkeden geliyorsun?'],
        ['g'=>'Stadt','a'=>'die','pl'=>'die Städte','tr'=>'şehir','pos'=>'noun','top'=>'laender','pr'=>'ŞTAT','ex'=>'Berlin ist eine große Stadt.','ext'=>'Berlin büyük bir şehir.','tip'=>'Baştaki st- "şt" okunur: ŞTAT.'],
        ['g'=>'Deutschland','tr'=>'Almanya','pos'=>'proper_noun','top'=>'laender','ex'=>'Ich arbeite in Deutschland.','ext'=>'Almanya\'da çalışıyorum.','note'=>'Ülke adları özel isimdir, artikel almaz ve büyük harfle yazılır.'],
        ['g'=>'Türkei','tr'=>'Türkiye','pos'=>'proper_noun','top'=>'laender','a'=>'die','ex'=>'Ich komme aus der Türkei.','ext'=>'Türkiye\'den geliyorum.','note'=>'Türkiye artikel alan ender ülkelerdendir: die Türkei. Bu yüzden "aus der Türkei" denir.'],
        ['g'=>'Österreich','tr'=>'Avusturya','pos'=>'proper_noun','top'=>'laender','ex'=>'Wien liegt in Österreich.','ext'=>'Viyana Avusturya\'dadır.'],
        ['g'=>'Schweiz','tr'=>'İsviçre','pos'=>'proper_noun','top'=>'laender','a'=>'die','ex'=>'Er wohnt in der Schweiz.','ext'=>'O İsviçre\'de yaşıyor.','note'=>'İsviçre de artikel alır: die Schweiz.'],
        ['g'=>'Deutsch','tr'=>'Almanca','pos'=>'proper_noun','top'=>'sprache','ex'=>'Ich lerne Deutsch.','ext'=>'Almanca öğreniyorum.'],
        ['g'=>'Türkisch','tr'=>'Türkçe','pos'=>'proper_noun','top'=>'sprache','ex'=>'Meine Muttersprache ist Türkisch.','ext'=>'Ana dilim Türkçe.'],
        ['g'=>'Englisch','tr'=>'İngilizce','pos'=>'proper_noun','top'=>'sprache','ex'=>'Sprechen Sie Englisch?','ext'=>'İngilizce konuşuyor musunuz?'],
        ['g'=>'Türke','a'=>'der','pl'=>'die Türken','tr'=>'Türk (erkek)','pos'=>'noun','top'=>'nationalitaet','ex'=>'Er ist Türke.','ext'=>'O Türk.','note'=>'Milliyet söylerken artikel kullanılmaz: Ich bin Türke.'],
        ['g'=>'Türkin','a'=>'die','pl'=>'die Türkinnen','tr'=>'Türk (kadın)','pos'=>'noun','top'=>'nationalitaet','ex'=>'Sie ist Türkin.','ext'=>'O Türk.','tip'=>'-in eki kadın biçimini yapar: Türke → Türkin.'],
        ['g'=>'Deutsche','a'=>'die','pl'=>'die Deutschen','tr'=>'Alman (kadın)','pos'=>'noun','top'=>'nationalitaet','ex'=>'Sie ist Deutsche.','ext'=>'O Alman.'],
        ['g'=>'Deutscher','a'=>'der','pl'=>'die Deutschen','tr'=>'Alman (erkek)','pos'=>'noun','top'=>'nationalitaet','ex'=>'Er ist Deutscher.','ext'=>'O Alman.'],

        /* --- Meslekler --- */
        ['g'=>'Beruf','a'=>'der','pl'=>'die Berufe','tr'=>'meslek','pos'=>'noun','top'=>'beruf','ex'=>'Was sind Sie von Beruf?','ext'=>'Mesleğiniz nedir?'],
        ['g'=>'Arbeit','a'=>'die','pl'=>'die Arbeiten','tr'=>'iş, çalışma','pos'=>'noun','top'=>'beruf','ex'=>'Ich suche Arbeit.','ext'=>'İş arıyorum.'],
        ['g'=>'Arbeiter','a'=>'der','pl'=>'die Arbeiter','tr'=>'işçi (erkek)','pos'=>'noun','top'=>'beruf','ex'=>'Er ist Arbeiter in einer Fabrik.','ext'=>'O bir fabrikada işçi.'],
        ['g'=>'Lehrer','a'=>'der','pl'=>'die Lehrer','tr'=>'öğretmen (erkek)','pos'=>'noun','top'=>'beruf','ex'=>'Mein Bruder ist Lehrer.','ext'=>'Kardeşim öğretmen.','tip'=>'-er ile biten meslekler genelde der alır ve çoğulda değişmez.'],
        ['g'=>'Lehrerin','a'=>'die','pl'=>'die Lehrerinnen','tr'=>'öğretmen (kadın)','pos'=>'noun','top'=>'beruf','ex'=>'Frau Klein ist Lehrerin.','ext'=>'Bayan Klein öğretmen.'],
        ['g'=>'Arzt','a'=>'der','pl'=>'die Ärzte','tr'=>'doktor (erkek)','pos'=>'noun','top'=>'beruf','pr'=>'ARTST','ex'=>'Der Arzt ist sehr freundlich.','ext'=>'Doktor çok güler yüzlü.'],
        ['g'=>'Ärztin','a'=>'die','pl'=>'die Ärztinnen','tr'=>'doktor (kadın)','pos'=>'noun','top'=>'beruf','ex'=>'Meine Ärztin heißt Frau Bauer.','ext'=>'Doktorumun adı Bayan Bauer.'],
        ['g'=>'Ingenieur','a'=>'der','pl'=>'die Ingenieure','tr'=>'mühendis (erkek)','pos'=>'noun','top'=>'beruf','pr'=>'in-je-NİÖR','ex'=>'Er arbeitet als Ingenieur.','ext'=>'O mühendis olarak çalışıyor.'],
        ['g'=>'Verkäufer','a'=>'der','pl'=>'die Verkäufer','tr'=>'satış görevlisi (erkek)','pos'=>'noun','top'=>'beruf','ex'=>'Der Verkäufer hilft mir.','ext'=>'Satış görevlisi bana yardım ediyor.'],
        ['g'=>'Koch','a'=>'der','pl'=>'die Köche','tr'=>'aşçı (erkek)','pos'=>'noun','top'=>'beruf','ex'=>'Mein Vater ist Koch.','ext'=>'Babam aşçı.'],
        ['g'=>'Fahrer','a'=>'der','pl'=>'die Fahrer','tr'=>'şoför','pos'=>'noun','top'=>'beruf','ex'=>'Er ist Fahrer bei einer Firma.','ext'=>'Bir firmada şoför.'],
        ['g'=>'Student','a'=>'der','pl'=>'die Studenten','tr'=>'öğrenci (üniversite, erkek)','pos'=>'noun','top'=>'beruf','ex'=>'Ich bin Student.','ext'=>'Üniversite öğrencisiyim.','note'=>'n-Deklination grubundadır: den Studenten.'],
        ['g'=>'Krankenschwester','a'=>'die','pl'=>'die Krankenschwestern','tr'=>'hemşire (kadın)','pos'=>'noun','top'=>'beruf','ex'=>'Die Krankenschwester kommt gleich.','ext'=>'Hemşire birazdan geliyor.'],
        ['g'=>'Firma','a'=>'die','pl'=>'die Firmen','tr'=>'firma, şirket','pos'=>'noun','top'=>'beruf','ex'=>'Ich arbeite bei einer Firma in Köln.','ext'=>'Köln\'de bir firmada çalışıyorum.'],

        /* --- Sayılar --- */
        ['g'=>'null','tr'=>'sıfır','pos'=>'numeral','top'=>'zahlen','ex'=>'Null Grad.','ext'=>'Sıfır derece.'],
        ['g'=>'eins','tr'=>'bir','pos'=>'numeral','top'=>'zahlen','ex'=>'Eins, zwei, drei.','ext'=>'Bir, iki, üç.','note'=>'İsimden önce "ein/eine" olur: ein Kaffee.'],
        ['g'=>'zwei','tr'=>'iki','pos'=>'numeral','top'=>'zahlen','ex'=>'Ich habe zwei Kinder.','ext'=>'İki çocuğum var.'],
        ['g'=>'drei','tr'=>'üç','pos'=>'numeral','top'=>'zahlen','ex'=>'Drei Euro, bitte.','ext'=>'Üç euro, lütfen.'],
        ['g'=>'vier','tr'=>'dört','pos'=>'numeral','top'=>'zahlen','pr'=>'FİİR','ex'=>'Vier Wochen Urlaub.','ext'=>'Dört hafta izin.'],
        ['g'=>'fünf','tr'=>'beş','pos'=>'numeral','top'=>'zahlen','ex'=>'Fünf Minuten Pause.','ext'=>'Beş dakika mola.'],
        ['g'=>'sechs','tr'=>'altı','pos'=>'numeral','top'=>'zahlen','pr'=>'ZEKS','ex'=>'Sechs Uhr.','ext'=>'Saat altı.','tip'=>'chs birleşimi "ks" okunur: ZEKS.'],
        ['g'=>'sieben','tr'=>'yedi','pos'=>'numeral','top'=>'zahlen','ex'=>'Sieben Tage.','ext'=>'Yedi gün.'],
        ['g'=>'acht','tr'=>'sekiz','pos'=>'numeral','top'=>'zahlen','pr'=>'AHT','ex'=>'Acht Stunden Arbeit.','ext'=>'Sekiz saat çalışma.'],
        ['g'=>'neun','tr'=>'dokuz','pos'=>'numeral','top'=>'zahlen','pr'=>'NOYN','ex'=>'Neun Euro.','ext'=>'Dokuz euro.'],
        ['g'=>'zehn','tr'=>'on','pos'=>'numeral','top'=>'zahlen','pr'=>'TSEEN','ex'=>'Zehn Minuten.','ext'=>'On dakika.'],
        ['g'=>'elf','tr'=>'on bir','pos'=>'numeral','top'=>'zahlen','ex'=>'Elf Uhr.','ext'=>'Saat on bir.'],
        ['g'=>'zwölf','tr'=>'on iki','pos'=>'numeral','top'=>'zahlen','ex'=>'Zwölf Monate.','ext'=>'On iki ay.'],
        ['g'=>'dreizehn','tr'=>'on üç','pos'=>'numeral','top'=>'zahlen','ex'=>'Dreizehn Personen.','ext'=>'On üç kişi.','tip'=>'13-19: sayı + zehn.'],
        ['g'=>'zwanzig','tr'=>'yirmi','pos'=>'numeral','top'=>'zahlen','ex'=>'Zwanzig Euro.','ext'=>'Yirmi euro.'],
        ['g'=>'einundzwanzig','tr'=>'yirmi bir','pos'=>'numeral','top'=>'zahlen','ex'=>'Einundzwanzig Grad.','ext'=>'Yirmi bir derece.','tip'=>'Almancada birler önce okunur: bir-ve-yirmi.'],
        ['g'=>'dreißig','tr'=>'otuz','pos'=>'numeral','top'=>'zahlen','ex'=>'Dreißig Minuten.','ext'=>'Otuz dakika.','note'=>'30 istisnadır: dreißig (dreizig değil).'],
        ['g'=>'vierzig','tr'=>'kırk','pos'=>'numeral','top'=>'zahlen','ex'=>'Vierzig Stunden pro Woche.','ext'=>'Haftada kırk saat.'],
        ['g'=>'fünfzig','tr'=>'elli','pos'=>'numeral','top'=>'zahlen','ex'=>'Fünfzig Euro.','ext'=>'Elli euro.'],
        ['g'=>'hundert','tr'=>'yüz','pos'=>'numeral','top'=>'zahlen','ex'=>'Hundert Prozent.','ext'=>'Yüzde yüz.'],

        /* --- Temel isimler ve kalıplar --- */
        ['g'=>'Telefonnummer','a'=>'die','pl'=>'die Telefonnummern','tr'=>'telefon numarası','pos'=>'noun','top'=>'kontakt','ex'=>'Wie ist Ihre Telefonnummer?','ext'=>'Telefon numaranız nedir?'],
        ['g'=>'Handy','a'=>'das','pl'=>'die Handys','tr'=>'cep telefonu','pos'=>'noun','top'=>'kontakt','pr'=>'HEN-di','ex'=>'Mein Handy ist kaputt.','ext'=>'Telefonum bozuk.','note'=>'Almancaya özgü bir kelimedir; İngilizcede "mobile phone" denir.'],
        ['g'=>'Adresse','a'=>'die','pl'=>'die Adressen','tr'=>'adres','pos'=>'noun','top'=>'kontakt','ex'=>'Meine Adresse ist Hauptstraße 12.','ext'=>'Adresim Hauptstraße 12.'],
        ['g'=>'Straße','a'=>'die','pl'=>'die Straßen','tr'=>'sokak, cadde','pos'=>'noun','top'=>'kontakt','pr'=>'ŞTRA-sı','ex'=>'Ich wohne in der Bahnhofstraße.','ext'=>'Bahnhofstraße\'de oturuyorum.','tip'=>'ß uzun okunan ünlüden sonra gelir: Straße.'],
        ['g'=>'Postleitzahl','a'=>'die','pl'=>'die Postleitzahlen','tr'=>'posta kodu','pos'=>'noun','top'=>'kontakt','ex'=>'Die Postleitzahl ist 50667.','ext'=>'Posta kodu 50667.','note'=>'Kısaltması PLZ.'],
        ['g'=>'E-Mail','a'=>'die','pl'=>'die E-Mails','tr'=>'e-posta','pos'=>'noun','top'=>'kontakt','ex'=>'Ich schreibe eine E-Mail.','ext'=>'Bir e-posta yazıyorum.'],
        ['g'=>'Jahr','a'=>'das','pl'=>'die Jahre','tr'=>'yıl','pos'=>'noun','top'=>'zeit','pr'=>'YAAR','ex'=>'Ich bin 30 Jahre alt.','ext'=>'30 yaşındayım.','tip'=>'j harfi Almancada "y" okunur: YAAR.'],
        ['g'=>'Tag','a'=>'der','pl'=>'die Tage','tr'=>'gün','pos'=>'noun','top'=>'zeit','ex'=>'Einen schönen Tag!','ext'=>'İyi günler!'],
        ['g'=>'Woche','a'=>'die','pl'=>'die Wochen','tr'=>'hafta','pos'=>'noun','top'=>'zeit','pr'=>'VO-hı','ex'=>'Nächste Woche fange ich an.','ext'=>'Gelecek hafta başlıyorum.','tip'=>'w harfi "v" okunur: VO-hı.'],
        ['g'=>'Monat','a'=>'der','pl'=>'die Monate','tr'=>'ay','pos'=>'noun','top'=>'zeit','ex'=>'Ich bin seit einem Monat hier.','ext'=>'Bir aydır buradayım.'],
        ['g'=>'Uhr','a'=>'die','pl'=>'die Uhren','tr'=>'saat','pos'=>'noun','top'=>'zeit','pr'=>'UUR','ex'=>'Es ist acht Uhr.','ext'=>'Saat sekiz.'],
        ['g'=>'Buchstabe','a'=>'der','pl'=>'die Buchstaben','tr'=>'harf','pos'=>'noun','top'=>'alphabet','ex'=>'Wie schreibt man das? Buchstabe für Buchstabe.','ext'=>'Bu nasıl yazılıyor? Harf harf.','note'=>'n-Deklination grubundadır.'],
        ['g'=>'Wort','a'=>'das','pl'=>'die Wörter','tr'=>'kelime','pos'=>'noun','top'=>'alphabet','ex'=>'Dieses Wort kenne ich nicht.','ext'=>'Bu kelimeyi bilmiyorum.','sim'=>'die Worte = bağlamlı sözler; die Wörter = tek tek kelimeler.'],
        ['g'=>'Frage','a'=>'die','pl'=>'die Fragen','tr'=>'soru','pos'=>'noun','top'=>'alphabet','ex'=>'Ich habe eine Frage.','ext'=>'Bir sorum var.'],
        ['g'=>'Antwort','a'=>'die','pl'=>'die Antworten','tr'=>'cevap','pos'=>'noun','top'=>'alphabet','ex'=>'Die Antwort ist richtig.','ext'=>'Cevap doğru.'],

        /* --- Temel fiiller --- */
        ['g'=>'heißen','tr'=>'adı ... olmak','pos'=>'verb','top'=>'vorstellung','p3'=>'heißt','pret'=>'hieß','p2'=>'geheißen','aux'=>'haben','irr'=>1,'ex'=>'Ich heiße Emre.','ext'=>'Benim adım Emre.','note'=>'Türkçede "adım ...dır" derken Almancada fiil kullanılır: Ich heiße ...'],
        ['g'=>'kommen','tr'=>'gelmek','pos'=>'verb','top'=>'basis','p3'=>'kommt','pret'=>'kam','p2'=>'gekommen','aux'=>'sein','irr'=>1,'ex'=>'Ich komme aus der Türkei.','ext'=>'Türkiye\'den geliyorum.'],
        ['g'=>'wohnen','tr'=>'oturmak, ikamet etmek','pos'=>'verb','top'=>'basis','p3'=>'wohnt','pret'=>'wohnte','p2'=>'gewohnt','aux'=>'haben','ex'=>'Ich wohne in Köln.','ext'=>'Köln\'de oturuyorum.','sim'=>'wohnen = ikamet etmek; leben = yaşamak (daha geniş).'],
        ['g'=>'sein','tr'=>'olmak','pos'=>'verb','top'=>'basis','p3'=>'ist','pret'=>'war','p2'=>'gewesen','aux'=>'sein','irr'=>1,'ex'=>'Ich bin Emre.','ext'=>'Ben Emre\'yim.','note'=>'En düzensiz fiildir; çekimi ezberlenmelidir.'],
        ['g'=>'haben','tr'=>'sahip olmak, var olmak','pos'=>'verb','top'=>'basis','p3'=>'hat','pret'=>'hatte','p2'=>'gehabt','aux'=>'haben','irr'=>1,'ex'=>'Ich habe eine Frage.','ext'=>'Bir sorum var.'],
        ['g'=>'sprechen','tr'=>'konuşmak','pos'=>'verb','top'=>'basis','p3'=>'spricht','pret'=>'sprach','p2'=>'gesprochen','aux'=>'haben','irr'=>1,'ex'=>'Sprechen Sie Deutsch?','ext'=>'Almanca konuşuyor musunuz?','tip'=>'du/er biçiminde e → i olur: sprechen → er spricht.'],
        ['g'=>'verstehen','tr'=>'anlamak','pos'=>'verb','top'=>'basis','p3'=>'versteht','pret'=>'verstand','p2'=>'verstanden','aux'=>'haben','irr'=>1,'ex'=>'Ich verstehe nicht.','ext'=>'Anlamıyorum.','note'=>'ver- ön eki ayrılmaz; Partizip II\'de ge- almaz.'],
        ['g'=>'lernen','tr'=>'öğrenmek','pos'=>'verb','top'=>'basis','p3'=>'lernt','pret'=>'lernte','p2'=>'gelernt','aux'=>'haben','ex'=>'Ich lerne Deutsch.','ext'=>'Almanca öğreniyorum.'],
        ['g'=>'arbeiten','tr'=>'çalışmak','pos'=>'verb','top'=>'beruf','p3'=>'arbeitet','pret'=>'arbeitete','p2'=>'gearbeitet','aux'=>'haben','ex'=>'Ich arbeite in Deutschland.','ext'=>'Almanya\'da çalışıyorum.','tip'=>'Kökü -t ile bittiği için araya e girer: du arbeit-e-st.'],
        ['g'=>'buchstabieren','tr'=>'harf harf söylemek','pos'=>'verb','top'=>'alphabet','p3'=>'buchstabiert','pret'=>'buchstabierte','p2'=>'buchstabiert','aux'=>'haben','ex'=>'Können Sie das buchstabieren?','ext'=>'Bunu harf harf söyleyebilir misiniz?','note'=>'-ieren ile biten fiiller Partizip II\'de ge- almaz.'],
        ['g'=>'wiederholen','tr'=>'tekrar etmek','pos'=>'verb','top'=>'alphabet','p3'=>'wiederholt','pret'=>'wiederholte','p2'=>'wiederholt','aux'=>'haben','ex'=>'Können Sie das bitte wiederholen?','ext'=>'Bunu lütfen tekrar edebilir misiniz?'],

        /* --- Kalıp cümleler --- */
        ['g'=>'Wie geht es Ihnen?','tr'=>'Nasılsınız?','pos'=>'phrase','top'=>'begrussung','ex'=>'Guten Tag! Wie geht es Ihnen?','ext'=>'İyi günler! Nasılsınız?','note'=>'Samimi biçimi: Wie geht es dir?'],
        ['g'=>'Wie geht\'s?','tr'=>'Nasılsın?','pos'=>'phrase','top'=>'begrussung','ex'=>'Hallo Emre, wie geht\'s?','ext'=>'Merhaba Emre, nasılsın?'],
        ['g'=>'Mir geht es gut.','tr'=>'İyiyim.','pos'=>'phrase','top'=>'begrussung','ex'=>'Danke, mir geht es gut.','ext'=>'Teşekkürler, iyiyim.'],
        ['g'=>'Ich verstehe nicht.','tr'=>'Anlamıyorum.','pos'=>'phrase','top'=>'notfall','ex'=>'Entschuldigung, ich verstehe nicht.','ext'=>'Affedersiniz, anlamıyorum.'],
        ['g'=>'Können Sie bitte langsamer sprechen?','tr'=>'Lütfen daha yavaş konuşabilir misiniz?','pos'=>'phrase','top'=>'notfall','ex'=>'Können Sie bitte langsamer sprechen?','ext'=>'Lütfen daha yavaş konuşabilir misiniz?','note'=>'Almanya\'da ilk günlerde en çok işe yarayan cümlelerden biridir.'],
        ['g'=>'Wie bitte?','tr'=>'Efendim? / Pardon?','pos'=>'phrase','top'=>'notfall','ex'=>'Wie bitte? Das habe ich nicht verstanden.','ext'=>'Efendim? Bunu anlamadım.'],
        ['g'=>'Ich spreche nur wenig Deutsch.','tr'=>'Sadece biraz Almanca konuşuyorum.','pos'=>'phrase','top'=>'notfall','ex'=>'Entschuldigung, ich spreche nur wenig Deutsch.','ext'=>'Affedersiniz, sadece biraz Almanca konuşuyorum.'],
        ['g'=>'Woher kommen Sie?','tr'=>'Nereden geliyorsunuz?','pos'=>'phrase','top'=>'vorstellung','ex'=>'Woher kommen Sie? — Aus der Türkei.','ext'=>'Nereden geliyorsunuz? — Türkiye\'den.'],
        ['g'=>'Wo wohnen Sie?','tr'=>'Nerede oturuyorsunuz?','pos'=>'phrase','top'=>'vorstellung','ex'=>'Wo wohnen Sie? — In Köln.','ext'=>'Nerede oturuyorsunuz? — Köln\'de.'],
        ['g'=>'Freut mich.','tr'=>'Memnun oldum.','pos'=>'phrase','top'=>'vorstellung','ex'=>'Ich bin Emre. — Freut mich!','ext'=>'Ben Emre. — Memnun oldum!'],
    ];
}
