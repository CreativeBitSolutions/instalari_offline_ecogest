<?php

$offlineConfigName = 'config_offline_agremprejba.json';
$offlineRootCandidates = array_unique([
    dirname(__DIR__, 2),
    dirname(__DIR__, 3),
]);
$offlineClientRoot = '';

foreach ($offlineRootCandidates as $candidate) {
    if (is_file($candidate . DIRECTORY_SEPARATOR . $offlineConfigName)
        && is_file($candidate . DIRECTORY_SEPARATOR . 'offline_config_loader.php')) {
        $offlineClientRoot = $candidate;
        break;
    }
}

if ($offlineClientRoot === '') {
    throw new RuntimeException(
        'Configurarea externa Agremprejma nu a fost gasita. Au fost verificate: '
        . implode(', ', $offlineRootCandidates)
    );
}

if (!defined('OFFLINE_EXTERNAL_CONFIG_FILE')) {
    define('OFFLINE_EXTERNAL_CONFIG_FILE', $offlineClientRoot . DIRECTORY_SEPARATOR . $offlineConfigName);
}
require_once $offlineClientRoot . DIRECTORY_SEPARATOR . 'offline_config_loader.php';

unset($offlineConfigName, $offlineRootCandidates, $offlineClientRoot);
