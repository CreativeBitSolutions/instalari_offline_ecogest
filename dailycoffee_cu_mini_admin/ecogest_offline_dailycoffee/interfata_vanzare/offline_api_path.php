<?php
require_once __DIR__ . '/offline_external_config.php';

function offline_api_root_path()
{
    return (string)offline_config_value('api_root_absolute');
}

function offline_api_web_root()
{
    return (string)offline_config_value('api_web_root');
}

function offline_api_path()
{
    $path = rtrim(offline_api_root_path(), "\\/");
    foreach (func_get_args() as $part) {
        $part = trim((string)$part, "\\/");
        if ($part !== '') {
            $path .= DIRECTORY_SEPARATOR . $part;
        }
    }

    return $path;
}

function offline_api_ensure_dir($path)
{
    return is_dir($path) || mkdir($path, 0777, true);
}

function offline_db_runtime_path()
{
    return (string)offline_config_value('db_runtime_file');
}

function offline_sync_online_api_root_path()
{
    return (string)offline_config_value('sync_online_api_root_absolute', offline_api_path('sincronizare_online_api'));
}

function offline_sync_online_api_web_root()
{
    return (string)offline_config_value('sync_online_api_web_root', offline_api_web_root() . '/sincronizare_online_api');
}
