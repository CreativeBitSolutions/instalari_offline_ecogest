<?php
declare(strict_types=1);

$params = [];
parse_str((string)($_SERVER['QUERY_STRING'] ?? ''), $params);
unset($params['tab']);

$target = 'vanzare_importa_comanda_qr.php';
if (!empty($params)) {
    $target .= '?' . http_build_query($params);
}

http_response_code(303);
header('Location: ' . $target);
exit;
