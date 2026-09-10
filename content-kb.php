<?php
/**
 * AlmancaPro - Bilgi tabani (AI kapaliyken "Ogretmene Sor" bunu kullanir).
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_knowledge_base(): array
{
    return [
        [
            'slug' => 'neden-der-den-oldu',
            'q' => 'Neden "der" yerine "den" oldu?',
            'kw' => 'der den akkusativ nesne hal artikel degisim belirtme',
            'level' => 'A1',
            'a' => "Çünkü isim cümlede NESNE konumuna girdi.\n\nAlmancada eril isimler (der ile başlayanlar) nesne olduğunda artikel \"den\" olur. Buna Akkusativ (belirtme hali) denir.\n\nDer Kaffee ist heiß. → Burada kahve ÖZNE (Nominativ), artikel \"der\" kalır.\nIch trinke den Kaffee. → Burada kahve NESNE (Akkusativ), artikel \"den\" olur.\n\nTürkçedeki karşılığı \"-i\" ekidir: kahve → kahveyi.\n\nÖnemli: Akkusativ'de SADECE eril artikel değişir.\nder → den\ndie → die (değişmez)\ndas → das (değişmez)\ndie (çoğul) → die (değişmez)\n\nKontrol yöntemi: Cümleye \"Wen?\" (kimi?) veya \"Was?\" (neyi?) sor. Cevap Akkusativ'dir.",
        ],
        [
            'slug' => 'mit-neden-dativ',
            'q' => 'mit neden Dativ istiyor?',
            'kw' => 'mit dativ edat praeposition neden hal ile',
            'level' => 'A2',
            'a' => "mit edatının Dativ istemesinin mantıksal bir açıklaması yoktur; bu, Almancanın sabit bir kuralıdır ve ezberlenir.\n\nmit + Dativ:\nIch fahre mit dem Bus. (der Bus → dem Bus)\nIch spreche mit der Kollegin. (die Kollegin → der Kollegin)\nIch arbeite mit dem Team. (das Team → dem Team)\n\nAynı grupta olan ve HER ZAMAN Dativ isteyen diğer edatlar:\nmit, nach, aus, zu, von, bei, seit, gegenüber\n\nÖneri: Edatı hiçbir zaman yalnız öğrenme. Defterine \"mit\" değil \"mit + Dativ\" yaz ve yanına örnek koy. Edatı hal bilgisiyle birlikte ezberlemek, sonradan tek tek düzeltmeye çalışmaktan çok daha hızlıdır.",
        ],
        [
            'slug' => 'zu-nach-in-farki',
            'q' => 'zu, nach ve in arasındaki fark nedir?',
            'kw' => 'zu nach in fark hedef yon gitmek nereye wohin',
            'level' => 'A2',
            'a' => "Üçü de Türkçede \"-e/-a\" olarak çevrilir ama Almancada hedefin TÜRÜNE göre seçilir.\n\nzu + Dativ → kişi veya kurum\nIch gehe zum Arzt. (doktora)\nIch gehe zur Bank. (bankaya)\nIch gehe zu meinem Freund. (arkadaşıma)\n\nnach → artikelsiz şehir/ülke ve yönler\nIch fahre nach Berlin.\nIch fliege nach Deutschland.\nGehen Sie nach links.\n\nin + Akkusativ → bir binanın veya alanın İÇİNE\nIch gehe in den Supermarkt. (marketin içine)\nIch fahre in die Stadt. (şehrin içine)\nIch fahre in die Türkei. (artikelli ülke)\n\nÖzel kalıplar:\nnach Hause = eve doğru\nzu Hause = evde\n\nKısayol: Hedef bir insan veya kurum mu? → zu. Bir şehir/ülke adı mı (artikelsiz)? → nach. İçine giriyor musun? → in + Akkusativ.",
        ],
        [
            'slug' => 'neden-fiil-sonda',
            'q' => 'Neden bazı cümlelerde fiil sonda?',
            'kw' => 'fiil sonda yan cumle nebensatz weil dass wenn kelime sirasi',
            'level' => 'A2',
            'a' => "Çünkü o cümle bir YAN CÜMLEDİR.\n\nAlmancada iki farklı kelime sırası vardır:\n\n1) ANA CÜMLE: çekimli fiil İKİNCİ pozisyonda\nIch lerne Deutsch.\nHeute lerne ich Deutsch.\n\n2) YAN CÜMLE: çekimli fiil SONDA\n..., weil ich Deutsch lerne.\n..., dass ich Deutsch lerne.\n\nFiili sona gönderen bağlaçlar:\nweil, dass, wenn, als, obwohl, damit, bevor, nachdem, während, seitdem, bis, sobald, falls, ob\n\nFiili sona GÖNDERMEYEN bağlaçlar (ana cümle bağlaçları):\nund, aber, oder, denn, sondern\n\nZarf bağlaçları (fiil hemen arkasından gelir):\ndeshalb, trotzdem, dann, danach\n\nİyi haber: Türkçede fiil zaten cümlenin sonundadır. Yani yan cümle yapısı sana yabancı değil; zor olan, aynı dilde iki farklı düzen arasında doğru seçimi yapmaktır.",
        ],
        [
            'slug' => 'kein-mi-nicht-mi',
            'q' => 'kein mi nicht mi kullanmalıyım?',
            'kw' => 'kein nicht olumsuz negation fark hangisi degil yok',
            'level' => 'A1',
            'a' => "Basit bir kural var:\n\nkein kullan:\n- İsmin önünde ein/eine varsa\n- İsmin önünde hiç artikel yoksa (çoğul veya sayılamayan)\n\nIch habe ein Auto. → Ich habe kein Auto.\nIch habe Zeit. → Ich habe keine Zeit.\nIch habe Kinder. → Ich habe keine Kinder.\n\nnicht kullan:\n- İsmin önünde der/die/das veya iyelik (mein, dein) varsa\n- Fiil, sıfat veya zarf olumsuzlanıyorsa\n\nIch kenne den Chef. → Ich kenne den Chef nicht.\nDas ist mein Auto. → Das ist nicht mein Auto.\nIch arbeite heute. → Ich arbeite heute nicht.\nDas ist gut. → Das ist nicht gut.\n\nkein çekimi ein gibidir:\nkein Chef · keine Frage · kein Auto · keine Kinder\nAkkusativ: keinen Chef · keine Frage · kein Auto · keine Kinder\n\nEn sık hata: \"Ich habe nicht Zeit\". Zeit artikelsiz olduğu için kein gerekir: \"Ich habe keine Zeit.\"",
        ],
        [
            'slug' => 'haben-mi-sein-mi-perfekt',
            'q' => 'Perfekt\'te haben mi sein mi kullanacağım?',
            'kw' => 'perfekt haben sein yardimci fiil gecmis zaman hangisi',
            'level' => 'A2',
            'a' => "sein kullanılır:\n\n1. YER DEĞİŞTİRME fiilleri: gehen, fahren, kommen, fliegen, laufen, reisen, umziehen, ankommen\n   Ich bin nach Berlin gefahren.\n\n2. DURUM DEĞİŞİMİ fiilleri: aufstehen, einschlafen, aufwachen, werden, sterben, wachsen\n   Ich bin um sechs Uhr aufgestanden.\n\n3. Üç istisna: sein, bleiben, passieren\n   Ich bin zu Hause geblieben.\n\nhaben kullanılır:\n- Diğer bütün fiiller\n- Akkusativ nesne alan bütün fiiller\n   Ich habe das Formular ausgefüllt.\n\nDikkat: Aynı fiil ikisini de alabilir.\nIch bin nach Köln gefahren. (yer değiştirme → sein)\nIch habe das Auto gefahren. (nesne var → haben)\n\nKısayol: \"Bu fiil beni bir yerden bir yere mi taşıyor ya da halimi mi değiştiriyor?\" Evet ise sein.",
        ],
        [
            'slug' => 'artikel-nasil-ezberlenir',
            'q' => 'der/die/das artikellerini nasıl ezberleyebilirim?',
            'kw' => 'artikel ezber der die das cinsiyet nasil ogrenirim yontem',
            'level' => 'A1',
            'a' => "Kötü haber: Artikelin çoğu için mantıksal bir kural yoktur, ezberlenir.\nİyi haber: Ezberi kolaylaştıran güvenilir eğilimler var.\n\nNeredeyse istisnasız kurallar:\n-ung, -heit, -keit, -schaft, -ion, -tät, -ik → die\n  die Wohnung, die Freiheit, die Möglichkeit, die Nation\n-chen, -lein → das\n  das Mädchen, das Brötchen\n-er (kişi/alet) → der\n  der Lehrer, der Fahrer, der Computer\n-ismus, -ling, -ig → der\n\nGüçlü eğilimler:\n-e ile bitenlerin çoğu → die (die Lampe, die Frage)\nGün, ay, mevsim, hava olayı → der (der Montag, der Sommer, der Regen)\nBileşik isimler → SON parçanın artikelini alır (das Zimmer → das Schlafzimmer)\n\nEn önemli yöntem: Kelimeyi hiçbir zaman yalnız söyleme.\nYanlış: \"Tisch = masa\"\nDoğru: \"der Tisch – die Tische = masa\"\n\nAlmancaPro bu yüzden artikeli ayrı test eder ve artikeli bilmeden kelimeyi öğrenilmiş saymaz.",
        ],
        [
            'slug' => 'wo-wohin-farki',
            'q' => 'Wo ve Wohin arasındaki fark nedir?',
            'kw' => 'wo wohin woher fark nerede nereye nereden hareket',
            'level' => 'A2',
            'a' => "Üç ayrı soru kelimesi vardır ve karıştırılmaz:\n\nWo? = Nerede? (durum, hareket yok)\n  Wo bist du? — Ich bin in der Küche.\n\nWohin? = Nereye? (hareket var)\n  Wohin gehst du? — Ich gehe in die Küche.\n\nWoher? = Nereden? (köken)\n  Woher kommst du? — Ich komme aus der Türkei.\n\nBu ayrım Wechselpräpositionen'da (in, an, auf, über, unter, vor, hinter, neben, zwischen) hali belirler:\n\nWo? → DATIV\n  Ich bin in der Küche.\n  Das Buch liegt auf dem Tisch.\n\nWohin? → AKKUSATIV\n  Ich gehe in die Küche.\n  Ich lege das Buch auf den Tisch.\n\nTürkçe konuşan için kolay kısayol: Cümleyi Türkçeye çevir. \"-de/-da\" diyorsan Dativ, \"-e/-a\" diyorsan Akkusativ. Bu yöntem çok güvenilir çalışır çünkü Türkçede de aynı ayrım vardır.",
        ],
        [
            'slug' => 'sie-du-ne-zaman',
            'q' => 'Ne zaman Sie ne zaman du kullanmalıyım?',
            'kw' => 'sie du resmi samimi hitap ne zaman formal',
            'level' => 'A0',
            'a' => "Sie (resmi) kullan:\n- Tanımadığın her yetişkinle\n- İş yerinde amirlerinle ve yeni tanıştığın iş arkadaşlarıyla\n- Memur, doktor, satıcı, ev sahibi ile\n- Müşterilerle\n\ndu (samimi) kullan:\n- Aile ve yakın arkadaşlarla\n- Çocuklarla\n- Sana \"du\" teklif edilmiş kişilerle\n- Bazı iş yerlerinde ekip kültürü buysa\n\nAltın kural: EMİN DEĞİLSEN \"Sie\" kullan. \"Sie\" hiçbir zaman kaba görünmez; erken \"du\" ise fazla samimi görünebilir.\n\n\"du\"ya geçiş nasıl olur?\nGenellikle yaşça büyük veya kıdemli olan kişi teklif eder:\n\"Wir können uns gerne duzen. Ich bin Thomas.\"\nBu teklif gelmeden sen kendiliğinden geçme.\n\nFiil farkı:\nWie heißen Sie? / Wie heißt du?\nWo wohnen Sie? / Wo wohnst du?\nSie ile fiil her zaman mastar biçimindedir.",
        ],
        [
            'slug' => 'muessen-nicht-duerfen-nicht',
            'q' => '"müssen nicht" ile "nicht dürfen" arasındaki fark nedir?',
            'kw' => 'muessen nicht duerfen yasak gereksiz fark modal',
            'level' => 'A1',
            'a' => "Bu fark İŞ GÜVENLİĞİ açısından hayati olabilir.\n\nmüssen + nicht/kein = GEREKLİ DEĞİL\n  Sie müssen keinen Helm tragen.\n  → Kask takmanız gerekmiyor. (isterseniz takabilirsiniz)\n\nnicht dürfen = YASAK\n  Sie dürfen keinen Helm tragen.\n  → Kask takmanız yasak.\n\nHier darf nicht geraucht werden. = Burada sigara içmek yasak.\nHier muss nicht geraucht werden. = Burada sigara içmek zorunda değilsiniz. (anlamsız bir cümle)\n\nTürkçede \"-memeli\" eki her iki anlamı da karşılayabildiği için Türkçe konuşanlar bunu sık karıştırır.\n\nBir talimatta hangisinin kastedildiğinden emin değilsen sor:\nBedeutet das, dass es verboten ist? — Bu yasak olduğu anlamına mı geliyor?",
        ],
        [
            'slug' => 'weil-denn-farki',
            'q' => 'weil ile denn arasındaki fark nedir?',
            'kw' => 'weil denn cunku fark baglac kelime sirasi',
            'level' => 'A2',
            'a' => "Anlamları AYNIDIR: ikisi de \"çünkü\" demektir. Fark YAPIDADIR.\n\nweil → YAN CÜMLE kurar, çekimli fiil SONA gider\n  Ich bleibe zu Hause, weil ich krank bin.\n\ndenn → ANA CÜMLE bağlacıdır, kelime sırası DEĞİŞMEZ\n  Ich bleibe zu Hause, denn ich bin krank.\n\nHer ikisi de doğrudur. Hangisini kullanacağın sana kalmış.\n\nEk fark: weil cümlesi başa alınabilir, denn cümlesi alınamaz.\n  Weil ich krank bin, bleibe ich zu Hause. ✓\n  Denn ich bin krank, bleibe ich zu Hause. ✗\n\nAyrıca \"deshalb\" (bu yüzden) sebep değil SONUÇ bildirir ve fiil hemen arkasından gelir:\n  Ich bin krank, deshalb bleibe ich zu Hause.",
        ],
        [
            'slug' => 'cogul-nasil-ogrenilir',
            'q' => 'Çoğul biçimlerini nasıl öğrenebilirim?',
            'kw' => 'cogul plural nasil ogrenirim ek bicim',
            'level' => 'A1',
            'a' => "Almancada çoğul için tek bir ek yoktur; beş yol vardır:\n\n1) -e : der Tisch → die Tische\n2) -er : das Kind → die Kinder (çoğu zaman umlaut ile: das Haus → die Häuser)\n3) -(e)n : die Lampe → die Lampen\n4) -s : das Auto → die Autos (yabancı kökenli)\n5) değişmez : der Lehrer → die Lehrer\n\nGüvenilir eğilimler:\n-e ile biten dişil isimler → -n (die Lampe → die Lampen)\n-in ile bitenler → -nen (die Lehrerin → die Lehrerinnen)\n-er, -el, -en ile biten eril/nötr isimler → değişmez\n-ung, -heit, -keit → -en\nYabancı kelimeler → -s\n\nEn etkili yöntem: Çoğulu ayrı bir bilgi olarak değil, kelimenin ikinci yarısı olarak öğren.\n\"der Tisch – die Tische\" tek bir birimdir.\n\nDikkat: Almancada sayıdan sonra çoğul kullanılır.\nTürkçe: üç masa\nAlmanca: drei Tische (drei Tisch değil)",
        ],
        [
            'slug' => 'buyuk-harf-kurali',
            'q' => 'Almancada hangi kelimeler büyük harfle yazılır?',
            'kw' => 'buyuk harf isim yazim kural gross klein',
            'level' => 'A0',
            'a' => "Almancada BÜTÜN isimler, cümlenin neresinde olursa olsun, büyük harfle yazılır.\n\nIch trinke Kaffee. (Kaffee = isim)\nDer Tisch ist neu. (Tisch = isim)\nIch arbeite in Deutschland. (Deutschland = isim)\n\nKüçük harfle yazılanlar:\nFiiller: trinken, arbeiten\nSıfatlar: neu, gut, wichtig\nEdatlar: in, mit, auf\nZarflar: heute, gern, sehr\n\nBüyük harf test yöntemi: Kelimenin önüne der/die/das koyabiliyor musun? Koyabiliyorsan isimdir ve büyük harfle yazılır.\n\nBüyük harf anlamı da değiştirir:\ndas Essen (yemek) — essen (yemek yemek)\nder Morgen (sabah) — morgen (yarın)\ndie Sie (siz, resmi) — sie (o/onlar)\n\nAyrıca resmi hitap \"Sie, Ihnen, Ihr\" her zaman büyük harfle yazılır.",
        ],
        [
            'slug' => 'ayrilabilir-fiil-nedir',
            'q' => 'Ayrılabilir fiil nedir, nasıl tanınır?',
            'kw' => 'ayrilabilir fiil trennbar on ek prefix aufstehen anrufen',
            'level' => 'A1',
            'a' => "Bazı Almanca fiillerin ön eki, ana cümlede fiilden ayrılıp cümlenin SONUNA gider.\n\naufstehen → Ich stehe um sechs Uhr auf.\nanrufen → Ich rufe den Arzt an.\neinkaufen → Ich kaufe am Samstag ein.\n\nNasıl tanınır? Vurguya bak:\nAYRILIR (ön ek vurgulu): AUFstehen, ANrufen, EINkaufen, MITbringen, ABsagen, AUSfüllen, ZUmachen, VORstellen\nAYRILMAZ (kök vurgulu): verSTEhen, beZAHlen, erKLÄren, entSCHULdigen, empFEHlen, gefallen\n\nAyrılmayan ön ek listesi: be-, ge-, er-, ver-, zer-, ent-, emp-, miss-\n\nÜç önemli durum:\n1. Modal fiil varsa AYRILMAZ:\n   Ich muss um sechs aufstehen.\n2. Yan cümlede AYRILMAZ:\n   ..., weil ich um sechs aufstehe.\n3. Partizip II'de ge- ARAYA girer:\n   aufstehen → aufgestanden\n   einkaufen → eingekauft\n\nAyrılmayan fiillerde Partizip II ge- ALMAZ: bezahlen → bezahlt.",
        ],
        [
            'slug' => 'almanca-ne-kadar-surede',
            'q' => 'B1 seviyesine ne kadar sürede ulaşırım?',
            'kw' => 'ne kadar sure b1 zaman kac ay ogrenme hizi',
            'level' => 'A1',
            'a' => "Dürüst cevap: kişiye ve çalışma yoğunluğuna göre çok değişir. Kimse sana kesin bir süre garantisi veremez.\n\nGenel eğilimler (garanti değil, gözlem):\nA0 → A1: yoğun çalışmayla birkaç ay\nA1 → A2: benzer bir süre\nA2 → B1: genellikle daha uzun, çünkü konular derinleşir\n\nSüreyi belirleyen etkenler:\n- Günlük gerçek çalışma süresi (30 dakika ile 3 saat çok farklı sonuç verir)\n- Almanca konuşulan bir ortamda olup olmadığın\n- Daha önce yabancı dil öğrenip öğrenmediğin\n- Düzenlilik (her gün 30 dakika, haftada bir 4 saatten iyidir)\n\nAlmancaPro'nun yaklaşımı: Süre vaat etmiyoruz. Bunun yerine her konuda gerçekten öğrendiğini kanıtlamanı istiyoruz. Hızlı ilerlemiş gibi görünüp temeli boş bırakmak, sonunda daha çok zaman kaybettirir.\n\n30 Günlük Program da bir garanti değil, yoğun bir çalışma planıdır; hedefi seni mümkün olan en verimli sırayla çalıştırmaktır.",
        ],
        [
            'slug' => 'anlamadim-ne-demeliyim',
            'q' => 'Almanca konuşurken anlamadığımda ne demeliyim?',
            'kw' => 'anlamadim ne demeliyim verstehe nicht yavas tekrar',
            'level' => 'A0',
            'a' => "Bu, Almanya'daki ilk aylarının en önemli becerisidir. Sessiz kalmak veya anlamış gibi yapmak en büyük hatadır.\n\nBeş temel cümle:\n\n1. Entschuldigung, ich verstehe nicht.\n   Affedersiniz, anlamıyorum.\n\n2. Können Sie das bitte wiederholen?\n   Bunu tekrar edebilir misiniz?\n\n3. Können Sie bitte langsamer sprechen?\n   Daha yavaş konuşabilir misiniz?\n\n4. Wie bitte?\n   Efendim? / Pardon?\n\n5. Ich spreche nur wenig Deutsch.\n   Sadece biraz Almanca konuşuyorum.\n\nEk olarak çok işe yarayan iki cümle:\n\nKönnen Sie das bitte aufschreiben?\nBunu yazabilir misiniz? (adres, tarih, rakam alırken hayat kurtarır)\n\nKönnen Sie mir das per E-Mail schicken?\nBunu bana e-postayla gönderebilir misiniz? (iş ortamında en güvenlisi)\n\nUnutma: Almanya'da hiç kimse senden mükemmel Almanca beklemiyor. Beklenen şey, anlamadığını net söylemendir. Özellikle iş güvenliği ve sağlık konularında anlamadan onaylamak ciddi risk taşır.",
        ],
    ];
}
