<?php

if (!function_exists('woo_sync_cfg')) {
    function woo_sync_cfg(): array {
        $path = __DIR__ . '/woo_sync_config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Lipsă fișier configurare Woo Sync: includes/woo_sync_config.php');
        }

        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new RuntimeException('Config Woo Sync invalid.');
        }

        return $cfg;
    }
}

if (!function_exists('woo_sync_http_get')) {
    function woo_sync_http_get(string $path, array $query = []): array {
        $cfg = woo_sync_cfg();
        $base = rtrim((string)($cfg['base_url'] ?? ''), '/');
        if ($base === '') {
            throw new RuntimeException('base_url lipsă în config Woo Sync.');
        }

        $url = $base . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'X-RS-KEY: ' . (string)($cfg['api_key'] ?? ''),
            'X-RS-SECRET: ' . (string)($cfg['api_secret'] ?? ''),
        ];

        $useHmac = !empty($cfg['use_hmac']);
        if ($useHmac) {
            $timestamp = (string) time();
            $routePath = parse_url($url, PHP_URL_PATH) ?: '';
            $queryArr = $query;
            $canonical = "GET\n" . $routePath . "\n" . $timestamp . "\n" . json_encode($queryArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha256', $canonical, (string)($cfg['api_secret'] ?? ''));
            $headers[] = 'X-RS-TIMESTAMP: ' . $timestamp;
            $headers[] = 'X-RS-SIGNATURE: ' . $signature;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)($cfg['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => !empty($cfg['verify_ssl']),
            CURLOPT_SSL_VERIFYHOST => !empty($cfg['verify_ssl']) ? 2 : 0,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Eroare cURL Woo Sync: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Woo Sync a răspuns cu HTTP ' . $httpCode . '. Body: ' . (string)$raw);
        }

        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Răspuns JSON invalid de la Woo Sync.');
        }

        return $json;
    }
}


if (!function_exists('woo_sync_fetch_wp_order_details')) {
    function woo_sync_fetch_wp_order_details(int $wooOrderId): array {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru detalii WordPress.');
        }

        $cfg = woo_sync_cfg();
        $baseUrl = trim((string)($cfg['wp_order_details_endpoint'] ?? ''));
        $apiKey = trim((string)($cfg['wp_order_details_api_key'] ?? ''));

        if ($baseUrl === '') {
            throw new RuntimeException('Lipsește wp_order_details_endpoint în config Woo Sync.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('Lipsește wp_order_details_api_key în config Woo Sync.');
        }

        $url = rtrim($baseUrl, '/') . '/' . rawurlencode((string)$wooOrderId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-GP-POS-Key: ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => (int)($cfg['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => !empty($cfg['verify_ssl']),
            CURLOPT_SSL_VERIFYHOST => !empty($cfg['verify_ssl']) ? 2 : 0,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Eroare cURL detalii WordPress Woo: ' . $error);
        }

        $json = json_decode((string)$raw, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($json) && isset($json['message'])
                ? (string)$json['message']
                : (string)$raw;

            throw new RuntimeException('Endpointul WordPress pentru detalii Woo a răspuns cu HTTP ' . $httpCode . '. Body: ' . $message);
        }

        if (!is_array($json)) {
            throw new RuntimeException('Răspuns JSON invalid de la endpointul WordPress pentru detalii Woo.');
        }

        if (array_key_exists('success', $json) && empty($json['success'])) {
            $message = isset($json['message']) ? (string)$json['message'] : 'success=false';
            throw new RuntimeException('Endpointul WordPress pentru detalii Woo a returnat eroare: ' . $message);
        }

        return $json;
    }
}

if (!function_exists('woo_sync_h')) {
    function woo_sync_h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('woo_sync_money')) {
    function woo_sync_money($v): string {
        return number_format((float)$v, 2, ',', '.');
    }
}

if (!function_exists('woo_sync_text_lower')) {
    function woo_sync_text_lower(string $text): string {
        return function_exists('mb_strtolower')
            ? mb_strtolower($text, 'UTF-8')
            : strtolower($text);
    }
}

if (!function_exists('woo_sync_text_contains_any')) {
    function woo_sync_text_contains_any(string $haystack, array $needles): bool {
        $haystack = woo_sync_text_lower($haystack);

        foreach ($needles as $needle) {
            $needle = woo_sync_text_lower(trim((string)$needle));
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('woo_sync_pick_scalar')) {
    function woo_sync_pick_scalar(array $data, array $keys): string {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                $value = trim((string)$data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('woo_sync_bool_from_value')) {
    function woo_sync_bool_from_value($value): ?bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((int)$value === 1) {
                return true;
            }
            if ((int)$value === 0) {
                return false;
            }
        }

        if (is_string($value)) {
            $v = woo_sync_text_lower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'da', 'paid', 'platita', 'plătită'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'no', 'nu', 'unpaid', 'neplatita', 'neplătită', 'not_paid'], true)) {
                return false;
            }
        }

        return null;
    }
}

if (!function_exists('woo_sync_map_payment_status_key')) {
    function woo_sync_map_payment_status_key(string $raw): string {
        $v = woo_sync_text_lower(trim($raw));
        $v = str_replace([' ', '-'], '_', $v);

        if ($v === '') {
            return '';
        }

        if (in_array($v, ['paid', 'platita', 'plătită', 'completed', 'captured', 'succeeded', 'success', 'successful'], true)) {
            return 'paid';
        }
        if (in_array($v, ['unpaid', 'not_paid', 'neplatita', 'neplătită'], true)) {
            return 'unpaid';
        }
        if (in_array($v, ['pending', 'pending_payment', 'awaiting_payment', 'on_hold', 'onhold'], true)) {
            return 'pending';
        }
        if (in_array($v, ['processing', 'in_process', 'in_progress', 'procesare'], true)) {
            return 'processing';
        }
        if (in_array($v, ['authorized', 'authorised', 'autorizata', 'autorizată'], true)) {
            return 'authorized';
        }
        if (in_array($v, ['failed', 'failure', 'declined', 'error', 'cancelled', 'canceled', 'void'], true)) {
            return 'failed';
        }
        if (in_array($v, ['refunded', 'rambursata', 'rambursată'], true)) {
            return 'refunded';
        }
        if (in_array($v, ['partially_refunded', 'partial_refund', 'partial_refunded'], true)) {
            return 'partially_refunded';
        }

        return '';
    }
}

if (!function_exists('woo_sync_payment_status_meta')) {
    function woo_sync_payment_status_meta(string $key): array {
        switch ($key) {
            case 'paid':
                return ['label' => 'Plătită', 'class' => 'text-bg-success'];
            case 'unpaid':
                return ['label' => 'Neplătită', 'class' => 'text-bg-danger'];
            case 'pending':
                return ['label' => 'În așteptare', 'class' => 'text-bg-warning text-dark'];
            case 'processing':
                return ['label' => 'În procesare', 'class' => 'text-bg-info text-dark'];
            case 'authorized':
                return ['label' => 'Autorizată', 'class' => 'text-bg-info text-dark'];
            case 'cod':
                return ['label' => 'La livrare', 'class' => 'text-bg-secondary'];
            case 'failed':
                return ['label' => 'Eșuată', 'class' => 'text-bg-danger'];
            case 'refunded':
                return ['label' => 'Rambursată', 'class' => 'text-bg-secondary'];
            case 'partially_refunded':
                return ['label' => 'Rambursată parțial', 'class' => 'text-bg-secondary'];
            default:
                return ['label' => 'Necunoscută', 'class' => 'text-bg-light text-dark border'];
        }
    }
}

if (!function_exists('woo_sync_order_payment_info')) {
    function woo_sync_order_payment_info(array $order): array {
        $payment = (isset($order['payment']) && is_array($order['payment'])) ? (array)$order['payment'] : [];

        $methodCode = woo_sync_pick_scalar($order, ['payment_method', 'payment_method_code', 'payment_code']);
        if ($methodCode === '' && $payment) {
            $methodCode = woo_sync_pick_scalar($payment, ['method', 'method_code', 'code', 'payment_method']);
        }

        $methodTitle = woo_sync_pick_scalar($order, ['payment_method_title', 'payment_title', 'payment_method_name']);
        if ($methodTitle === '' && $payment) {
            $methodTitle = woo_sync_pick_scalar($payment, ['method_title', 'title', 'name', 'payment_method_title']);
        }

        $statusRaw = woo_sync_pick_scalar($order, ['payment_status', 'paymentStatus', 'payment_state', 'paid_status']);
        if ($statusRaw === '' && $payment) {
            $statusRaw = woo_sync_pick_scalar($payment, ['status', 'payment_status', 'state']);
        }

        $transactionId = woo_sync_pick_scalar($order, ['transaction_id', 'payment_transaction_id']);
        if ($transactionId === '' && $payment) {
            $transactionId = woo_sync_pick_scalar($payment, ['transaction_id', 'transaction', 'id']);
        }

        $datePaidRaw = woo_sync_pick_scalar($order, ['date_paid', 'date_paid_gmt', 'paid_at', 'payment_date']);
        if ($datePaidRaw === '' && $payment) {
            $datePaidRaw = woo_sync_pick_scalar($payment, ['date_paid', 'paid_at', 'payment_date']);
        }

        $paidBool = null;
        foreach (['paid', 'is_paid', 'payment_paid'] as $key) {
            if (array_key_exists($key, $order)) {
                $paidBool = woo_sync_bool_from_value($order[$key]);
                if ($paidBool !== null) {
                    break;
                }
            }
        }

        if ($paidBool === null && $payment) {
            foreach (['paid', 'is_paid'] as $key) {
                if (array_key_exists($key, $payment)) {
                    $paidBool = woo_sync_bool_from_value($payment[$key]);
                    if ($paidBool !== null) {
                        break;
                    }
                }
            }
        }

        $needsPayment = null;
        foreach (['needs_payment', 'requires_payment'] as $key) {
            if (array_key_exists($key, $order)) {
                $needsPayment = woo_sync_bool_from_value($order[$key]);
                if ($needsPayment !== null) {
                    break;
                }
            }
        }

        $methodSearch = trim($methodCode . ' ' . $methodTitle);
        $isCashOnDelivery = $methodSearch !== '' && woo_sync_text_contains_any($methodSearch, [
            'cod',
            'cash on delivery',
            'ramburs',
            'plata la livrare',
            'plată la livrare',
            'numerar la livrare',
        ]);

        $key = '';
        if ($paidBool === true || $datePaidRaw !== '') {
            $key = 'paid';
        } elseif ($statusRaw !== '') {
            $key = woo_sync_map_payment_status_key($statusRaw);
        } elseif ($paidBool === false) {
            $key = $isCashOnDelivery ? 'cod' : 'unpaid';
        } elseif ($needsPayment === true) {
            $key = 'pending';
        } elseif ($isCashOnDelivery) {
            $key = 'cod';
        }

        if ($key === '') {
            $orderStatus = woo_sync_text_lower(trim((string)($order['status'] ?? '')));
            $orderStatus = str_replace([' ', '-'], '_', $orderStatus);

            if (in_array($orderStatus, ['completed', 'processing'], true)) {
                $key = 'paid';
            } elseif (in_array($orderStatus, ['pending', 'on_hold'], true)) {
                $key = 'pending';
            } elseif (in_array($orderStatus, ['failed', 'cancelled', 'canceled'], true)) {
                $key = 'failed';
            } elseif ($orderStatus === 'refunded') {
                $key = 'refunded';
            } else {
                $key = 'unknown';
            }
        }

        $meta = woo_sync_payment_status_meta($key);
        $methodDisplay = $methodTitle !== '' ? $methodTitle : $methodCode;

        return [
            'key' => $key,
            'label' => $meta['label'],
            'class' => $meta['class'],
            'method' => $methodDisplay,
            'method_code' => $methodCode,
            'raw_status' => $statusRaw,
            'transaction_id' => $transactionId,
            'date_paid' => $datePaidRaw,
        ];
    }
}

if (!function_exists('woo_sync_payment_badge_html')) {
    function woo_sync_payment_badge_html(array $order): string {
        if (!$order) {
            return '<span class="text-muted">-</span>';
        }

        $info = woo_sync_order_payment_info($order);
        $html = '<span class="badge ' . woo_sync_h($info['class']) . '">' . woo_sync_h($info['label']) . '</span>';

        $extra = [];

        if ((string)$info['method'] !== '') {
            $extra[] = 'Metodă: ' . (string)$info['method'];
        }

        if ((string)$info['date_paid'] !== '') {
            $paidAt = function_exists('woo_sync_parse_bucharest_datetime')
                ? woo_sync_parse_bucharest_datetime($info['date_paid'])
                : null;

            $extra[] = 'Achitat la: ' . ($paidAt ? $paidAt->format('d.m.Y H:i') : (string)$info['date_paid']);
        }

        if ($extra) {
            $html .= '<div class="small-muted mt-1">' . woo_sync_h(implode(' · ', $extra)) . '</div>';
        }

        return $html;
    }
}

if (!function_exists('woo_sync_collect_order_delivery_texts')) {
    function woo_sync_collect_order_delivery_texts($value, string $keyHint = ''): array {
        $texts = [];

        $interestingKeys = [
            'shipping',
            'delivery',
            'livrare',
            'ridicare',
            'pickup',
            'method',
            'metoda',
            'fulfillment',
            'order_type',
            'local',
            'transport',
        ];

        $keyIsInteresting = woo_sync_text_contains_any($keyHint, $interestingKeys);

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $childKey = trim($keyHint . ' ' . (string)$k);
                $texts = array_merge($texts, woo_sync_collect_order_delivery_texts($v, $childKey));
            }

            return $texts;
        }

        if (is_scalar($value) && $keyIsInteresting) {
            $text = trim((string)$value);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }
}

if (!function_exists('woo_sync_order_is_restaurant_pickup')) {
    function woo_sync_order_is_restaurant_pickup(array $order): bool {
        $cfg = woo_sync_cfg();
        $feeCfg = (array)($cfg['delivery_fee'] ?? []);
        $pickupKeywords = (array)($feeCfg['pickup_keywords'] ?? []);

        if (!$pickupKeywords) {
            $pickupKeywords = [
                'ridicare',
                'ridică',
                'ridica',
                'ridicare restaurant',
                'ridicare de la restaurant',
                'pickup',
                'pick-up',
                'pick up',
                'local_pickup',
                'local pickup',
                'takeaway',
                'take away',
                'take-away',
            ];
        }

        // 1. Verificări directe pentru câmpuri booleene uzuale.
        // Important: valoarea false / "false" / 0 NU trebuie interpretată drept pickup doar pentru că numele cheii conține pickup.
        $boolPickupKeys = [
            'is_pickup',
            'shipping_is_pickup',
            'pickup',
            'local_pickup',
            'restaurant_pickup',
            'ridicare',
        ];

        $boolContainers = [$order];
        foreach (['shipping', 'delivery', 'fulfillment'] as $containerKey) {
            if (isset($order[$containerKey]) && is_array($order[$containerKey])) {
                $boolContainers[] = (array)$order[$containerKey];
            }
        }

        foreach ($boolContainers as $container) {
            foreach ($boolPickupKeys as $key) {
                if (array_key_exists($key, $container)) {
                    $boolValue = woo_sync_bool_from_value($container[$key]);
                    if ($boolValue === true) {
                        return true;
                    }
                }
            }
        }

        // 2. Câmpuri directe unde Woo / pluginul poate trimite metoda de livrare.
        $candidateTexts = [];

        $directKeys = [
            'shipping_method',
            'shipping_method_title',
            'shipping_title',
            'method_title',
            'delivery_method',
            'delivery_method_title',
            'delivery_type',
            'fulfillment_method',
            'order_type',

            // IMPORTANT: în unele payload-uri metoda apare în notă/text comandă.
            'customer_note',
            'note',
            'notes',
            'order_note',
            'order_notes',
            'client_note',
            'observatii',
            'observatii_comanda',
            'comentariu',
            'comentarii',
        ];

        foreach ($directKeys as $key) {
            if (isset($order[$key]) && is_scalar($order[$key])) {
                $candidateTexts[] = (string)$order[$key];
            }
        }

        // 3. Verificăm doar valorile relevante din zone nested, nu tot JSON-ul comenzii.
        // Căutarea în tot payload-ul producea fals pozitiv din cheia is_pickup=false.
        $nestedKeys = [
            'shipping_lines',
            'shipping',
            'delivery',
            'fulfillment',
            'meta_data',
            'meta',
            'payment',
            'customer',
        ];

        foreach ($nestedKeys as $key) {
            if (isset($order[$key])) {
                $candidateTexts = array_merge(
                    $candidateTexts,
                    woo_sync_collect_order_delivery_texts($order[$key], $key)
                );
            }
        }

        $candidateTexts = array_values(array_unique(array_filter(array_map('trim', $candidateTexts))));

        // 4. Expresii clare de ridicare.
        $strongPickupPhrases = [
            'metoda de livrare: ridicare',
            'metoda livrare: ridicare',
            'livrare: ridicare',
            'ridicare de la restaurant',
            'ridicare restaurant',
            'local_pickup',
            'local pickup',
            'pick up',
            'pick-up',
            'pickup',
            'takeaway',
            'take away',
            'take-away',
        ];

        foreach ($candidateTexts as $text) {
            if (woo_sync_text_contains_any($text, $strongPickupPhrases)) {
                return true;
            }
        }

        // 5. Fallback pe lista din config, tot doar pe valori relevante.
        foreach ($candidateTexts as $text) {
            if (woo_sync_text_contains_any($text, $pickupKeywords)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('woo_sync_order_products_total_with_tax')) {
    function woo_sync_order_products_total_with_tax(array $order): float {
        $products = (array)($order['products'] ?? []);
        $total = 0.0;

        foreach ($products as $product) {
            $product = (array)$product;

            $qty = (float)($product['quantity'] ?? 0);
            $lineTotal = (float)($product['line_total'] ?? 0);
            $lineTax = (float)($product['line_total_tax'] ?? 0);
            $lineTotalWithTax = $lineTotal + $lineTax;

            // Fallback dacă API-ul nu trimite line_total / line_total_tax.
            if ($lineTotalWithTax <= 0 && $qty > 0) {
                $lineTotalWithTax = (float)($product['price'] ?? 0) * $qty;
            }

            if ($lineTotalWithTax > 0) {
                $total += $lineTotalWithTax;
            }
        }

        // Fallback final, în caz că lipsesc totalurile pe produse.
        if ($total <= 0 && isset($order['total'])) {
            $total = (float)$order['total'];
        }

        return round($total, 2);
    }
}

if (!function_exists('woo_sync_array_path_value')) {
    function woo_sync_array_path_value(array $data, array $path) {
        $value = $data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }
}

if (!function_exists('woo_sync_numeric_path_value')) {
    function woo_sync_numeric_path_value(array $data, array $path): ?float {
        $value = woo_sync_array_path_value($data, $path);
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        return null;
    }
}

if (!function_exists('woo_sync_order_shipping_total_with_tax')) {
    function woo_sync_order_shipping_total_with_tax(array $order): float {
        // 1. Prioritate: câmpul explicit trimis de pluginul Woo modificat.
        foreach ([
            ['shipping_total_incl_tax'],
            ['shipping', 'total_incl_tax'],
            ['shipping_total_with_tax'],
            ['shipping', 'total_with_tax'],
        ] as $path) {
            $value = woo_sync_numeric_path_value($order, $path);
            if ($value !== null) {
                return round($value, 2);
            }
        }

        // 2. Fallback compatibil: total fără TVA + TVA, la nivel rădăcină.
        $rootTotal = woo_sync_numeric_path_value($order, ['shipping_total_excl_tax']);
        if ($rootTotal === null) {
            $rootTotal = woo_sync_numeric_path_value($order, ['shipping_total']);
        }
        if ($rootTotal !== null) {
            $rootTax = woo_sync_numeric_path_value($order, ['shipping_tax']) ?? 0.0;
            return round($rootTotal + $rootTax, 2);
        }

        // 3. Fallback compatibil: total fără TVA + TVA, în obiectul shipping.
        $shippingTotal = woo_sync_numeric_path_value($order, ['shipping', 'total_excl_tax']);
        if ($shippingTotal === null) {
            $shippingTotal = woo_sync_numeric_path_value($order, ['shipping', 'total']);
        }
        if ($shippingTotal !== null) {
            $shippingTax = woo_sync_numeric_path_value($order, ['shipping', 'tax']) ?? 0.0;
            return round($shippingTotal + $shippingTax, 2);
        }

        // 4. Fallback pe liniile de transport.
        $lineTotal = 0.0;
        $hasShippingLine = false;
        if (!empty($order['shipping_lines']) && is_array($order['shipping_lines'])) {
            foreach ($order['shipping_lines'] as $shippingLine) {
                $shippingLine = (array)$shippingLine;
                $lineIncl = woo_sync_numeric_path_value($shippingLine, ['total_incl_tax']);
                if ($lineIncl !== null) {
                    $lineTotal += $lineIncl;
                    $hasShippingLine = true;
                    continue;
                }

                $lineBase = woo_sync_numeric_path_value($shippingLine, ['total']) ?? 0.0;
                $lineTax = woo_sync_numeric_path_value($shippingLine, ['total_tax']);
                if ($lineTax === null) {
                    $lineTax = woo_sync_numeric_path_value($shippingLine, ['tax']) ?? 0.0;
                }
                $lineTotal += $lineBase + $lineTax;
                $hasShippingLine = true;
            }
        }

        return $hasShippingLine ? round($lineTotal, 2) : 0.0;
    }
}

if (!function_exists('woo_sync_delivery_fee_mappings')) {
    function woo_sync_delivery_fee_mappings(array $feeCfg): array {
        $mappings = (array)($feeCfg['mappings'] ?? []);

        // Compatibilitate cu vechea logică, dacă fișierul config nu a fost actualizat.
        if (!$mappings) {
            $mappings = [
                ['label' => 'Livrare 10 lei', 'amount' => 10.00, 'cod_produs' => 436, 'price' => 10.00],
                ['label' => 'Livrare 15 lei', 'amount' => 15.00, 'cod_produs' => 1680, 'price' => 15.00],
            ];
        }

        return $mappings;
    }
}

if (!function_exists('woo_sync_resolve_delivery_fee_line')) {
    function woo_sync_resolve_delivery_fee_line(array $order): ?array {
        $cfg = woo_sync_cfg();
        $feeCfg = (array)($cfg['delivery_fee'] ?? []);

        if (empty($feeCfg['enabled'])) {
            return null;
        }

        // Dacă este ridicare de la restaurant, nu se adaugă taxă transport.
        if (woo_sync_order_is_restaurant_pickup($order)) {
            return null;
        }

        // Valoarea de comparat este transportul cu TVA inclus.
        // Pluginul nou trimite shipping_total_incl_tax; pentru compatibilitate calculăm și shipping_total + shipping_tax.
        $wooShippingTotal = woo_sync_order_shipping_total_with_tax($order);

        // Dacă WooCommerce zice 0 lei transport, nu adăugăm nimic.
        // Aici intră și cazul "Livrare gratuită".
        if ($wooShippingTotal <= 0) {
            return null;
        }

        $tolerance = (float)($feeCfg['amount_tolerance'] ?? 0.01);
        if ($tolerance < 0) {
            $tolerance = 0.01;
        }

        $acceptedAmounts = [];
        foreach (woo_sync_delivery_fee_mappings($feeCfg) as $mapping) {
            $mapping = (array)$mapping;
            $amount = isset($mapping['amount']) ? round((float)$mapping['amount'], 2) : null;
            $codProdus = isset($mapping['cod_produs']) ? (int)$mapping['cod_produs'] : 0;

            if ($amount === null || $amount <= 0 || $codProdus <= 0) {
                continue;
            }

            $acceptedAmounts[] = number_format($amount, 2, '.', '');

            if (abs($wooShippingTotal - $amount) <= $tolerance) {
                $price = isset($mapping['price']) ? round((float)$mapping['price'], 2) : $amount;
                if ($price <= 0) {
                    $price = $amount;
                }

                return [
                    'cod_produs' => $codProdus,
                    'price' => $price,
                    'amount' => $wooShippingTotal,
                    'label' => (string)($mapping['label'] ?? ''),
                    'order_products_total' => woo_sync_order_products_total_with_tax($order),
                    'source' => 'woo_shipping_total_incl_tax',
                ];
            }
        }

        // Protecție: dacă WooCommerce trimite o taxă de transport necunoscută,
        // nu inventăm cod produs greșit.
        $acceptedText = $acceptedAmounts ? implode(', ', array_values(array_unique($acceptedAmounts))) : 'nicio sumă configurată';
        throw new RuntimeException(
            'Taxă de transport WooCommerce necunoscută: ' . number_format($wooShippingTotal, 2, '.', '') .
            ' lei. Nu există mapare în delivery_fee.mappings. Sume mapate: ' . $acceptedText . ' lei.'
        );
    }
}

if (!function_exists('woo_sync_delivery_fee_preview')) {
    function woo_sync_delivery_fee_preview(array $order): array {
        $isPickup = false;
        $line = null;
        $error = '';

        try {
            $isPickup = woo_sync_order_is_restaurant_pickup($order);
            $line = woo_sync_resolve_delivery_fee_line($order);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'line' => $line,
            'is_pickup' => $isPickup,
            'error' => $error,
        ];
    }
}

if (!function_exists('woo_sync_fetch_delivery_fee_product')) {
    function woo_sync_fetch_delivery_fee_product(PDO $pdo, int $codProdus): array {
        $stmt = $pdo->prepare("
            SELECT
                cod_produs,
                nume,
                cota_tva,
                fel_mancare,
                pret_cu_tva
            FROM produse_servicii
            WHERE cod_produs = ?
            LIMIT 1
        ");
        $stmt->execute([$codProdus]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Produsul pentru taxa de transport nu există în produse_servicii. Cod produs: ' . $codProdus);
        }

        return $row;
    }
}

if (!function_exists('woo_sync_insert_delivery_fee_into_note')) {
    function woo_sync_insert_delivery_fee_into_note(PDO $pdo, int $nrBonTarget, array $order): bool {
        $feeLine = woo_sync_resolve_delivery_fee_line($order);
        if (!$feeLine) {
            return false;
        }

        $codProdus = (int)$feeLine['cod_produs'];
        $feeProduct = woo_sync_fetch_delivery_fee_product($pdo, $codProdus);

        $numeProdus = trim((string)($feeProduct['nume'] ?? ''));
        if ($numeProdus === '') {
            $numeProdus = 'Taxă transport';
        }

        $cotaTva = (float)($feeProduct['cota_tva'] ?? 0);
        $pretCuTva = round((float)$feeLine['price'], 2);

        if ($pretCuTva <= 0) {
            $pretCuTva = round((float)($feeProduct['pret_cu_tva'] ?? 0), 2);
        }

        if ($pretCuTva <= 0) {
            throw new RuntimeException('Preț invalid pentru taxa de transport. Cod produs: ' . $codProdus);
        }

        $cantitate = 1.0;
        $valoareVanzareCuTva = $pretCuTva;

        if ($cotaTva > 0) {
            $tvaCol = round($valoareVanzareCuTva * $cotaTva / (100 + $cotaTva), 2);
        } else {
            $tvaCol = 0.00;
        }

        $valoareVanzare = round($valoareVanzareCuTva - $tvaCol, 2);
        $prioritate = (int)($feeProduct['fel_mancare'] ?? 0);

        $sql = "
            INSERT INTO det_note (
                nr_bon,
                cod_p,
                nume_produs,
                cantitate,
                cota_tva,
                tva_col,
                pret_vanzare,
                valoare_vanzare,
                valoare_vanzare_cu_tva,
                `data`,
                `ora`,
                cod_meniu,
                observatie_produs,
                t_list,
                discount,
                pachet,
                preparat,
                preluat_osp,
                prioritate
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ";

        $ins = $pdo->prepare($sql);
        $ins->execute([
            $nrBonTarget,
            $codProdus,
            $numeProdus,
            $cantitate,
            $cotaTva,
            $tvaCol,
            $pretCuTva,
            $valoareVanzare,
            $valoareVanzareCuTva,
            date('Y-m-d'),
            date('H:i:s'),
            0,
            'Taxă transport Woo',
            0, // rămâne produs normal pe notă, ca să poată fi șters înainte de trimitere
            0,
            0,
            0,
            0,
            $prioritate,
        ]);

        return true;
    }
}

if (!function_exists('woo_sync_parse_bucharest_datetime')) {
    function woo_sync_parse_bucharest_datetime($value): ?DateTimeImmutable {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $tz = new DateTimeZone('Europe/Bucharest');
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d',
            DateTimeInterface::ATOM,
        ];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->setTimezone($tz);
            }
        }

        try {
            return (new DateTimeImmutable($value, $tz))->setTimezone($tz);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('woo_sync_order_created_at_bucharest')) {
    function woo_sync_order_created_at_bucharest(array $order): ?DateTimeImmutable {
        $tz = new DateTimeZone('Europe/Bucharest');

        $localValue = trim((string)($order['date_created'] ?? ''));
        if ($localValue !== '') {
            $localDt = woo_sync_parse_bucharest_datetime($localValue);
            if ($localDt instanceof DateTimeImmutable) {
                return $localDt->setTimezone($tz);
            }
        }

        $gmtValue = trim((string)($order['date_created_gmt'] ?? ''));
        if ($gmtValue !== '') {
            try {
                return (new DateTimeImmutable($gmtValue, new DateTimeZone('UTC')))->setTimezone($tz);
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('woo_sync_order_in_last_window')) {
    function woo_sync_order_in_last_window(array $order, DateTimeInterface $from, DateTimeInterface $to): bool {
        $createdAt = woo_sync_order_created_at_bucharest($order);
        if (!$createdAt instanceof DateTimeImmutable) {
            return false;
        }

        return $createdAt >= $from && $createdAt <= $to;
    }
}

if (!function_exists('woo_sync_filter_orders_by_window')) {
    function woo_sync_filter_orders_by_window(array $orders, DateTimeInterface $from, DateTimeInterface $to): array {
        return array_values(array_filter($orders, static function ($order) use ($from, $to) {
            return is_array($order) && woo_sync_order_in_last_window($order, $from, $to);
        }));
    }
}

if (!function_exists('woo_sync_fetch_imports_last_24h')) {
    function woo_sync_fetch_imports_last_24h(PDO $pdoPOS, int $locatie, DateTimeInterface $from, DateTimeInterface $to): array {
        $stmt = $pdoPOS->prepare("
            SELECT
                woo_order_id,
                woo_order_number,
                note_nrbon,
                locatie,
                imported_by,
                imported_at,
                payload_hash,
                COALESCE(listat_auto_de_pe_site, 0) AS listat_auto_de_pe_site
            FROM woo_order_imports
            WHERE locatie = ?
              AND imported_at >= ?
              AND imported_at <= ?
            ORDER BY imported_at DESC, woo_order_id DESC
        ");
        $stmt->execute([
            $locatie,
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('woo_sync_fetch_orders')) {
    function woo_sync_fetch_orders(array $filters): array {
        return woo_sync_http_get('orders', $filters);
    }
}

if (!function_exists('woo_sync_fetch_orders_lite')) {
    function woo_sync_fetch_orders_lite(array $filters): array {
        return woo_sync_http_get('orders-lite', $filters);
    }
}

if (!function_exists('woo_sync_fetch_order')) {
    function woo_sync_fetch_order(int $orderId): array {
        return woo_sync_http_get('orders/' . $orderId);
    }
}

if (!function_exists('woo_sync_current_note_is_valid')) {
    function woo_sync_current_note_is_valid(PDO $pdoPOS, int $nrBon, int $operator, int $locatie): bool {
        $stmt = $pdoPOS->prepare("SELECT COUNT(*) FROM note WHERE nrbon = ? AND operator = ? AND locatie = ? AND status = 'S'");
        $stmt->execute([$nrBon, $operator, $locatie]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('woo_sync_create_nota_noua')) {
    function woo_sync_create_nota_noua(PDO $pdoPOS, int $codMasa, int $operator, int $locatie): int {
        $sql = "INSERT INTO note (operator, locatie, cod_masa, data_bon, ora_bon, status, listat_nota_plata, fiscalizat)
                VALUES (?, ?, ?, ?, ?, 'S', 0, 0)";
        $stmt = $pdoPOS->prepare($sql);
        $stmt->execute([$operator, $locatie, $codMasa, date('Y-m-d'), date('H:i:s')]);
        return (int)$pdoPOS->lastInsertId();
    }
}

if (!function_exists('woo_sync_fetch_open_notes_for_table')) {
    function woo_sync_fetch_open_notes_for_table(PDO $pdoPOS, int $codMasa, int $locatie): array {
        $stmt = $pdoPOS->prepare("SELECT nrbon, operator FROM note WHERE cod_masa = ? AND locatie = ? AND status = 'S' ORDER BY nrbon DESC");
        $stmt->execute([$codMasa, $locatie]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('woo_sync_resolve_target_note')) {
    function woo_sync_resolve_target_note(PDO $pdoPOS, int $operator, int $locatie, string $targetMode, int $currentNrBon, int $codMasaTarget): array {
        if ($targetMode === 'current_note') {
            if ($currentNrBon <= 0 || !woo_sync_current_note_is_valid($pdoPOS, $currentNrBon, $operator, $locatie)) {
                throw new RuntimeException('Nota curentă nu este validă sau nu mai este deschisă.');
            }

            return [
                'nr_bon' => $currentNrBon,
                'cod_masa' => 0,
                'created' => false,
            ];
        }

        if ($codMasaTarget <= 0) {
            throw new RuntimeException('Selectează o masă validă.');
        }

        $openNotes = woo_sync_fetch_open_notes_for_table($pdoPOS, $codMasaTarget, $locatie);
        if (!empty($openNotes)) {
            foreach ($openNotes as $note) {
                if ((int)($note['operator'] ?? 0) === $operator) {
                    return [
                        'nr_bon' => (int)$note['nrbon'],
                        'cod_masa' => $codMasaTarget,
                        'created' => false,
                    ];
                }
            }

            throw new RuntimeException('Masa selectată este deja deschisă la alt operator.');
        }

        $nrBon = woo_sync_create_nota_noua($pdoPOS, $codMasaTarget, $operator, $locatie);

        return [
            'nr_bon' => $nrBon,
            'cod_masa' => $codMasaTarget,
            'created' => true,
        ];
    }
}

if (!function_exists('woo_sync_recalc_nota')) {
    function woo_sync_recalc_nota(PDO $pdoPOS, int $nrBon): void {
        $sql = "
            UPDATE note n
               SET n.valoare_vanzare_cu_tva = (SELECT COALESCE(SUM(d.valoare_vanzare_cu_tva), 0) FROM det_note d WHERE d.nr_bon = n.nrbon),
                   n.tva_colectata = (SELECT COALESCE(SUM(d.tva_col), 0) FROM det_note d WHERE d.nr_bon = n.nrbon),
                   n.discount = (SELECT COALESCE(SUM(d.discount), 0) FROM det_note d WHERE d.nr_bon = n.nrbon)
             WHERE n.nrbon = ?
        ";
        $stmt = $pdoPOS->prepare($sql);
        $stmt->execute([$nrBon]);
    }
}

if (!function_exists('woo_sync_set_masa_ocupata')) {
    function woo_sync_set_masa_ocupata(PDO $pdoPOS, int $codMasa): void {
        if ($codMasa <= 0) {
            return;
        }

        try {
            $stmt = $pdoPOS->prepare("UPDATE mese SET stare = 1 WHERE cod_masa = ?");
            $stmt->execute([$codMasa]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('woo_sync_upsert_ultim_bon')) {
    function woo_sync_upsert_ultim_bon(PDO $pdoPOS, int $locatie, int $nrBon): void {
        $ts = date('Y-m-d H:i:s');

        try {
            restaurantTouchUltimBonConectat($pdoPOS, $locatie, $nrBon, $ts);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('woo_sync_is_imported')) {
    function woo_sync_is_imported(PDO $pdoPOS, int $wooOrderId, int $locatie): bool {
        $stmt = $pdoPOS->prepare("SELECT COUNT(*) FROM woo_order_imports WHERE woo_order_id = ? AND locatie = ?");
        $stmt->execute([$wooOrderId, $locatie]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('woo_sync_fetch_imported_order_ids')) {
    function woo_sync_fetch_imported_order_ids(PDO $pdoPOS, array $wooOrderIds, int $locatie): array {
        $wooOrderIds = array_values(array_unique(array_filter(array_map('intval', $wooOrderIds), static function ($v) {
            return $v > 0;
        })));

        if (!$wooOrderIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($wooOrderIds), '?'));
        $params = $wooOrderIds;
        $params[] = $locatie;

        $sql = "SELECT woo_order_id
                FROM woo_order_imports
                WHERE woo_order_id IN ($placeholders)
                  AND locatie = ?";

        $stmt = $pdoPOS->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('woo_sync_get_new_order_ids_today')) {
    function woo_sync_get_new_order_ids_today(PDO $pdoPOS, int $locatie, array $extraFilters = []): array {
        $baseFilters = array_merge([
            'date' => date('Y-m-d'),
            'page' => 1,
            'per_page' => 100,
        ], $extraFilters);

        $allIds = [];
        $page = 1;
        $totalPages = 1;
        $maxPages = 5; // protecție simplă

        do {
            $filters = $baseFilters;
            $filters['page'] = $page;

            $resp = woo_sync_fetch_orders_lite(array_filter($filters, static function ($v) {
                return $v !== '' && $v !== null;
            }));

            foreach ((array)($resp['data'] ?? []) as $row) {
                $oid = (int)($row['id'] ?? 0);
                if ($oid > 0) {
                    $allIds[] = $oid;
                }
            }

            $totalPages = max(1, (int)($resp['pagination']['total_pages'] ?? 1));
            $page++;
        } while ($page <= $totalPages && $page <= $maxPages);

        $allIds = array_values(array_unique($allIds));
        if (!$allIds) {
            return [];
        }

        $importedIds = woo_sync_fetch_imported_order_ids($pdoPOS, $allIds, $locatie);
        if (!$importedIds) {
            rsort($allIds, SORT_NUMERIC);
            return $allIds;
        }

        $importedLookup = array_fill_keys(array_map('intval', $importedIds), true);

        $newIds = array_values(array_filter($allIds, static function ($oid) use ($importedLookup) {
            return empty($importedLookup[(int)$oid]);
        }));

        rsort($newIds, SORT_NUMERIC);
        return $newIds;
    }
}

if (!function_exists('woo_sync_insert_import_log')) {
    function woo_sync_insert_import_log(PDO $pdoPOS, array $order, int $nrBon, int $locatie, int $operator, string $hashPrefix = '', int $listatAutoDePeSite = 0): void {
        $payloadHash = hash('sha256', $hashPrefix . json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $stmt = $pdoPOS->prepare("
            INSERT INTO woo_order_imports (
                woo_order_id,
                woo_order_number,
                note_nrbon,
                locatie,
                imported_by,
                imported_at,
                payload_hash,
                listat_auto_de_pe_site
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            (int)($order['id'] ?? 0),
            (string)($order['number'] ?? ''),
            $nrBon,
            $locatie,
            $operator,
            date('Y-m-d H:i:s'),
            $payloadHash,
            $listatAutoDePeSite ? 1 : 0,
        ]);
    }
}

if (!function_exists('woo_sync_mark_imported')) {
    function woo_sync_mark_imported(PDO $pdoPOS, array $order, int $nrBon, int $locatie, int $operator, int $listatAutoDePeSite = 0): void {
        woo_sync_insert_import_log($pdoPOS, $order, $nrBon, $locatie, $operator, '', $listatAutoDePeSite);
    }
}

if (!function_exists('woo_sync_mark_manually_imported')) {
    function woo_sync_mark_manually_imported(PDO $pdoPOS, array $order, int $locatie, int $operator): void {
        $order['_manual_mark_only'] = true;
        woo_sync_insert_import_log($pdoPOS, $order, 0, $locatie, $operator, 'manual|');
    }
}
if (!function_exists('woo_sync_detect_tip')) {
    function woo_sync_detect_tip(array $product): string {
        $tip = strtolower(trim((string)($product['woo_tip'] ?? '')));
        if ($tip === 'simple' || $tip === 'variable') {
            return $tip;
        }

        return ((int)($product['variation_id'] ?? 0) > 0) ? 'variable' : 'simple';
    }
}

if (!function_exists('woo_sync_split_name_and_observation')) {
    function woo_sync_split_name_and_observation(string $name): array {
        $name = trim((string)preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return ['', ''];
        }

        $sepPos = mb_strpos($name, ' - ');
        if ($sepPos === false) {
            return [$name, ''];
        }

        $base = trim(mb_substr($name, 0, $sepPos));
        $obs = trim(mb_substr($name, $sepPos + 3));

        return [$base, $obs];
    }
}

if (!function_exists('woo_sync_find_pos_mapping')) {
    function woo_sync_find_pos_mapping(PDO $pdoPOS, int $wooProductId, string $wooTip): ?array {
        $stmt = $pdoPOS->prepare("
            SELECT
                id,
                woo_product_id,
                woo_tip,
                cod_produs,
                nume_woo,
                nume_pos,
                metoda_mapare,
                activ
            FROM mapare_woo_pos
            WHERE woo_product_id = ?
              AND woo_tip = ?
              AND activ = 1
            LIMIT 1
        ");
        $stmt->execute([$wooProductId, $wooTip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('woo_sync_build_line_observation')) {
    function woo_sync_build_line_observation(array $product): string {
        [, $obsFromName] = woo_sync_split_name_and_observation((string)($product['name'] ?? ''));

        $parts = [];
        if ($obsFromName !== '') {
            $parts[] = $obsFromName;
        }

        $lineNotes = trim((string)($product['notes'] ?? ''));
        if ($lineNotes !== '') {
            $parts[] = $lineNotes;
        }

        $parts = array_values(array_unique(array_filter($parts, static function ($v) {
            return trim((string)$v) !== '';
        })));

        return mb_substr(implode(' | ', $parts), 0, 100);
    }
}

if (!function_exists('woo_sync_assert_order_mappings')) {
    function woo_sync_assert_order_mappings(PDO $pdoPOS, array $order): void {
        $products = (array)($order['products'] ?? []);
        if (!$products) {
            throw new RuntimeException('Comanda Woo nu conține produse.');
        }

        $missing = [];

        foreach ($products as $product) {
            $product = (array)$product;
            $wooProductId = (int)($product['product_id'] ?? 0);
            $wooTip = woo_sync_detect_tip($product);

            if ($wooProductId <= 0) {
                $missing[] = 'Produs fără product_id: ' . ((string)($product['name'] ?? 'necunoscut'));
                continue;
            }

            $map = woo_sync_find_pos_mapping($pdoPOS, $wooProductId, $wooTip);
            if (!$map) {
                $missing[] = sprintf(
                    '%s [product_id=%d, woo_tip=%s]',
                    (string)($product['name'] ?? 'Produs necunoscut'),
                    $wooProductId,
                    $wooTip
                );
            }
        }

        if ($missing) {
            throw new RuntimeException(
                "Lipsesc mapări în mapare_woo_pos pentru:\n- " . implode("\n- ", $missing)
            );
        }
    }
}

if (!function_exists('woo_sync_insert_items_into_note')) {
    function woo_sync_insert_items_into_note(PDO $pdo, int $nrBonTarget, array $order): void {
        $products = (array)($order['products'] ?? []);
        if (!$products) {
            throw new RuntimeException('Comanda Woo nu conține produse de importat.');
        }

        $sql = "
            INSERT INTO det_note (
                nr_bon,
                cod_p,
                nume_produs,
                cantitate,
                cota_tva,
                tva_col,
                pret_vanzare,
                valoare_vanzare,
                valoare_vanzare_cu_tva,
                `data`,
                `ora`,
                cod_meniu,
                observatie_produs,
                t_list,
                discount,
                pachet,
                preparat,
                preluat_osp,
                prioritate
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ";
        $ins = $pdo->prepare($sql);

        foreach ($products as $product) {
            $product = (array)$product;

            $wooProductId = (int)($product['product_id'] ?? 0);
            $wooTip = woo_sync_detect_tip($product);

            if ($wooProductId <= 0) {
                throw new RuntimeException('Un produs din comandă nu are product_id valid.');
            }

            $map = woo_sync_find_pos_mapping($pdo, $wooProductId, $wooTip);
            if (!$map) {
                throw new RuntimeException(
                    'Nu există mapare activă pentru product_id=' . $wooProductId . ' și woo_tip=' . $wooTip
                );
            }

            $qty = (float)($product['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new RuntimeException(
                    'Cantitate invalidă pentru produsul Woo: ' . (string)($product['name'] ?? '')
                );
            }

            $lineTotal = (float)($product['line_total'] ?? 0);
            $lineTax = (float)($product['line_total_tax'] ?? 0);
            $lineTotalWithTax = $lineTotal + $lineTax;
            $unitPriceWithTax = $qty > 0 ? ($lineTotalWithTax / $qty) : 0.0;

            $cotaTva = 0;
            if ($lineTotal > 0 && $lineTax > 0) {
                $cotaTva = (int)round(($lineTax * 100) / $lineTotal);
            }

            [$baseWooName, ] = woo_sync_split_name_and_observation((string)($product['name'] ?? ''));
            $numeProdusPos = trim((string)($map['nume_pos'] ?? ''));
            if ($numeProdusPos === '') {
                $numeProdusPos = $baseWooName !== '' ? $baseWooName : (string)($product['name'] ?? '');
            }

            $observatie = woo_sync_build_line_observation($product);

                        $ins->execute([
                $nrBonTarget,
                (int)$map['cod_produs'],
                $numeProdusPos,
                $qty,
                $cotaTva,
                $lineTax,
                $unitPriceWithTax,
                $lineTotal,
                $lineTotalWithTax,
                date('Y-m-d'),
                date('H:i:s'),
                0,
                $observatie,
                0,
                0,
                0,
                0,
                0,
                0,
            ]);
        }

        woo_sync_insert_delivery_fee_into_note($pdo, $nrBonTarget, $order);
    }
}

if (!function_exists('woo_sync_fetch_tables_for_operator')) {
    function woo_sync_fetch_tables_for_operator(PDO $pdoPOS, int $locatie, int $operator): array {
        $stmt = $pdoPOS->prepare("
            SELECT
                m.cod_masa,
                CASE
                    WHEN own_note.nrbon IS NOT NULL THEN CONCAT(
                        COALESCE(NULLIF(m.nume_masa, ''), CONCAT('Masa ', m.cod_masa)),
                        ' (nota #',
                        own_note.nrbon,
                        ' - deschisa)'
                    )
                    ELSE COALESCE(NULLIF(m.nume_masa, ''), CONCAT('Masa ', m.cod_masa))
                END AS label
            FROM mese m
            LEFT JOIN (
                SELECT cod_masa, MAX(nrbon) AS nrbon
                FROM note
                WHERE locatie = :loc
                  AND status = 'S'
                  AND operator = :op
                GROUP BY cod_masa
            ) own_note ON own_note.cod_masa = m.cod_masa
            WHERE m.cod_locatie = :loc_mese
              AND COALESCE(m.masa_comenzi_online, 0) = 1
              AND (m.stare = 0 OR own_note.nrbon IS NOT NULL)
            ORDER BY m.cod_masa ASC
        ");
        $stmt->execute([
            ':loc' => $locatie,
            ':op' => $operator,
            ':loc_mese' => $locatie,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


if (!function_exists('woo_sync_auto_bar_log_path')) {
    function woo_sync_auto_bar_log_path(): string {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'woo_auto_import_bar.log';
    }
}
if (!function_exists('woo_sync_auto_bar_log')) {
    function woo_sync_auto_bar_log(string $stage, array $context = [], ?Throwable $exception = null): void {
        // Log dezactivat intenționat.
        return;
    }
}


if (!function_exists('woo_sync_auto_bar_listed_registry_path')) {
    function woo_sync_auto_bar_listed_registry_path(): string {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'woo_auto_bar_listed_orders.json';
    }
}

if (!function_exists('woo_sync_auto_bar_registry_key')) {
    function woo_sync_auto_bar_registry_key(int $wooOrderId, int $locatie): string {
        $clientId = (string)($_SESSION['client_id'] ?? $_SESSION['clientId'] ?? '');
        $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', $clientId);
        if ($clientId === '') {
            $clientId = 'client';
        }

        return $clientId . '|' . $locatie . '|' . $wooOrderId;
    }
}

if (!function_exists('woo_sync_auto_bar_read_listed_registry')) {
    function woo_sync_auto_bar_read_listed_registry(): array {
        $path = woo_sync_auto_bar_listed_registry_path();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('woo_sync_auto_bar_write_listed_registry')) {
    function woo_sync_auto_bar_write_listed_registry(array $registry): void {
        $path = woo_sync_auto_bar_listed_registry_path();

        // Curățăm intrările foarte vechi, ca fișierul să rămână mic.
        $cutoff = time() - (14 * 86400);
        foreach ($registry as $key => $row) {
            $ts = 0;
            if (is_array($row) && !empty($row['listed_at'])) {
                $ts = strtotime((string)$row['listed_at']) ?: 0;
            }
            if ($ts > 0 && $ts < $cutoff) {
                unset($registry[$key]);
            }
        }

        $json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Nu pot genera registrul local pentru comenzile listate automat la BAR.');
        }

        if (@file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Nu pot scrie registrul local pentru comenzile listate automat la BAR: ' . $path);
        }
    }
}

if (!function_exists('woo_sync_auto_bar_was_listed')) {
    function woo_sync_auto_bar_was_listed(int $wooOrderId, int $locatie): bool {
        if ($wooOrderId <= 0) {
            return false;
        }

        $registry = woo_sync_auto_bar_read_listed_registry();
        $key = woo_sync_auto_bar_registry_key($wooOrderId, $locatie);

        return isset($registry[$key]);
    }
}

if (!function_exists('woo_sync_auto_bar_mark_listed')) {
    function woo_sync_auto_bar_mark_listed(int $wooOrderId, int $locatie, string $queueFile, string $reason = 'scanner_auto_bar_only'): void {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru marcarea locală a listării BAR.');
        }

        $registry = woo_sync_auto_bar_read_listed_registry();
        $key = woo_sync_auto_bar_registry_key($wooOrderId, $locatie);

        $registry[$key] = [
            'woo_order_id' => $wooOrderId,
            'locatie' => $locatie,
            'client_id' => $_SESSION['client_id'] ?? $_SESSION['clientId'] ?? null,
            'listed_at' => date('Y-m-d H:i:s'),
            'queue_file' => $queueFile,
            'reason' => $reason,
        ];

        woo_sync_auto_bar_write_listed_registry($registry);
    }
}

if (!function_exists('woo_sync_apply_pos_tva_to_note_from_products')) {
    function woo_sync_apply_pos_tva_to_note_from_products(PDO $pdo, int $nrBon): void {
        if ($nrBon <= 0) {
            throw new RuntimeException('Număr bon invalid pentru recalcul TVA.');
        }

        $updateDetailsSql = "
            UPDATE det_note dn
            INNER JOIN produse_servicii ps ON ps.cod_produs = dn.cod_p
            SET
                dn.cota_tva = COALESCE(ps.cota_tva, 0),
                dn.tva_col = ROUND(
                    COALESCE(dn.valoare_vanzare_cu_tva, 0) * COALESCE(ps.cota_tva, 0) / (100 + COALESCE(ps.cota_tva, 0)),
                    2
                ),
                dn.valoare_vanzare = ROUND(
                    COALESCE(dn.valoare_vanzare_cu_tva, 0) -
                    (
                        COALESCE(dn.valoare_vanzare_cu_tva, 0) * COALESCE(ps.cota_tva, 0) / (100 + COALESCE(ps.cota_tva, 0))
                    ),
                    2
                )
            WHERE dn.nr_bon = :nr_bon
        ";
        $stmt = $pdo->prepare($updateDetailsSql);
        $stmt->execute([':nr_bon' => $nrBon]);

        $updateNoteSql = "
            UPDATE note n
            SET n.tva_colectata = (
                SELECT ROUND(COALESCE(SUM(dn.tva_col), 0), 2)
                FROM det_note dn
                WHERE dn.nr_bon = :nr_bon_sum
            )
            WHERE n.nrbon = :nr_bon_note
        ";
        $stmt = $pdo->prepare($updateNoteSql);
        $stmt->execute([
            ':nr_bon_sum' => $nrBon,
            ':nr_bon_note' => $nrBon,
        ]);
    }
}

if (!function_exists('woo_sync_set_order_listat_auto_de_pe_site')) {
    function woo_sync_set_order_listat_auto_de_pe_site(PDO $pdoPOS, int $wooOrderId, int $locatie, int $flag = 1): void {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru actualizare listat_auto_de_pe_site.');
        }

        $stmt = $pdoPOS->prepare("UPDATE woo_order_imports SET listat_auto_de_pe_site = ? WHERE woo_order_id = ? AND locatie = ?");
        $stmt->execute([$flag ? 1 : 0, $wooOrderId, $locatie]);
    }
}

if (!function_exists('woo_sync_site_bar_lower')) {
    function woo_sync_site_bar_lower(string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('woo_sync_site_bar_clean_text')) {
    function woo_sync_site_bar_clean_text($value): string {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = $encoded === false ? '' : $encoded;
        }

        $text = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

if (!function_exists('woo_sync_site_bar_money')) {
    function woo_sync_site_bar_money($value): string {
        if (!is_numeric($value)) {
            $clean = woo_sync_site_bar_clean_text($value);
            return $clean !== '' ? $clean . ' lei' : '0 lei';
        }

        $amount = (float)$value;
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return number_format($amount, $decimals, ',', '.') . ' lei';
    }
}

if (!function_exists('woo_sync_site_bar_qty')) {
    function woo_sync_site_bar_qty($value): string {
        if (!is_numeric($value)) {
            $clean = woo_sync_site_bar_clean_text($value);
            return $clean !== '' ? $clean : '0';
        }

        $qty = (float)$value;
        if (abs($qty - round($qty)) < 0.00001) {
            return (string)(int)round($qty);
        }

        return rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }
}

if (!function_exists('woo_sync_site_bar_format_date')) {
    function woo_sync_site_bar_format_date($value): string {
        $raw = woo_sync_site_bar_clean_text($value);
        if ($raw === '') {
            return date('d-m-Y H:i');
        }

        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone('Europe/Bucharest'));
            return $dt->format('d-m-Y H:i');
        } catch (Throwable $e) {
            return $raw;
        }
    }
}

if (!function_exists('woo_sync_site_bar_address')) {
    function woo_sync_site_bar_address(array $address, array $customer = []): string {
        $nameParts = [];
        foreach (['first_name', 'last_name'] as $key) {
            $part = woo_sync_site_bar_clean_text($address[$key] ?? '');
            if ($part !== '') {
                $nameParts[] = $part;
            }
        }

        $name = trim(implode(' ', $nameParts));
        if ($name === '') {
            $name = woo_sync_site_bar_clean_text($customer['name'] ?? '');
        }

        $parts = [];
        if ($name !== '') {
            $parts[] = $name;
        }

        foreach (['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $key) {
            $part = woo_sync_site_bar_clean_text($address[$key] ?? '');
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts ? implode(', ', $parts) : '-';
    }
}

if (!function_exists('woo_sync_site_bar_shipping_method')) {
    function woo_sync_site_bar_shipping_method(array $order): string {
        foreach ((array)($order['shipping_lines'] ?? []) as $line) {
            $line = (array)$line;
            $name = woo_sync_site_bar_clean_text($line['name'] ?? '');
            if ($name !== '') {
                return $name;
            }
        }

        $shippingTotal = (float)($order['totals']['shipping_total'] ?? 0) + (float)($order['totals']['shipping_tax'] ?? 0);
        return $shippingTotal > 0 ? 'Livrare' : 'Ridicare de la restaurant';
    }
}

if (!function_exists('woo_sync_site_bar_item_notes')) {
    function woo_sync_site_bar_item_notes(array $item): array {
        $notes = [];
        $seen = [];

        foreach ((array)($item['meta'] ?? []) as $meta) {
            $meta = (array)$meta;
            $key = woo_sync_site_bar_clean_text($meta['key'] ?? $meta['display_key'] ?? '');
            if ($key !== '' && strpos($key, '_') === 0) {
                continue;
            }

            $value = woo_sync_site_bar_clean_text($meta['display_value'] ?? $meta['value'] ?? '');
            if ($value === '' || $value === '[]' || $value === '{}') {
                continue;
            }

            $dedupeKey = woo_sync_site_bar_lower($value);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $notes[] = $value;
        }

        return $notes;
    }
}

if (!function_exists('woo_sync_site_bar_order_notes')) {
    function woo_sync_site_bar_order_notes(array $order): string {
        $notes = [];
        $seen = [];

        $customer = (array)($order['customer'] ?? []);
        $customerNote = woo_sync_site_bar_clean_text($customer['customer_note'] ?? '');
        if ($customerNote !== '') {
            $notes[] = $customerNote;
            $seen[woo_sync_site_bar_lower($customerNote)] = true;
        }

        foreach ((array)($order['notes'] ?? []) as $note) {
            $note = (array)$note;
            $content = woo_sync_site_bar_clean_text($note['content'] ?? '');
            if ($content === '') {
                continue;
            }

            $key = woo_sync_site_bar_lower($content);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $notes[] = $content;
        }

        return $notes ? implode(' | ', $notes) : '-';
    }
}

if (!function_exists('woo_sync_format_site_order_for_bar')) {
    function woo_sync_format_site_order_for_bar(array $wpDetails, int $wooOrderId): string {
        $order = (array)($wpDetails['order'] ?? []);
        if (!$order) {
            throw new RuntimeException('Răspunsul WordPress nu conține obiectul comenzii.');
        }

        $separator = str_repeat('-', 18);
        $customer = (array)($order['customer'] ?? []);
        $billing = (array)($order['billing'] ?? []);
        $shipping = (array)($order['shipping'] ?? []);
        $payment = (array)($order['payment'] ?? []);
        $totals = (array)($order['totals'] ?? []);
        $dates = (array)($order['dates'] ?? []);
        $items = (array)($order['items'] ?? []);

        $orderNumber = woo_sync_site_bar_clean_text($order['number'] ?? $order['id'] ?? $wooOrderId);
        if ($orderNumber === '') {
            $orderNumber = (string)$wooOrderId;
        }

        $customerName = woo_sync_site_bar_clean_text($customer['name'] ?? '');
        if ($customerName === '') {
            $customerName = trim(woo_sync_site_bar_clean_text($billing['first_name'] ?? '') . ' ' . woo_sync_site_bar_clean_text($billing['last_name'] ?? ''));
        }
        if ($customerName === '') {
            $customerName = '-';
        }

        $paymentMethod = woo_sync_site_bar_clean_text($payment['method_title'] ?? $payment['method'] ?? '-');
        if ($paymentMethod === '') {
            $paymentMethod = '-';
        }

        $phone = woo_sync_site_bar_clean_text($customer['phone'] ?? $billing['phone'] ?? '-');
        if ($phone === '') {
            $phone = '-';
        }

        $shippingAddress = woo_sync_site_bar_address($shipping, $customer);
        if ($shippingAddress === '-' || trim($shippingAddress) === '') {
            $shippingAddress = woo_sync_site_bar_address($billing, $customer);
        }

        $createdAt = woo_sync_site_bar_format_date($dates['created'] ?? $order['date_created'] ?? '');
        $shippingTotal = (float)($totals['shipping_total'] ?? 0) + (float)($totals['shipping_tax'] ?? 0);
        $finalTotal = (float)($totals['total'] ?? 0);

        $productTotal = 0.0;
        $productLines = [];
        $itemNotes = [];

        foreach ($items as $item) {
            $item = (array)$item;
            $qty = (float)($item['quantity'] ?? 0);
            $name = woo_sync_site_bar_clean_text($item['name'] ?? 'Produs');
            if ($name === '') {
                $name = 'Produs';
            }

            $lineTotal = (float)($item['total'] ?? 0) + (float)($item['total_tax'] ?? 0);
            if (abs($lineTotal) < 0.00001) {
                $lineTotal = (float)($item['subtotal'] ?? 0) + (float)($item['subtotal_tax'] ?? 0);
            }

            $productTotal += $lineTotal;
            $productLines[] = woo_sync_site_bar_qty($qty) . ' x ' . $name . ' = ' . woo_sync_site_bar_money($lineTotal);

            foreach (woo_sync_site_bar_item_notes($item) as $noteLine) {
                $itemNotes[] = $noteLine;
            }
        }

        if (abs($productTotal) < 0.00001) {
            $productTotal = max(0, $finalTotal - $shippingTotal);
        }

        $lines = [];
        $lines[] = 'Comanda Noua: #' . $orderNumber . ' (' . $createdAt . ')';
        $lines[] = 'Ai primit urmatoarea comanda de la';
        $lines[] = $customerName;
        $lines[] = $separator;

        if ($productLines) {
            foreach ($productLines as $productLine) {
                $lines[] = $productLine;
            }
        } else {
            $lines[] = '- fara produse -';
        }

        $lines[] = $separator;

        $uniqueItemNotes = [];
        $seenItemNotes = [];
        foreach ($itemNotes as $itemNote) {
            $itemNote = woo_sync_site_bar_clean_text($itemNote);
            if ($itemNote === '') {
                continue;
            }
            $key = woo_sync_site_bar_lower($itemNote);
            if (isset($seenItemNotes[$key])) {
                continue;
            }
            $seenItemNotes[$key] = true;
            $uniqueItemNotes[] = $itemNote;
        }

        if ($uniqueItemNotes) {
            $lines[] = '';
            foreach ($uniqueItemNotes as $itemNote) {
                $lines[] = $itemNote;
            }
            $lines[] = $separator;
        }

        $lines[] = '';
        $lines[] = 'Total: ' . woo_sync_site_bar_money($productTotal);
        $lines[] = 'Livrare: ' . woo_sync_site_bar_money($shippingTotal);
        $lines[] = 'Metoda de plata: ' . $paymentMethod;
        $lines[] = $separator;
        $lines[] = 'Metoda de livrare: ' . woo_sync_site_bar_shipping_method($order);
        $lines[] = $separator;
        $lines[] = 'Pret final: ' . woo_sync_site_bar_money($finalTotal);
        $lines[] = 'Telefon: ' . $phone;
        $lines[] = $separator;
        $lines[] = 'Adresa de livrare:';
        $lines[] = $shippingAddress;
        $lines[] = $separator;
        $lines[] = 'Nota comanda:';
        $lines[] = woo_sync_site_bar_order_notes($order);
        $lines[] = $separator;

        return implode("\n", $lines);
    }
}

if (!function_exists('woo_sync_write_site_bar_queue_file')) {
    function woo_sync_write_site_bar_queue_file(string $content, int $wooOrderId, int $codLocatie): string {
        $cfg = woo_sync_cfg();
        $clientId = (string)($_SESSION['client_id'] ?? $_SESSION['clientId'] ?? '');
        $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', $clientId);

        woo_sync_auto_bar_log('queue.prepare', [
            'woo_order_id' => $wooOrderId,
            'cod_locatie' => $codLocatie,
            'client_id' => $clientId,
        ]);

        if ($clientId === '') {
            throw new RuntimeException('Lipsește client_id în sesiune pentru coada imprimantei.');
        }

        $queueBaseDir = (string)($cfg['printer_queue_base_dir'] ?? (defined('RESTAURANT_OFFLINE_API_DIR') ? RESTAURANT_OFFLINE_API_DIR : dirname(dirname(dirname(__DIR__))) . '/api_offline_taverna_amicii'));
        $queueDir = rtrim($queueBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $codLocatie;

        woo_sync_auto_bar_log('queue.path', [
            'woo_order_id' => $wooOrderId,
            'queue_base_dir' => $queueBaseDir,
            'queue_dir' => $queueDir,
        ]);

        if (!is_dir($queueDir) && !mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
            throw new RuntimeException('Nu pot crea folderul pentru coada imprimantei: ' . $queueDir);
        }

        $queueFile = $queueDir . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';
        $deadline = time() + 60;
        $waited = 0;

        while (is_file($queueFile) && time() < $deadline) {
            woo_sync_auto_bar_log('queue.busy_wait', [
                'woo_order_id' => $wooOrderId,
                'queue_file' => $queueFile,
                'waited_seconds' => $waited,
            ]);
            sleep(5);
            $waited += 5;
            clearstatcache(true, $queueFile);
        }

        if (is_file($queueFile)) {
            woo_sync_auto_bar_log('queue.timeout', [
                'woo_order_id' => $wooOrderId,
                'queue_file' => $queueFile,
                'waited_seconds' => $waited,
            ]);
            throw new RuntimeException('Coada de imprimare BAR este ocupată după ' . $waited . ' secunde. Fișier existent: ' . $queueFile);
        }

        $printData = [[
            'data'                    => date('Y-m-d'),
            'ora'                     => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon'                   => $wooOrderId,
            'locatie'                 => $codLocatie,
            'departament_listare'     => 'BAR',
            'departament'             => 'BAR',
            'tip'                     => 'COMANDA_ONLINE_SITE_AUTO',
            'woo_order_id'            => $wooOrderId,
            'continut'                => $content,
        ]];

        $jsonArray = [
            'status'  => 'success',
            'message' => 'Date pentru imprimantă generate pentru un singur departament.',
            'data'    => $printData,
        ];

        $tmpFile = $queueFile . '.tmp.' . getmypid();
        $json = json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Nu pot genera JSON-ul pentru coada de imprimare BAR.');
        }

        woo_sync_auto_bar_log('queue.write_tmp', [
            'woo_order_id' => $wooOrderId,
            'tmp_file' => $tmpFile,
            'bytes' => strlen($json),
        ]);

        if (file_put_contents($tmpFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('Nu pot scrie fișierul temporar pentru imprimanta BAR: ' . $tmpFile);
        }

        clearstatcache(true, $queueFile);
        if (is_file($queueFile)) {
            @unlink($tmpFile);
            throw new RuntimeException('Coada BAR s-a ocupat înainte de publicare. Nu suprascriu fișierul existent: ' . $queueFile);
        }

        if (!rename($tmpFile, $queueFile)) {
            @unlink($tmpFile);
            throw new RuntimeException('Nu pot publica fișierul pentru imprimanta BAR: ' . $queueFile);
        }

        woo_sync_auto_bar_log('queue.published', [
            'woo_order_id' => $wooOrderId,
            'queue_file' => $queueFile,
            'waited_seconds' => $waited,
        ]);

        return $queueFile;
    }
}

if (!function_exists('woo_sync_send_site_order_to_bar_printer')) {
    function woo_sync_send_site_order_to_bar_printer(array $wpDetails, int $wooOrderId, int $codLocatie): string {
        woo_sync_auto_bar_log('print.format_start', [
            'woo_order_id' => $wooOrderId,
            'cod_locatie' => $codLocatie,
        ]);

        $content = woo_sync_format_site_order_for_bar($wpDetails, $wooOrderId);

        woo_sync_auto_bar_log('print.format_done', [
            'woo_order_id' => $wooOrderId,
            'content_length' => strlen($content),
        ]);

        return woo_sync_write_site_bar_queue_file($content, $wooOrderId, $codLocatie);
    }
}


if (!function_exists('woo_sync_auto_print_site_order_to_bar_only')) {
    function woo_sync_auto_print_site_order_to_bar_only(PDO $pdoPOS, int $wooOrderId, int $locatie, string $reason = 'scanner_auto_bar_only'): array {
        woo_sync_auto_bar_log('auto_bar_only.start', [
            'woo_order_id' => $wooOrderId,
            'locatie' => $locatie,
            'reason' => $reason,
        ]);

        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru listare automată BAR.');
        }

        if (woo_sync_is_imported($pdoPOS, $wooOrderId, $locatie)) {
            woo_sync_auto_bar_log('auto_bar_only.skip_imported', [
                'woo_order_id' => $wooOrderId,
                'locatie' => $locatie,
            ]);

            return [
                'woo_order_id' => $wooOrderId,
                'printed' => false,
                'skipped' => true,
                'reason' => 'already_imported',
                'message' => 'Comanda este deja importată local; scannerul automat nu o mai listează separat.',
            ];
        }

        if (woo_sync_auto_bar_was_listed($wooOrderId, $locatie)) {
            woo_sync_auto_bar_log('auto_bar_only.skip_already_listed', [
                'woo_order_id' => $wooOrderId,
                'locatie' => $locatie,
            ]);

            return [
                'woo_order_id' => $wooOrderId,
                'printed' => false,
                'skipped' => true,
                'reason' => 'already_listed_by_scanner',
                'message' => 'Comanda a fost deja pusă anterior în coada BAR de scanner.',
            ];
        }

        woo_sync_auto_bar_log('auto_bar_only.site_details_start', [
            'woo_order_id' => $wooOrderId,
        ]);

        $wpDetails = woo_sync_fetch_wp_order_details($wooOrderId);

        woo_sync_auto_bar_log('auto_bar_only.site_details_done', [
            'woo_order_id' => $wooOrderId,
            'has_order' => !empty($wpDetails['order']),
        ]);

        $queueFile = woo_sync_send_site_order_to_bar_printer($wpDetails, $wooOrderId, $locatie);

        // Important: aici NU inserăm în woo_order_imports și NU setăm imported_by.
        // Salvăm doar local faptul că scannerul a pus comanda în coada BAR,
        // ca să nu relisteze aceeași comandă la fiecare polling până la importul manual.
        woo_sync_auto_bar_mark_listed($wooOrderId, $locatie, $queueFile, $reason);

        woo_sync_auto_bar_log('auto_bar_only.done', [
            'woo_order_id' => $wooOrderId,
            'queue_file' => $queueFile,
            'db_import_log_written' => 0,
            'imported_by_written' => 0,
        ]);

        return [
            'woo_order_id' => $wooOrderId,
            'printed' => true,
            'skipped' => false,
            'queue_file' => $queueFile,
            'message' => 'Comanda a fost pusă în coada BAR fără import POS.',
        ];
    }
}

if (!function_exists('woo_sync_pick_online_order_table')) {
    function woo_sync_pick_online_order_table(PDO $pdoPOS, int $locatie, int $operator): array {
        $tables = woo_sync_fetch_tables_for_operator($pdoPOS, $locatie, $operator);
        if (!$tables) {
            throw new RuntimeException('Nu există masă disponibilă pentru comenzi online (masa_comenzi_online = 1).');
        }

        return (array)$tables[0];
    }
}

if (!function_exists('woo_sync_print_imported_site_order_to_bar')) {
    function woo_sync_print_imported_site_order_to_bar(PDO $pdoPOS, int $wooOrderId, int $locatie, string $reason = 'auto'): array {
        woo_sync_auto_bar_log('print_imported.start', [
            'woo_order_id' => $wooOrderId,
            'locatie' => $locatie,
            'reason' => $reason,
        ]);

        $wpDetails = woo_sync_fetch_wp_order_details($wooOrderId);
        woo_sync_auto_bar_log('print_imported.site_details_done', [
            'woo_order_id' => $wooOrderId,
            'has_order' => !empty($wpDetails['order']),
        ]);

        $queueFile = woo_sync_send_site_order_to_bar_printer($wpDetails, $wooOrderId, $locatie);
        woo_sync_set_order_listat_auto_de_pe_site($pdoPOS, $wooOrderId, $locatie, 1);

        woo_sync_auto_bar_log('print_imported.done', [
            'woo_order_id' => $wooOrderId,
            'queue_file' => $queueFile,
            'flag_set' => 1,
        ]);

        return [
            'woo_order_id' => $wooOrderId,
            'printed' => true,
            'queue_file' => $queueFile,
        ];
    }
}

if (!function_exists('woo_sync_fetch_imports_pending_auto_bar_print')) {
    function woo_sync_fetch_imports_pending_auto_bar_print(PDO $pdoPOS, int $locatie, int $limit = 1): array {
        $limit = max(1, min(10, $limit));
        $stmt = $pdoPOS->prepare("\n            SELECT woo_order_id, woo_order_number, note_nrbon, locatie, imported_by, imported_at, COALESCE(listat_auto_de_pe_site, 0) AS listat_auto_de_pe_site\n            FROM woo_order_imports\n            WHERE locatie = ?\n              AND COALESCE(listat_auto_de_pe_site, 0) = 0\n              AND COALESCE(note_nrbon, 0) > 0\n              AND imported_at >= ?\n            ORDER BY imported_at ASC, woo_order_id ASC\n            LIMIT " . (int)$limit);
        $stmt->execute([
            $locatie,
            date('Y-m-d 00:00:00'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('woo_sync_auto_import_order_to_online_table')) {
    function woo_sync_auto_import_order_to_online_table(PDO $pdoPOS, int $wooOrderId, int $locatie, int $operator): array {
        woo_sync_auto_bar_log('auto_import.start', [
            'woo_order_id' => $wooOrderId,
            'locatie' => $locatie,
            'operator' => $operator,
        ]);

        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru import automat.');
        }

        if (woo_sync_is_imported($pdoPOS, $wooOrderId, $locatie)) {
            woo_sync_auto_bar_log('auto_import.already_imported', [
                'woo_order_id' => $wooOrderId,
            ]);
            return [
                'woo_order_id' => $wooOrderId,
                'imported' => false,
                'printed' => false,
                'message' => 'Comanda era deja importată.',
            ];
        }

        woo_sync_auto_bar_log('auto_import.fetch_order_start', ['woo_order_id' => $wooOrderId]);
        $orderResponse = woo_sync_fetch_order($wooOrderId);
        $order = (array)($orderResponse['data'] ?? []);
        if (!$order) {
            throw new RuntimeException('Comanda Woo nu a putut fi încărcată din API pentru import automat.');
        }
        woo_sync_auto_bar_log('auto_import.fetch_order_done', [
            'woo_order_id' => $wooOrderId,
            'order_number' => $order['number'] ?? null,
            'products_count' => count((array)($order['products'] ?? [])),
        ]);

        woo_sync_auto_bar_log('auto_import.mapping_check_start', ['woo_order_id' => $wooOrderId]);
        woo_sync_assert_order_mappings($pdoPOS, $order);
        woo_sync_auto_bar_log('auto_import.mapping_check_done', ['woo_order_id' => $wooOrderId]);

        $targetTable = woo_sync_pick_online_order_table($pdoPOS, $locatie, $operator);
        $codMasaTarget = (int)($targetTable['cod_masa'] ?? 0);
        if ($codMasaTarget <= 0) {
            throw new RuntimeException('Masa pentru comenzi online este invalidă.');
        }
        woo_sync_auto_bar_log('auto_import.table_selected', [
            'woo_order_id' => $wooOrderId,
            'cod_masa' => $codMasaTarget,
            'table_label' => $targetTable['label'] ?? null,
        ]);

        $nrBonTarget = 0;

        try {
            woo_sync_auto_bar_log('auto_import.transaction_begin', ['woo_order_id' => $wooOrderId]);
            $pdoPOS->beginTransaction();

            $target = woo_sync_resolve_target_note($pdoPOS, $operator, $locatie, 'table', 0, $codMasaTarget);
            $nrBonTarget = (int)$target['nr_bon'];
            woo_sync_auto_bar_log('auto_import.note_resolved', [
                'woo_order_id' => $wooOrderId,
                'nr_bon' => $nrBonTarget,
                'note_created' => !empty($target['created']),
            ]);

            woo_sync_insert_items_into_note($pdoPOS, $nrBonTarget, $order);
            woo_sync_auto_bar_log('auto_import.items_inserted', ['woo_order_id' => $wooOrderId, 'nr_bon' => $nrBonTarget]);

            woo_sync_apply_pos_tva_to_note_from_products($pdoPOS, $nrBonTarget);
            woo_sync_recalc_nota($pdoPOS, $nrBonTarget);
            woo_sync_apply_pos_tva_to_note_from_products($pdoPOS, $nrBonTarget);
            woo_sync_auto_bar_log('auto_import.note_recalculated', ['woo_order_id' => $wooOrderId, 'nr_bon' => $nrBonTarget]);

            woo_sync_set_masa_ocupata($pdoPOS, $codMasaTarget);
            woo_sync_upsert_ultim_bon($pdoPOS, $locatie, $nrBonTarget);
            woo_sync_mark_imported($pdoPOS, $order, $nrBonTarget, $locatie, $operator, 0);
            woo_sync_auto_bar_log('auto_import.import_log_inserted', ['woo_order_id' => $wooOrderId, 'nr_bon' => $nrBonTarget]);

            $pdoPOS->commit();
            woo_sync_auto_bar_log('auto_import.transaction_commit', ['woo_order_id' => $wooOrderId, 'nr_bon' => $nrBonTarget]);
        } catch (Throwable $e) {
            if ($pdoPOS->inTransaction()) {
                $pdoPOS->rollBack();
                woo_sync_auto_bar_log('auto_import.transaction_rollback', ['woo_order_id' => $wooOrderId], $e);
            }
            throw $e;
        }

        $printed = false;
        $printError = '';
        $queueFile = '';

        try {
            $printResult = woo_sync_print_imported_site_order_to_bar($pdoPOS, $wooOrderId, $locatie, 'after_auto_import');
            $printed = true;
            $queueFile = (string)($printResult['queue_file'] ?? '');
        } catch (Throwable $e) {
            $printError = $e->getMessage();
            woo_sync_auto_bar_log('auto_import.print_failed', [
                'woo_order_id' => $wooOrderId,
                'nr_bon' => $nrBonTarget,
            ], $e);
        }

        $result = [
            'woo_order_id' => $wooOrderId,
            'order_number' => (string)($order['number'] ?? $wooOrderId),
            'nr_bon' => $nrBonTarget,
            'cod_masa' => $codMasaTarget,
            'imported' => true,
            'printed' => $printed,
            'queue_file' => $queueFile,
            'print_error' => $printError,
        ];

        woo_sync_auto_bar_log('auto_import.done', $result);

        return $result;
    }
}
