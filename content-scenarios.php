<?php
/**
 * AlmancaPro - Rol yapma senaryolari (AI olmadan da calisan dallanmali diyalog).
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_scenarios(): array
{
    return [
        [
            'slug' => 'baeckerei',
            'title' => 'Fırında Alışveriş',
            'level' => 'A1',
            'category' => 'daily',
            'setting' => 'Sabah saat 7. Mahalledeki fırındasın ve kahvaltı için ekmek almak istiyorsun.',
            'role_user' => 'Müşteri',
            'role_partner' => 'Fırıncı',
            'turns' => [
                [
                    'de' => 'Guten Morgen! Was darf es sein?',
                    'tr' => 'Günaydın! Ne arzu edersiniz?',
                    'instruction' => 'Selam ver ve üç adet Brötchen iste.',
                    'hint' => 'Guten Morgen + ... Brötchen, bitte.',
                    'options' => [
                        ['de'=>'Guten Morgen! Drei Brötchen, bitte.','q'=>'good','fb'=>'Mükemmel. Selamlaşma + net sipariş + bitte. Almanya\'da tam beklenen biçim bu.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Hallo. Ich will drei Brötchen.','q'=>'ok','fb'=>'Anlaşılır ama "Ich will" biraz sert durur. Almanya\'da alışverişte "Ich möchte" veya doğrudan "Drei Brötchen, bitte" tercih edilir.','alt'=>'Ich möchte drei Brötchen, bitte.','sc'=>[5,4,3,2]],
                        ['de'=>'Drei Brötchen.','q'=>'ok','fb'=>'Anlaşılır fakat "bitte" olmadan biraz kaba durur. Tek kelime büyük fark yaratır.','alt'=>'Drei Brötchen, bitte.','sc'=>[5,4,3,2]],
                        ['de'=>'Ich habe drei Brötchen.','q'=>'bad','fb'=>'Bu "Üç ekmeğim var" demektir. Sipariş vermek için möchten kullanılır ya da doğrudan miktar söylenir.','alt'=>'Ich möchte drei Brötchen, bitte.','sc'=>[1,2,1,3]],
                    ],
                ],
                [
                    'de' => 'Gerne. Sonst noch etwas?',
                    'tr' => 'Memnuniyetle. Başka bir şey?',
                    'instruction' => 'Bir de kahve iste.',
                    'hint' => 'einen Kaffee (der Kaffee → Akkusativ: einen)',
                    'options' => [
                        ['de'=>'Ja, einen Kaffee bitte.','q'=>'good','fb'=>'Doğru. der Kaffee Akkusativ\'de "einen Kaffee" olur.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ein Kaffee bitte.','q'=>'ok','fb'=>'Anlaşılır ama hal hatası var. der Kaffee nesne olduğu için "einen Kaffee" olmalı.','alt'=>'Ja, einen Kaffee bitte.','sc'=>[5,3,4,5]],
                        ['de'=>'Nein, danke. Das ist alles.','q'=>'good','fb'=>'Bu da tamamen doğru bir cevap. "Das ist alles" alışverişi kapatmanın standart yoludur.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ich mag Kaffee.','q'=>'bad','fb'=>'"Kahveyi severim" demek sipariş vermek değildir. Fırıncı ne isteyeceğini soruyor.','alt'=>'Ja, einen Kaffee bitte.','sc'=>[2,4,2,4]],
                    ],
                ],
                [
                    'de' => 'Das macht vier Euro zwanzig.',
                    'tr' => 'Dört euro yirmi sent ediyor.',
                    'instruction' => 'Kartla ödemek istediğini söyle.',
                    'hint' => 'mit Karte zahlen',
                    'options' => [
                        ['de'=>'Kann ich mit Karte zahlen?','q'=>'good','fb'=>'Doğru ve kibar. Almanya\'da küçük fırınlarda hâlâ nakit isteyen yerler olduğu için sormak yerinde.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Mit Karte.','q'=>'ok','fb'=>'Anlaşılır ve pratikte sık kullanılır, ama tam cümle daha kibar durur.','alt'=>'Kann ich mit Karte zahlen?','sc'=>[5,4,4,3]],
                        ['de'=>'Ich zahle mit die Karte.','q'=>'ok','fb'=>'Anlam doğru ama hal hatası var: mit her zaman Dativ ister → mit der Karte.','alt'=>'Ich zahle mit der Karte.','sc'=>[4,2,3,4]],
                        ['de'=>'Ich habe kein Geld.','q'=>'bad','fb'=>'Bu "Param yok" demektir ve istediğin anlam bu değil.','alt'=>'Kann ich mit Karte zahlen?','sc'=>[1,4,2,3]],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'arbeit-erste-woche',
            'title' => 'İş Yerinde: Amirin Erken Gelmeni İstiyor',
            'level' => 'A2',
            'category' => 'workplace',
            'setting' => 'İş yerinde ilk haftandasın. Amirin yanına geliyor ve yarın için bir istekte bulunuyor.',
            'role_user' => 'Çalışan',
            'role_partner' => 'Amir',
            'turns' => [
                [
                    'de' => 'Können Sie morgen eine Stunde früher kommen?',
                    'tr' => 'Yarın bir saat erken gelebilir misiniz?',
                    'instruction' => 'Kabul et ve saat kaçta geleceğini teyit et.',
                    'hint' => 'Ja, natürlich. + saat teyidi',
                    'options' => [
                        ['de'=>'Ja, natürlich. Also um fünf Uhr statt um sechs?','q'=>'good','fb'=>'Çok iyi. Kabul ediyorsun ve saati teyit ediyorsun. Teyit etmek yanlış anlaşılmayı önler ve profesyonel görünür.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, kein Problem.','q'=>'ok','fb'=>'Kabul edilebilir ama saati teyit etmedin. İş yerinde saat teyidi her zaman iyi bir alışkanlıktır.','alt'=>'Ja, kein Problem. Also um fünf Uhr?','sc'=>[4,5,5,5]],
                        ['de'=>'Ja.','q'=>'ok','fb'=>'Anlaşılır ama çok kısa. Alman iş ortamında kısa cevap soğuk durabilir.','alt'=>'Ja, natürlich. Um wie viel Uhr genau?','sc'=>[4,5,3,3]],
                        ['de'=>'Nein, ich kann nicht.','q'=>'bad','fb'=>'Reddetmek hakkın ama gerekçesiz "hayır" Alman iş kültüründe kaba durur. Gerekçe eklemek beklenir.','alt'=>'Das ist leider schwierig, weil ich meine Kinder zur Schule bringen muss.','sc'=>[4,5,2,1]],
                    ],
                ],
                [
                    'de' => 'Genau, um fünf Uhr. Schaffen Sie das?',
                    'tr' => 'Aynen, saat beşte. Yetiştirebilir misiniz?',
                    'instruction' => 'Yetiştirebileceğini söyle ve toplu taşıma durumunu belirt.',
                    'hint' => 'mit dem Bus / mit dem Auto + fahren',
                    'options' => [
                        ['de'=>'Ja, ich fahre mit dem Auto, das geht.','q'=>'good','fb'=>'Doğru. mit + Dativ (dem Auto) ve net bir onay. Amirin planlama yapabilir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ich fahre mit dem Bus. Aber der erste Bus kommt um 4:50.','q'=>'good','fb'=>'Çok iyi. Kabul ediyorsun ama olası riski de belirtiyorsun. Bu, Almanya\'da beklenen şeffaf iletişimdir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ich gehe mit dem Bus.','q'=>'ok','fb'=>'Anlam anlaşılıyor ama fiil yanlış: araçla gidiliyorsa "fahren" kullanılır, "gehen" yürümektir.','alt'=>'Ja, ich fahre mit dem Bus.','sc'=>[4,3,3,5]],
                        ['de'=>'Ich weiß nicht.','q'=>'bad','fb'=>'Belirsiz cevap iş planlamasını zorlaştırır. Emin değilsen bunu açıkça söyle ve ne zaman teyit edeceğini belirt.','alt'=>'Ich prüfe die Busverbindung und sage Ihnen heute Nachmittag Bescheid.','sc'=>[3,5,3,3]],
                    ],
                ],
                [
                    'de' => 'Sehr gut. Dann bis morgen um fünf.',
                    'tr' => 'Çok iyi. O zaman yarın beşte görüşürüz.',
                    'instruction' => 'Vedalaş.',
                    'hint' => 'Resmi ortamda uygun veda kalıbı',
                    'options' => [
                        ['de'=>'Alles klar, bis morgen. Auf Wiedersehen!','q'=>'good','fb'=>'Doğru ve doğal. Resmi ortamda "Auf Wiedersehen" güvenli seçimdir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Bis morgen!','q'=>'good','fb'=>'Kısa ama tamamen uygun. İş arkadaşları arasında yaygındır.','alt'=>null,'sc'=>[5,5,5,4]],
                        ['de'=>'Tschüss!','q'=>'ok','fb'=>'Anlaşılır fakat amire karşı biraz fazla samimi olabilir. Ekipte "du" kullanılıyorsa sorun değildir.','alt'=>'Auf Wiedersehen!','sc'=>[5,5,4,3]],
                        ['de'=>'Gute Nacht.','q'=>'bad','fb'=>'"Gute Nacht" yalnızca gece yatmadan önce kullanılır. Gündüz vedalaşmak için uygun değildir.','alt'=>'Auf Wiedersehen!','sc'=>[2,5,1,3]],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'arzttermin',
            'title' => 'Doktordan Randevu Alma',
            'level' => 'A2',
            'category' => 'health',
            'setting' => 'Sırtın ağrıyor. Aile hekiminin muayenehanesini arıyorsun.',
            'role_user' => 'Hasta',
            'role_partner' => 'Muayenehane sekreteri',
            'turns' => [
                [
                    'de' => 'Praxis Dr. Bauer, guten Tag. Was kann ich für Sie tun?',
                    'tr' => 'Dr. Bauer muayenehanesi, iyi günler. Sizin için ne yapabilirim?',
                    'instruction' => 'Kendini tanıt ve randevu istediğini söyle.',
                    'hint' => 'Guten Tag, hier ist ... + einen Termin',
                    'options' => [
                        ['de'=>'Guten Tag, hier ist Emre Yılmaz. Ich möchte einen Termin vereinbaren.','q'=>'good','fb'=>'Mükemmel. Telefonda "hier ist + ad" standart tanıtımdır; randevu için "einen Termin vereinbaren" doğru kalıptır.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Hallo, ich brauche einen Termin.','q'=>'ok','fb'=>'Anlaşılır ama kendini tanıtmadın. Telefonda ad vermek beklenir.','alt'=>'Guten Tag, hier ist Emre Yılmaz. Ich brauche einen Termin.','sc'=>[5,5,3,3]],
                        ['de'=>'Guten Tag, ich möchte ein Termin machen.','q'=>'ok','fb'=>'Anlaşılır ama hal hatası var: der Termin → einen Termin.','alt'=>'Guten Tag, ich möchte einen Termin machen.','sc'=>[5,3,4,5]],
                        ['de'=>'Ich bin krank.','q'=>'ok','fb'=>'Bilgi doğru ama randevu istediğini söylemedin. Sekreter ne yapacağını bilemez.','alt'=>'Guten Tag, ich bin krank und möchte einen Termin vereinbaren.','sc'=>[3,5,3,4]],
                    ],
                ],
                [
                    'de' => 'Waren Sie schon einmal bei uns? Und was haben Sie für Beschwerden?',
                    'tr' => 'Daha önce bize geldiniz mi? Şikayetiniz nedir?',
                    'instruction' => 'İlk kez geldiğini ve sırtının ağrıdığını söyle.',
                    'hint' => 'zum ersten Mal + Schmerzen im Rücken',
                    'options' => [
                        ['de'=>'Nein, ich komme zum ersten Mal. Ich habe Schmerzen im Rücken.','q'=>'good','fb'=>'Doğru ve eksiksiz. İki soruyu da cevapladın.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Nein. Mein Rücken tut weh.','q'=>'good','fb'=>'Bu da tamamen doğru. "wehtun" günlük dilde çok kullanılır: Mein Rücken tut weh.','alt'=>null,'sc'=>[5,5,5,4]],
                        ['de'=>'Nein, erste Mal. Ich habe Schmerzen in Rücken.','q'=>'ok','fb'=>'Anlaşılır ama iki küçük hata var: "zum ersten Mal" ve "im Rücken" (in + dem = im).','alt'=>'Nein, ich komme zum ersten Mal. Ich habe Schmerzen im Rücken.','sc'=>[4,2,3,4]],
                        ['de'=>'Ich habe Kopfschmerzen.','q'=>'bad','fb'=>'Bu "başım ağrıyor" demektir. Sırt için "Rückenschmerzen" veya "Schmerzen im Rücken" kullanılır.','alt'=>'Ich habe Rückenschmerzen.','sc'=>[1,5,3,4]],
                    ],
                ],
                [
                    'de' => 'Wir hätten am Donnerstag um 14:30 Uhr einen Termin frei. Passt das?',
                    'tr' => 'Perşembe 14:30\'da boş bir randevumuz var. Uygun mu?',
                    'instruction' => 'Kabul et ve ne getirmen gerektiğini sor.',
                    'hint' => 'Was soll ich mitbringen? / Versichertenkarte',
                    'options' => [
                        ['de'=>'Ja, das passt. Was soll ich mitbringen?','q'=>'good','fb'=>'Çok iyi. Neyi getirmen gerektiğini sormak, ilk ziyarette zaman kaybını önler.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, das passt gut. Vielen Dank.','q'=>'good','fb'=>'Doğru ve kibar. Yine de sigorta kartını getirmeyi unutma.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja. Was ich bringen muss?','q'=>'ok','fb'=>'Anlaşılır ama kelime sırası yanlış. Soru cümlesinde fiil ikinci sırada olur.','alt'=>'Ja. Was muss ich mitbringen?','sc'=>[4,2,3,4]],
                        ['de'=>'Nein, ich habe keine Zeit.','q'=>'bad','fb'=>'Reddetmek hakkın ama alternatif istemeden reddetmek işini çözmez.','alt'=>'Donnerstag passt leider nicht. Haben Sie einen anderen Termin?','sc'=>[3,5,3,3]],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'buergeramt-anmeldung',
            'title' => 'Bürgeramt: İkamet Kaydı',
            'level' => 'A2',
            'category' => 'authority',
            'setting' => 'Almanya\'da yeni bir daireye taşındın. Bürgeramt\'ta ikamet kaydı yaptırıyorsun.',
            'role_user' => 'Başvuru sahibi',
            'role_partner' => 'Memur',
            'turns' => [
                [
                    'de' => 'Guten Tag. Wie kann ich Ihnen helfen?',
                    'tr' => 'İyi günler. Size nasıl yardımcı olabilirim?',
                    'instruction' => 'Kayıt yaptırmak istediğini söyle.',
                    'hint' => 'sich anmelden (dönüşlü fiil)',
                    'options' => [
                        ['de'=>'Guten Tag. Ich möchte mich anmelden.','q'=>'good','fb'=>'Doğru. sich anmelden dönüşlü bir fiildir; "mich" zorunludur.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Guten Tag. Ich möchte anmelden.','q'=>'ok','fb'=>'Anlaşılır ama dönüşlü zamir eksik: "Ich möchte mich anmelden."','alt'=>'Ich möchte mich anmelden.','sc'=>[4,2,3,5]],
                        ['de'=>'Guten Tag. Ich habe eine neue Wohnung und muss mich anmelden.','q'=>'good','fb'=>'Çok iyi. Sebebi de belirtmek memura bağlamı verir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ich will Papier.','q'=>'bad','fb'=>'Bu ne istediğini anlatmıyor. Net bir talep gerekiyor.','alt'=>'Ich möchte mich anmelden.','sc'=>[1,3,1,2]],
                    ],
                ],
                [
                    'de' => 'Haben Sie die Wohnungsgeberbestätigung und Ihren Ausweis dabei?',
                    'tr' => 'Ev sahibi onay belgesi ve kimliğiniz yanınızda mı?',
                    'instruction' => 'Kimliğinin yanında olduğunu söyle, ama belgeyi anlamadıysan sor.',
                    'hint' => 'Was bedeutet ...? / Ich habe ... dabei',
                    'options' => [
                        ['de'=>'Meinen Ausweis habe ich dabei. Was bedeutet Wohnungsgeberbestätigung genau?','q'=>'good','fb'=>'Mükemmel. Bildiğini söyleyip bilmediğini sormak en doğru davranış. Memurlar açıklamakla yükümlüdür.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ich habe alles dabei.','q'=>'bad','fb'=>'Emin değilsen "her şey yanımda" demek risklidir. Belge eksikse randevu boşa gider ve yeni randevu haftalar sürebilir.','alt'=>'Meinen Ausweis habe ich. Was ist eine Wohnungsgeberbestätigung?','sc'=>[3,5,3,4]],
                        ['de'=>'Ich habe mein Ausweis dabei.','q'=>'ok','fb'=>'Anlaşılır ama hal hatası var: der Ausweis nesne olduğu için "meinen Ausweis" olmalı.','alt'=>'Ich habe meinen Ausweis dabei.','sc'=>[5,3,4,5]],
                        ['de'=>'Entschuldigung, ich verstehe das Wort nicht. Können Sie es erklären?','q'=>'good','fb'=>'Çok iyi. Anlamadığını söylemek profesyonelliktir; resmi kurumlarda tamamen normaldir.','alt'=>null,'sc'=>[5,5,5,5]],
                    ],
                ],
                [
                    'de' => 'Das ist eine Bestätigung von Ihrem Vermieter, dass Sie dort wohnen. Ohne die Bestätigung können wir Sie leider nicht anmelden.',
                    'tr' => 'Bu, ev sahibinizin orada oturduğunuzu onayladığı bir belgedir. Bu belge olmadan maalesef kaydınızı yapamıyoruz.',
                    'instruction' => 'Belgeyi getireceğini söyle ve yeni randevu iste.',
                    'hint' => 'Ich bringe ... mit / einen neuen Termin',
                    'options' => [
                        ['de'=>'Verstanden. Ich bringe die Bestätigung mit. Kann ich einen neuen Termin bekommen?','q'=>'good','fb'=>'Mükemmel. Sorunu kabul ediyor, çözümü söylüyor ve sonraki adımı istiyorsun.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Okay. Ich komme später wieder.','q'=>'ok','fb'=>'Anlaşılır ama Almanya\'da çoğu Bürgeramt randevu ile çalışır. Yeni randevu istemek daha güvenlidir.','alt'=>'Kann ich bitte gleich einen neuen Termin vereinbaren?','sc'=>[4,5,4,4]],
                        ['de'=>'Warum brauchen Sie das? Das ist nicht fair.','q'=>'bad','fb'=>'Memura karşı çıkmak süreci hızlandırmaz. Belge yasal bir gerekliliktir; itiraz yerine çözüm istemek daha etkilidir.','alt'=>'Verstanden. Wie bekomme ich diese Bestätigung?','sc'=>[4,5,2,1]],
                        ['de'=>'Wie bekomme ich diese Bestätigung?','q'=>'good','fb'=>'Çok iyi bir soru. Belgeyi nasıl alacağını öğrenmek en pratik adımdır.','alt'=>null,'sc'=>[5,5,5,5]],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'wohnungsbesichtigung',
            'title' => 'Daire Görme Randevusu',
            'level' => 'B1',
            'category' => 'daily',
            'setting' => 'Bir daire ilanı için ev sahibiyle görüşüyorsun. Daireyi görmeye geldin.',
            'role_user' => 'Kiracı adayı',
            'role_partner' => 'Ev sahibi',
            'turns' => [
                [
                    'de' => 'Guten Tag. Sie interessieren sich für die Wohnung, richtig?',
                    'tr' => 'İyi günler. Daireyle ilgileniyorsunuz, doğru mu?',
                    'instruction' => 'Onayla ve kendini kısaca tanıt.',
                    'hint' => 'sich interessieren für + Akkusativ',
                    'options' => [
                        ['de'=>'Ja, genau. Ich heiße Emre Yılmaz und ich interessiere mich sehr für die Wohnung.','q'=>'good','fb'=>'Mükemmel. sich interessieren für + Akkusativ doğru kullanılmış ve kendini tanıtmışsın.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja. Ich interessiere mich für die Wohnung.','q'=>'good','fb'=>'Doğru. İsim eklemek daha da iyi olurdu.','alt'=>null,'sc'=>[5,5,4,4]],
                        ['de'=>'Ja, ich interessiere für die Wohnung.','q'=>'ok','fb'=>'Dönüşlü zamir eksik: sich interessieren fiilinde "mich" zorunludur.','alt'=>'Ja, ich interessiere mich für die Wohnung.','sc'=>[5,2,3,4]],
                        ['de'=>'Ja, ich will die Wohnung.','q'=>'ok','fb'=>'Anlaşılır ama "Ich will" bu bağlamda fazla doğrudan durur. İlgilendiğini söylemek daha uygun.','alt'=>'Ja, ich interessiere mich sehr für die Wohnung.','sc'=>[5,5,3,2]],
                    ],
                ],
                [
                    'de' => 'Die Kaltmiete beträgt 780 Euro, dazu kommen 190 Euro Nebenkosten. Die Kaution sind drei Kaltmieten.',
                    'tr' => 'Çıplak kira 780 euro, buna 190 euro ek gider ekleniyor. Depozito üç aylık çıplak kira.',
                    'instruction' => 'Ek giderlerin neleri kapsadığını sor.',
                    'hint' => 'Was ist in den Nebenkosten enthalten?',
                    'options' => [
                        ['de'=>'Was ist in den Nebenkosten enthalten? Sind Heizung und Wasser dabei?','q'=>'good','fb'=>'Mükemmel soru. Ek giderlerin kapsamı Almanya\'da daireye göre çok değişir; sormak zorunludur.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Was kostet das insgesamt?','q'=>'ok','fb'=>'Yararlı ama kapsamı sormuyor. Toplam tutar (Warmmiete) 970 euro; asıl soru ek giderlerin neleri içerdiğidir.','alt'=>'Was ist in den Nebenkosten enthalten?','sc'=>[5,5,4,4]],
                        ['de'=>'Das ist zu teuer.','q'=>'bad','fb'=>'Doğrudan fiyat eleştirisi görüşmeyi olumsuz başlatır. Önce bilgi topla, sonra karar ver.','alt'=>'Können Sie mir sagen, was in den Nebenkosten enthalten ist?','sc'=>[4,5,3,1]],
                        ['de'=>'Ich verstehe. Und die Kaution muss ich sofort zahlen?','q'=>'good','fb'=>'Doğru ve pratik bir soru. Depozito ödeme zamanlaması önemlidir.','alt'=>null,'sc'=>[5,5,5,5]],
                    ],
                ],
                [
                    'de' => 'Heizung und Wasser sind enthalten, Strom müssen Sie selbst anmelden. Möchten Sie sich bewerben?',
                    'tr' => 'Isınma ve su dahil, elektriği kendiniz kaydettirmelisiniz. Başvurmak ister misiniz?',
                    'instruction' => 'İlgilendiğini belirt ve hangi belgeleri getirmen gerektiğini sor.',
                    'hint' => 'Welche Unterlagen brauchen Sie?',
                    'options' => [
                        ['de'=>'Ja, sehr gern. Welche Unterlagen brauchen Sie von mir?','q'=>'good','fb'=>'Mükemmel. Almanya\'da daire başvurusunda genellikle gelir belgesi ve kimlik istenir; hazırlıklı olmak avantaj sağlar.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja. Ich schicke Ihnen die Unterlagen heute noch.','q'=>'good','fb'=>'Çok iyi. Hızlı hareket etmek rekabetçi konut piyasasında önemlidir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ich möchte mich bewerben. Was für Unterlagen?','q'=>'ok','fb'=>'Anlaşılır. "Welche Unterlagen brauchen Sie?" daha akıcı bir sorudur.','alt'=>'Ja, ich möchte mich bewerben. Welche Unterlagen brauchen Sie?','sc'=>[5,4,4,4]],
                        ['de'=>'Ich denke darüber nach.','q'=>'ok','fb'=>'Dilbilgisel olarak doğru ve kibar (nachdenken über + Akkusativ → darüber). Ancak yoğun piyasada beklemek daireyi kaybettirebilir.','alt'=>null,'sc'=>[5,5,5,4]],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'problem-melden',
            'title' => 'İş Yerinde Sorun Bildirme',
            'level' => 'B1',
            'category' => 'workplace',
            'setting' => 'Kullandığın makine tuhaf ses çıkarıyor. Amirine bildirmen gerekiyor.',
            'role_user' => 'Çalışan',
            'role_partner' => 'Vardiya amiri',
            'turns' => [
                [
                    'de' => 'Ja, was gibt es?',
                    'tr' => 'Evet, ne var?',
                    'instruction' => 'Makinede bir sorun olduğunu bildir.',
                    'hint' => 'Es gibt ein Problem mit + Dativ',
                    'options' => [
                        ['de'=>'Es gibt ein Problem mit der Maschine. Sie macht ein komisches Geräusch.','q'=>'good','fb'=>'Mükemmel. Sorunu net bildiriyor ve belirtiyi tarif ediyorsun. İş güvenliğinde bu doğru davranıştır.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Die Maschine ist kaputt.','q'=>'ok','fb'=>'Anlaşılır ama kesin bir teşhis koyuyorsun. Emin değilsen belirtiyi tarif etmek daha doğru.','alt'=>'Die Maschine macht ein komisches Geräusch. Ich bin nicht sicher, was los ist.','sc'=>[4,5,4,4]],
                        ['de'=>'Es gibt ein Problem mit die Maschine.','q'=>'ok','fb'=>'Anlam doğru ama hal hatası var: mit her zaman Dativ ister → mit der Maschine.','alt'=>'Es gibt ein Problem mit der Maschine.','sc'=>[5,2,4,5]],
                        ['de'=>'Nichts, alles gut.','q'=>'bad','fb'=>'Sorunu bildirmemek iş güvenliği açısından ciddi risktir. Şüphelendiğin her durumu bildirmelisin.','alt'=>'Es gibt ein Problem mit der Maschine.','sc'=>[1,5,3,3]],
                    ],
                ],
                [
                    'de' => 'Seit wann ist das so?',
                    'tr' => 'Ne zamandır böyle?',
                    'instruction' => 'Yaklaşık yarım saattir olduğunu söyle.',
                    'hint' => 'seit + Dativ (seit einer halben Stunde)',
                    'options' => [
                        ['de'=>'Seit ungefähr einer halben Stunde.','q'=>'good','fb'=>'Doğru. seit + Dativ; "einer halben Stunde" tam doğru biçim.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Seit eine halbe Stunde.','q'=>'ok','fb'=>'Anlaşılır ama seit Dativ ister: seit einer halben Stunde.','alt'=>'Seit einer halben Stunde.','sc'=>[5,2,3,5]],
                        ['de'=>'Vor einer halben Stunde hat es angefangen.','q'=>'good','fb'=>'Bu da tamamen doğru ve doğal bir cevap: "vor + Dativ" olayın başlangıcını bildirir.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ich weiß nicht.','q'=>'ok','fb'=>'Dürüst ama işe yaramaz. Tam bilmiyorsan bile tahmini bir süre vermek bakım ekibine yardımcı olur.','alt'=>'Ich bin nicht sicher, aber ich habe es vor etwa 30 Minuten bemerkt.','sc'=>[3,5,4,4]],
                    ],
                ],
                [
                    'de' => 'Gut, dass Sie es gemeldet haben. Haben Sie die Maschine ausgeschaltet?',
                    'tr' => 'Bildirmeniz iyi oldu. Makineyi kapattınız mı?',
                    'instruction' => 'Kapattığını söyle ve ne yapman gerektiğini sor.',
                    'hint' => 'ausschalten (ayrılabilir) + Was soll ich jetzt machen?',
                    'options' => [
                        ['de'=>'Ja, ich habe sie sofort ausgeschaltet. Was soll ich jetzt machen?','q'=>'good','fb'=>'Mükemmel. Doğru davranış + sonraki adımı sorma. Partizip "ausgeschaltet" da doğru.','alt'=>null,'sc'=>[5,5,5,5]],
                        ['de'=>'Ja, ich habe sie ausgeschaltet.','q'=>'good','fb'=>'Doğru. Sonraki adımı sormak daha da iyi olurdu.','alt'=>null,'sc'=>[5,5,5,4]],
                        ['de'=>'Ja, ich habe sie ausschalten.','q'=>'ok','fb'=>'Partizip biçimi yanlış: ausschalten → ausgeschaltet.','alt'=>'Ja, ich habe sie ausgeschaltet.','sc'=>[5,2,3,5]],
                        ['de'=>'Nein, sie läuft noch.','q'=>'bad','fb'=>'Arızalı bir makineyi çalışır durumda bırakmak tehlikelidir. Şüphelendiğinde önce durdur, sonra bildir.','alt'=>'Nein, soll ich sie sofort ausschalten?','sc'=>[5,5,3,2]],
                    ],
                ],
            ],
        ],
    ];
}
