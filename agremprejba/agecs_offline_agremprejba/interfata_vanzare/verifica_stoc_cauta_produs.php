<?php

include __DIR__ . '/session.php';
require_once __DIR__ . '/offline_stock_online_lib.php';

$query = trim((string)($_GET['q'] ?? ''));
offline_stock_online_send('search', array('q' => $query));
