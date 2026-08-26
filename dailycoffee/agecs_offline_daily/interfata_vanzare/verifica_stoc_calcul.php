<?php

include __DIR__ . '/session.php';
require_once __DIR__ . '/offline_stock_online_lib.php';

$productId = (int)($_GET['cod_produs'] ?? 0);
offline_stock_online_send('stock', array('cod_produs' => $productId));
