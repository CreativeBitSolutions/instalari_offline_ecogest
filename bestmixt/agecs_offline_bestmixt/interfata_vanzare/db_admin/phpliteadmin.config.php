<?php
require_once dirname(__DIR__) . '/offline_external_config.php';

$password = (string)offline_config_value('db_admin_password');

$directory = false;
$subdirectories = false;

$databases = array(
    array(
        'path' => (string)offline_config_value('db_runtime_file'),
        'name' => (string)offline_config_value('db_admin_name')
    ),
);

$theme = 'phpliteadmin.css';
$language = 'en';
$rowsNum = 50;
$charsNum = 300;
$maxSavedQueries = 20;

$custom_functions = array(
    'md5',
    'sha1',
    'time',
    'strtotime',
);

$cookie_name = 'agecs_pos_sqlite_admin';
$debug = true;
$allowed_extensions = array('db', 'db3', 'sqlite', 'sqlite3');
