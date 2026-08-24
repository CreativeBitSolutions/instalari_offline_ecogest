<?php

if (!defined('OFFLINE_EXTERNAL_CONFIG_FILE')) {
    throw new RuntimeException('Fisierul extern de configurare nu a fost definit.');
}

if (!function_exists('offline_config_path')) {
    function offline_config_path(): string
    {
        return OFFLINE_EXTERNAL_CONFIG_FILE;
    }
}

if (!function_exists('offline_config_all')) {
    function offline_config_all(bool $refresh = false): array
    {
        static $config = null;
        if (!$refresh && is_array($config)) {
            return $config;
        }

        $path = offline_config_path();
        if (!is_file($path)) {
            throw new RuntimeException('Lipseste configurarea externa: ' . $path);
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Configurarea externa nu poate fi citita: ' . $path);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Configurarea externa nu este JSON valid: ' . json_last_error_msg());
        }

        $required = [
            'client_id',
            'sync_client_id',
            'cod_locatie_default',
            'installation_uuid',
            'app_name',
            'driver',
            'live_id',
            'no_session_validation',
            'db_seed_file',
            'db_runtime_file',
            'db_local_root_absolute',
            'apache_app_path',
            'api_root_relative',
            'api_root_absolute',
            'api_web_root',
            'sync_online_api_root_absolute',
            'sync_online_api_web_root',
            'offline_mode',
            'allow_remote_scale',
            'use_parent_login',
            'use_parent_logout',
            'sync_state_url',
            'db_admin_password',
            'db_admin_name',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $decoded) || $decoded[$key] === '') {
                throw new RuntimeException('Configurarea externa nu contine cheia obligatorie: ' . $key);
            }
        }
        $config = $decoded;
        return $config;
    }
}

if (!function_exists('offline_config_value')) {
    function offline_config_value(string $key, $default = null)
    {
        $config = offline_config_all();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
}
