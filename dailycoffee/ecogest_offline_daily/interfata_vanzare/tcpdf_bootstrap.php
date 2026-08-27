<?php

$tcpdfCandidates = [
    __DIR__ . '/tcpdf/tcpdf.php',
    dirname(dirname(__DIR__)) . '/online/tcpdf/tcpdf.php',
    'D:/xampp2/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php',
];

$tcpdfLoaded = false;

foreach ($tcpdfCandidates as $tcpdfPath) {
    if (is_file($tcpdfPath)) {
        require_once $tcpdfPath;
        $tcpdfLoaded = true;
        break;
    }
}

if (!$tcpdfLoaded) {
    http_response_code(500);
    exit('Biblioteca TCPDF nu este disponibila in mediul local.');
}