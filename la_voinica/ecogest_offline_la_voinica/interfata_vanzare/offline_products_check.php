<?php
date_default_timezone_set('Europe/Bucharest');

function opc_json_flags(): int
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

function opc_send_json(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, opc_json_flags());
    exit;
}

function opc_products_payload(array $productsSyncGuard): array
{
    $productsSyncStatus = (string)($productsSyncGuard['status'] ?? '');
    $productsDiffStats = isset($productsSyncGuard['diff_stats']) && is_array($productsSyncGuard['diff_stats'])
        ? $productsSyncGuard['diff_stats']
        : [];
    $productsDiffProducts = isset($productsDiffStats['products']) && is_array($productsDiffStats['products'])
        ? $productsDiffStats['products']
        : [];

    return [
        'ok' => true,
        'allow' => !empty($productsSyncGuard['allow']),
        'status' => $productsSyncStatus,
        'changed' => $productsSyncStatus === 'products_changed',
        'login_blocked' => empty($productsSyncGuard['allow']) && $productsSyncStatus !== 'products_changed',
        'message' => (string)($productsSyncGuard['message'] ?? ''),
        'products_count' => (int)($productsSyncGuard['products_count'] ?? 0),
        'diff' => [
            'received' => (int)($productsDiffProducts['received'] ?? ($productsSyncGuard['products_count'] ?? 0)),
            'missing' => (int)($productsDiffProducts['missing'] ?? 0),
            'different' => (int)($productsDiffProducts['different'] ?? 0),
            'extra_local' => (int)($productsDiffProducts['extra_local'] ?? 0),
            'unchanged' => (int)($productsDiffProducts['unchanged'] ?? 0),
            'skipped' => (int)($productsDiffProducts['skipped'] ?? 0),
            'lookup_changed' => (int)($productsDiffStats['lookup_changed'] ?? 0),
        ],
    ];
}

if (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1') {
    try {
        require_once __DIR__ . '/database_connection.php';
        require_once __DIR__ . '/offline_products_guard.php';

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $productsSyncGuard = opg_check_products_sync($pdo, $restaurantConfig ?? []);
        opc_send_json(200, opc_products_payload($productsSyncGuard));
    } catch (Throwable $e) {
        opc_send_json(500, [
            'ok' => false,
            'status' => 'error',
            'message' => 'Verificarea produselor a esuat: ' . $e->getMessage(),
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verificare produse offline</title>
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #212529;
            color: #212529;
        }
        .page-wrap {
            max-width: 920px;
            margin: 0 auto;
            padding: 32px 12px;
        }
        .status-card {
            border-radius: 8px;
            border: 0;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.22);
        }
        .status-loader {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin-bottom: 18px;
            border: 6px solid #d7dde5;
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: opc-spin 0.9s linear infinite;
        }
        @keyframes opc-spin {
            to {
                transform: rotate(360deg);
            }
        }
        .stat-box {
            height: 100%;
            border: 1px solid #d7dde5;
            border-radius: 6px;
            background: #f8fafc;
            padding: 12px;
            text-align: center;
        }
        .stat-box span,
        .stat-box strong {
            display: block;
        }
        .stat-box span {
            color: #59636e;
            font-size: 0.9rem;
        }
        .stat-box strong {
            font-size: 1.4rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        .actions .btn {
            min-width: 160px;
        }
        .d-none {
            display: none !important;
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="card status-card">
        <div class="card-body text-center">
            <div id="loadingBlock">
                <div class="status-loader" aria-hidden="true"></div>
                <h3 class="text-primary mb-3">Se verifica produsele</h3>
                <p class="mb-2">Verificam lista de produse. Va rugam asteptati.</p>
                <p class="text-muted mb-4">Timp scurs: <span id="elapsedSeconds">0</span> secunde</p>
            </div>

            <div id="resultBlock" class="d-none">
                <h3 id="statusTitle" class="mb-3"></h3>
                <p id="statusMessage" class="mb-4"></p>
                <div id="statsRow" class="row g-3 mb-4 d-none"></div>
                <div id="actionsRow" class="actions"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const loadingBlock = document.getElementById('loadingBlock');
    const resultBlock = document.getElementById('resultBlock');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statsRow = document.getElementById('statsRow');
    const actionsRow = document.getElementById('actionsRow');
    const elapsedSeconds = document.getElementById('elapsedSeconds');
    let seconds = 0;

    const timer = window.setInterval(function() {
        seconds += 1;
        elapsedSeconds.textContent = String(seconds);
    }, 1000);

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setTitle(text, className) {
        statusTitle.className = className + ' mb-3';
        statusTitle.textContent = text;
    }

    function statBox(label, value) {
        return '' +
            '<div class="col-6 col-md-3">' +
                '<div class="stat-box">' +
                    '<span>' + escapeHtml(label) + '</span>' +
                    '<strong>' + escapeHtml(value) + '</strong>' +
                '</div>' +
            '</div>';
    }

    function actionButton(label, href, className) {
        return '<a class="btn ' + className + '" href="' + escapeHtml(href) + '">' + escapeHtml(label) + '</a>';
    }

    function showResult() {
        window.clearInterval(timer);
        loadingBlock.classList.add('d-none');
        resultBlock.classList.remove('d-none');
    }

    function renderStats(diff) {
        const boxes = [];
        boxes.push(statBox('Produse diferite', diff.different || 0));
        boxes.push(statBox('Lipsa offline', diff.missing || 0));
        boxes.push(statBox('Sterse online', diff.extra_local || 0));
        boxes.push(statBox('Neschimbate', diff.unchanged || 0));
        boxes.push(statBox('Online', diff.received || 0));
        if ((diff.skipped || 0) > 0) {
            boxes.push(statBox('Ignorate', diff.skipped));
        }
        if ((diff.lookup_changed || 0) > 0) {
            boxes.push(statBox('Date auxiliare diferite', diff.lookup_changed));
        }
        statsRow.innerHTML = boxes.join('');
        statsRow.classList.remove('d-none');
    }

    function renderActions(includeSync) {
        const buttons = [];
        if (includeSync) {
            buttons.push(actionButton('Sincronizare Produse', 'offline_products_sync.php?force=1&rewrite_existing=1', 'btn-success'));
        }
        buttons.push(actionButton('Reverifica', 'offline_products_check.php', 'btn-outline-secondary'));
        buttons.push(actionButton('Inapoi la login', 'agecs_login.php', 'btn-outline-dark'));
        actionsRow.innerHTML = buttons.join('');
    }

    function renderResponse(data) {
        showResult();
        statsRow.classList.add('d-none');
        statsRow.innerHTML = '';

        if (!data || data.ok === false) {
            setTitle('Verificarea a esuat', 'text-danger');
            statusMessage.textContent = data && data.message ? data.message : 'Nu s-a putut verifica lista de produse.';
            renderActions(false);
            return;
        }

        if (data.changed) {
            setTitle('Diferente produse', 'text-warning');
            const diff = data.diff || {};
            statusMessage.textContent = (diff.extra_local || 0) > 0
                ? 'Exista produse in baza offline care nu mai exista online. La sincronizare vor fi sterse local.'
                : 'Exista diferente intre produsele offline si produsele online.';
            renderStats(diff);
            renderActions(true);
            return;
        }

        if (data.login_blocked) {
            setTitle('Acces blocat', 'text-danger');
            statusMessage.textContent = data.message || 'Nomenclatorul local nu este sincronizat.';
            renderActions(true);
            return;
        }

        if (data.status === 'ok') {
            setTitle('Produsele sunt sincronizate', 'text-success');
            statusMessage.textContent = 'Produse online verificate: ' + String(data.products_count || 0);
            renderActions(false);
            return;
        }

        setTitle('Verificare produse', 'text-secondary');
        statusMessage.textContent = data.message || 'Sincronizarea produselor nu necesita interventie.';
        renderActions(false);
    }

    fetch('offline_products_check.php?ajax=1&t=' + Date.now(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json'
        }
    })
        .then(function(response) {
            return response.text().then(function(text) {
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { ok: false, message: text ? text.substring(0, 500) : 'Raspuns invalid de la server.' };
                }
                if (!response.ok) {
                    data.ok = false;
                }
                return data;
            });
        })
        .then(renderResponse)
        .catch(function(error) {
            renderResponse({
                ok: false,
                message: error && error.message ? error.message : 'Verificarea produselor a esuat.'
            });
        });
})();
</script>
</body>
</html>
