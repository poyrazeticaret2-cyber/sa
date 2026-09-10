<?php
/**
 * AlmancaPro - Skill tanimlari.
 * Her ogrenilebilir unsur bagimsiz olarak takip edilir.
 */
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string)$_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)
    && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * code, name, category, level, threshold, critical, production, spelling
 */
function almancapro_skills(): array
{
    $rows = [
        /* --- A0 --- */
        ['alphabet', 'Alman alfabesi ve harf isimleri', 'pronunciation', 'A0', 85, 1, 0, 1],
        ['umlaut_sounds', 'Umlaut sesleri: ä, ö, ü', 'pronunciation', 'A0', 85, 1, 0, 1],
        ['eszett', 'ß harfi ve ss ile farkı', 'pronunciation', 'A0', 85, 1, 0, 1],
        ['digraph_ch_sch', 'ch ve sch okunuşu', 'pronunciation', 'A0', 85, 1, 0, 0],
        ['digraph_sp_st', 'sp / st başlangıç okunuşu', 'pronunciation', 'A0', 85, 1, 0, 0],
        ['diphthongs', 'ei, ie, eu, äu okunuşu', 'pronunciation', 'A0', 90, 1, 0, 1],
        ['consonants_wvjzr', 'w, v, j, z, r, h okunuşu', 'pronunciation', 'A0', 85, 1, 0, 0],
        ['vowel_length', 'Uzun ve kısa ünlüler', 'pronunciation', 'A0', 80, 0, 0, 0],
        ['word_stress', 'Kelime vurgusu', 'pronunciation', 'A0', 80, 0, 0, 0],
        ['greetings', 'Selamlaşma kalıpları', 'vocabulary', 'A0', 90, 1, 1, 0],
        ['self_introduction', 'Kendini tanıtma', 'function', 'A0', 90, 1, 1, 0],
        ['asking_name', 'İsim sorma ve söyleme', 'function', 'A0', 90, 1, 1, 0],
        ['countries_nationalities', 'Ülkeler ve milliyetler', 'vocabulary', 'A0', 90, 1, 1, 0],
        ['where_you_live', 'Nerede yaşadığını söyleme', 'function', 'A0', 90, 1, 1, 0],
        ['numbers_0_20', 'Sayılar 0-20', 'vocabulary', 'A0', 90, 1, 0, 1],
        ['numbers_21_100', 'Sayılar 21-100 ve ters okuma', 'vocabulary', 'A0', 90, 1, 0, 1],
        ['phone_numbers', 'Telefon numarası söyleme', 'function', 'A0', 85, 1, 1, 0],
        ['address_info', 'Adres bilgisi verme', 'function', 'A0', 85, 1, 1, 0],
        ['professions', 'Meslekler', 'vocabulary', 'A0', 90, 1, 1, 0],
        ['basic_phrases', 'Temel günlük kalıplar', 'function', 'A0', 90, 1, 1, 0],
        ['sie_du', 'Sie / du ayrımı', 'grammar', 'A0', 90, 1, 1, 0],
        ['noun_capitalization', 'İsimlerin büyük harfle yazılması', 'grammar', 'A0', 90, 1, 0, 1],

        /* --- A1 --- */
        ['personal_pronouns', 'Kişi zamirleri (ich, du, er ...)', 'grammar', 'A1', 90, 1, 1, 0],
        ['sein_present', 'sein fiili - Präsens', 'grammar', 'A1', 90, 1, 1, 0],
        ['haben_present', 'haben fiili - Präsens', 'grammar', 'A1', 90, 1, 1, 0],
        ['regular_verbs_present', 'Düzenli fiil çekimi - Präsens', 'grammar', 'A1', 90, 1, 1, 0],
        ['irregular_verbs_present', 'Kök değiştiren fiiller (fahren, sprechen...)', 'grammar', 'A1', 90, 1, 1, 0],
        ['w_questions', 'W-Fragen (wer, was, wo, wie ...)', 'grammar', 'A1', 90, 1, 1, 0],
        ['yes_no_questions', 'Ja/Nein soruları', 'grammar', 'A1', 90, 1, 1, 0],
        ['verb_second', 'V2 kuralı: fiil ikinci sırada', 'grammar', 'A1', 90, 1, 1, 0],
        ['negation_nicht', 'nicht ile olumsuzlama', 'grammar', 'A1', 90, 1, 1, 0],
        ['negation_kein', 'kein ile olumsuzlama', 'grammar', 'A1', 90, 1, 1, 0],
        ['definite_articles', 'der / die / das', 'grammar', 'A1', 90, 1, 1, 0],
        ['indefinite_articles', 'ein / eine', 'grammar', 'A1', 90, 1, 1, 0],
        ['plural_forms', 'Çoğul biçimleri', 'grammar', 'A1', 90, 1, 0, 1],
        ['nominativ', 'Nominativ (özne hali)', 'grammar', 'A1', 90, 1, 1, 0],
        ['akkusativ', 'Akkusativ (belirtme hali)', 'grammar', 'A1', 90, 1, 1, 0],
        ['possessive_articles', 'İyelik sıfatları (mein, dein ...)', 'grammar', 'A1', 90, 1, 1, 0],
        ['modal_koennen', 'können', 'grammar', 'A1', 90, 1, 1, 0],
        ['modal_muessen', 'müssen', 'grammar', 'A1', 90, 1, 1, 0],
        ['modal_wollen_moechten', 'wollen / möchten', 'grammar', 'A1', 90, 1, 1, 0],
        ['modal_duerfen_sollen', 'dürfen / sollen', 'grammar', 'A1', 90, 1, 1, 0],
        ['modal_moegen', 'mögen', 'grammar', 'A1', 88, 1, 1, 0],
        ['separable_verbs', 'Ayrılabilir fiiller', 'grammar', 'A1', 90, 1, 1, 0],
        ['imperative_basic', 'Emir kipi - temel', 'grammar', 'A1', 88, 1, 1, 0],
        ['time_clock', 'Saat söyleme', 'function', 'A1', 90, 1, 1, 0],
        ['days_months_dates', 'Günler, aylar, tarihler', 'vocabulary', 'A1', 90, 1, 1, 0],
        ['daily_routine', 'Günlük rutin anlatma', 'function', 'A1', 88, 1, 1, 0],
        ['food_drink', 'Yiyecek ve içecek', 'vocabulary', 'A1', 88, 1, 1, 0],
        ['restaurant', 'Restoranda sipariş', 'function', 'A1', 88, 1, 1, 0],
        ['shopping_prices', 'Alışveriş ve fiyatlar', 'function', 'A1', 88, 1, 1, 0],
        ['family', 'Aile', 'vocabulary', 'A1', 88, 1, 1, 0],
        ['home_rooms', 'Ev ve odalar', 'vocabulary', 'A1', 88, 1, 1, 0],
        ['city_transport', 'Şehir ve ulaşım', 'vocabulary', 'A1', 88, 1, 1, 0],
        ['directions', 'Yol tarifi', 'function', 'A1', 88, 1, 1, 0],
        ['doctor_appointment', 'Doktor randevusu', 'function', 'A1', 88, 1, 1, 0],
        ['work_basics_a1', 'İş yerinde temel iletişim', 'workplace', 'A1', 88, 1, 1, 0],

        /* --- A2 --- */
        ['dativ', 'Dativ (yönelme hali)', 'grammar', 'A2', 90, 1, 1, 0],
        ['akk_dat_distinction', 'Akkusativ / Dativ ayrımı', 'grammar', 'A2', 90, 1, 1, 0],
        ['dativ_pronouns', 'Dativ zamirleri (mir, dir, ihm ...)', 'grammar', 'A2', 90, 1, 1, 0],
        ['dativ_prepositions', 'Dativ edatları (mit, nach, aus ...)', 'grammar', 'A2', 90, 1, 1, 0],
        ['akkusativ_prepositions', 'Akkusativ edatları (für, ohne, gegen ...)', 'grammar', 'A2', 90, 1, 1, 0],
        ['wechselpraepositionen', 'Wechselpräpositionen (in, an, auf ...)', 'grammar', 'A2', 90, 1, 1, 0],
        ['wo_wohin', 'Wo? / Wohin? ayrımı', 'grammar', 'A2', 90, 1, 1, 0],
        ['perfekt', 'Perfekt zamanı', 'grammar', 'A2', 90, 1, 1, 0],
        ['perfekt_auxiliary', 'haben / sein seçimi', 'grammar', 'A2', 90, 1, 1, 0],
        ['partizip_ii_regular', 'Düzenli Partizip II (ge-...-t)', 'grammar', 'A2', 90, 1, 0, 1],
        ['partizip_ii_irregular', 'Düzensiz Partizip II (ge-...-en)', 'grammar', 'A2', 90, 1, 0, 1],
        ['praeteritum_sein_haben', 'Präteritum: war / hatte', 'grammar', 'A2', 90, 1, 1, 0],
        ['modal_past', 'Modal fiillerin geçmiş zamanı', 'grammar', 'A2', 88, 1, 1, 0],
        ['reflexive_verbs', 'Dönüşlü fiiller', 'grammar', 'A2', 88, 1, 1, 0],
        ['reflexive_pronouns', 'Dönüşlü zamirler (mich, dich, sich)', 'grammar', 'A2', 88, 1, 1, 0],
        ['adjective_basics', 'Sıfatların temel kullanımı', 'grammar', 'A2', 88, 1, 1, 0],
        ['comparative', 'Karşılaştırma (Komparativ)', 'grammar', 'A2', 88, 1, 1, 0],
        ['superlative', 'Üstünlük (Superlativ)', 'grammar', 'A2', 88, 1, 1, 0],
        ['subordinate_weil', 'weil ile yan cümle', 'grammar', 'A2', 90, 1, 1, 0],
        ['subordinate_dass', 'dass ile yan cümle', 'grammar', 'A2', 90, 1, 1, 0],
        ['subordinate_wenn', 'wenn ile yan cümle', 'grammar', 'A2', 90, 1, 1, 0],
        ['subordinate_als', 'als (geçmişte tek olay)', 'grammar', 'A2', 88, 1, 1, 0],
        ['subordinate_obwohl', 'obwohl ile yan cümle', 'grammar', 'A2', 88, 1, 1, 0],
        ['nebensatz_verb_final', 'Yan cümlede fiil sonda', 'grammar', 'A2', 90, 1, 1, 0],
        ['connectors_deshalb', 'deshalb / trotzdem', 'grammar', 'A2', 88, 1, 1, 0],
        ['conjunctions_aduso', 'aber, denn, oder, sondern, und', 'grammar', 'A2', 88, 1, 1, 0],
        ['zu_infinitiv', 'zu + Infinitiv', 'grammar', 'A2', 88, 1, 1, 0],
        ['workplace_email', 'İş e-postası yazma', 'workplace', 'A2', 88, 1, 1, 0],
        ['telephone_a2', 'Telefonda konuşma', 'workplace', 'A2', 88, 1, 1, 0],
        ['polite_requests', 'Kibar rica kalıpları', 'function', 'A2', 88, 1, 1, 0],
        ['renting_flat', 'Ev kiralama', 'daily_life', 'A2', 88, 1, 1, 0],
        ['health_a2', 'Sağlık ve hastalık', 'daily_life', 'A2', 88, 1, 1, 0],
        ['bank_a2', 'Banka işlemleri', 'daily_life', 'A2', 88, 1, 1, 0],
        ['authorities_a2', 'Resmi kurumlar (Amt)', 'daily_life', 'A2', 88, 1, 1, 0],
        ['travel_a2', 'Seyahat ve tatil', 'daily_life', 'A2', 88, 1, 1, 0],

        /* --- B1 --- */
        ['adj_decl_definite', 'Sıfat çekimi: belirli artikelden sonra', 'grammar', 'B1', 90, 1, 1, 0],
        ['adj_decl_indefinite', 'Sıfat çekimi: belirsiz artikelden sonra', 'grammar', 'B1', 90, 1, 1, 0],
        ['adj_decl_zero', 'Sıfat çekimi: artikelsiz', 'grammar', 'B1', 90, 1, 1, 0],
        ['relative_clause_basics', 'İlgi cümlesi temeli', 'grammar', 'B1', 90, 1, 1, 0],
        ['relative_nominativ', 'İlgi zamiri - Nominativ', 'grammar', 'B1', 90, 1, 1, 0],
        ['relative_akkusativ', 'İlgi zamiri - Akkusativ', 'grammar', 'B1', 90, 1, 1, 0],
        ['relative_dativ', 'İlgi zamiri - Dativ', 'grammar', 'B1', 90, 1, 1, 0],
        ['genitiv', 'Genitiv temeli', 'grammar', 'B1', 88, 1, 1, 0],
        ['passive_present', 'Präsens Passiv', 'grammar', 'B1', 90, 1, 1, 0],
        ['passive_past', 'Perfekt / Präteritum Passiv', 'grammar', 'B1', 88, 1, 1, 0],
        ['werden_uses', 'werden fiilinin kullanımları', 'grammar', 'B1', 88, 1, 1, 0],
        ['lassen', 'lassen fiili', 'grammar', 'B1', 85, 1, 1, 0],
        ['konjunktiv_ii_wuerde', 'Konjunktiv II: würde', 'grammar', 'B1', 90, 1, 1, 0],
        ['konjunktiv_ii_haette_waere', 'Konjunktiv II: hätte / wäre', 'grammar', 'B1', 90, 1, 1, 0],
        ['konjunktiv_ii_modal', 'Konjunktiv II: könnte / sollte / müsste', 'grammar', 'B1', 88, 1, 1, 0],
        ['unreal_conditions', 'Gerçek dışı koşullar', 'grammar', 'B1', 88, 1, 1, 0],
        ['um_zu', 'um ... zu', 'grammar', 'B1', 90, 1, 1, 0],
        ['ohne_statt_zu', 'ohne ... zu / statt ... zu', 'grammar', 'B1', 88, 1, 1, 0],
        ['damit', 'damit', 'grammar', 'B1', 88, 1, 1, 0],
        ['temporal_bevor_nachdem', 'bevor / nachdem', 'grammar', 'B1', 90, 1, 1, 0],
        ['temporal_waehrend_seit', 'während / seit / seitdem', 'grammar', 'B1', 88, 1, 1, 0],
        ['temporal_bis_sobald', 'bis / sobald', 'grammar', 'B1', 88, 1, 1, 0],
        ['n_deklination', 'n-Deklination', 'grammar', 'B1', 85, 1, 0, 1],
        ['verb_preposition', 'Fiil + edat kalıpları', 'grammar', 'B1', 90, 1, 1, 0],
        ['pronominal_adverbs', 'da-/wo- bileşikleri (darauf, worauf)', 'grammar', 'B1', 88, 1, 1, 0],
        ['tekamolo', 'TeKaMoLo: cümle içi sıralama', 'grammar', 'B1', 88, 1, 1, 0],
        ['indirect_questions', 'Dolaylı sorular', 'grammar', 'B1', 88, 1, 1, 0],
        ['formal_writing_b1', 'Resmi yazışma', 'workplace', 'B1', 88, 1, 1, 0],
        ['meetings_b1', 'Toplantı dili', 'workplace', 'B1', 88, 1, 1, 0],
        ['opinions_b1', 'Görüş bildirme ve tartışma', 'function', 'B1', 88, 1, 1, 0],
        ['complaints_b1', 'Şikayet ve sorun anlatma', 'function', 'B1', 88, 1, 1, 0],
        ['workplace_safety_b1', 'İş güvenliği talimatları', 'workplace', 'B1', 88, 1, 1, 0],
        ['contract_vocabulary_b1', 'Sözleşme ve iş hukuku kelimeleri', 'workplace', 'B1', 88, 1, 0, 0],
        ['b1_reading', 'B1 okuma anlama', 'skill', 'B1', 85, 0, 0, 0],
        ['b1_writing', 'B1 yazma', 'skill', 'B1', 85, 1, 1, 1],
        ['b1_speaking', 'B1 konuşma', 'skill', 'B1', 85, 1, 1, 0],
        ['b1_listening', 'B1 dinleme', 'skill', 'B1', 85, 0, 0, 0],
    ];

    $out = [];
    $order = 0;
    foreach ($rows as $r) {
        $out[] = [
            'code'                => $r[0],
            'name'                => $r[1],
            'category'            => $r[2],
            'cefr_level'          => $r[3],
            'mastery_threshold'   => $r[4],
            'is_critical'         => $r[5],
            'requires_production' => $r[6],
            'requires_spelling'   => $r[7],
            'sort_order'          => $order++,
        ];
    }
    return $out;
}

/** Hata kategorileri. */
function almancapro_error_categories(): array
{
    return [
        ['article_error', 'Artikel hatası', 'der/die/das veya ein/eine seçimi yanlış.', 'İsimleri her zaman artikeliyle birlikte öğren: der Tisch, die Lampe, das Auto.'],
        ['gender_error', 'Cinsiyet hatası', 'İsmin dilbilgisel cinsiyeti yanlış.', 'Cinsiyet Türkçede yok; kelimeyi artikeliyle ezberle, tahmin etme.'],
        ['case_error', 'Hal (Kasus) hatası', 'Nominativ/Akkusativ/Dativ seçimi yanlış.', 'Fiilin ve edatın hangi hali istediğini kontrol et.'],
        ['conjugation_error', 'Fiil çekimi hatası', 'Fiil kişiye göre yanlış çekilmiş.', 'Kişi zamiri ile fiil sonunu birlikte çalış: ich -e, du -st, er -t.'],
        ['word_order_error', 'Kelime sırası hatası', 'Cümlede öğe sırası yanlış.', 'Ana cümlede fiil her zaman ikinci sırada; yan cümlede sonda.'],
        ['spelling_error', 'Yazım hatası', 'Harf, umlaut veya ß yazımı yanlış.', 'ä, ö, ü ve ß karakterlerini doğru yaz; ss ile ß aynı değildir.'],
        ['capitalization_error', 'Büyük harf hatası', 'İsim küçük harfle yazılmış.', 'Almancada bütün isimler büyük harfle başlar.'],
        ['plural_error', 'Çoğul hatası', 'Çoğul biçimi yanlış.', 'Çoğulu kelimeyle birlikte öğren: der Tisch – die Tische.'],
        ['vocabulary_recall_error', 'Kelime hatırlama hatası', 'Doğru kelime hatırlanamadı.', 'Bu kelimeyi tekrar kuyruğunda daha sık göreceksin.'],
        ['preposition_error', 'Edat hatası', 'Yanlış edat veya edatın istediği hal yanlış.', 'Edatı istediği hal ile birlikte öğren: mit + Dativ.'],
        ['auxiliary_error', 'Yardımcı fiil hatası', 'Perfekt için haben/sein seçimi yanlış.', 'Yer değiştirme ve durum değişimi fiilleri sein alır.'],
        ['participle_error', 'Partizip II hatası', 'Partizip II biçimi yanlış.', 'Düzenli: ge-...-t, düzensiz: ge-...-en. Düzensizleri liste halinde çalış.'],
        ['separable_verb_error', 'Ayrılabilir fiil hatası', 'Ön ek yanlış yere konmuş.', 'Ana cümlede ön ek cümlenin sonuna gider: Ich stehe um 7 Uhr auf.'],
        ['pronoun_error', 'Zamir hatası', 'Zamir seçimi veya hali yanlış.', 'Zamir tablosunu hal ile birlikte çalış: ich/mich/mir.'],
        ['adjective_ending_error', 'Sıfat takısı hatası', 'Sıfatın çekim eki yanlış.', 'Sıfat çekimi artikel türüne, cinsiyete ve hale bağlıdır.'],
        ['semantic_error', 'Anlam hatası', 'Cevap istenen anlamı karşılamıyor.', 'Sorunun ne istediğini tekrar oku; kelimenin Türkçe karşılığını kontrol et.'],
    ];
}
