<?php
/**
 * config_loader.php
 * Expune valorile din configurarea externa ca variabile PHP.
 * Include acest fisier o singura data la bootstrap.
 */

require_once __DIR__ . '/offline_external_config.php';
$_config = offline_config_all();

// Valori de runtime expuse ca variabile PHP
if (!function_exists('app_config_resolve_path')) {
    function app_config_resolve_path($path)
    {
        $path = (string)$path;
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || strpos($path, '\\\\') === 0 || strpos($path, '/') === 0) {
            return $path;
        }

        return __DIR__ . '/' . ltrim($path, "\\/");
    }
}

$app_client_id          = (int)$_config['client_id'];
$app_cod_locatie        = (int)$_config['cod_locatie_default'];
$app_name               = (string)$_config['app_name'];
$app_db_seed_file       = app_config_resolve_path($_config['db_seed_file']);
$app_db_runtime_file    = app_config_resolve_path($_config['db_runtime_file']);
$app_db_local_root_absolute = (string)$_config['db_local_root_absolute'];
$app_api_root_relative  = (string)$_config['api_root_relative'];
$app_api_root_absolute  = (string)$_config['api_root_absolute'];
$app_api_web_root       = (string)$_config['api_web_root'];
$app_sync_online_api_root_absolute = (string)$_config['sync_online_api_root_absolute'];
$app_sync_online_api_web_root = (string)$_config['sync_online_api_web_root'];
$app_offline_mode       = (bool)$_config['offline_mode'];
$app_allow_remote_scale = (bool)$_config['allow_remote_scale'];
$app_use_parent_login   = (bool)$_config['use_parent_login'];
$app_use_parent_logout  = (bool)$_config['use_parent_logout'];

unset($_config);
