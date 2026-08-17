<?php
// vanzare_importa_comanda_woo.php — import comenzi WooCommerce -> nota curenta / masa selectata
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/eroare_woo_sync.log');

date_default_timezone_set('Europe/Bucharest');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/session.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('<h1>Lipsă conexiune POS ($pdo).</h1>');
}
if (!isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
    header('Location: logout.php');
    exit;
}
if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
    http_response_code(403);
    die('<h1>Importul WooCommerce este dezactivat in modul SQLite offline.</h1>');
}

require_once __DIR__ . '/includes/woo_sync_helpers.php';

$adm_id = (int)$_SESSION['admin_id'];
$cod_locatie = (int)$_SESSION['cod_locatie'];
$currentNrBon = (int)($_SESSION['nr_bon'] ?? 0);

$tzBucharest = new DateTimeZone('Europe/Bucharest');
$defaultWindowTo = new DateTimeImmutable('now', $tzBucharest);
$defaultWindowFrom = $defaultWindowTo->sub(new DateInterval('PT24H'));

$dateFromInput = trim((string)($_GET['date_from'] ?? ''));
$dateToInput = trim((string)($_GET['date_to'] ?? ''));
$dateFilterActive = ($dateFromInput !== '' || $dateToInput !== '');
$dateFilterWarning = '';

$windowFrom = $defaultWindowFrom;
$windowTo = $defaultWindowTo;
$windowModeLabel = 'ultimele 24h';

if ($dateFilterActive) {
    $effectiveDateFrom = $dateFromInput !== '' ? $dateFromInput : $dateToInput;
    $effectiveDateTo = $dateToInput !== '' ? $dateToInput : $defaultWindowTo->format('Y-m-d');

    $parsedFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDateFrom, $tzBucharest);
    $parsedTo = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDateTo, $tzBucharest);

    $fromIsValid = $parsedFrom instanceof DateTimeImmutable && $parsedFrom->format('Y-m-d') === $effectiveDateFrom;
    $toIsValid = $parsedTo instanceof DateTimeImmutable && $parsedTo->format('Y-m-d') === $effectiveDateTo;

    if (!$fromIsValid || !$toIsValid) {
        $dateFilterWarning = 'Filtrul de dată este invalid. Se afișează implicit ultimele 24h.';
    } else {
        $windowFrom = $parsedFrom->setTime(0, 0, 0);
        $windowTo = $parsedTo->setTime(23, 59, 59);

        if ($windowFrom > $windowTo) {
            $tmp = $windowFrom;
            $windowFrom = $windowTo->setTime(0, 0, 0);
            $windowTo = $tmp->setTime(23, 59, 59);
            $dateFilterWarning = 'Data de început era mai mare decât data de final. Intervalul a fost inversat automat.';
        }

        $windowModeLabel = 'interval selectat';
    }
}

$windowLabel = $windowFrom->format('d.m.Y H:i') . ' - ' . $windowTo->format('d.m.Y H:i') . ' (Europe/Bucharest)';

$currentPageQuery = array_filter([
    'date_from' => $dateFromInput,
    'date_to' => $dateToInput,
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
], static function ($v) {
    return $v !== '' && $v !== null && $v !== 1;
});
$currentImportUrl = 'vanzare_importa_comanda_woo.php' . ($currentPageQuery ? '?' . http_build_query($currentPageQuery) : '');

$message = $_SESSION['woo_flash_message'] ?? null;
$error = null;
unset($_SESSION['woo_flash_message']);



if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['ajax'] ?? '') === 'wp_order_details') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $wooOrderId = (int)($_GET['woo_order_id'] ?? 0);
        $details = woo_sync_fetch_wp_order_details($wooOrderId);

        echo json_encode([
            'ok' => true,
            'details' => $details,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);

        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['ajax'] ?? '') === 'print_wp_order_bar') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $wooOrderId = (int)($_POST['woo_order_id'] ?? 0);
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru listare BAR.');
        }

        $wpDetails = woo_sync_fetch_wp_order_details($wooOrderId);
        woo_sync_send_wp_order_to_bar_printer($wpDetails, $wooOrderId, $cod_locatie);

        echo json_encode([
            'ok' => true,
            'message' => 'Comanda Woo #' . $wooOrderId . ' a fost trimisă la imprimanta BAR.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);

        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}


/**
 * Completează TVA-ul pe liniile din det_note după importul Woo.
 * Cota TVA se ia din produse_servicii.cota_tva, iar TVA colectată se calculează
 * din valoarea cu TVA deja importată pe linie.
 */
function woo_sync_apply_pos_tva_to_note(PDO $pdo, int $nrBon): void
{
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

function woo_sync_scanare_json_base_url(): string
{
    $cfg = woo_sync_cfg();
    $base = trim((string)($cfg['order_json_base_url'] ?? ''));

    if ($base === '') {
        throw new RuntimeException('Lipsește order_json_base_url în includes/woo_sync_config.php.');
    }

    return rtrim($base, '/');
}

function woo_sync_fetch_scanare_order_json(int $wooOrderId): array
{
    if ($wooOrderId <= 0) {
        throw new RuntimeException('ID comandă Woo invalid pentru citirea JSON.');
    }

    $url = woo_sync_scanare_json_base_url() . '/Nota_' . $wooOrderId . '.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException('Nu pot citi JSON-ul comenzii Woo: ' . $err);
    }

    if ($httpCode === 404) {
        throw new RuntimeException('Nu există fișierul Nota_' . $wooOrderId . '.json.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Citirea JSON-ului Woo a răspuns cu HTTP ' . $httpCode . '.');
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        throw new RuntimeException('Fișierul JSON al comenzii Woo este invalid.');
    }

    return $data;
}

function woo_sync_format_scanare_json_for_bar(array $data): string
{
    $lines = [];

    $lines[] = 'COMANDA ONLINE #' . (string)($data['order_id'] ?? '');
    $lines[] = 'Data: ' . (string)($data['order_date'] ?? '');
    $lines[] = 'Client: ' . (string)($data['client'] ?? '-');
    $lines[] = 'Telefon: ' . (string)($data['phone'] ?? '-');
    $lines[] = 'Plata: ' . (string)($data['payment_method'] ?? '-');
    $lines[] = 'Livrare: ' . (string)($data['shipping_method'] ?? '-');

    $address = trim((string)($data['shipping_address'] ?? ''));
    if ($address !== '') {
        $lines[] = 'Adresa: ' . $address;
    }

    $note = trim((string)($data['order_note'] ?? ''));
    if ($note !== '') {
        $lines[] = 'Observatii: ' . str_replace(["\r\n", "\r", "\n"], ' | ', $note);
    }

    $lines[] = str_repeat('-', 32);
    $lines[] = 'PRODUSE:';

    $items = (array)($data['items'] ?? []);

    if (!$items) {
        $lines[] = '- fara produse -';
    } else {
        foreach ($items as $item) {
            $item = (array)$item;

            $qty = (float)($item['quantity'] ?? 0);
            $name = trim((string)($item['name'] ?? 'Produs'));
            $subtotal = (float)($item['subtotal'] ?? 0);

            $lines[] = $qty . ' x ' . $name . ' = ' . number_format($subtotal, 2, ',', '.') . ' lei';

            $meta = (array)($item['meta'] ?? []);
            foreach ($meta as $mk => $mv) {
                if (is_array($mv) || is_object($mv)) {
                    $mv = json_encode($mv, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                $mv = trim((string)$mv);
                if ($mv !== '') {
                    $lines[] = '  - ' . (string)$mk . ': ' . $mv;
                }
            }
        }
    }

    $lines[] = str_repeat('-', 32);
    $lines[] = 'Total: ' . number_format((float)($data['total'] ?? 0), 2, ',', '.') . ' lei';
    $lines[] = 'Transport: ' . number_format((float)($data['shipping_cost'] ?? 0), 2, ',', '.') . ' lei';
    $lines[] = 'Total final: ' . number_format((float)($data['final_price'] ?? $data['total'] ?? 0), 2, ',', '.') . ' lei';

    return implode("\n", $lines);
}


function woo_sync_bar_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function woo_sync_bar_clean_text($value): string
{
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

function woo_sync_bar_money($value): string
{
    if (!is_numeric($value)) {
        $clean = woo_sync_bar_clean_text($value);
        return $clean !== '' ? $clean . ' lei' : '0 lei';
    }

    $amount = (float)$value;
    $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

    return number_format($amount, $decimals, ',', '.') . ' lei';
}

function woo_sync_bar_qty($value): string
{
    if (!is_numeric($value)) {
        $clean = woo_sync_bar_clean_text($value);
        return $clean !== '' ? $clean : '0';
    }

    $qty = (float)$value;
    if (abs($qty - round($qty)) < 0.00001) {
        return (string)(int)round($qty);
    }

    return rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
}

function woo_sync_bar_format_date($value): string
{
    $raw = woo_sync_bar_clean_text($value);
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

function woo_sync_bar_address(array $address, array $customer = []): string
{
    $nameParts = [];
    foreach (['first_name', 'last_name'] as $key) {
        $part = woo_sync_bar_clean_text($address[$key] ?? '');
        if ($part !== '') {
            $nameParts[] = $part;
        }
    }

    $name = trim(implode(' ', $nameParts));
    if ($name === '') {
        $name = woo_sync_bar_clean_text($customer['name'] ?? '');
    }

    $parts = [];
    if ($name !== '') {
        $parts[] = $name;
    }

    foreach (['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $key) {
        $part = woo_sync_bar_clean_text($address[$key] ?? '');
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return $parts ? implode(', ', $parts) : '-';
}

function woo_sync_bar_shipping_method(array $order): string
{
    $shippingLines = (array)($order['shipping_lines'] ?? []);
    foreach ($shippingLines as $line) {
        $line = (array)$line;
        $name = woo_sync_bar_clean_text($line['name'] ?? '');
        if ($name !== '') {
            return $name;
        }
    }

    $shippingTotal = (float)($order['totals']['shipping_total'] ?? 0) + (float)($order['totals']['shipping_tax'] ?? 0);
    return $shippingTotal > 0 ? 'Livrare' : 'Ridicare de la restaurant';
}

function woo_sync_bar_item_notes(array $item): array
{
    $notes = [];
    $seen = [];
    $metaRows = (array)($item['meta'] ?? []);

    foreach ($metaRows as $meta) {
        $meta = (array)$meta;
        $key = woo_sync_bar_clean_text($meta['key'] ?? $meta['display_key'] ?? '');

        if ($key !== '' && strpos($key, '_') === 0) {
            continue;
        }

        $value = $meta['display_value'] ?? $meta['value'] ?? '';
        $value = woo_sync_bar_clean_text($value);

        if ($value === '' || $value === '[]' || $value === '{}') {
            continue;
        }

        $dedupeKey = woo_sync_bar_lower($value);
        if (isset($seen[$dedupeKey])) {
            continue;
        }

        $seen[$dedupeKey] = true;
        $notes[] = $value;
    }

    return $notes;
}

function woo_sync_bar_order_notes(array $order): string
{
    $notes = [];
    $seen = [];

    $customer = (array)($order['customer'] ?? []);
    $customerNote = woo_sync_bar_clean_text($customer['customer_note'] ?? '');
    if ($customerNote !== '') {
        $notes[] = $customerNote;
        $seen[woo_sync_bar_lower($customerNote)] = true;
    }

    foreach ((array)($order['notes'] ?? []) as $note) {
        $note = (array)$note;
        $content = woo_sync_bar_clean_text($note['content'] ?? '');
        if ($content === '') {
            continue;
        }

        $key = woo_sync_bar_lower($content);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $notes[] = $content;
    }

    return $notes ? implode(' | ', $notes) : '-';
}

function woo_sync_format_wp_order_details_for_bar(array $wpDetails, int $wooOrderId): string
{
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

    $orderNumber = woo_sync_bar_clean_text($order['number'] ?? $order['id'] ?? $wooOrderId);
    if ($orderNumber === '') {
        $orderNumber = (string)$wooOrderId;
    }

    $customerName = woo_sync_bar_clean_text($customer['name'] ?? '');
    if ($customerName === '') {
        $customerName = trim(woo_sync_bar_clean_text($billing['first_name'] ?? '') . ' ' . woo_sync_bar_clean_text($billing['last_name'] ?? ''));
    }
    if ($customerName === '') {
        $customerName = '-';
    }

    $paymentMethod = woo_sync_bar_clean_text($payment['method_title'] ?? $payment['method'] ?? '-');
    if ($paymentMethod === '') {
        $paymentMethod = '-';
    }

    $phone = woo_sync_bar_clean_text($customer['phone'] ?? $billing['phone'] ?? '-');
    if ($phone === '') {
        $phone = '-';
    }

    $shippingAddress = woo_sync_bar_address($shipping, $customer);
    if ($shippingAddress === '-' || trim($shippingAddress) === '') {
        $shippingAddress = woo_sync_bar_address($billing, $customer);
    }

    $createdAt = woo_sync_bar_format_date($dates['created'] ?? $order['date_created'] ?? '');
    $shippingTotal = (float)($totals['shipping_total'] ?? 0) + (float)($totals['shipping_tax'] ?? 0);
    $finalTotal = (float)($totals['total'] ?? 0);

    $productTotal = 0.0;
    $productLines = [];
    $itemNotes = [];

    foreach ($items as $item) {
        $item = (array)$item;
        $qty = (float)($item['quantity'] ?? 0);
        $name = woo_sync_bar_clean_text($item['name'] ?? 'Produs');
        if ($name === '') {
            $name = 'Produs';
        }

        $lineTotal = (float)($item['total'] ?? 0) + (float)($item['total_tax'] ?? 0);
        if (abs($lineTotal) < 0.00001) {
            $lineTotal = (float)($item['subtotal'] ?? 0) + (float)($item['subtotal_tax'] ?? 0);
        }

        $productTotal += $lineTotal;
        $productLines[] = woo_sync_bar_qty($qty) . ' x ' . $name . ' = ' . woo_sync_bar_money($lineTotal);

        foreach (woo_sync_bar_item_notes($item) as $noteLine) {
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
        $itemNote = woo_sync_bar_clean_text($itemNote);
        if ($itemNote === '') {
            continue;
        }
        $key = woo_sync_bar_lower($itemNote);
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
    $lines[] = 'Total: ' . woo_sync_bar_money($productTotal);
    $lines[] = 'Livrare: ' . woo_sync_bar_money($shippingTotal);
    $lines[] = 'Metoda de plata: ' . $paymentMethod;
    $lines[] = $separator;
    $lines[] = 'Metoda de livrare: ' . woo_sync_bar_shipping_method($order);
    $lines[] = $separator;
    $lines[] = 'Pret final: ' . woo_sync_bar_money($finalTotal);
    $lines[] = 'Telefon: ' . $phone;
    $lines[] = $separator;
    $lines[] = 'Adresa de livrare:';
    $lines[] = $shippingAddress;
    $lines[] = $separator;
    $lines[] = 'Nota comanda:';
    $lines[] = woo_sync_bar_order_notes($order);
    $lines[] = $separator;

    return implode("\n", $lines);
}

function woo_sync_write_bar_queue_file(string $content, int $wooOrderId, int $codLocatie, array $wpDetails = []): string
{
    $cfg = woo_sync_cfg();

    $clientId = (string)($_SESSION['client_id'] ?? $_SESSION['clientId'] ?? '');
    $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', $clientId);

    if ($clientId === '') {
        throw new RuntimeException('Lipsește client_id în sesiune.');
    }

    $queueBaseDir = (string)($cfg['printer_queue_base_dir'] ?? RESTAURANT_OFFLINE_API_DIR);
    $queueDir = rtrim($queueBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $codLocatie;

    if (!is_dir($queueDir) && !mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
        throw new RuntimeException('Nu pot crea folderul pentru coada imprimantei: ' . $queueDir);
    }

    $queueFile = $queueDir . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';

    $deadline = time() + 60;
    while (is_file($queueFile) && time() < $deadline) {
        sleep(5);
        clearstatcache(true, $queueFile);
    }

    if (is_file($queueFile)) {
        throw new RuntimeException('Coada de imprimare este ocupată. Încearcă din nou după câteva secunde.');
    }

    $printData = [[
        'data'                    => date('Y-m-d'),
        'ora'                     => date('H:i:s'),
        'de_trimis_la_imprimanta' => 1,
        'nrbon'                   => $wooOrderId,
        'locatie'                 => $codLocatie,
        'departament_listare'     => 'BAR',
        'departament'             => 'BAR',
        'tip'                     => 'COMANDA_ONLINE_SITE',
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
        throw new RuntimeException('Nu pot genera JSON-ul pentru coada de imprimare.');
    }

    if (file_put_contents($tmpFile, $json, LOCK_EX) === false) {
        throw new RuntimeException('Nu pot scrie fișierul temporar pentru imprimantă.');
    }

    if (!rename($tmpFile, $queueFile)) {
        @unlink($tmpFile);
        throw new RuntimeException('Nu pot publica fișierul pentru imprimantă.');
    }

    return $queueFile;
}

function woo_sync_send_wp_order_to_bar_printer(array $wpDetails, int $wooOrderId, int $codLocatie): string
{
    $content = woo_sync_format_wp_order_details_for_bar($wpDetails, $wooOrderId);
    return woo_sync_write_bar_queue_file($content, $wooOrderId, $codLocatie, $wpDetails);
}

function woo_sync_send_json_to_bar_printer(array $data, int $wooOrderId, int $codLocatie): string
{
    $cfg = woo_sync_cfg();

    $clientId = (string)($_SESSION['client_id'] ?? $_SESSION['clientId'] ?? '');
    $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', $clientId);

    if ($clientId === '') {
        throw new RuntimeException('Lipsește client_id în sesiune.');
    }

    $queueBaseDir = (string)($cfg['printer_queue_base_dir'] ?? RESTAURANT_OFFLINE_API_DIR);
    $queueDir = rtrim($queueBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $clientId . DIRECTORY_SEPARATOR . $codLocatie;

    if (!is_dir($queueDir) && !mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
        throw new RuntimeException('Nu pot crea folderul pentru coada imprimantei: ' . $queueDir);
    }

    $queueFile = $queueDir . DIRECTORY_SEPARATOR . 'de_listat_la_imprimanta.json';

    $deadline = time() + 60;
    while (is_file($queueFile) && time() < $deadline) {
        sleep(5);
        clearstatcache(true, $queueFile);
    }

    if (is_file($queueFile)) {
        throw new RuntimeException('Coada de imprimare este ocupată. Încearcă din nou după câteva secunde.');
    }

    $text = woo_sync_format_scanare_json_for_bar($data);

    $printData = [[
        'id'                      => 0,
        'data'                    => date('Y-m-d'),
        'ora'                     => date('H:i:s'),
        'de_trimis_la_imprimanta' => 1,
        'nrbon'                   => (int)$wooOrderId,
        'locatie'                 => (int)$codLocatie,
        'departament_listare'     => 'BAR',
        'departament'             => 'BAR',
        'tip'                     => 'COMANDA_ONLINE_VERIFICARE',
        'woo_order_id'            => (int)$wooOrderId,
        'created_at'              => date('Y-m-d H:i:s'),
        'continut'                => $text,
        'mesaj'                   => $text,
        'json_original'           => $data,
    ]];

    $jsonArray = [
        'status'  => 'success',
        'message' => 'Datele comenzii online au fost pregătite pentru verificare la BAR.',
        'data'    => $printData,
    ];

    $tmpFile = $queueFile . '.tmp.' . getmypid();

    $json = json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Nu pot genera JSON-ul pentru coada de imprimare.');
    }

    if (file_put_contents($tmpFile, $json, LOCK_EX) === false) {
        throw new RuntimeException('Nu pot scrie fișierul temporar pentru imprimantă.');
    }

    if (!rename($tmpFile, $queueFile)) {
        @unlink($tmpFile);
        throw new RuntimeException('Nu pot publica fișierul pentru imprimantă.');
    }

    return $queueFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'list_woo_order_bar') {
    $wooOrderId = (int)($_POST['woo_order_id'] ?? 0);

    try {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid pentru listare.');
        }

        $wpDetails = woo_sync_fetch_wp_order_details($wooOrderId);
        woo_sync_send_wp_order_to_bar_printer($wpDetails, $wooOrderId, $cod_locatie);

        $_SESSION['woo_flash_message'] = 'Comanda Woo #' . $wooOrderId . ' a fost trimisă la imprimanta BAR pentru verificare.';

        header('Location: ' . $currentImportUrl);
        exit;
    } catch (Throwable $e) {
        $error = 'Eroare listare BAR: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'mark_woo_order_imported') {
    $wooOrderId = (int)($_POST['woo_order_id'] ?? 0);

    try {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid.');
        }

        if (woo_sync_is_imported($pdo, $wooOrderId, $cod_locatie)) {
            $_SESSION['woo_flash_message'] = 'Comanda Woo #' . $wooOrderId . ' era deja marcată ca importată.';
        } else {
            $orderResponse = woo_sync_fetch_order($wooOrderId);
            $order = (array)($orderResponse['data'] ?? []);
            if (!$order) {
                throw new RuntimeException('Comanda Woo nu a putut fi încărcată din API.');
            }

            woo_sync_mark_manually_imported($pdo, $order, $cod_locatie, $adm_id);
            $_SESSION['woo_flash_message'] = 'Comanda Woo #' . ((string)($order['number'] ?? $wooOrderId)) . ' a fost marcată manual ca importată.';
        }

        header('Location: ' . $currentImportUrl);
        exit;
    } catch (Throwable $e) {
        $error = 'Eroare marcare manuală Woo: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'import_woo_order') {
    $wooOrderId = (int)($_POST['woo_order_id'] ?? 0);

    $targetMode = (string)($_POST['target_mode'] ?? 'table');
    if (!in_array($targetMode, ['table', 'current_note'], true)) {
        $targetMode = 'table';
    }

    $codMasaTarget = (int)($_POST['cod_masa_target'] ?? 0);
    if ($targetMode === 'current_note') {
        $codMasaTarget = 0;
    }

    try {
        if ($wooOrderId <= 0) {
            throw new RuntimeException('ID comandă Woo invalid.');
        }

        if ($targetMode === 'table' && $codMasaTarget <= 0) {
            throw new RuntimeException('Selectează masa țintă înainte de import.');
        }

        if (woo_sync_is_imported($pdo, $wooOrderId, $cod_locatie)) {
            throw new RuntimeException('Comanda Woo a fost deja importată local.');
        }

        $orderResponse = woo_sync_fetch_order($wooOrderId);
        $order = (array)($orderResponse['data'] ?? []);
        if (!$order) {
            throw new RuntimeException('Comanda Woo nu a putut fi încărcată din API.');
        }

        woo_sync_assert_order_mappings($pdo, $order);

        $pdo->beginTransaction();

        $target = woo_sync_resolve_target_note($pdo, $adm_id, $cod_locatie, $targetMode, $currentNrBon, $codMasaTarget);
        $nrBonTarget = (int)$target['nr_bon'];

        woo_sync_insert_items_into_note($pdo, $nrBonTarget, $order);
        woo_sync_apply_pos_tva_to_note($pdo, $nrBonTarget);
        woo_sync_recalc_nota($pdo, $nrBonTarget);
        woo_sync_apply_pos_tva_to_note($pdo, $nrBonTarget);

        if ((int)$target['cod_masa'] > 0) {
            woo_sync_set_masa_ocupata($pdo, (int)$target['cod_masa']);
            $_SESSION['masa_curenta'] = (int)$target['cod_masa'];
        }

        woo_sync_upsert_ultim_bon($pdo, $cod_locatie, $nrBonTarget);
        woo_sync_mark_imported($pdo, $order, $nrBonTarget, $cod_locatie, $adm_id);

        $_SESSION['nr_bon'] = $nrBonTarget;
        $_SESSION['trimis_comanda'] = 0;

        $pdo->commit();
        header('Location: vanzare_restaurant.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Eroare import Woo: ' . $e->getMessage();
    }
}

$filters = [
    'date' => '',
    'date_from' => $windowFrom->format('Y-m-d'),
    'date_to' => $windowTo->format('Y-m-d'),
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per_page' => 100,
];

$ordersRaw = [];
$ordersInWindow = [];
$pendingOrders = [];
$pagination = ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 100];
$detailsByOrder = [];
$importedDetailsByOrder = [];
$ordersById = [];
$importedMap = [];
$importedRows = [];

try {
    $resp = woo_sync_fetch_orders(array_filter($filters, static function ($v) {
        return $v !== '' && $v !== null;
    }));

    $ordersRaw = (array)($resp['data'] ?? []);
    $ordersInWindow = woo_sync_filter_orders_by_window($ordersRaw, $windowFrom, $windowTo);
    $pagination = array_merge($pagination, (array)($resp['pagination'] ?? []));

    foreach ($ordersInWindow as $order) {
        if (isset($order['id'])) {
            $ordersById[(int)$order['id']] = $order;
        }
    }
} catch (Throwable $e) {
    $error = 'Nu s-au putut încărca comenzile Woo: ' . $e->getMessage();
}

try {
    $importedRows = woo_sync_fetch_imports_last_24h($pdo, $cod_locatie, $windowFrom, $windowTo);
    foreach ($importedRows as $row) {
        $oid = (int)($row['woo_order_id'] ?? 0);
        if ($oid > 0) {
            $importedMap[$oid] = true;
        }
    }
} catch (Throwable $e) {
    $importedRows = [];
    $error = ($error ? $error . ' | ' : '') . 'Eroare încărcare importuri Woo în intervalul selectat: ' . $e->getMessage();
}

foreach ($ordersInWindow as $order) {
    $oid = (int)($order['id'] ?? 0);
    if ($oid <= 0) {
        continue;
    }

    if (empty($importedMap[$oid])) {
        try {
            $importedMap[$oid] = woo_sync_is_imported($pdo, $oid, $cod_locatie);
        } catch (Throwable $e) {
            $importedMap[$oid] = false;
            $error = ($error ? $error . ' | ' : '') . 'Eroare verificare import comandă #' . $oid . ': ' . $e->getMessage();
        }
    }

    if (empty($importedMap[$oid])) {
        $pendingOrders[] = $order;
        $detailsByOrder[$oid] = $order;
    }
}

// Pentru tabelul de importate, încercăm să completăm clientul/totalul și pentru rândurile care nu mai sunt în pagina curentă din API.
foreach ($importedRows as $row) {
    $oid = (int)($row['woo_order_id'] ?? 0);
    if ($oid <= 0 || isset($ordersById[$oid])) {
        continue;
    }

    try {
        $orderResponse = woo_sync_fetch_order($oid);
        $order = (array)($orderResponse['data'] ?? []);
        if ($order) {
            $ordersById[$oid] = $order;
        }
    } catch (Throwable $e) {
        // Dacă Woo nu mai răspunde pentru o comandă importată, păstrăm afișarea din logul local.
    }
}


// Pregătim detaliile pentru comenzile deja importate, ca să poată fi deschise în modal doar pentru vizualizare.
foreach ($importedRows as $row) {
    $oid = (int)($row['woo_order_id'] ?? 0);
    if ($oid > 0 && !empty($ordersById[$oid])) {
        $importedDetailsByOrder[$oid] = $ordersById[$oid];
    }
}
$tableOptions = [];
$currentNoteValid = false;

try {
    $tableOptions = woo_sync_fetch_tables_for_operator($pdo, $cod_locatie, $adm_id);
} catch (Throwable $e) {
    $tableOptions = [];
    $error = ($error ? $error . ' | ' : '') . 'Eroare încărcare mese POS: ' . $e->getMessage();
}

try {
    $currentNoteValid = $currentNrBon > 0 && woo_sync_current_note_is_valid($pdo, $currentNrBon, $adm_id, $cod_locatie);
} catch (Throwable $e) {
    $currentNoteValid = false;
    $error = ($error ? $error . ' | ' : '') . 'Eroare validare notă curentă: ' . $e->getMessage();
}

$pageLinkFilters = [
    'date_from' => $dateFromInput,
    'date_to' => $dateToInput,
    'status' => $filters['status'],
    'search' => $filters['search'],
];
$pageLinkFilters = array_filter($pageLinkFilters, static function ($v) {
    return $v !== '' && $v !== null;
});
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import comenzi WooCommerce</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-size: 1.05rem; background:#f8f9fa; }
    .page-header { background:linear-gradient(90deg,#0d6efd,#20c997); color:#fff; padding:20px; }
    .back-sales-btn {
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      padding:.45rem .85rem;
      border-radius:999px;
      background:rgba(255,255,255,.92);
      color:#0d6efd;
      border:1px solid rgba(255,255,255,.75) !important;
      font-weight:700;
      font-size:.9rem;
      box-shadow:0 4px 12px rgba(0,0,0,.12);
      transition:all .18s ease-in-out;
      text-decoration:none;
    }

    .back-sales-btn:hover {
      background:#fff;
      color:#0a58ca;
      transform:translateY(-1px);
      box-shadow:0 6px 16px rgba(0,0,0,.16);
      text-decoration:none;
    }

    .back-sales-btn:active {
      transform:translateY(0);
      box-shadow:0 3px 8px rgba(0,0,0,.12);
    }
    .cursor-pointer { cursor:pointer; }
    .nowrap { white-space:nowrap; }
    .toolbar-card {
      border:0;
      border-radius:1rem;
      box-shadow:0 8px 24px rgba(13,110,253,.10), 0 2px 8px rgba(0,0,0,.05);
      overflow:hidden;
      background:#fff;
    }
    .filter-panel-header {
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:1rem;
      padding:1rem 1.15rem;
      background:linear-gradient(90deg, rgba(13,110,253,.10), rgba(32,201,151,.10));
      border-bottom:1px solid rgba(13,110,253,.10);
    }
    .filter-title {
      font-size:1.08rem;
      font-weight:800;
      color:#1f2937;
      margin:0;
      letter-spacing:-.01em;
    }
    .filter-subtitle {
      margin:.2rem 0 0;
      color:#6c757d;
      font-size:.92rem;
    }
    .filter-window-badge {
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      padding:.42rem .7rem;
      border-radius:999px;
      background:#fff;
      color:#0d6efd;
      font-weight:700;
      font-size:.88rem;
      border:1px solid rgba(13,110,253,.16);
      box-shadow:0 1px 4px rgba(0,0,0,.04);
      white-space:nowrap;
    }
    .filter-panel-body { padding:1.15rem; }
    .filter-panel-body .form-label {
      margin-bottom:.35rem;
      color:#495057;
      font-size:.82rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.035em;
    }
    .filter-panel-body .form-control {
      min-height:44px;
      border-radius:.75rem;
      border-color:#dbe3ef;
      background:#fbfcff;
      box-shadow:none;
    }
    .filter-panel-body .form-control:focus {
      border-color:#86b7fe;
      background:#fff;
      box-shadow:0 0 0 .2rem rgba(13,110,253,.12);
    }
    .filter-actions {
      display:grid;
      grid-template-columns:1fr;
      gap:.55rem;
    }
    .filter-actions .btn {
      min-height:44px;
      border-radius:.75rem;
      font-weight:700;
    }
    .filter-actions .btn-primary {
      box-shadow:0 6px 14px rgba(13,110,253,.18);
    }
    .filter-help {
      margin-top:.85rem;
      padding:.7rem .85rem;
      border-radius:.85rem;
      background:#f8fafc;
      border:1px dashed #cfd8e3;
      color:#6c757d;
      font-size:.9rem;
    }
    .interval-card {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
      padding:.85rem 1rem;
      border-radius:1rem;
      background:#eef7ff;
      border:1px solid rgba(13,110,253,.12);
      color:#24405f;
    }
    .interval-card .interval-title {
      font-weight:800;
      color:#0d6efd;
    }
    .interval-card .interval-text {
      font-weight:700;
      color:#1f2937;
    }
    .interval-card .interval-note {
      display:block;
      margin-top:.12rem;
      color:#6c757d;
      font-size:.88rem;
    }
    .modal-products-scroll { max-height:min(52vh, 460px); overflow:auto; scrollbar-width:auto; }

    .woo-scroll-controls {
      position: sticky;
      top: 0;
      z-index: 5;
      background: #fff;
      padding: .35rem .5rem;
      border: 1px solid #dee2e6;
      border-bottom: 0;
      border-radius: .375rem .375rem 0 0;
    }

    .woo-scroll-controls .btn {
      min-width: 120px;
    }

    .small-muted { color:#6c757d; font-size:.92rem; }
    .pill { display:inline-block; padding:.2rem .55rem; border-radius:999px; background:#eef3ff; }
    .woo-modal-template { display:none !important; }
    .section-title { font-size:1.15rem; font-weight:700; margin:0; }
    .table-card { border:0; box-shadow:0 3px 12px rgba(0,0,0,.08); overflow:hidden; }
    .table-card .card-header { padding:.9rem 1rem; }
    .table-card .table th { vertical-align:middle; }
    .table-card .table td { vertical-align:middle; }
    .actions-cell { min-width:230px; }
    .imported-card { margin-top:1.75rem; }
    .manual-form { display:inline; }
    .woo-hscroll-area {
      position: relative;
    }

    .woo-hscroll-toolbar {
      position: sticky;
      top: 0;
      left: 0;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .65rem;
      padding: .65rem;
      background: linear-gradient(90deg, #ffffff, #f1f7ff);
      border-bottom: 1px solid #dbe4ff;
      box-shadow: 0 3px 10px rgba(0,0,0,.06);
      min-width: 100%;
    }

    .woo-hscroll-btn {
      min-width: 150px;
      min-height: 48px;
      border-radius: .85rem;
      font-size: 1rem;
      font-weight: 800;
      border: 1px solid #0d6efd;
      background: #0d6efd;
      color: #fff;
      box-shadow: 0 5px 14px rgba(13,110,253,.20);
      transition: all .15s ease-in-out;
    }

    .woo-hscroll-btn:hover:not(:disabled) {
      background: #0a58ca;
      border-color: #0a58ca;
      transform: translateY(-1px);
    }

    .woo-hscroll-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
    }

    .woo-hscroll-hint {
      color: #495057;
      font-size: .92rem;
      font-weight: 700;
      text-align: center;
      white-space: nowrap;
    }

    @media (max-width: 768px) {
      .woo-hscroll-toolbar {
        gap: .45rem;
        padding: .55rem;
      }

      .woo-hscroll-btn {
        flex: 1;
        min-width: 0;
        min-height: 54px;
        font-size: 1rem;
      }

      .woo-hscroll-hint {
        display: none;
      }
    }

    .wp-order-details-box {
      border:1px solid #dbe4ff;
      background:#f8fbff;
      border-radius:.75rem;
      padding:1rem;
      margin-bottom:1rem;
    }
    .wp-order-details-title {
      font-weight:700;
      color:#0d6efd;
      margin-bottom:.75rem;
    }
    .wp-detail-grid {
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:.75rem;
    }
    .wp-detail-card {
      background:#fff;
      border:1px solid #e9ecef;
      border-radius:.6rem;
      padding:.7rem;
      min-width:0;
    }
    .wp-detail-label {
      font-size:.78rem;
      color:#6c757d;
      text-transform:uppercase;
      letter-spacing:.03em;
    }
    .wp-detail-value {
      font-weight:700;
      word-break:break-word;
    }
    .wp-address-box {
      background:#fff;
      border:1px solid #e9ecef;
      border-radius:.6rem;
      padding:.75rem;
      height:100%;
    }
    .wp-raw-json {
      max-height:360px;
      overflow:auto;
      background:#111827;
      color:#e5e7eb;
      border-radius:.5rem;
      padding:.75rem;
      font-size:.82rem;
      white-space:pre-wrap;
    }

    @media (max-width: 768px) {
      body { font-size:1rem; }
      .actions-cell { min-width:260px; }
      .filter-panel-header,
      .interval-card { flex-direction:column; align-items:flex-start; }
      .filter-window-badge { white-space:normal; }
      .wp-detail-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }


    @media (max-width: 576px) {
      .wp-detail-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<header class="page-header">
  <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <a href="vanzare_restaurant.php" class="btn btn-light btn-sm border back-sales-btn">← Înapoi la vânzări</a>
      <h1 class="m-0 fs-3">Import comenzi WooCommerce</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($currentNoteValid): ?>
        <span class="badge text-bg-light fs-6">Nota curentă: #<?= (int)$currentNrBon ?></span>
      <?php else: ?>
        <span class="badge text-bg-warning text-dark fs-6">Nu există notă curentă activă</span>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="container my-4">
  <?php if ($message): ?><div class="alert alert-success"><?= woo_sync_h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= nl2br(woo_sync_h($error)) ?></div><?php endif; ?>
  <?php if ($dateFilterWarning !== ''): ?><div class="alert alert-warning"><?= woo_sync_h($dateFilterWarning) ?></div><?php endif; ?>

  <div class="interval-card mb-3">
    <div>
      <span class="interval-title">Interval afișat</span>
      <span class="text-muted">(<?= woo_sync_h($windowModeLabel) ?>)</span>:
      <span class="interval-text"><?= woo_sync_h($windowLabel) ?></span>
      <span class="interval-note">Lasă câmpurile de dată necompletate pentru afișarea implicită pe ultimele 24h.</span>
    </div>
  </div>

  <div class="card toolbar-card mb-4">
    <div class="filter-panel-header">
      <div>
        <h2 class="filter-title">Filtre comenzi WooCommerce</h2>
        <p class="filter-subtitle">Selectează intervalul, statusul sau caută rapid după comandă, client ori telefon.</p>
      </div>
      <span class="filter-window-badge">⏱ <?= woo_sync_h($windowModeLabel) ?></span>
    </div>
    <div class="filter-panel-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label" for="date_from">De la data</label>
          <input type="date" class="form-control" id="date_from" name="date_from" value="<?= woo_sync_h($dateFromInput) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="date_to">Până la data</label>
          <input type="date" class="form-control" id="date_to" name="date_to" value="<?= woo_sync_h($dateToInput) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="status">Status</label>
          <input type="text" class="form-control" id="status" name="status" placeholder="processing,on-hold" value="<?= woo_sync_h($filters['status']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="search">Caută</label>
          <input type="text" class="form-control" id="search" name="search" placeholder="nr. comandă, client, telefon" value="<?= woo_sync_h($filters['search']) ?>">
        </div>
        <div class="col-md-2">
          <div class="filter-actions">
            <button class="btn btn-primary" type="submit">🔎 Caută</button>
            <a class="btn btn-outline-secondary" href="vanzare_importa_comanda_woo.php">Resetează</a>
          </div>
        </div>
      </form>
      <div class="filter-help">
        Pentru comenzi mai vechi de 24h, completează intervalul de dată dorit și apasă „Caută”.
      </div>
    </div>
  </div>

  <div class="alert alert-warning py-2 px-3 mb-3">
    Dacă o comandă a fost preluată manual, folosește <strong>Marchează importată</strong>. Comanda nu se șterge, ci se mută în tabelul „Importate în intervalul afișat”.
  </div>

  <div class="card table-card">
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
      <div>
        <h2 class="section-title">Comenzi Woo de preluat</h2>
        <div class="small-muted">Neimportate, create în intervalul afișat.</div>
      </div>
      <span class="badge text-bg-primary fs-6"><?= count($pendingOrders) ?> de preluat</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-dark">
          <tr>
            <th class="text-center">Comandă</th>
            <th>Data comandă</th>
            <th>Client</th>
            <th>Telefon</th>
            <th>Status</th>
            <th>Plată</th>
            <th class="text-end">Total</th>
            <th class="text-center">Produse</th>
            <th>Acțiuni</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$pendingOrders): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">Nu există comenzi Woo neimportate în intervalul afișat.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($pendingOrders as $order):
              $oid = (int)($order['id'] ?? 0);
              $customer = (array)($order['customer'] ?? []);
              $createdAt = woo_sync_order_created_at_bucharest($order);
              $deliveryFeePreviewData = woo_sync_delivery_fee_preview($order);
              $deliveryFeePreview = $deliveryFeePreviewData['line'];
              $isPickupPreview = (bool)$deliveryFeePreviewData['is_pickup'];
              $deliveryFeeError = (string)$deliveryFeePreviewData['error'];
            ?>
            <tr class="cursor-pointer" data-woo-id="<?= $oid ?>">
              <td class="text-center fw-semibold">#<?= woo_sync_h($order['number'] ?? $oid) ?></td>
              <td><span class="nowrap"><?= $createdAt ? woo_sync_h($createdAt->format('d.m.Y H:i')) : woo_sync_h($order['date_created'] ?? '') ?></span></td>
              <td><?= woo_sync_h($customer['name'] ?? '') ?></td>
              <td><?= woo_sync_h($customer['phone'] ?? '') ?></td>
              <td><span class="pill"><?= woo_sync_h($order['status'] ?? '') ?></span></td>
              <td><?= woo_sync_payment_badge_html($order) ?></td>
              <td class="text-end fw-bold">
                <?= woo_sync_money($order['total'] ?? 0) ?> <?= woo_sync_h($order['currency'] ?? '') ?>

                <?php if ($deliveryFeePreview): ?>
                  <div class="small text-warning fw-semibold">
                    + taxă transport POS: <?= woo_sync_money($deliveryFeePreview['price']) ?> lei
                  </div>
                <?php elseif ($deliveryFeeError !== ''): ?>
                  <div class="small text-danger fw-semibold">
                    <?= woo_sync_h($deliveryFeeError) ?>
                  </div>
                <?php elseif ($isPickupPreview): ?>
                  <div class="small text-muted">
                    Ridicare restaurant - fără taxă transport
                  </div>
                <?php else: ?>
                  <div class="small text-muted">
                    Transport gratuit
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= count((array)($order['products'] ?? [])) ?></td>
              <td class="actions-cell">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="event.stopPropagation(); openWooModal(<?= $oid ?>);">🔍 Vezi &amp; Importă</button>
                  <button type="button" class="btn btn-outline-info btn-sm" onclick="event.stopPropagation(); openWooSiteDetailsModal(<?= $oid ?>);">🌐 Vezi detalii site</button>
                  <form method="post" class="manual-form js-manual-mark-form" onclick="event.stopPropagation();">
                    <input type="hidden" name="action" value="mark_woo_order_imported">
                    <input type="hidden" name="woo_order_id" value="<?= $oid ?>">
                    <button type="submit" class="btn btn-warning btn-sm">✓ Marchează importată</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ((int)$pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center flex-wrap">
        <?php
          $start = max(1, (int)$pagination['page'] - 3);
          $end = min((int)$pagination['total_pages'], (int)$pagination['page'] + 3);
        ?>
        <li class="page-item <?= (int)$pagination['page'] <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= (int)$pagination['page'] <= 1 ? '#' : 'vanzare_importa_comanda_woo.php?' . http_build_query(array_merge($pageLinkFilters, ['page' => (int)$pagination['page'] - 1])) ?>">Anterior</a>
        </li>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <li class="page-item <?= $p === (int)$pagination['page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= 'vanzare_importa_comanda_woo.php?' . http_build_query(array_merge($pageLinkFilters, ['page' => $p])) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= (int)$pagination['page'] >= (int)$pagination['total_pages'] ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= (int)$pagination['page'] >= (int)$pagination['total_pages'] ? '#' : 'vanzare_importa_comanda_woo.php?' . http_build_query(array_merge($pageLinkFilters, ['page' => (int)$pagination['page'] + 1])) ?>">Următor</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

  <div class="card table-card imported-card">
    <div class="card-header bg-success-subtle d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
      <div>
        <h2 class="section-title">Importate în intervalul afișat</h2>
        <div class="small-muted">Include comenzile importate efectiv și comenzile marcate manual ca importate.</div>
      </div>
      <span class="badge text-bg-success fs-6"><?= count($importedRows) ?> importate</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-success">
          <tr>
            <th class="text-center">Comandă</th>
            <th>Importată la</th>
            <th>Tip</th>
            <th>Plată</th>
            <th>Data comandă</th>
            <th>Client</th>
            <th>Telefon</th>
            <th class="text-end">Total</th>
            <th class="text-center">Produse</th>
            <th>Notă POS</th>
            <th>Operator</th>
            <th>Acțiuni</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$importedRows): ?>
            <tr>
              <td colspan="12" class="text-center py-4 text-muted">Nu există comenzi marcate/importate în intervalul afișat.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($importedRows as $row):
              $oid = (int)($row['woo_order_id'] ?? 0);
              $order = (array)($ordersById[$oid] ?? []);
              $customer = (array)($order['customer'] ?? []);
              $createdAt = $order ? woo_sync_order_created_at_bucharest($order) : null;
              $importedAt = woo_sync_parse_bucharest_datetime($row['imported_at'] ?? '');
              $noteNrBon = (int)($row['note_nrbon'] ?? 0);
              $orderNumber = (string)($row['woo_order_number'] ?? '');
              if ($orderNumber === '' && $order) {
                  $orderNumber = (string)($order['number'] ?? $oid);
              }
              $isManual = $noteNrBon <= 0;
            ?>
            <tr class="<?= $order ? 'cursor-pointer' : '' ?>" <?= $order ? 'data-woo-view-id="' . (int)$oid . '"' : '' ?>>
              <td class="text-center fw-semibold">#<?= woo_sync_h($orderNumber !== '' ? $orderNumber : $oid) ?></td>
              <td><span class="nowrap"><?= $importedAt ? woo_sync_h($importedAt->format('d.m.Y H:i')) : woo_sync_h($row['imported_at'] ?? '') ?></span></td>
              <td><?= $isManual ? '<span class="badge text-bg-warning text-dark">marcată manual</span>' : '<span class="badge text-bg-success">import POS</span>' ?></td>
              <td><?= $order ? woo_sync_payment_badge_html($order) : '<span class="text-muted">-</span>' ?></td>
              <td><span class="nowrap"><?= $createdAt ? woo_sync_h($createdAt->format('d.m.Y H:i')) : woo_sync_h($order['date_created'] ?? '-') ?></span></td>
              <td><?= woo_sync_h($customer['name'] ?? '-') ?></td>
              <td><?= woo_sync_h($customer['phone'] ?? '-') ?></td>
              <td class="text-end fw-semibold"><?= $order ? (woo_sync_money($order['total'] ?? 0) . ' ' . woo_sync_h($order['currency'] ?? '')) : '-' ?></td>
              <td class="text-center"><?= $order ? count((array)($order['products'] ?? [])) : '<span class="text-muted">-</span>' ?></td>
              <td><?= $isManual ? '<span class="text-muted">manual / fără notă POS</span>' : ('#' . (int)$noteNrBon) ?></td>
              <td><?= (int)($row['imported_by'] ?? 0) ?></td>
              <td class="actions-cell">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <?php if ($order): ?>
                    <button 
                      type="button" 
                      class="btn btn-outline-success btn-sm" 
                      onclick="event.stopPropagation(); openWooModal(<?= $oid ?>, true);">
                      🔍 Vezi detalii
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">Detalii indisponibile</span>
                  <?php endif; ?>

                  <button
                    type="button"
                    class="btn btn-outline-info btn-sm"
                    onclick="event.stopPropagation(); openWooSiteDetailsModal(<?= $oid ?>);">
                    🌐 Listeaza
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php foreach ($detailsByOrder as $oid => $order):
      $customer = (array)($order['customer'] ?? []);
      $products = (array)($order['products'] ?? []);
      $deliveryFeePreviewData = woo_sync_delivery_fee_preview($order);
      $deliveryFeePreview = $deliveryFeePreviewData['line'];
      $isPickupPreview = (bool)$deliveryFeePreviewData['is_pickup'];
      $deliveryFeeError = (string)$deliveryFeePreviewData['error'];
  ?>
    <div id="woo-details-<?= $oid ?>" class="woo-modal-template">
      <div class="mb-3">
        <div class="form-check">
          <label class="form-check-label d-flex align-items-center gap-2">
            <input class="form-check-input js-target-mode m-0" type="radio" name="target_mode" value="table" checked>
            <span>Importă pe masa selectată</span>
          </label>
        </div>

        <div class="form-check mt-2">
          <label class="form-check-label d-flex align-items-center gap-2 <?= !$currentNoteValid ? 'text-muted' : '' ?>">
            <input class="form-check-input js-target-mode m-0" type="radio" name="target_mode" value="current_note" <?= !$currentNoteValid ? 'disabled' : '' ?>>
            <span>Importă în nota curentă<?= $currentNoteValid ? ' (#' . (int)$currentNrBon . ')' : '' ?></span>
          </label>
        </div>
      </div>

      <div class="mb-3 js-table-select-wrap">
        <label class="form-label fw-semibold">Masă țintă</label>
        <select class="form-select" name="cod_masa_target">
          <option value="">— Selectează o masă —</option>
          <?php foreach ($tableOptions as $t): ?>
            <option value="<?= (int)$t['cod_masa'] ?>"><?= woo_sync_h($t['label'] ?? ('Masa ' . $t['cod_masa'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="alert alert-light border py-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <strong>Status plată:</strong>
          <?= woo_sync_payment_badge_html($order) ?>
        </div>
      </div>

      <?php if (!empty($order['customer_note'])): ?>
        <div class="alert alert-info py-2"><strong>Observație client:</strong> <?= nl2br(woo_sync_h($order['customer_note'])) ?></div>
      <?php endif; ?>

      <?php if ($deliveryFeePreview): ?>
        <div class="alert alert-warning py-2">
          <strong>Taxă transport:</strong>
          la import se va adăuga automat produsul cod
          <strong><?= (int)$deliveryFeePreview['cod_produs'] ?></strong>,
          în valoare de
          <strong><?= woo_sync_money($deliveryFeePreview['price']) ?> lei</strong>.
          <br>
          <span class="small">
            Valoare transport Woo cu TVA inclus: <?= woo_sync_money($deliveryFeePreview['amount'] ?? $deliveryFeePreview['price']) ?> lei.
            Valoare produse comandă: <?= woo_sync_money($deliveryFeePreview['order_products_total']) ?> lei.
          </span>
        </div>
      <?php elseif ($deliveryFeeError !== ''): ?>
        <div class="alert alert-danger py-2">
          <strong>Taxă transport nemapată:</strong> <?= woo_sync_h($deliveryFeeError) ?>
        </div>
      <?php elseif ($isPickupPreview): ?>
        <div class="alert alert-secondary py-2">
          <strong>Ridicare de la restaurant:</strong> nu se adaugă taxă de transport.
        </div>
      <?php else: ?>
        <div class="alert alert-success py-2">
          <strong>Transport gratuit:</strong> WooCommerce a trimis transport 0 lei.
        </div>
      <?php endif; ?>

      <div class="woo-scroll-controls d-flex justify-content-end gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-up">
          ↑ Scroll sus
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-down">
          ↓ Scroll jos
        </button>
      </div>

      <div class="table-responsive modal-products-scroll border rounded-bottom bg-white js-products-scroll-area">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>ID Produs WooCommerce</th>
              <th>Produs</th>
              <th class="text-center">Cant.</th>
              <th class="text-end">Preț</th>
              <th class="text-end">Total</th>
              <th>Observații</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$products): ?>
            <tr><td colspan="6" class="text-muted">Comanda nu conține produse.</td></tr>
          <?php else: foreach ($products as $p): ?>
            <tr>
              <td><?= (int)($p['product_id'] ?? 0) ?></td>
              <td><?= woo_sync_h($p['name'] ?? '') ?></td>
              <td class="text-center"><?= (float)($p['quantity'] ?? 0) ?></td>
              <td class="text-end"><?= woo_sync_money($p['price'] ?? 0) ?></td>
              <td class="text-end fw-semibold"><?= woo_sync_money(((float)($p['line_total'] ?? 0) + (float)($p['line_total_tax'] ?? 0))) ?></td>
              <td><?= woo_sync_h($p['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <?php foreach ($importedDetailsByOrder as $oid => $order):
      $customer = (array)($order['customer'] ?? []);
      $products = (array)($order['products'] ?? []);
      $deliveryFeePreviewData = woo_sync_delivery_fee_preview($order);
      $deliveryFeePreview = $deliveryFeePreviewData['line'];
      $isPickupPreview = (bool)$deliveryFeePreviewData['is_pickup'];
      $deliveryFeeError = (string)$deliveryFeePreviewData['error'];
      $importRow = null;
      foreach ($importedRows as $r) {
          if ((int)($r['woo_order_id'] ?? 0) === (int)$oid) {
              $importRow = $r;
              break;
          }
      }
      $noteNrBon = (int)($importRow['note_nrbon'] ?? 0);
      $isManual = $noteNrBon <= 0;
      $listatAutoSite = (int)($importRow['listat_auto_de_pe_site'] ?? 0);
      $importedAt = woo_sync_parse_bucharest_datetime($importRow['imported_at'] ?? '');
  ?>
    <div id="woo-details-<?= $oid ?>" class="woo-modal-template">
      <div class="alert alert-success py-2 mb-3">
        <strong>Comandă deja <?= $isManual ? 'marcată ca importată manual' : 'importată în POS' ?>.</strong>
        <?php if ($importedAt): ?>
          <span class="ms-1">Importată la <?= woo_sync_h($importedAt->format('d.m.Y H:i')) ?>.</span>
        <?php endif; ?>
        <?php if (!$isManual): ?>
          <span class="ms-1">Notă POS: <strong>#<?= (int)$noteNrBon ?></strong>.</span>
        <?php endif; ?>
        <span class="ms-1">Listare automată site: <strong><?= $listatAutoSite === 1 ? 'DA' : 'NU' ?></strong>.</span>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <div class="border rounded bg-light p-2 h-100">
            <div class="small text-muted">Client</div>
            <div class="fw-semibold"><?= woo_sync_h($customer['name'] ?? '-') ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded bg-light p-2 h-100">
            <div class="small text-muted">Telefon</div>
            <div class="fw-semibold"><?= woo_sync_h($customer['phone'] ?? '-') ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded bg-light p-2 h-100">
            <div class="small text-muted">Total comandă</div>
            <div class="fw-semibold"><?= woo_sync_money($order['total'] ?? 0) ?> <?= woo_sync_h($order['currency'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <div class="alert alert-light border py-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <strong>Status plată:</strong>
          <?= woo_sync_payment_badge_html($order) ?>
        </div>
      </div>

      <?php if (!empty($order['customer_note'])): ?>
        <div class="alert alert-info py-2"><strong>Observație client:</strong> <?= nl2br(woo_sync_h($order['customer_note'])) ?></div>
      <?php endif; ?>

      <?php if ($deliveryFeePreview): ?>
        <div class="alert alert-warning py-2">
          <strong>Taxă transport:</strong>
          pentru această comandă este identificată taxa de transport POS cu produsul cod
          <strong><?= (int)$deliveryFeePreview['cod_produs'] ?></strong>,
          în valoare de
          <strong><?= woo_sync_money($deliveryFeePreview['price']) ?> lei</strong>.
          <br>
          <span class="small">
            Valoare transport Woo cu TVA inclus: <?= woo_sync_money($deliveryFeePreview['amount'] ?? $deliveryFeePreview['price']) ?> lei.
            Valoare produse comandă: <?= woo_sync_money($deliveryFeePreview['order_products_total']) ?> lei.
          </span>
        </div>
      <?php elseif ($deliveryFeeError !== ''): ?>
        <div class="alert alert-danger py-2">
          <strong>Taxă transport nemapată:</strong> <?= woo_sync_h($deliveryFeeError) ?>
        </div>
      <?php elseif ($isPickupPreview): ?>
        <div class="alert alert-secondary py-2">
          <strong>Ridicare de la restaurant:</strong> nu se adaugă taxă de transport.
        </div>
      <?php else: ?>
        <div class="alert alert-success py-2">
          <strong>Transport gratuit:</strong> WooCommerce a trimis transport 0 lei.
        </div>
      <?php endif; ?>

      <div class="woo-scroll-controls d-flex justify-content-end gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-up">
          ↑ Scroll sus
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm js-products-scroll-down">
          ↓ Scroll jos
        </button>
      </div>

      <div class="table-responsive modal-products-scroll border rounded-bottom bg-white js-products-scroll-area">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>ID Produs WooCommerce</th>
              <th>Produs</th>
              <th class="text-center">Cant.</th>
              <th class="text-end">Preț</th>
              <th class="text-end">Total</th>
              <th>Observații</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$products): ?>
            <tr><td colspan="6" class="text-muted">Comanda nu conține produse.</td></tr>
          <?php else: foreach ($products as $p): ?>
            <tr>
              <td><?= (int)($p['product_id'] ?? 0) ?></td>
              <td><?= woo_sync_h($p['name'] ?? '') ?></td>
              <td class="text-center"><?= (float)($p['quantity'] ?? 0) ?></td>
              <td class="text-end"><?= woo_sync_money($p['price'] ?? 0) ?></td>
              <td class="text-end fw-semibold"><?= woo_sync_money(((float)($p['line_total'] ?? 0) + (float)($p['line_total_tax'] ?? 0))) ?></td>
              <td><?= woo_sync_h($p['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="wooOrderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="wooImportForm" method="post" action="<?= woo_sync_h($currentImportUrl) ?>">
        <div class="modal-header align-items-start gap-2">
          <div class="flex-grow-1">
            <h5 class="modal-title mb-1" id="wooOrderModalTitle">Detalii comandă Woo</h5>
            <div class="small text-muted" id="wooOrderModalSubtitle">Alege destinația importului, apoi apasă Importă.</div>
          </div>

          <button type="submit" class="btn btn-primary btn-sm mt-1" id="wooImportTopSubmit">
            Importă
          </button>

          <button type="button" class="btn-close mt-2" data-bs-dismiss="modal" aria-label="Închide"></button>
        </div>

        <div class="modal-body" id="wooOrderModalBody"></div>

        <div class="modal-footer">
          <input type="hidden" name="action" value="import_woo_order">
          <input type="hidden" name="woo_order_id" value="">

          <button type="submit" class="btn btn-primary" id="wooImportSubmit">Importă</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="wooSiteOrderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header align-items-start gap-2">
        <div class="flex-grow-1">
          <h5 class="modal-title mb-1" id="wooSiteOrderModalTitle">Detalii site comandă Woo</h5>
          <div class="small text-muted">Date citite live din WordPress / WooCommerce, fără modificări în POS.</div>
        </div>
        <button type="button" class="btn-close mt-2" data-bs-dismiss="modal" aria-label="Închide"></button>
      </div>
      <div class="modal-body" id="wooSiteOrderModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" id="wooSitePrintBarBtn" disabled onclick="printWooSiteOrderBar();">
          🖨 Listează BAR
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Închide</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('click', function (ev) {
    const row = ev.target.closest('tr[data-woo-id], tr[data-woo-view-id]');
    if (!row) return;
    if (ev.target.closest('button,a,input,label,select,option,form')) return;

    if (row.dataset.wooId) {
      openWooModal(row.dataset.wooId, false);
    } else if (row.dataset.wooViewId) {
      openWooModal(row.dataset.wooViewId, true);
    }
  }, { passive: true });

  document.querySelectorAll('.js-manual-mark-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      const ok = confirm('Marchezi această comandă ca importată fără să adaugi produsele în POS? Comanda nu va fi ștearsă.');
      if (!ok) {
        ev.preventDefault();
      }
    });
  });

  document.getElementById('wooImportForm').addEventListener('submit', function (ev) {
    if (this.dataset.viewOnly === '1') {
      ev.preventDefault();
    }
  });

  function syncWooTargetInputs() {
    const body = document.getElementById('wooOrderModalBody');
    const checkedMode = body.querySelector('.js-target-mode:checked');
    const tableWrap = body.querySelector('.js-table-select-wrap');
    const select = body.querySelector('select[name="cod_masa_target"]');

    const mode = checkedMode ? checkedMode.value : 'table';

    if (tableWrap) {
      tableWrap.classList.toggle('d-none', mode !== 'table');
    }

    if (select) {
      if (mode === 'table') {
        select.disabled = false;
        select.required = true;
      } else {
        select.value = '';
        select.required = false;
        select.disabled = true;
      }
    }
  }

  function wireWooProductScrollButtons() {
    const body = document.getElementById('wooOrderModalBody');
    const scrollArea = body.querySelector('.js-products-scroll-area');
    const btnUp = body.querySelector('.js-products-scroll-up');
    const btnDown = body.querySelector('.js-products-scroll-down');

    if (!scrollArea || !btnUp || !btnDown) {
      return;
    }

    function getScrollStep() {
      const firstRow = scrollArea.querySelector('tbody tr');
      const rowHeight = firstRow ? firstRow.getBoundingClientRect().height : 42;
      return Math.max(160, rowHeight * 5);
    }

    btnUp.addEventListener('click', function () {
      scrollArea.scrollBy({
        top: -getScrollStep(),
        behavior: 'smooth'
      });
    });

    btnDown.addEventListener('click', function () {
      scrollArea.scrollBy({
        top: getScrollStep(),
        behavior: 'smooth'
      });
    });
  }

  function wireWooModal() {
    const body = document.getElementById('wooOrderModalBody');

    body.querySelectorAll('.js-target-mode').forEach(function (input) {
      input.addEventListener('change', syncWooTargetInputs);
    });

    const select = body.querySelector('select[name="cod_masa_target"]');
    if (select) {
      select.addEventListener('change', syncWooTargetInputs);
    }

    wireWooProductScrollButtons();
    syncWooTargetInputs();
  }



  function wooEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

  function wooValue(value) {
    if (value === null || value === undefined || value === '') {
      return '-';
    }

    return wooEscapeHtml(value);
  }

  function wooFormatMoneyRaw(value) {
    const n = Number(value || 0);
    if (!Number.isFinite(n)) {
      return wooValue(value);
    }

    return n.toLocaleString('ro-RO', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function wooAddressText(address) {
    address = address || {};

    const parts = [
      address.address_1,
      address.address_2,
      address.city,
      address.state,
      address.postcode,
      address.country
    ].filter(function (part) {
      return part !== null && part !== undefined && String(part).trim() !== '';
    });

    return parts.length ? parts.join(', ') : '-';
  }

  function setWooSitePrintState(disabled, text) {
    const btn = document.getElementById('wooSitePrintBarBtn');

    if (!btn) {
      return;
    }

    btn.disabled = !!disabled;
    if (text) {
      btn.textContent = text;
    }
  }

  function showWooSitePrintMessage(type, message) {
    const body = document.getElementById('wooSiteOrderModalBody');

    if (!body) {
      return;
    }

    const old = body.querySelector('.js-site-print-message');
    if (old) {
      old.remove();
    }

    const div = document.createElement('div');
    div.className = 'alert alert-' + type + ' js-site-print-message';
    div.textContent = message;
    body.prepend(div);
  }

  function printWooSiteOrderBar() {
    const modalEl = document.getElementById('wooSiteOrderModal');
    const orderId = modalEl ? modalEl.dataset.orderId : '';

    if (!orderId) {
      showWooSitePrintMessage('danger', 'Nu există ID comandă pentru listare.');
      return;
    }

    if (!confirm('Listezi comanda Woo #' + orderId + ' la imprimanta BAR?')) {
      return;
    }

    const formData = new FormData();
    formData.append('ajax', 'print_wp_order_bar');
    formData.append('woo_order_id', orderId);

    setWooSitePrintState(true, 'Se trimite la BAR...');

    fetch('vanzare_importa_comanda_woo.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json'
      },
      body: formData
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json.ok) {
          throw new Error(json.error || 'Nu s-a putut trimite comanda la imprimanta BAR.');
        }

        showWooSitePrintMessage('success', json.message || 'Comanda a fost trimisă la imprimanta BAR.');
      })
      .catch(function (err) {
        showWooSitePrintMessage('danger', err.message || 'Eroare la listarea comenzii la BAR.');
      })
      .finally(function () {
        setWooSitePrintState(false, '🖨 Listează BAR');
      });
  }

  function loadWpOrderDetails(orderId) {
    const modalBody = document.getElementById('wooSiteOrderModalBody');

    if (!modalBody) {
      return;
    }

    modalBody.innerHTML = '';
    setWooSitePrintState(true, 'Se încarcă detaliile...');

    const box = document.createElement('div');
    box.id = 'wpOrderDetailsBox';
    box.className = 'wp-order-details-box';
    box.innerHTML = `
      <div class="wp-order-details-title">Detalii complete din WordPress</div>
      <div class="text-muted">Se încarcă datele direct din WooCommerce...</div>
    `;

    modalBody.appendChild(box);

    fetch('vanzare_importa_comanda_woo.php?ajax=wp_order_details&woo_order_id=' + encodeURIComponent(orderId), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json'
      }
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json.ok) {
          throw new Error(json.error || 'Nu s-au putut încărca detaliile WordPress.');
        }

        renderWpOrderDetails(json.details);
        setWooSitePrintState(false, '🖨 Listează BAR');
      })
      .catch(function (err) {
        const target = document.getElementById('wpOrderDetailsBox');

        if (target) {
          target.innerHTML = `
            <div class="wp-order-details-title text-danger">Detalii WordPress indisponibile</div>
            <div class="alert alert-danger mb-0">${wooEscapeHtml(err.message)}</div>
          `;
        }

        setWooSitePrintState(true, '🖨 Listează BAR');
      });
  }

  function renderWpOrderDetails(payload) {
    const target = document.getElementById('wpOrderDetailsBox');

    if (!target) {
      return;
    }

    const order = payload && payload.order ? payload.order : {};
    const customer = order.customer || {};
    const payment = order.payment || {};
    const totals = order.totals || {};
    const dates = order.dates || {};
    const billing = order.billing || {};
    const shipping = order.shipping || {};
    const items = Array.isArray(order.items) ? order.items : [];
    const shippingLines = Array.isArray(order.shipping_lines) ? order.shipping_lines : [];
    const notes = Array.isArray(order.notes) ? order.notes : [];
    const meta = Array.isArray(order.meta) ? order.meta : [];

    let itemsRows = '';

    if (items.length) {
      itemsRows = items.map(function (item) {
        const product = item.product || {};

        return `
          <tr>
            <td>${wooValue(item.name)}</td>
            <td class="text-center">${wooValue(item.quantity)}</td>
            <td>${wooValue(product.sku)}</td>
            <td class="text-end">${wooFormatMoneyRaw(item.subtotal)}</td>
            <td class="text-end">${wooFormatMoneyRaw(item.total)}</td>
            <td class="text-end">${wooFormatMoneyRaw(item.total_tax)}</td>
          </tr>
        `;
      }).join('');
    } else {
      itemsRows = '<tr><td colspan="6" class="text-muted">Nu există produse în răspunsul WordPress.</td></tr>';
    }

    const shippingRows = shippingLines.length
      ? shippingLines.map(function (line) {
          return `
            <tr>
              <td>${wooValue(line.name)}</td>
              <td class="text-end">${wooFormatMoneyRaw(line.total)}</td>
              <td class="text-end">${wooFormatMoneyRaw(line.total_tax)}</td>
            </tr>
          `;
        }).join('')
      : '<tr><td colspan="3" class="text-muted">Nu există linii de livrare.</td></tr>';

    const lastNotes = notes.slice(0, 5).map(function (note) {
      return `
        <div class="border rounded bg-white p-2 mb-2">
          <div class="small text-muted">${wooValue(note.date_created)} · ${wooValue(note.author)}${note.customer_note ? ' · notă client' : ''}</div>
          <div>${wooValue(note.content)}</div>
        </div>
      `;
    }).join('') || '<div class="text-muted">Nu există note WooCommerce în răspuns.</div>';

    const rawJson = wooEscapeHtml(JSON.stringify(order, null, 2));

    target.innerHTML = `
      <div class="wp-order-details-title">
        Detalii complete din WordPress / WooCommerce
      </div>

      <div class="wp-detail-grid mb-3">
        <div class="wp-detail-card">
          <div class="wp-detail-label">Comandă</div>
          <div class="wp-detail-value">#${wooValue(order.number || order.id)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Status</div>
          <div class="wp-detail-value">${wooValue(order.status)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Data creare</div>
          <div class="wp-detail-value">${wooValue(dates.created)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Total</div>
          <div class="wp-detail-value">${wooFormatMoneyRaw(totals.total)} ${wooValue(order.currency)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Client</div>
          <div class="wp-detail-value">${wooValue(customer.name)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Telefon</div>
          <div class="wp-detail-value">${wooValue(customer.phone)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Email</div>
          <div class="wp-detail-value">${wooValue(customer.email)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">IP client</div>
          <div class="wp-detail-value">${wooValue(customer.ip_address)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Plată</div>
          <div class="wp-detail-value">${wooValue(payment.method_title || payment.method)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Tranzacție</div>
          <div class="wp-detail-value">${wooValue(payment.transaction_id)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Transport</div>
          <div class="wp-detail-value">${wooFormatMoneyRaw(totals.shipping_total)} ${wooValue(order.currency)}</div>
        </div>

        <div class="wp-detail-card">
          <div class="wp-detail-label">Meta câmpuri</div>
          <div class="wp-detail-value">${meta.length}</div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="wp-address-box">
            <div class="fw-bold mb-1">Adresă facturare</div>
            <div>${wooValue(wooAddressText(billing))}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="wp-address-box">
            <div class="fw-bold mb-1">Adresă livrare</div>
            <div>${wooValue(wooAddressText(shipping))}</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-bold mb-2">Produse din WooCommerce</div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered bg-white mb-0">
            <thead class="table-light">
              <tr>
                <th>Produs</th>
                <th class="text-center">Cant.</th>
                <th>SKU</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Total</th>
                <th class="text-end">TVA</th>
              </tr>
            </thead>
            <tbody>${itemsRows}</tbody>
          </table>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-bold mb-2">Livrare WooCommerce</div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered bg-white mb-0">
            <thead class="table-light">
              <tr>
                <th>Metodă</th>
                <th class="text-end">Valoare</th>
                <th class="text-end">TVA</th>
              </tr>
            </thead>
            <tbody>${shippingRows}</tbody>
          </table>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-bold mb-2">Note WooCommerce recente</div>
        ${lastNotes}
      </div>

      <details>
        <summary class="fw-bold cursor-pointer">Vezi JSON complet din WordPress</summary>
        <pre class="wp-raw-json mt-2 mb-0">${rawJson}</pre>
      </details>
    `;
  }

  function openWooSiteDetailsModal(orderId) {
    const modalEl = document.getElementById('wooSiteOrderModal');
    const title = document.getElementById('wooSiteOrderModalTitle');
    const body = document.getElementById('wooSiteOrderModalBody');

    if (!modalEl || !body) {
      return;
    }

    modalEl.dataset.orderId = orderId;

    if (title) {
      title.textContent = 'Detalii site comandă Woo #' + orderId;
    }

    body.innerHTML = '';
    setWooSitePrintState(true, 'Se încarcă detaliile...');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    loadWpOrderDetails(orderId);
  }

  function openWooModal(orderId, viewOnly) {
    const tpl = document.getElementById('woo-details-' + orderId);
    if (!tpl) return;

    const isViewOnly = !!viewOnly;
    const form = document.getElementById('wooImportForm');
    const subtitle = document.getElementById('wooOrderModalSubtitle');
    const topSubmit = document.getElementById('wooImportTopSubmit');
    const bottomSubmit = document.getElementById('wooImportSubmit');

    document.getElementById('wooOrderModalTitle').textContent = (isViewOnly ? 'Detalii comandă Woo #' : 'Importă comandă Woo #') + orderId;
    document.getElementById('wooOrderModalBody').innerHTML = tpl.innerHTML;

    if (form) {
      form.dataset.viewOnly = isViewOnly ? '1' : '0';
    }

    if (subtitle) {
      subtitle.textContent = isViewOnly
        ? 'Comanda este deja importată. Poți consulta detaliile fără să modifici nota POS.'
        : 'Alege destinația importului, apoi apasă Importă.';
    }

    if (topSubmit) {
      topSubmit.classList.toggle('d-none', isViewOnly);
    }

    if (bottomSubmit) {
      bottomSubmit.classList.toggle('d-none', isViewOnly);
    }

    const orderInput = document.querySelector('#wooImportForm input[name="woo_order_id"]');
    if (orderInput) {
      orderInput.value = isViewOnly ? '' : orderId;
    }

    wireWooModal();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('wooOrderModal')).show();
  }

  document.getElementById('wooOrderModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('wooOrderModalTitle').textContent = 'Detalii comandă Woo';
    document.getElementById('wooOrderModalBody').innerHTML = '';

    const form = document.getElementById('wooImportForm');
    const subtitle = document.getElementById('wooOrderModalSubtitle');
    const topSubmit = document.getElementById('wooImportTopSubmit');
    const bottomSubmit = document.getElementById('wooImportSubmit');

    if (form) {
      form.dataset.viewOnly = '0';
    }

    if (subtitle) {
      subtitle.textContent = 'Alege destinația importului, apoi apasă Importă.';
    }

    if (topSubmit) {
      topSubmit.classList.remove('d-none');
    }

    if (bottomSubmit) {
      bottomSubmit.classList.remove('d-none');
    }

    const orderInput = document.querySelector('#wooImportForm input[name="woo_order_id"]');
    if (orderInput) {
      orderInput.value = '';
    }
  });


  document.getElementById('wooSiteOrderModal').addEventListener('hidden.bs.modal', function () {
    const title = document.getElementById('wooSiteOrderModalTitle');
    const body = document.getElementById('wooSiteOrderModalBody');

    if (title) {
      title.textContent = 'Detalii site comandă Woo';
    }

    const modalEl = document.getElementById('wooSiteOrderModal');
    if (modalEl) {
      delete modalEl.dataset.orderId;
    }

    if (body) {
      body.innerHTML = '';
    }

    setWooSitePrintState(true, '🖨 Listează BAR');
  });


  document.getElementById('wooSiteOrderModal').addEventListener('hidden.bs.modal', function () {
    const title = document.getElementById('wooSiteOrderModalTitle');
    const body = document.getElementById('wooSiteOrderModalBody');

    if (title) {
      title.textContent = 'Detalii site comandă Woo';
    }

    const modalEl = document.getElementById('wooSiteOrderModal');
    if (modalEl) {
      delete modalEl.dataset.orderId;
    }

    if (body) {
      body.innerHTML = '';
    }

    setWooSitePrintState(true, '🖨 Listează BAR');
  });
    function initWooHorizontalTableScroll(root) {
    root = root || document;

    root.querySelectorAll('.table-responsive').forEach(function (area) {
      if (area.dataset.hscrollReady === '1') {
        return;
      }

      const table = area.querySelector('table');

      if (!table) {
        return;
      }

      area.dataset.hscrollReady = '1';
      area.classList.add('woo-hscroll-area');

      const toolbar = document.createElement('div');
      toolbar.className = 'woo-hscroll-toolbar';
      toolbar.innerHTML = `
        <button type="button" class="woo-hscroll-btn js-hscroll-left">⇦ Stânga</button>
        <span class="woo-hscroll-hint">Derulează tabelul pentru a vedea toate coloanele și acțiunile</span>
        <button type="button" class="woo-hscroll-btn js-hscroll-right">Dreapta ⇨</button>
      `;

      area.insertBefore(toolbar, area.firstChild);

      const btnLeft = toolbar.querySelector('.js-hscroll-left');
      const btnRight = toolbar.querySelector('.js-hscroll-right');

      function getScrollStep() {
        return Math.max(280, Math.floor(area.clientWidth * 0.80));
      }

      function refreshButtons() {
        const canScroll = area.scrollWidth > area.clientWidth + 8;

        toolbar.style.display = canScroll ? 'flex' : 'none';

        if (!canScroll) {
          return;
        }

        btnLeft.disabled = area.scrollLeft <= 3;
        btnRight.disabled = area.scrollLeft + area.clientWidth >= area.scrollWidth - 3;
      }

      btnLeft.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();

        area.scrollBy({
          left: -getScrollStep(),
          behavior: 'smooth'
        });
      });

      btnRight.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();

        area.scrollBy({
          left: getScrollStep(),
          behavior: 'smooth'
        });
      });

      area.addEventListener('scroll', refreshButtons, { passive: true });
      window.addEventListener('resize', refreshButtons);

      setTimeout(refreshButtons, 80);
      setTimeout(refreshButtons, 400);
    });
  }

  initWooHorizontalTableScroll(document);

  const wooHorizontalScrollObserver = new MutationObserver(function () {
    initWooHorizontalTableScroll(document);
  });
wooHorizontalScrollObserver.observe(document.body, {
    childList: true,
    subtree: true
  });
</script>

<script>
(function () {
  if (window.__wooImportPageScannerStarted) {
    return;
  }

  window.__wooImportPageScannerStarted = true;

  var CHECK_URL = 'woo_check_comenzi_noi.php';
  var POLL_MS = 20000;
  var inFlight = false;

  function pollWooOrdersForBarListing() {
    if (inFlight) {
      return;
    }

    inFlight = true;

    fetch(CHECK_URL + '?ts=' + Date.now(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json'
      },
      cache: 'no-store'
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json || json.success !== true) {
          if (window.console && console.warn) {
            console.warn('[Woo scanner import page] răspuns invalid:', json);
          }
          return;
        }

        if (window.console && console.log) {
          console.log(
            '[Woo scanner import page] verificare OK | count=',
            json.count,
            '| ids=',
            Array.isArray(json.ids) ? json.ids.join(',') : ''
          );
        }
      })
      .catch(function (err) {
        if (window.console && console.warn) {
          console.warn('[Woo scanner import page] eroare:', err.message || err);
        }
      })
      .finally(function () {
        inFlight = false;
      });
  }

  setTimeout(pollWooOrdersForBarListing, 3000);
  setInterval(pollWooOrdersForBarListing, POLL_MS);
})();
</script>
</body>
</html>
