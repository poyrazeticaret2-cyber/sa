<?php
/**
 * AlmancaPro - Seviye tespit sinavi.
 * Alti eksende olcum: kelime, dilbilgisi, okuma, uretim, yazim, cumle kurma.
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function almancapro_placement_questions(): array
{
    return [
        /* ---------- Kelime ---------- */
        ['id'=>'v1','section'=>'vocabulary','level'=>'A0','skill'=>'greetings','type'=>'multiple_choice',
         'q'=>'"Guten Morgen" ne anlama gelir?','a'=>'Günaydın','opts'=>['Günaydın','İyi geceler','Hoşça kal','Teşekkürler']],
        ['id'=>'v2','section'=>'vocabulary','level'=>'A0','skill'=>'numbers_21_100','type'=>'multiple_choice',
         'q'=>'"vierundzwanzig" hangi sayıdır?','a'=>'24','opts'=>['24','42','4','14']],
        ['id'=>'v3','section'=>'vocabulary','level'=>'A1','skill'=>'definite_articles','type'=>'multiple_choice',
         'q'=>'"Wohnung" kelimesinin artikeli nedir?','a'=>'die','opts'=>['die','der','das','artikel almaz']],
        ['id'=>'v4','section'=>'vocabulary','level'=>'A1','skill'=>'city_transport','type'=>'multiple_choice',
         'q'=>'"der Bahnhof" ne demektir?','a'=>'tren garı','opts'=>['tren garı','banka','eczane','hastane']],
        ['id'=>'v5','section'=>'vocabulary','level'=>'A2','skill'=>'renting_flat','type'=>'multiple_choice',
         'q'=>'"die Kaution" ne demektir?','a'=>'depozito','opts'=>['depozito','kira','fatura','sözleşme']],
        ['id'=>'v6','section'=>'vocabulary','level'=>'B1','skill'=>'contract_vocabulary_b1','type'=>'multiple_choice',
         'q'=>'"die Kündigungsfrist" ne demektir?','a'=>'fesih ihbar süresi','opts'=>['fesih ihbar süresi','deneme süresi','izin süresi','mola süresi']],
        ['id'=>'v7','section'=>'vocabulary','level'=>'B1','skill'=>'opinions_b1','type'=>'multiple_choice',
         'q'=>'"Es kommt darauf an." ne anlama gelir?','a'=>'Duruma bağlı.','opts'=>['Duruma bağlı.','Kesinlikle öyle.','Anlamıyorum.','Hemen geliyorum.']],

        /* ---------- Dilbilgisi ---------- */
        ['id'=>'g1','section'=>'grammar','level'=>'A1','skill'=>'sein_present','type'=>'multiple_choice',
         'q'=>'"Ich ___ Ingenieur." Boşluğa hangisi gelir?','a'=>'bin','opts'=>['bin','ist','bist','sind']],
        ['id'=>'g2','section'=>'grammar','level'=>'A1','skill'=>'verb_second','type'=>'multiple_choice',
         'q'=>'Hangi cümle doğrudur?','a'=>'Heute arbeite ich in Köln.','opts'=>['Heute arbeite ich in Köln.','Heute ich arbeite in Köln.','Heute in Köln ich arbeite.','Ich heute arbeite in Köln.']],
        ['id'=>'g3','section'=>'grammar','level'=>'A1','skill'=>'akkusativ','type'=>'multiple_choice',
         'q'=>'"Ich kaufe ___ Kaffee." Boşluğa hangisi gelir?','a'=>'den','opts'=>['den','der','dem','des']],
        ['id'=>'g4','section'=>'grammar','level'=>'A1','skill'=>'negation_kein','type'=>'multiple_choice',
         'q'=>'"Vaktim yok" nasıl denir?','a'=>'Ich habe keine Zeit.','opts'=>['Ich habe keine Zeit.','Ich habe nicht Zeit.','Ich habe kein Zeit.','Ich bin keine Zeit.']],
        ['id'=>'g5','section'=>'grammar','level'=>'A1','skill'=>'separable_verbs','type'=>'multiple_choice',
         'q'=>'Hangi cümle doğrudur?','a'=>'Ich stehe um sechs Uhr auf.','opts'=>['Ich stehe um sechs Uhr auf.','Ich aufstehe um sechs Uhr.','Ich auf stehe um sechs Uhr.','Ich stehe auf um sechs Uhr.']],
        ['id'=>'g6','section'=>'grammar','level'=>'A2','skill'=>'dativ','type'=>'multiple_choice',
         'q'=>'"Ich helfe ___ Kollegen." Boşluğa hangisi gelir?','a'=>'dem','opts'=>['dem','den','der','das']],
        ['id'=>'g7','section'=>'grammar','level'=>'A2','skill'=>'wechselpraepositionen','type'=>'multiple_choice',
         'q'=>'"Ich gehe in ___ Küche." (mutfağa gidiyorum) Boşluğa hangisi gelir?','a'=>'die','opts'=>['die','der','dem','den']],
        ['id'=>'g8','section'=>'grammar','level'=>'A2','skill'=>'perfekt_auxiliary','type'=>'multiple_choice',
         'q'=>'"Ich ___ nach Berlin gefahren." Boşluğa hangisi gelir?','a'=>'bin','opts'=>['bin','habe','war','wurde']],
        ['id'=>'g9','section'=>'grammar','level'=>'A2','skill'=>'nebensatz_verb_final','type'=>'multiple_choice',
         'q'=>'Hangi cümle doğrudur?','a'=>'Ich lerne Deutsch, weil ich hier arbeite.','opts'=>['Ich lerne Deutsch, weil ich hier arbeite.','Ich lerne Deutsch, weil ich arbeite hier.','Ich lerne Deutsch, weil arbeite ich hier.','Ich lerne Deutsch weil hier ich arbeite.']],
        ['id'=>'g10','section'=>'grammar','level'=>'B1','skill'=>'adj_decl_indefinite','type'=>'multiple_choice',
         'q'=>'"Das ist ein neu___ Chef." Boşluğa hangi ek gelir?','a'=>'er','opts'=>['er','e','en','es']],
        ['id'=>'g11','section'=>'grammar','level'=>'B1','skill'=>'relative_akkusativ','type'=>'multiple_choice',
         'q'=>'"Das ist der Kollege, ___ ich gestern getroffen habe." Boşluğa hangisi gelir?','a'=>'den','opts'=>['den','der','dem','das']],
        ['id'=>'g12','section'=>'grammar','level'=>'B1','skill'=>'passive_present','type'=>'multiple_choice',
         'q'=>'"Makine her gün kontrol ediliyor" nasıl denir?','a'=>'Die Maschine wird jeden Tag geprüft.','opts'=>['Die Maschine wird jeden Tag geprüft.','Die Maschine ist jeden Tag geprüft.','Die Maschine hat jeden Tag geprüft.','Die Maschine prüft jeden Tag.']],
        ['id'=>'g13','section'=>'grammar','level'=>'B1','skill'=>'konjunktiv_ii_haette_waere','type'=>'multiple_choice',
         'q'=>'"Vaktim olsaydı daha çok çalışırdım." Boşluğu tamamla: "Wenn ich mehr Zeit ___, würde ich mehr lernen."','a'=>'hätte','opts'=>['hätte','habe','hatte','haben']],
        ['id'=>'g14','section'=>'grammar','level'=>'B1','skill'=>'verb_preposition','type'=>'multiple_choice',
         'q'=>'"Ich warte ___ den Bus." Boşluğa hangisi gelir?','a'=>'auf','opts'=>['auf','für','an','über']],

        /* ---------- Okuma ---------- */
        ['id'=>'r1','section'=>'reading','level'=>'A2','skill'=>'b1_reading','type'=>'multiple_choice',
         'ctx'=>"Sehr geehrte Mitarbeiterinnen und Mitarbeiter,\n\nab dem 1. Juni beginnt die Frühschicht um 6:00 Uhr statt um 6:30 Uhr. Die Spätschicht endet unverändert um 22:00 Uhr. Fragen bitte bis zum 25. Mai an die Personalabteilung.",
         'q'=>'Metne göre sabah vardiyası 1 Haziran\'dan sonra saat kaçta başlıyor?','a'=>'6:00','opts'=>['6:00','6:30','22:00','25:00']],
        ['id'=>'r2','section'=>'reading','level'=>'A2','skill'=>'b1_reading','type'=>'multiple_choice',
         'ctx'=>"Sehr geehrte Mitarbeiterinnen und Mitarbeiter,\n\nab dem 1. Juni beginnt die Frühschicht um 6:00 Uhr statt um 6:30 Uhr. Die Spätschicht endet unverändert um 22:00 Uhr. Fragen bitte bis zum 25. Mai an die Personalabteilung.",
         'q'=>'Sorular en geç ne zamana kadar iletilmeli?','a'=>'25 Mayıs','opts'=>['25 Mayıs','1 Haziran','22 Mayıs','30 Haziran']],
        ['id'=>'r3','section'=>'reading','level'=>'B1','skill'=>'b1_reading','type'=>'multiple_choice',
         'ctx'=>"Wohnung zu vermieten: 3 ZKB, 75 m², 4. OG ohne Aufzug, 780 € kalt + 190 € NK, 3 MM Kaution. EBK vorhanden. Besichtigung nach Vereinbarung.",
         'q'=>'İlana göre aylık toplam ödenecek kira (Warmmiete) ne kadardır?','a'=>'970 euro','opts'=>['970 euro','780 euro','190 euro','2340 euro']],
        ['id'=>'r4','section'=>'reading','level'=>'B1','skill'=>'b1_reading','type'=>'multiple_choice',
         'ctx'=>"Wohnung zu vermieten: 3 ZKB, 75 m², 4. OG ohne Aufzug, 780 € kalt + 190 € NK, 3 MM Kaution. EBK vorhanden. Besichtigung nach Vereinbarung.",
         'q'=>'İlana göre binada asansör var mı?','a'=>'Hayır, asansör yok','opts'=>['Hayır, asansör yok','Evet, asansör var','İlanda belirtilmemiş','Sadece 4. kata kadar var']],

        /* ---------- Yazim ---------- */
        ['id'=>'s1','section'=>'spelling','level'=>'A0','skill'=>'noun_capitalization','type'=>'multiple_choice',
         'q'=>'Hangi cümle doğru yazılmıştır?','a'=>'Ich arbeite in Deutschland.','opts'=>['Ich arbeite in Deutschland.','Ich arbeite in deutschland.','ich arbeite in Deutschland.','Ich Arbeite in Deutschland.']],
        ['id'=>'s2','section'=>'spelling','level'=>'A0','skill'=>'umlaut_sounds','type'=>'text_input',
         'q'=>'"beş" sayısını Almanca yaz.','a'=>'fünf','alt'=>['fuenf']],
        ['id'=>'s3','section'=>'spelling','level'=>'A1','skill'=>'plural_forms','type'=>'text_input',
         'q'=>'"das Haus" kelimesinin çoğulunu artikeliyle yaz.','a'=>'die Häuser','alt'=>['die Haeuser']],
        ['id'=>'s4','section'=>'spelling','level'=>'A2','skill'=>'partizip_ii_irregular','type'=>'text_input',
         'q'=>'"sprechen" fiilinin Partizip II biçimini yaz.','a'=>'gesprochen'],

        /* ---------- Uretim ---------- */
        ['id'=>'p1','section'=>'production','level'=>'A0','skill'=>'self_introduction','type'=>'text_input',
         'q'=>'"Türkiye\'den geliyorum" cümlesini Almanca yaz.','a'=>'Ich komme aus der Türkei.','alt'=>['Ich komme aus der Tuerkei.']],
        ['id'=>'p2','section'=>'production','level'=>'A1','skill'=>'akkusativ','type'=>'text_input',
         'q'=>'"Bir randevum var" cümlesini Almanca yaz. (der Termin)','a'=>'Ich habe einen Termin.'],
        ['id'=>'p3','section'=>'production','level'=>'A2','skill'=>'perfekt','type'=>'text_input',
         'q'=>'"Dün çalıştım" cümlesini Perfekt ile yaz.','a'=>'Ich habe gestern gearbeitet.','alt'=>['Gestern habe ich gearbeitet.']],
        ['id'=>'p4','section'=>'production','level'=>'B1','skill'=>'konjunktiv_ii_modal','type'=>'text_input',
         'q'=>'"Bana yardım edebilir misiniz?" cümlesini en kibar biçimde yaz.','a'=>'Könnten Sie mir bitte helfen?','alt'=>['Könnten Sie mir helfen?','Koennten Sie mir bitte helfen?']],

        /* ---------- Cumle kurma ---------- */
        ['id'=>'c1','section'=>'sentence','level'=>'A1','skill'=>'verb_second','type'=>'word_order',
         'q'=>'Kelimeleri doğru sıraya diz: "morgen / ich / einen Termin / habe"','a'=>'Morgen habe ich einen Termin.','alt'=>['Ich habe morgen einen Termin.']],
        ['id'=>'c2','section'=>'sentence','level'=>'A2','skill'=>'nebensatz_verb_final','type'=>'word_order',
         'q'=>'İki cümleyi weil ile birleştir: "Ich bleibe zu Hause." + "Ich bin krank."','a'=>'Ich bleibe zu Hause, weil ich krank bin.'],
        ['id'=>'c3','section'=>'sentence','level'=>'B1','skill'=>'um_zu','type'=>'word_order',
         'q'=>'Cümleyi um...zu ile tamamla: "Ich lerne Deutsch, ___ in Deutschland ___ arbeiten."','a'=>'Ich lerne Deutsch, um in Deutschland zu arbeiten.'],
        ['id'=>'c4','section'=>'sentence','level'=>'B1','skill'=>'tekamolo','type'=>'word_order',
         'q'=>'Kelimeleri TeKaMoLo sırasına göre diz: "nach Berlin / ich / morgen / fahre / mit dem Zug"','a'=>'Ich fahre morgen mit dem Zug nach Berlin.'],
    ];
}

/**
 * Puanlara gore onerilen baslangic seviyesi.
 */
function almancapro_placement_level(array $sectionPercents, int $totalPercent): string
{
    $grammar = $sectionPercents['grammar'] ?? 0;
    $production = $sectionPercents['production'] ?? 0;

    if ($totalPercent < 25 || $grammar < 25) {
        return 'A0';
    }
    if ($totalPercent < 50 || $grammar < 45) {
        return 'A1';
    }
    if ($totalPercent < 72 || $grammar < 65 || $production < 40) {
        return 'A2';
    }
    return 'B1';
}
