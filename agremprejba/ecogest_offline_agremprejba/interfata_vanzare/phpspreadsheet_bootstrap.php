<?php

$phpspreadsheetAutoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    dirname(dirname(__DIR__)) . '/online/vendor/autoload.php',
    'D:/xampp2/phpMyAdmin/vendor/autoload.php',
];

$phpspreadsheetLoaded = false;

foreach ($phpspreadsheetAutoloadCandidates as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        $phpspreadsheetLoaded = true;
        break;
    }
}

if (!$phpspreadsheetLoaded) {
    http_response_code(500);
    exit('Biblioteca PhpSpreadsheet nu este disponibila in mediul local.');
}