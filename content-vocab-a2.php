<?php
/**
 * AlmancaPro - A2 kelime hazinesi.
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_vocab_a2(): array
{
    return [
        /* --- Edatlar --- */
        ['g'=>'mit','tr'=>'ile','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich fahre mit dem Bus.','ext'=>'Otobüsle gidiyorum.','note'=>'mit her zaman Dativ ister. "mit dem Bus", "mit der Bahn".','tip'=>'mit + Dativ birlikte ezberlenir; ikisini ayrı öğrenme.'],
        ['g'=>'nach','tr'=>'-e doğru (şehir/ülke); sonra','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich fahre nach Berlin.','ext'=>'Berlin\'e gidiyorum.','sim'=>'Şehir ve artikelsiz ülkeler için nach; artikelli ülkeler için "in die": in die Türkei.'],
        ['g'=>'aus','tr'=>'-den (içinden), -li','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich komme aus der Türkei.','ext'=>'Türkiye\'denim.'],
        ['g'=>'zu','tr'=>'-e (kişi/kurum)','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich gehe zum Arzt.','ext'=>'Doktora gidiyorum.','note'=>'zu + dem = zum, zu + der = zur.'],
        ['g'=>'von','tr'=>'-den, -in','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Das ist ein Brief von meinem Chef.','ext'=>'Bu amirimden bir mektup.'],
        ['g'=>'bei','tr'=>'yanında, -de (kurum/kişi)','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich arbeite bei Siemens.','ext'=>'Siemens\'te çalışıyorum.'],
        ['g'=>'seit','tr'=>'-den beri','pos'=>'preposition','top'=>'praepositionen','case'=>'dativ','ex'=>'Ich wohne seit einem Jahr hier.','ext'=>'Bir yıldır burada oturuyorum.','note'=>'Türkçedeki "-den beri" ile aynı; Almancada şimdiki zamanla kullanılır.'],
        ['g'=>'für','tr'=>'için','pos'=>'preposition','top'=>'praepositionen','case'=>'akkusativ','ex'=>'Das ist für dich.','ext'=>'Bu senin için.'],
        ['g'=>'ohne','tr'=>'-siz','pos'=>'preposition','top'=>'praepositionen','case'=>'akkusativ','ex'=>'Ich trinke Kaffee ohne Zucker.','ext'=>'Kahveyi şekersiz içiyorum.','note'=>'ohne\'den sonra artikel çoğu zaman düşer: ohne Zucker.'],
        ['g'=>'gegen','tr'=>'karşı; civarında','pos'=>'preposition','top'=>'praepositionen','case'=>'akkusativ','ex'=>'Ich habe etwas gegen Kopfschmerzen.','ext'=>'Baş ağrısına karşı bir şeyim var.'],
        ['g'=>'durch','tr'=>'içinden, aracılığıyla','pos'=>'preposition','top'=>'praepositionen','case'=>'akkusativ','ex'=>'Wir gehen durch den Park.','ext'=>'Parkın içinden geçiyoruz.'],
        ['g'=>'um','tr'=>'-de (saat); etrafında','pos'=>'preposition','top'=>'praepositionen','case'=>'akkusativ','ex'=>'Die Arbeit beginnt um acht Uhr.','ext'=>'İş sekizde başlıyor.'],
        ['g'=>'in','tr'=>'içinde / içine','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Ich bin in der Küche. / Ich gehe in die Küche.','ext'=>'Mutfaktayım. / Mutfağa gidiyorum.','note'=>'Wechselpräposition: Wo? → Dativ, Wohin? → Akkusativ.'],
        ['g'=>'an','tr'=>'-de/-e (dikey yüzey, kenar)','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Das Bild hängt an der Wand.','ext'=>'Resim duvarda asılı.'],
        ['g'=>'auf','tr'=>'üstünde / üstüne','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Das Buch liegt auf dem Tisch.','ext'=>'Kitap masanın üstünde.'],
        ['g'=>'unter','tr'=>'altında / altına','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Die Tasche steht unter dem Stuhl.','ext'=>'Çanta sandalyenin altında.'],
        ['g'=>'über','tr'=>'üzerinde (temassız); hakkında','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Die Lampe hängt über dem Tisch.','ext'=>'Lamba masanın üzerinde asılı.'],
        ['g'=>'vor','tr'=>'önünde / önüne; önce','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Ich warte vor dem Haus.','ext'=>'Evin önünde bekliyorum.'],
        ['g'=>'hinter','tr'=>'arkasında / arkasına','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Der Garten ist hinter dem Haus.','ext'=>'Bahçe evin arkasında.'],
        ['g'=>'neben','tr'=>'yanında / yanına','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Die Apotheke ist neben der Bank.','ext'=>'Eczane bankanın yanında.'],
        ['g'=>'zwischen','tr'=>'arasında / arasına','pos'=>'preposition','top'=>'wechselpraepositionen','ex'=>'Der Kiosk ist zwischen der Post und der Bank.','ext'=>'Büfe postane ile banka arasında.'],

        /* --- Bağlaçlar --- */
        ['g'=>'weil','tr'=>'çünkü','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Ich lerne Deutsch, weil ich in Deutschland arbeite.','ext'=>'Almanca öğreniyorum çünkü Almanya\'da çalışıyorum.','note'=>'weil yan cümle kurar; fiil sona gider.','tip'=>'weil = fiil sonda, denn = fiil normal yerde. Anlamları aynıdır.'],
        ['g'=>'dass','tr'=>'-diğini, ki','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Ich glaube, dass er heute kommt.','ext'=>'Sanırım bugün geliyor.','note'=>'Türkçede "-diğini" ekiyle karşılanır; Almancada ayrı bir bağlaçtır.'],
        ['g'=>'wenn','tr'=>'eğer; -diğinde','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Wenn ich Zeit habe, lerne ich Deutsch.','ext'=>'Vaktim olduğunda Almanca öğreniyorum.','sim'=>'wenn = tekrarlanan/gelecek, als = geçmişte tek olay.'],
        ['g'=>'als','tr'=>'-diğinde (geçmişte bir kez); olarak','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Als ich nach Deutschland kam, sprach ich kein Deutsch.','ext'=>'Almanya\'ya geldiğimde hiç Almanca bilmiyordum.'],
        ['g'=>'obwohl','tr'=>'-e rağmen','pos'=>'conjunction','top'=>'nebensatz','ex'=>'Obwohl es regnet, gehe ich zur Arbeit.','ext'=>'Yağmur yağmasına rağmen işe gidiyorum.'],
        ['g'=>'denn','tr'=>'çünkü','pos'=>'conjunction','top'=>'konnektoren','ex'=>'Ich bleibe zu Hause, denn ich bin krank.','ext'=>'Evde kalıyorum çünkü hastayım.','note'=>'denn ana cümle bağlacıdır, fiil ikinci sırada kalır.'],
        ['g'=>'sondern','tr'=>'aksine, -değil ... ama','pos'=>'conjunction','top'=>'konnektoren','ex'=>'Ich komme nicht aus Berlin, sondern aus Köln.','ext'=>'Berlin\'den değil, Köln\'den geliyorum.','note'=>'Yalnızca olumsuz bir cümleden sonra kullanılır.'],
        ['g'=>'deshalb','tr'=>'bu yüzden','pos'=>'adverb','top'=>'konnektoren','ex'=>'Ich bin krank, deshalb bleibe ich zu Hause.','ext'=>'Hastayım, bu yüzden evde kalıyorum.','note'=>'deshalb\'den sonra fiil hemen gelir (V2 kuralı).'],
        ['g'=>'trotzdem','tr'=>'yine de','pos'=>'adverb','top'=>'konnektoren','ex'=>'Es regnet, trotzdem gehe ich raus.','ext'=>'Yağmur yağıyor, yine de dışarı çıkıyorum.'],

        /* --- Zamirler --- */
        ['g'=>'mir','tr'=>'bana','pos'=>'pronoun','top'=>'dativ','ex'=>'Kannst du mir helfen?','ext'=>'Bana yardım edebilir misin?'],
        ['g'=>'dir','tr'=>'sana','pos'=>'pronoun','top'=>'dativ','ex'=>'Ich gebe dir das Formular.','ext'=>'Formu sana veriyorum.'],
        ['g'=>'ihm','tr'=>'ona (erkek/nötr)','pos'=>'pronoun','top'=>'dativ','ex'=>'Ich schreibe ihm eine E-Mail.','ext'=>'Ona bir e-posta yazıyorum.'],
        ['g'=>'uns','tr'=>'bize; bizi','pos'=>'pronoun','top'=>'dativ','ex'=>'Er hilft uns.','ext'=>'O bize yardım ediyor.'],
        ['g'=>'sich','tr'=>'kendini/kendine','pos'=>'pronoun','top'=>'reflexiv','ex'=>'Er wäscht sich.','ext'=>'O yıkanıyor.'],

        /* --- Fiiller --- */
        ['g'=>'sich freuen','tr'=>'sevinmek','pos'=>'verb','top'=>'reflexiv','p3'=>'freut sich','pret'=>'freute sich','p2'=>'gefreut','aux'=>'haben','refl'=>1,'prep'=>'auf + Akkusativ','ex'=>'Ich freue mich auf den Urlaub.','ext'=>'Tatili dört gözle bekliyorum.'],
        ['g'=>'sich anmelden','tr'=>'kayıt yaptırmak','pos'=>'verb','top'=>'behoerde','p3'=>'meldet sich an','pret'=>'meldete sich an','p2'=>'angemeldet','aux'=>'haben','refl'=>1,'sep'=>'an','ex'=>'Ich muss mich beim Bürgeramt anmelden.','ext'=>'Nüfus dairesine kayıt yaptırmam gerekiyor.','note'=>'Almanya\'da taşındıktan sonra Anmeldung yasal bir yükümlülüktür.'],
        ['g'=>'sich bewerben','tr'=>'başvurmak','pos'=>'verb','top'=>'arbeit','p3'=>'bewirbt sich','pret'=>'bewarb sich','p2'=>'beworben','aux'=>'haben','refl'=>1,'irr'=>1,'prep'=>'um + Akkusativ','ex'=>'Ich bewerbe mich um eine Stelle.','ext'=>'Bir pozisyona başvuruyorum.'],
        ['g'=>'sich vorstellen','tr'=>'kendini tanıtmak; hayal etmek','pos'=>'verb','top'=>'arbeit','p3'=>'stellt sich vor','pret'=>'stellte sich vor','p2'=>'vorgestellt','aux'=>'haben','refl'=>1,'sep'=>'vor','ex'=>'Darf ich mich vorstellen?','ext'=>'Kendimi tanıtabilir miyim?'],
        ['g'=>'bleiben','tr'=>'kalmak','pos'=>'verb','top'=>'basis','p3'=>'bleibt','pret'=>'blieb','p2'=>'geblieben','aux'=>'sein','irr'=>1,'ex'=>'Ich bleibe zu Hause.','ext'=>'Evde kalıyorum.','note'=>'Hareket olmasa da sein alan ender fiillerdendir.'],
        ['g'=>'werden','tr'=>'olmak (dönüşmek)','pos'=>'verb','top'=>'basis','p3'=>'wird','pret'=>'wurde','p2'=>'geworden','aux'=>'sein','irr'=>1,'ex'=>'Ich werde Ingenieur.','ext'=>'Mühendis olacağım.'],
        ['g'=>'bringen','tr'=>'getirmek','pos'=>'verb','top'=>'basis','p3'=>'bringt','pret'=>'brachte','p2'=>'gebracht','aux'=>'haben','irr'=>1,'case'=>'akkusativ','ex'=>'Bringen Sie bitte die Unterlagen mit.','ext'=>'Lütfen belgeleri getirin.'],
        ['g'=>'mitbringen','tr'=>'yanında getirmek','pos'=>'verb','top'=>'basis','p3'=>'bringt mit','pret'=>'brachte mit','p2'=>'mitgebracht','aux'=>'haben','irr'=>1,'sep'=>'mit','ex'=>'Ich bringe meinen Ausweis mit.','ext'=>'Kimliğimi yanımda getiriyorum.'],
        ['g'=>'anrufen','tr'=>'telefon etmek','pos'=>'verb','top'=>'telefon','p3'=>'ruft an','pret'=>'rief an','p2'=>'angerufen','aux'=>'haben','irr'=>1,'sep'=>'an','case'=>'akkusativ','ex'=>'Ich rufe den Arzt an.','ext'=>'Doktoru arıyorum.','note'=>'anrufen Akkusativ ister: jemanden anrufen.'],
        ['g'=>'erklären','tr'=>'açıklamak','pos'=>'verb','top'=>'kommunikation','p3'=>'erklärt','pret'=>'erklärte','p2'=>'erklärt','aux'=>'haben','ex'=>'Können Sie das bitte erklären?','ext'=>'Bunu açıklayabilir misiniz?'],
        ['g'=>'erzählen','tr'=>'anlatmak','pos'=>'verb','top'=>'kommunikation','p3'=>'erzählt','pret'=>'erzählte','p2'=>'erzählt','aux'=>'haben','ex'=>'Erzähl mir von deiner Arbeit.','ext'=>'Bana işinden bahset.'],
        ['g'=>'vergessen','tr'=>'unutmak','pos'=>'verb','top'=>'basis','p3'=>'vergisst','pret'=>'vergaß','p2'=>'vergessen','aux'=>'haben','irr'=>1,'case'=>'akkusativ','ex'=>'Ich habe den Termin vergessen.','ext'=>'Randevuyu unuttum.','note'=>'ver- ön eki nedeniyle Partizip II\'de ge- yoktur.'],
        ['g'=>'kündigen','tr'=>'işten ayrılmak, fesih bildirmek','pos'=>'verb','top'=>'arbeit','p3'=>'kündigt','pret'=>'kündigte','p2'=>'gekündigt','aux'=>'haben','ex'=>'Ich habe die Wohnung gekündigt.','ext'=>'Ev sözleşmesini feshettim.'],
        ['g'=>'umziehen','tr'=>'taşınmak','pos'=>'verb','top'=>'wohnen','p3'=>'zieht um','pret'=>'zog um','p2'=>'umgezogen','aux'=>'sein','irr'=>1,'sep'=>'um','ex'=>'Wir ziehen nächsten Monat um.','ext'=>'Gelecek ay taşınıyoruz.'],
        ['g'=>'mieten','tr'=>'kiralamak (kiracı olarak)','pos'=>'verb','top'=>'wohnen','p3'=>'mietet','pret'=>'mietete','p2'=>'gemietet','aux'=>'haben','case'=>'akkusativ','ex'=>'Wir mieten eine Wohnung in Köln.','ext'=>'Köln\'de bir daire kiralıyoruz.','sim'=>'mieten = kiracı; vermieten = kiraya veren.'],

        /* --- Ev / kiralama --- */
        ['g'=>'Vermieter','a'=>'der','pl'=>'die Vermieter','tr'=>'ev sahibi','pos'=>'noun','top'=>'wohnen','ex'=>'Der Vermieter kommt morgen.','ext'=>'Ev sahibi yarın geliyor.'],
        ['g'=>'Mietvertrag','a'=>'der','pl'=>'die Mietverträge','tr'=>'kira sözleşmesi','pos'=>'noun','top'=>'wohnen','ex'=>'Bitte lesen Sie den Mietvertrag genau.','ext'=>'Lütfen kira sözleşmesini dikkatle okuyun.'],
        ['g'=>'Kaution','a'=>'die','pl'=>'die Kautionen','tr'=>'depozito','pos'=>'noun','top'=>'wohnen','ex'=>'Die Kaution beträgt drei Kaltmieten.','ext'=>'Depozito üç aylık çıplak kiradır.'],
        ['g'=>'Nebenkosten','a'=>'die','pl'=>'die Nebenkosten','tr'=>'ek giderler (aidat, ısınma)','pos'=>'noun','top'=>'wohnen','ex'=>'Die Nebenkosten sind nicht inbegriffen.','ext'=>'Ek giderler dahil değil.','note'=>'Sadece çoğul kullanılır.'],
        ['g'=>'Hausmeister','a'=>'der','pl'=>'die Hausmeister','tr'=>'bina görevlisi','pos'=>'noun','top'=>'wohnen','ex'=>'Der Hausmeister repariert die Heizung.','ext'=>'Bina görevlisi kaloriferi tamir ediyor.'],
        ['g'=>'Heizung','a'=>'die','pl'=>'die Heizungen','tr'=>'kalorifer','pos'=>'noun','top'=>'wohnen','ex'=>'Die Heizung funktioniert nicht.','ext'=>'Kalorifer çalışmıyor.'],

        /* --- Resmi kurumlar --- */
        ['g'=>'Bürgeramt','a'=>'das','pl'=>'die Bürgerämter','tr'=>'nüfus/vatandaş dairesi','pos'=>'noun','top'=>'behoerde','ex'=>'Ich habe einen Termin beim Bürgeramt.','ext'=>'Nüfus dairesinde randevum var.'],
        ['g'=>'Anmeldung','a'=>'die','pl'=>'die Anmeldungen','tr'=>'ikamet kaydı','pos'=>'noun','top'=>'behoerde','ex'=>'Die Anmeldung ist Pflicht.','ext'=>'İkamet kaydı zorunludur.'],
        ['g'=>'Ausweis','a'=>'der','pl'=>'die Ausweise','tr'=>'kimlik','pos'=>'noun','top'=>'behoerde','ex'=>'Bitte zeigen Sie Ihren Ausweis.','ext'=>'Lütfen kimliğinizi gösterin.'],
        ['g'=>'Formular','a'=>'das','pl'=>'die Formulare','tr'=>'form','pos'=>'noun','top'=>'behoerde','ex'=>'Füllen Sie bitte das Formular aus.','ext'=>'Lütfen formu doldurun.'],
        ['g'=>'Unterlagen','a'=>'die','pl'=>'die Unterlagen','tr'=>'belgeler','pos'=>'noun','top'=>'behoerde','ex'=>'Bringen Sie alle Unterlagen mit.','ext'=>'Bütün belgeleri getirin.','note'=>'Genellikle çoğul kullanılır.'],
        ['g'=>'Krankenkasse','a'=>'die','pl'=>'die Krankenkassen','tr'=>'sağlık sigortası kurumu','pos'=>'noun','top'=>'gesundheit','ex'=>'Bei welcher Krankenkasse sind Sie?','ext'=>'Hangi sağlık sigortasındasınız?','note'=>'Almanya\'da sağlık sigortası zorunludur.'],
        ['g'=>'Versicherung','a'=>'die','pl'=>'die Versicherungen','tr'=>'sigorta','pos'=>'noun','top'=>'behoerde','ex'=>'Ich brauche eine Versicherung.','ext'=>'Bir sigortaya ihtiyacım var.'],
        ['g'=>'Bankkonto','a'=>'das','pl'=>'die Bankkonten','tr'=>'banka hesabı','pos'=>'noun','top'=>'bank','ex'=>'Ich möchte ein Bankkonto eröffnen.','ext'=>'Bir banka hesabı açmak istiyorum.'],
        ['g'=>'Überweisung','a'=>'die','pl'=>'die Überweisungen','tr'=>'havale','pos'=>'noun','top'=>'bank','ex'=>'Die Überweisung dauert einen Tag.','ext'=>'Havale bir gün sürüyor.'],
        ['g'=>'Rechnung bezahlen','tr'=>'faturayı ödemek','pos'=>'phrase','top'=>'bank','ex'=>'Ich muss die Rechnung bezahlen.','ext'=>'Faturayı ödemem gerekiyor.'],

        /* --- İş Almancası --- */
        ['g'=>'Arbeitsvertrag','a'=>'der','pl'=>'die Arbeitsverträge','tr'=>'iş sözleşmesi','pos'=>'noun','top'=>'arbeit','ex'=>'Im Arbeitsvertrag steht die Arbeitszeit.','ext'=>'İş sözleşmesinde çalışma saati yazıyor.'],
        ['g'=>'Krankmeldung','a'=>'die','pl'=>'die Krankmeldungen','tr'=>'hastalık bildirimi','pos'=>'noun','top'=>'arbeit','ex'=>'Ich schicke die Krankmeldung per E-Mail.','ext'=>'Hastalık bildirimini e-postayla gönderiyorum.'],
        ['g'=>'Besprechung','a'=>'die','pl'=>'die Besprechungen','tr'=>'toplantı','pos'=>'noun','top'=>'arbeit','ex'=>'Die Besprechung beginnt um zehn.','ext'=>'Toplantı onda başlıyor.'],
        ['g'=>'Abteilung','a'=>'die','pl'=>'die Abteilungen','tr'=>'departman','pos'=>'noun','top'=>'arbeit','ex'=>'Ich arbeite in der Abteilung Logistik.','ext'=>'Lojistik departmanında çalışıyorum.'],
        ['g'=>'Gehalt','a'=>'das','pl'=>'die Gehälter','tr'=>'maaş','pos'=>'noun','top'=>'arbeit','ex'=>'Das Gehalt kommt am Monatsende.','ext'=>'Maaş ay sonunda geliyor.'],
        ['g'=>'Lohn','a'=>'der','pl'=>'die Löhne','tr'=>'ücret (saatlik/işçi)','pos'=>'noun','top'=>'arbeit','ex'=>'Der Lohn wird stündlich berechnet.','ext'=>'Ücret saatlik hesaplanıyor.','sim'=>'Gehalt genelde aylık maaş, Lohn saat/gün üzerinden ücrettir.'],
        ['g'=>'Überstunden','a'=>'die','pl'=>'die Überstunden','tr'=>'fazla mesai','pos'=>'noun','top'=>'arbeit','ex'=>'Ich habe zehn Überstunden.','ext'=>'On saat fazla mesaim var.'],
        ['g'=>'Sicherheit','a'=>'die','pl'=>'die Sicherheiten','tr'=>'güvenlik','pos'=>'noun','top'=>'arbeit','ex'=>'Sicherheit hat Vorrang.','ext'=>'Güvenlik önceliklidir.'],
        ['g'=>'Anweisung','a'=>'die','pl'=>'die Anweisungen','tr'=>'talimat','pos'=>'noun','top'=>'arbeit','ex'=>'Bitte befolgen Sie die Anweisungen.','ext'=>'Lütfen talimatlara uyun.'],

        /* --- Seyahat --- */
        ['g'=>'Reise','a'=>'die','pl'=>'die Reisen','tr'=>'yolculuk','pos'=>'noun','top'=>'reisen','ex'=>'Die Reise dauert drei Stunden.','ext'=>'Yolculuk üç saat sürüyor.'],
        ['g'=>'Flughafen','a'=>'der','pl'=>'die Flughäfen','tr'=>'havalimanı','pos'=>'noun','top'=>'reisen','ex'=>'Der Flughafen ist weit weg.','ext'=>'Havalimanı uzakta.'],
        ['g'=>'Gepäck','a'=>'das','tr'=>'bagaj','pos'=>'noun','top'=>'reisen','ex'=>'Mein Gepäck ist schwer.','ext'=>'Bagajım ağır.'],
        ['g'=>'Verspätung','a'=>'die','pl'=>'die Verspätungen','tr'=>'gecikme','pos'=>'noun','top'=>'reisen','ex'=>'Der Zug hat 20 Minuten Verspätung.','ext'=>'Tren 20 dakika gecikmeli.'],
        ['g'=>'Ausflug','a'=>'der','pl'=>'die Ausflüge','tr'=>'gezi','pos'=>'noun','top'=>'reisen','ex'=>'Wir machen einen Ausflug nach Bonn.','ext'=>'Bonn\'a bir gezi yapıyoruz.'],

        /* --- Sıfat karşılaştırma --- */
        ['g'=>'besser','tr'=>'daha iyi','pos'=>'adjective','top'=>'komparativ','ex'=>'Heute geht es mir besser.','ext'=>'Bugün daha iyiyim.','note'=>'gut → besser → am besten (düzensiz).'],
        ['g'=>'am besten','tr'=>'en iyi','pos'=>'adjective','top'=>'komparativ','ex'=>'Das ist am besten.','ext'=>'Bu en iyisi.'],
        ['g'=>'größer','tr'=>'daha büyük','pos'=>'adjective','top'=>'komparativ','ex'=>'Die neue Wohnung ist größer.','ext'=>'Yeni daire daha büyük.','tip'=>'Tek heceli sıfatlar çoğu zaman umlaut alır: groß → größer.'],
        ['g'=>'mehr','tr'=>'daha çok','pos'=>'adverb','top'=>'komparativ','ex'=>'Ich brauche mehr Zeit.','ext'=>'Daha çok vakte ihtiyacım var.'],
        ['g'=>'weniger','tr'=>'daha az','pos'=>'adverb','top'=>'komparativ','ex'=>'Heute habe ich weniger Arbeit.','ext'=>'Bugün daha az işim var.'],
        ['g'=>'genauso','tr'=>'tam olarak aynı şekilde','pos'=>'adverb','top'=>'komparativ','ex'=>'Er arbeitet genauso schnell wie ich.','ext'=>'O da benim kadar hızlı çalışıyor.','note'=>'Eşitlik: (genau)so ... wie.'],

        /* --- Sosyal hayat --- */
        ['g'=>'Nachbar','a'=>'der','pl'=>'die Nachbarn','tr'=>'komşu','pos'=>'noun','top'=>'sozial','ex'=>'Mein Nachbar ist sehr nett.','ext'=>'Komşum çok kibar.','note'=>'n-Deklination: den Nachbarn.'],
        ['g'=>'Verein','a'=>'der','pl'=>'die Vereine','tr'=>'dernek, kulüp','pos'=>'noun','top'=>'sozial','ex'=>'Ich bin in einem Sportverein.','ext'=>'Bir spor kulübündeyim.'],
        ['g'=>'einladen','tr'=>'davet etmek','pos'=>'verb','top'=>'sozial','p3'=>'lädt ein','pret'=>'lud ein','p2'=>'eingeladen','aux'=>'haben','irr'=>1,'sep'=>'ein','case'=>'akkusativ','ex'=>'Ich lade dich zum Essen ein.','ext'=>'Seni yemeğe davet ediyorum.'],
        ['g'=>'absagen','tr'=>'iptal etmek','pos'=>'verb','top'=>'sozial','p3'=>'sagt ab','pret'=>'sagte ab','p2'=>'abgesagt','aux'=>'haben','sep'=>'ab','ex'=>'Ich muss den Termin absagen.','ext'=>'Randevuyu iptal etmem gerekiyor.'],
        ['g'=>'sich verabreden','tr'=>'sözleşmek, buluşma ayarlamak','pos'=>'verb','top'=>'sozial','p3'=>'verabredet sich','pret'=>'verabredete sich','p2'=>'verabredet','aux'=>'haben','refl'=>1,'ex'=>'Wir verabreden uns für Samstag.','ext'=>'Cumartesi için sözleşiyoruz.'],
    ];
}
