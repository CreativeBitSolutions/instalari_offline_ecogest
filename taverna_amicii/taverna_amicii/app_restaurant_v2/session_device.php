<?php
if (!defined('AGECS_SESSION_LIFETIME')) {
    define('AGECS_SESSION_LIFETIME', 60 * 60 * 24 * 365 * 10);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', (string)AGECS_SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string)AGECS_SESSION_LIFETIME);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => AGECS_SESSION_LIFETIME,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function restaurantDeviceUid(): string
{
    $cookieName = 'agecs_device_uid';
    $uid = isset($_COOKIE[$cookieName]) ? preg_replace('/[^a-f0-9]/', '', strtolower((string)$_COOKIE[$cookieName])) : '';

    if (strlen($uid) !== 32) {
        try {
            $uid = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $uid = md5(uniqid('', true) . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . microtime(true));
        }
        $_COOKIE[$cookieName] = $uid;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['device_uid'] = $uid;
    }

    setcookie($cookieName, $uid, [
        'expires' => time() + AGECS_SESSION_LIFETIME,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $uid;
}

function restaurantDeviceIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function restaurantDeviceUserAgent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function restaurantTableHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $driver = '';
    try {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {
        $driver = '';
    }

    $key = $driver . ':' . $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        if ($driver === 'sqlite') {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                $cache[$key] = false;
                return false;
            }
            $stmt = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")');
            $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($columns as $row) {
                if (isset($row['name']) && strcasecmp((string)$row['name'], $column) === 0) {
                    $cache[$key] = true;
                    return true;
                }
            }
            $cache[$key] = false;
        } else {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column_name");
            $stmt->execute([':column_name' => $column]);
            $cache[$key] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function restaurantTableUsesDevice(PDO $pdo, string $table): bool
{
    return restaurantTableHasColumn($pdo, $table, 'device_uid');
}

function restaurantTouchUltimaConexiune(PDO $pdo, int $locatie, int $adminId, string $timestamp): void
{
    if (restaurantTableUsesDevice($pdo, 'ultima_conexiune')) {
        $deviceUid = restaurantDeviceUid();
        $stmt = $pdo->prepare("
            UPDATE ultima_conexiune
               SET admin_id = :admin_id,
                   timestamp = :timestamp,
                   device_ip = :device_ip,
                   device_user_agent = :device_user_agent
             WHERE locatie = :locatie
               AND device_uid = :device_uid
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':timestamp' => $timestamp,
            ':device_ip' => restaurantDeviceIp(),
            ':device_user_agent' => restaurantDeviceUserAgent(),
            ':locatie' => $locatie,
            ':device_uid' => $deviceUid,
        ]);

        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO ultima_conexiune
                    (admin_id, locatie, timestamp, device_uid, device_ip, device_user_agent)
                VALUES
                    (:admin_id, :locatie, :timestamp, :device_uid, :device_ip, :device_user_agent)
            ");
            $insert->execute([
                ':admin_id' => $adminId,
                ':locatie' => $locatie,
                ':timestamp' => $timestamp,
                ':device_uid' => $deviceUid,
                ':device_ip' => restaurantDeviceIp(),
                ':device_user_agent' => restaurantDeviceUserAgent(),
            ]);
        }
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE ultima_conexiune
           SET admin_id = :admin_id,
               timestamp = :timestamp
         WHERE locatie = :locatie
    ");
    $stmt->execute([
        ':admin_id' => $adminId,
        ':timestamp' => $timestamp,
        ':locatie' => $locatie,
    ]);
}

function restaurantFetchUltimaConexiune(PDO $pdo, int $locatie): ?array
{
    if (restaurantTableUsesDevice($pdo, 'ultima_conexiune')) {
        $stmt = $pdo->prepare("
            SELECT admin_id
              FROM ultima_conexiune
             WHERE locatie = :locatie
               AND device_uid = :device_uid
             ORDER BY timestamp DESC
             LIMIT 1
        ");
        $stmt->execute([
            ':locatie' => $locatie,
            ':device_uid' => restaurantDeviceUid(),
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT admin_id
              FROM ultima_conexiune
             WHERE locatie = :locatie
             ORDER BY timestamp DESC
             LIMIT 1
        ");
        $stmt->execute([':locatie' => $locatie]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function restaurantTouchUltimBonConectat(PDO $pdo, int $locatie, int $nrBon, string $timestamp): void
{
    if (restaurantTableUsesDevice($pdo, 'ultim_bon_conectat')) {
        $deviceUid = restaurantDeviceUid();
        $stmt = $pdo->prepare("
            UPDATE ultim_bon_conectat
               SET nr_bon = :nr_bon,
                   timestamp = :timestamp,
                   device_ip = :device_ip,
                   device_user_agent = :device_user_agent
             WHERE locatie = :locatie
               AND device_uid = :device_uid
        ");
        $stmt->execute([
            ':nr_bon' => $nrBon,
            ':timestamp' => $timestamp,
            ':device_ip' => restaurantDeviceIp(),
            ':device_user_agent' => restaurantDeviceUserAgent(),
            ':locatie' => $locatie,
            ':device_uid' => $deviceUid,
        ]);

        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO ultim_bon_conectat
                    (locatie, nr_bon, timestamp, device_uid, device_ip, device_user_agent)
                VALUES
                    (:locatie, :nr_bon, :timestamp, :device_uid, :device_ip, :device_user_agent)
            ");
            $insert->execute([
                ':locatie' => $locatie,
                ':nr_bon' => $nrBon,
                ':timestamp' => $timestamp,
                ':device_uid' => $deviceUid,
                ':device_ip' => restaurantDeviceIp(),
                ':device_user_agent' => restaurantDeviceUserAgent(),
            ]);
        }
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE ultim_bon_conectat
           SET nr_bon = :nr_bon,
               timestamp = :timestamp
         WHERE locatie = :locatie
    ");
    $stmt->execute([
        ':nr_bon' => $nrBon,
        ':timestamp' => $timestamp,
        ':locatie' => $locatie,
    ]);
}

function restaurantFetchUltimBonConectat(PDO $pdo, int $locatie): ?array
{
    if (restaurantTableUsesDevice($pdo, 'ultim_bon_conectat')) {
        $stmt = $pdo->prepare("
            SELECT nr_bon
              FROM ultim_bon_conectat
             WHERE locatie = :locatie
               AND device_uid = :device_uid
             ORDER BY timestamp DESC
             LIMIT 1
        ");
        $stmt->execute([
            ':locatie' => $locatie,
            ':device_uid' => restaurantDeviceUid(),
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT nr_bon
              FROM ultim_bon_conectat
             WHERE locatie = :locatie
             ORDER BY timestamp DESC
             LIMIT 1
        ");
        $stmt->execute([':locatie' => $locatie]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
