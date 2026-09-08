<?php

if (!function_exists('dictionar_spv')) {
    function dictionar_spv($text) {
        $trans = array(
            'ș' => 's','Ș' => 'S','Ş' => 'S',
            'ț' => 't','Ț' => 'T',
            'ă' => 'a','Ă' => 'A',
            'î' => 'i','Î' => 'I',
            'â' => 'a','Â' => 'A',
        );
        return strtr($text, $trans);
    }
}

function normalize_country_name($s) {
    $s = trim((string)$s);
    if ($s === '') return '';

    $s = dictionar_spv($s);
    $s = strtoupper($s);

    $s = str_replace(
        ["\r","\n","\t",".",",",";",";",":","’","'","\"","(",")","[","]","{","}","/","\\","-","_"],
        " ",
        $s
    );

    $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    return trim($s);
}

function load_country_lists() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $roPath = __DIR__ . '/countrylist_ro.json';
    $enPath = __DIR__ . '/countrylist_en.json';

    $ro = [];
    $en = [];

    if (is_file($roPath)) {
        $tmp = json_decode(file_get_contents($roPath), true);
        if (is_array($tmp)) $ro = $tmp;
    }
    if (is_file($enPath)) {
        $tmp = json_decode(file_get_contents($enPath), true);
        if (is_array($tmp)) $en = $tmp;
    }

    $cache = ['ro' => $ro, 'en' => $en];
    return $cache;
}

function build_country_name_index() {
    static $index = null;
    static $validCodes = null;

    if ($index !== null) {
        return [$index, $validCodes];
    }

    $index = [];
    $validCodes = [];

    $lists = load_country_lists();
    $all = [$lists['ro'], $lists['en']];

    foreach ($all as $map) {
        foreach ($map as $code => $name) {
            $code = strtoupper(trim((string)$code));
            if (!preg_match('/^[A-Z]{2}$/', $code)) continue;

            $validCodes[$code] = true;

            $n1 = normalize_country_name($name);
            if ($n1 !== '') $index[$n1] = $code;

            // variantă fără paranteze (ex: "Myanmar (Birmania)" -> "Myanmar")
            $noParen = preg_replace('/\s*\(.*?\)\s*/', ' ', (string)$name);
            $n2 = normalize_country_name($noParen);
            if ($n2 !== '') $index[$n2] = $code;
        }
    }

    // sinonime frecvente (în plus față de liste)
    $syn = [
        'SUA' => 'US',
        'USA' => 'US',
        'UNITED STATES' => 'US',
        'UNITED STATES OF AMERICA' => 'US',
        'STATELE UNITE' => 'US',
        'STATELE UNITE ALE AMERICII' => 'US',

        'UK' => 'GB',
        'UNITED KINGDOM' => 'GB',
        'MAREA BRITANIE' => 'GB',
        'GREAT BRITAIN' => 'GB',
        'BRITAIN' => 'GB',

        'OLANDA' => 'NL',
        'THE NETHERLANDS' => 'NL',
        'NETHERLANDS' => 'NL',

        'HONGKONG' => 'HK',
        'HONG KONG' => 'HK',
        'HONG KONG SAR' => 'HK',

        'MACAO' => 'MO',
        'MACAU' => 'MO',

        'CEHIA' => 'CZ',
        'CZECH REPUBLIC' => 'CZ',

        'COASTA DE FILDES' => 'CI',
        'IVORY COAST' => 'CI',
        'COTE D IVOIRE' => 'CI',

        'BIRMANIA' => 'MM',
        'MYANMAR' => 'MM',

        'MACEDONIA' => 'MK',
        'MACEDONIA DE NORD' => 'MK',
        'NORTH MACEDONIA' => 'MK',
    ];

    foreach ($syn as $k => $v) {
        $nk = normalize_country_name($k);
        if ($nk !== '') $index[$nk] = strtoupper($v);
        $validCodes[strtoupper($v)] = true;
    }

    return [$index, $validCodes];
}

function getCountryCode($countryName) {
    $raw = trim((string)$countryName);
    if ($raw === '') return '';

    $rawUp = strtoupper(trim($raw));

    // dacă e deja ISO alpha-2
    [$index, $valid] = build_country_name_index();
    if (preg_match('/^[A-Z]{2}$/', $rawUp) && isset($valid[$rawUp])) {
        return $rawUp;
    }

    $key = normalize_country_name($raw);
    return $index[$key] ?? '';
}

function getCountryListRoSorted() {
    $lists = load_country_lists();
    $ro = $lists['ro'];
    if (!is_array($ro)) $ro = [];
    asort($ro, SORT_STRING);
    return $ro; // code => name
}
