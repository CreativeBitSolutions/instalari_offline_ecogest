<?php

$externalConfig = __DIR__ . '/offline_external_config.php';
$restaurantLocalConfig = __DIR__ . '/offline_config.local.php';
if (is_file($externalConfig)) {
    require_once $externalConfig;
} elseif (is_file($restaurantLocalConfig)) {
    $restaurantConfig = require $restaurantLocalConfig;
    if (!is_array($restaurantConfig)) {
        throw new RuntimeException('Configurarea locala a restaurantului este invalida.');
    }
} else {
    throw new RuntimeException('Configurarea externa a aplicatiei offline lipseste.');
}
require_once __DIR__ . '/offline_license_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['offline_license_csrf'])) {
    $_SESSION['offline_license_csrf'] = bin2hex(random_bytes(24));
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!hash_equals((string)$_SESSION['offline_license_csrf'], $token)) {
        $result = array('ok' => false, 'code' => 'invalid_request', 'message' => 'Cererea de verificare nu este valida.');
    } else {
        $currentStatus = offline_license_status(false);
        if (!empty($currentStatus['valid'])) {
            $result = array(
                'ok' => true,
                'code' => 'license_already_valid',
                'message' => 'Licenta locala este deja valida.',
            );
        } else {
            $result = offline_license_check_online();
        }
    }
}

$status = offline_license_status(!is_array($result));
$config = offline_license_config();

function offline_license_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificare licență ECOGEST offline</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #20282f;
            color: #17212b;
            font-family: Arial, sans-serif;
        }
        .license-panel {
            width: min(680px, 100%);
            background: #fff;
            border: 1px solid #cbd5df;
            border-radius: 6px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, .24);
        }
        .license-header {
            padding: 22px 26px;
            border-bottom: 1px solid #dbe2e8;
        }
        .license-header h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: 0;
        }
        .license-body { padding: 24px 26px 28px; }
        .status {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-left: 5px solid #c0392b;
            background: #fff2f0;
            line-height: 1.45;
        }
        .status.valid {
            border-left-color: #238653;
            background: #edf9f2;
        }
        .status.pending {
            border-left-color: #c47b08;
            background: #fff8e8;
        }
        .field-label {
            display: block;
            margin-bottom: 7px;
            color: #586575;
            font-size: 14px;
        }
        .details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .detail {
            padding: 12px 14px;
            border: 1px solid #dbe2e8;
            background: #fff;
        }
        .detail strong { display: block; margin-top: 5px; overflow-wrap: anywhere; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        button, .button {
            min-height: 42px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        button { background: #16794f; color: #fff; }
        .button { background: #eef2f5; color: #17212b; border-color: #aeb9c4; }
        .provider-note { margin: 4px 0 22px; line-height: 1.5; }
        @media (max-width: 620px) {
            .details { grid-template-columns: 1fr; }
            .license-header, .license-body { padding-left: 18px; padding-right: 18px; }
        }
    </style>
    <?php include __DIR__ . '/i18n/i18n_bootstrap.php'; ?>
</head>
<body>
<main class="license-panel">
    <header class="license-header">
        <h1>Licență ECOGEST offline</h1>
    </header>
    <div class="license-body">
        <?php if (!empty($status['valid'])): ?>
            <div class="status valid">
                Licența este validă până la <strong><?= offline_license_html(date('d.m.Y H:i', strtotime($status['expires_at']))) ?></strong>.
            </div>
        <?php elseif (is_array($result) && empty($result['ok'])): ?>
            <div class="status <?= in_array((string)($result['code'] ?? ''), array('hardware_not_allowed', 'installation_mismatch'), true) ? 'pending' : '' ?>">
                <?= offline_license_html(isset($result['message']) ? $result['message'] : 'Verificarea licenței a eșuat.') ?>
            </div>
        <?php else: ?>
            <div class="status">
                <?= offline_license_html(isset($status['message']) ? $status['message'] : 'Licența trebuie verificată online.') ?>
            </div>
        <?php endif; ?>

        <div class="details">
            <div class="detail">
                <span class="field-label">Client</span>
                <strong><?= (int)$config['client_id'] ?></strong>
            </div>
            <div class="detail">
                <span class="field-label">Instalare</span>
                <strong><?= offline_license_html($config['installation_uuid']) ?></strong>
            </div>
            <div class="detail">
                <span class="field-label">Ultima verificare</span>
                <strong><?= !empty($status['issued_at']) ? offline_license_html(date('d.m.Y H:i', strtotime($status['issued_at']))) : 'Neverificată' ?></strong>
            </div>
        </div>

        <?php if (empty($status['valid'])): ?>
            <p class="provider-note">Solicitarea transmite automat identificatorul dispozitivului. După aprobare, verificați din nou licența.</p>
        <?php endif; ?>

        <div class="actions">
            <?php if (empty($status['valid'])): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= offline_license_html($_SESSION['offline_license_csrf']) ?>">
                <button type="submit"><?= is_array($result) && in_array((string)($result['code'] ?? ''), array('hardware_not_allowed', 'installation_mismatch'), true) ? 'VERIFICĂ APROBAREA LICENȚEI' : 'TRIMITE SOLICITAREA DE LICENȚIERE' ?></button>
            </form>
            <?php else: ?>
                <a class="button" href="agecs_login.php">ÎNAPOI LA LOGIN</a>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="offline_sync_heartbeat.js"></script>
</body>
</html>
