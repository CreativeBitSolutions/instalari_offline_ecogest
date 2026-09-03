<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
date_default_timezone_set('Europe/Bucharest');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = offline_config_all();
$_SESSION['client_id'] = (int)($config['client_id'] ?? 0);
$_SESSION['cod_locatie'] = (int)($config['cod_locatie_default'] ?? 1);
$_SESSION['adminloggedin'] = $_SESSION['adminloggedin'] ?? 1;
unset($_SESSION['nr_bon']);

require_once __DIR__ . '/setari_lorand_schema.php';
vanzare_v2_ensure_lorand_settings($pdo);
vanzare_v2_ensure_lorand_offline_schema($pdo);
$lorandSettings = vanzare_v2_lorand_settings($pdo);

if (!isset($_SESSION['offline_login_csrf']) || !is_string($_SESSION['offline_login_csrf'])) {
    $_SESSION['offline_login_csrf'] = bin2hex(random_bytes(24));
}

$locationId = (int)$_SESSION['cod_locatie'];
$operatorsStmt = $pdo->prepare(
    "SELECT admin_id, admin_firstname, admin_lastname, rank, conectat
       FROM {$tabel_final_admins}
      WHERE locatie = :locatie AND lucreaza_la = 'magazin'
      ORDER BY admin_firstname, admin_lastname, admin_id"
);
$operatorsStmt->execute([':locatie' => $locationId]);
$operators = $operatorsStmt->fetchAll(PDO::FETCH_ASSOC);

function lorand_login_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ECOGEST Lorand, conectare operator</title>
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/sb-admin.css" rel="stylesheet">
    <link href="css/offline-login.css" rel="stylesheet">
    <script src="js/offline-persistent-zoom.js"></script>
</head>
<body class="bg-dark offline-login-page">
<main class="container login-shell">
    <header class="login-topbar">
        <div class="login-brand">
            <span class="login-kicker">ECOGEST MAGAZIN OFFLINE</span>
            <h1>Conectare operator</h1>
        </div>
        <nav class="login-toolbar" aria-label="Administrare locală">
            <span class="location-badge">Locația <?= $locationId ?></span>
            <a class="toolbar-button" href="offline_products_check.php" title="Sincronizare produse și observații">Produse și observații</a>
            <form method="post" action="offline_users_sync.php" class="toolbar-form">
                <input type="hidden" name="csrf_token" value="<?= lorand_login_h($_SESSION['offline_login_csrf']) ?>">
                <button type="submit" class="toolbar-button">Utilizatori și TVA</button>
            </form>
            <button type="button" class="toolbar-button toolbar-button-green" id="syncButton">Trimite operațiunile</button>
            <a class="toolbar-button" href="istoric_sincronizare.php">Istoric sincronizare</a>
            <a class="toolbar-button toolbar-button-muted" href="offline_license_check.php">Verifică licența</a>
            <a class="toolbar-button toolbar-button-danger" href="curatare_date_locale.php">Curățare date locale</a>
        </nav>
    </header>

    <section class="products-status-panel">
        <div class="products-autosync-status is-loading" id="productsAutosyncStatus" role="status" aria-live="polite">
            <span class="products-autosync-dot" aria-hidden="true"></span>
            <span class="products-autosync-copy">
                <span class="status-kicker">Nomenclator local</span>
                <strong>Ultima sincronizare produse</strong>
                <small id="productsAutosyncMessage">Se citește ultima stare locală...</small>
            </span>
        </div>
        <a class="status-link" href="offline_products_sync.php">Vezi jurnalul</a>
    </section>

    <?php if (isset($_GET['users_sync'])): ?>
        <div class="login-alert <?= $_GET['users_sync'] === 'success' ? 'is-success' : 'is-error' ?>" role="status">
            <?= lorand_login_h($_GET['message'] ?? ($_GET['users_sync'] === 'success'
                ? 'Utilizatorii și cotele TVA au fost sincronizate.'
                : 'Sincronizarea utilizatorilor și TVA nu a reușit.')) ?>
        </div>
    <?php endif; ?>
    <div id="syncStatus" class="login-alert sync-status" role="status" aria-live="polite" hidden></div>

    <?php include __DIR__ . '/offline_pending_closures_notice.php'; ?>

    <section class="operators-panel">
        <div class="section-heading">
            <div>
                <span>Acces vânzare</span>
                <h2>Alege operatorul</h2>
            </div>
        </div>
        <div class="operators-grid">
            <?php foreach ($operators as $operator): ?>
                <?php
                $operatorId = (int)($operator['admin_id'] ?? 0);
                $operatorName = trim((string)($operator['admin_firstname'] ?? '') . ' ' . (string)($operator['admin_lastname'] ?? ''));
                $isConnected = (int)($operator['conectat'] ?? 0) === 1;
                ?>
                <figure>
                    <button type="button" class="my_button" value="<?= $operatorId ?>" <?= $isConnected ? 'disabled' : '' ?> aria-label="Conectare <?= lorand_login_h($operatorName) ?>">
                        <img src="images/operator1.jpg" alt="">
                    </button>
                    <figcaption><?= lorand_login_h($operatorName !== '' ? $operatorName : ('Operator ' . $operatorId)) ?></figcaption>
                    <?php if ($isConnected): ?>
                        <p class="operator-connected">Utilizator deja conectat</p>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
            <?php if (!$operators): ?>
                <p class="operators-empty">Nu există operatori de magazin pentru locația <?= $locationId ?>.</p>
            <?php endif; ?>
        </div>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="login-alert is-error login-error"><?= lorand_login_h($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </section>
</main>

<div id="pinBackdrop" class="pin-backdrop" hidden></div>
<section id="pinDialog" class="pin-card" role="dialog" aria-modal="true" aria-labelledby="pinTitle" hidden>
    <form method="post" action="admin_logincheck.php" id="operatorLoginForm" autocomplete="off">
        <input type="hidden" name="oper" id="selectedOperator" value="">
        <header class="pin-heading">
            <div>
                <span>Operator selectat</span>
                <h2 id="pinTitle">Introdu codul PIN</h2>
            </div>
            <button type="button" class="pin-close" id="pinClose" title="Închide" aria-label="Închide">&times;</button>
        </header>
        <div class="pin-body">
            <input type="password" name="calc_result" maxlength="10" id="calc_result" class="calc_result" inputmode="numeric" autocomplete="off" aria-label="Cod PIN">
            <div class="pin-keypad">
                <?php foreach ([7, 8, 9, 4, 5, 6, 1, 2, 3] as $digit): ?>
                    <button type="button" class="calc_btn" data-digit="<?= $digit ?>"><?= $digit ?></button>
                <?php endforeach; ?>
                <button type="button" class="calc_btn pin-clear" id="pinClear">C</button>
                <button type="button" class="calc_btn" data-digit="0">0</button>
                <button type="button" class="calc_btn pin-backspace" id="pinBackspace" aria-label="Șterge ultima cifră">&#8592;</button>
            </div>
            <button type="submit" class="pin-submit" name="continua" value="Continuă">Continuă</button>
        </div>
    </form>
</section>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="offline_sync_heartbeat.js"></script>
<script src="offline_products_autosync_status.js"></script>
<script>
(function () {
    'use strict';

    var dialog = document.getElementById('pinDialog');
    var backdrop = document.getElementById('pinBackdrop');
    var pinInput = document.getElementById('calc_result');
    var selectedOperator = document.getElementById('selectedOperator');

    function openPin(operatorId) {
        selectedOperator.value = operatorId;
        pinInput.value = '';
        dialog.hidden = false;
        backdrop.hidden = false;
        window.setTimeout(function () { pinInput.focus(); }, 0);
    }

    function closePin() {
        dialog.hidden = true;
        backdrop.hidden = true;
        selectedOperator.value = '';
        pinInput.value = '';
    }

    document.querySelectorAll('.my_button:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () { openPin(button.value); });
    });
    document.querySelectorAll('[data-digit]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (pinInput.value.length < 10) {
                pinInput.value += button.getAttribute('data-digit');
            }
            pinInput.focus();
        });
    });
    document.getElementById('pinBackspace').addEventListener('click', function () {
        pinInput.value = pinInput.value.slice(0, -1);
        pinInput.focus();
    });
    document.getElementById('pinClear').addEventListener('click', function () {
        pinInput.value = '';
        pinInput.focus();
    });
    document.getElementById('pinClose').addEventListener('click', closePin);
    backdrop.addEventListener('click', closePin);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !dialog.hidden) {
            closePin();
        }
    });

    var syncButton = document.getElementById('syncButton');
    var syncStatus = document.getElementById('syncStatus');
    syncButton.addEventListener('click', function () {
        syncButton.disabled = true;
        syncStatus.hidden = false;
        syncStatus.className = 'login-alert sync-status';
        syncStatus.textContent = 'Se verifică și se trimite coada...';
        var requests = 0;
        var sent = 0;

        function finish(message, isError) {
            syncStatus.className = 'login-alert sync-status ' + (isError ? 'is-error' : 'is-success');
            syncStatus.textContent = message;
            syncButton.disabled = false;
        }

        function drainNext() {
            requests += 1;
            fetch('offline_sync_worker.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            }).then(function (response) {
                return response.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); } catch (error) { data = { message: text }; }
                    if (!response.ok) { throw data; }
                    return data;
                });
            }).then(function (data) {
                var queue = data && data.queue ? data.queue : {};
                var pending = parseInt(queue.pending || 0, 10);
                var sending = parseInt(queue.sending || 0, 10);
                var retry = parseInt(queue.retry || 0, 10);
                var blocked = parseInt(queue.blocked || 0, 10);
                if (data.status === 'sent') { sent += 1; }
                if (pending + sending > 0 && requests < 120) {
                    syncStatus.textContent = 'Confirmate acum: ' + sent + '. Rămase: ' + (pending + sending + retry) + '.';
                    window.setTimeout(drainNext, 600);
                    return;
                }
                if (blocked > 0) {
                    finish('Confirmate acum: ' + sent + '. Operațiuni blocate: ' + blocked + '.', true);
                    return;
                }
                if (retry > 0) {
                    finish('Confirmate acum: ' + sent + '. În așteptare pentru reîncercare: ' + retry + '.', false);
                    return;
                }
                finish(sent > 0 ? 'Operațiunile au fost trimise. Confirmate: ' + sent + '.' : 'Nu există operațiuni noi de trimis.', false);
            }).catch(function (error) {
                finish(error && error.message ? error.message : 'Trimiterea nu a reușit. Reîncercarea automată rămâne activă.', true);
            });
        }

        drainNext();
    });
}());
</script>
</body>
</html>
