<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

date_default_timezone_set('Europe/Bucharest');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
$returnPage = agecs_offline_printer_safe_return((string)($_GET['return'] ?? 'vanzare_restaurant.php'));
$contextKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_GET['context'] ?? 'document')));
$queuePath = agecs_offline_printer_queue_path($clientId, $locationId);

$contextLabels = [
    'comanda' => 'Comanda a fost înregistrată și pusă în coada imprimantei.',
    'nota_plata' => 'Nota a fost înregistrată și pusă în coada imprimantei.',
    'inchidere_z' => 'Închiderea și raportul Z sunt salvate. Documentele au fost puse în coada imprimantei.',
    'raport' => 'Raportul a fost generat și pus în coada imprimantei.',
    'impartire' => 'Împărțirea notei este salvată. Documentele au fost puse în coada imprimantei.',
    'corectie' => 'Modificarea este salvată. Documentul de corecție a fost pus în coada imprimantei.',
    'document' => 'Operațiunea este salvată. Documentul a fost pus în coada imprimantei.',
];
$contextMessage = $contextLabels[$contextKey] ?? $contextLabels['document'];
$printerError = trim((string)($_SESSION['offline_printer_error'] ?? ''));
unset($_SESSION['offline_printer_error']);
$directPrintEnabled = in_array($clientId, [1008, 1021], true);
if ($directPrintEnabled && empty($_SESSION['offline_direct_print_token'])) {
    try {
        $_SESSION['offline_direct_print_token'] = bin2hex(random_bytes(24));
    } catch (Throwable $exception) {
        $_SESSION['offline_direct_print_token'] = hash('sha256', session_id() . microtime(true));
    }
}
$directPrintToken = $directPrintEnabled
    ? (string)($_SESSION['offline_direct_print_token'] ?? '')
    : '';

function agecs_offline_printer_pending(string $queuePath): array
{
    clearstatcache(true, $queuePath);
    if (!is_file($queuePath)) {
        return ['pending' => false, 'age' => 0, 'documents' => 0];
    }

    $documents = 0;
    $raw = @file_get_contents($queuePath);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
        $documents = count($payload['data']);
    }

    $modified = @filemtime($queuePath);
    return [
        'pending' => true,
        'age' => is_int($modified) ? max(0, time() - $modified) : 0,
        'documents' => $documents,
    ];
}

if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(agecs_offline_printer_pending($queuePath), JSON_UNESCAPED_UNICODE);
    exit;
}

$initialState = agecs_offline_printer_pending($queuePath);
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Așteptare imprimantă</title>
    <style>
        :root {
            --ink: #17232c;
            --muted: #52636f;
            --paper: #fffdf7;
            --line: #d9d2c3;
            --amber: #e6a91f;
            --amber-soft: #fff2c4;
            --green: #16745d;
            --red: #a32c25;
            --navy: #13212b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 14px;
            background:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px),
                var(--navy);
            background-size: 34px 34px;
            color: var(--ink);
            font-family: "Trebuchet MS", Tahoma, sans-serif;
        }
        .panel {
            width: min(980px, 100%);
            max-height: calc(100vh - 28px);
            overflow: auto;
            border: 1px solid #344752;
            border-top: 7px solid var(--amber);
            border-radius: 10px;
            background: var(--paper);
            box-shadow: 0 18px 50px rgba(0,0,0,.32);
        }
        .top {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: center;
            padding: 20px 24px 14px;
        }
        .signal {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border: 3px solid #eedca8;
            border-top-color: var(--green);
            border-radius: 50%;
            color: var(--green);
            font-size: 26px;
            font-weight: 900;
            animation: spin 1s linear infinite;
        }
        .signal.done {
            border-color: var(--green);
            animation: none;
        }
        h1 { margin: 0 0 5px; font-size: clamp(24px, 3vw, 34px); line-height: 1.08; }
        .lead { margin: 0; color: var(--muted); font-size: 17px; line-height: 1.4; }
        .saved {
            margin: 0 24px 16px;
            padding: 12px 14px;
            border-left: 5px solid var(--green);
            background: #eaf7f1;
            color: #145342;
            font-weight: 800;
            line-height: 1.35;
        }
        .steps {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 14px;
            margin: 0 24px;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 15px 17px;
        }
        .card.warning { background: var(--amber-soft); border-color: #dfbd58; }
        .card h2 { margin: 0 0 9px; font-size: 19px; }
        ol { margin: 0; padding-left: 22px; line-height: 1.42; }
        li + li { margin-top: 7px; }
        .restart { margin: 0; color: #4a3a09; line-height: 1.46; }
        .restart strong { display: block; margin-bottom: 6px; }
        .avoid {
            margin: 14px 24px 0;
            padding: 10px 13px;
            border: 2px solid #d66a62;
            border-radius: 7px;
            background: #fff1ef;
            color: #7d1f1a;
            font-weight: 900;
            text-align: center;
        }
        .footer {
            display: flex;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px 20px;
        }
        .status { color: var(--muted); font-weight: 800; line-height: 1.35; }
        .status.alert { color: var(--red); }
        .continue {
            flex: 0 0 auto;
            padding: 11px 17px;
            border: 0;
            border-radius: 6px;
            background: var(--ink);
            color: #fff;
            font: inherit;
            font-weight: 900;
            text-decoration: none;
        }
        .actions { display: flex; flex: 0 0 auto; gap: 9px; align-items: center; }
        .direct-print {
            display: none;
            padding: 10px 15px;
            border: 2px solid var(--amber);
            border-radius: 6px;
            background: #fff8df;
            color: #473300;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }
        .direct-print.visible { display: inline-block; }
        .direct-print:disabled { cursor: wait; opacity: .65; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 720px), (max-height: 650px) {
            body { padding: 6px; }
            .panel { max-height: calc(100vh - 12px); }
            .top { padding: 12px 14px 8px; gap: 10px; }
            .signal { width: 38px; height: 38px; font-size: 19px; }
            .lead { font-size: 14px; }
            .saved { margin: 0 14px 9px; padding: 8px 10px; font-size: 14px; }
            .steps { grid-template-columns: 1fr; margin: 0 14px; gap: 8px; }
            .card { padding: 9px 11px; font-size: 13px; }
            .card h2 { margin-bottom: 5px; font-size: 16px; }
            li + li { margin-top: 3px; }
            .avoid { margin: 8px 14px 0; padding: 7px 9px; font-size: 13px; }
            .footer { padding: 9px 14px 12px; font-size: 13px; }
            .footer { flex-wrap: wrap; }
            .continue { padding: 9px 11px; }
            .actions { width: 100%; justify-content: flex-end; flex-wrap: wrap; }
            .direct-print { padding: 8px 10px; }
        }
    </style>
</head>
<body>
<main class="panel" aria-live="polite">
    <header class="top">
        <div class="signal<?php echo $initialState['pending'] ? '' : ' done'; ?>" id="printerSignal"><?php echo $printerError !== '' ? '!' : ($initialState['pending'] ? 'P' : '✓'); ?></div>
        <div>
            <h1 id="printerTitle"><?php echo $printerError !== '' ? 'Listarea necesită verificare' : ($initialState['pending'] ? 'Așteptați preluarea listării' : 'Listarea a fost preluată'); ?></h1>
            <p class="lead">Pagina urmărește numai fișierul imprimantei. Nu mai salvează și nu modifică operațiunea efectuată.</p>
        </div>
    </header>

    <div class="saved"><?php echo htmlspecialchars($contextMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($printerError !== ''): ?>
        <div class="avoid"><?php echo htmlspecialchars($printerError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="steps">
        <div class="card warning">
            <h2>Dacă hârtia nu apare</h2>
            <ol>
                <li>Așteptați cel puțin 10 secunde. Scannerul poate procesa mai multe documente din același lot.</li>
                <li>Verificați dacă aplicația scannerului de imprimantă este pornită și vizibilă pe ecran.</li>
                <li>Verificați hârtia, alimentarea imprimantei și imprimanta selectată pentru BAR sau BUCĂTĂRIE.</li>
                <li>Dacă aplicația nu pornește sau rămâne blocată, reporniți calculatorul.</li>
            </ol>
        </div>
        <div class="card">
            <h2>După repornirea calculatorului</h2>
            <p class="restart"><strong>Nu repetați vânzarea sau închiderea.</strong> Datele sunt deja salvate. Porniți aplicația scannerului de imprimantă. Fișierul rămas în coadă va fi preluat automat. Folosiți relistarea numai dacă documentul nu a fost tipărit.</p>
        </div>
    </section>

    <div class="avoid">Nu încasați nota din nou și nu repetați închiderea de tură sau raportul Z.</div>

    <footer class="footer">
        <div class="status" id="printerStatus">Verificare automată în curs...</div>
        <div class="actions">
            <?php if ($directPrintEnabled): ?>
                <button type="button" class="direct-print" id="directPrintButton">Încearcă listarea directă</button>
            <?php endif; ?>
            <a class="continue" href="<?php echo htmlspecialchars($returnPage, ENT_QUOTES, 'UTF-8'); ?>">Continuă fără să aștepți</a>
        </div>
    </footer>
</main>
<script>
(function () {
    var returnPage = <?php echo json_encode($returnPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var initialPending = <?php echo $initialState['pending'] ? 'true' : 'false'; ?>;
    var hasPrinterError = <?php echo $printerError !== '' ? 'true' : 'false'; ?>;
    var statusNode = document.getElementById('printerStatus');
    var titleNode = document.getElementById('printerTitle');
    var signalNode = document.getElementById('printerSignal');
    var directPrintButton = document.getElementById('directPrintButton');
    var directPrintToken = <?php echo json_encode($directPrintToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var pageStartedAt = Date.now();
    var running = false;
    var completed = false;
    var alternativeRunning = false;

    function updateDirectPrintButton(queueAge) {
        if (!directPrintButton || completed || alternativeRunning) return;
        var pageAge = Math.floor((Date.now() - pageStartedAt) / 1000);
        if (Math.max(Number(queueAge || 0), pageAge) >= 5) {
            directPrintButton.classList.add('visible');
            directPrintButton.disabled = false;
        }
    }

    function finish() {
        if (completed) return;
        completed = true;
        titleNode.textContent = 'Listarea a fost preluată';
        signalNode.textContent = '✓';
        signalNode.classList.add('done');
        statusNode.classList.remove('alert');
        statusNode.textContent = 'Scannerul a preluat lotul. Revenim în aplicație...';
        window.setTimeout(function () { window.location.replace(returnPage); }, 900);
    }

    function check() {
        if (running || completed) return;
        running = true;
        fetch('asteapta_imprimanta.php?status=1&t=' + Date.now(), {
            cache: 'no-store',
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Răspuns invalid');
                return response.json();
            })
            .then(function (state) {
                if (!state.pending) {
                    if (alternativeRunning) {
                        window.setTimeout(check, 700);
                        return;
                    }
                    finish();
                    return;
                }
                var count = Number(state.documents || 0);
                var age = Number(state.age || 0);
                updateDirectPrintButton(age);
                if (age < 10) {
                    statusNode.classList.remove('alert');
                    statusNode.textContent = count > 1
                        ? 'Scannerul trebuie să preia un lot cu ' + count + ' documente.'
                        : 'Documentul așteaptă să fie preluat de scanner.';
                } else {
                    statusNode.classList.add('alert');
                    statusNode.textContent = 'Au trecut ' + age + ' secunde. Verificați acum aplicația scannerului de imprimantă.';
                }
                window.setTimeout(check, 1000);
            })
            .catch(function () {
                statusNode.classList.add('alert');
                statusNode.textContent = 'Verificarea nu a răspuns. Datele rămân salvate. Verificați scannerul și încercați din nou.';
                window.setTimeout(check, 2000);
            })
            .finally(function () { running = false; });
    }

    if (hasPrinterError && !initialPending) {
        signalNode.classList.add('done');
        statusNode.classList.add('alert');
        statusNode.textContent = 'Datele operației sunt salvate. Verificați scannerul și folosiți relistarea documentului.';
    } else if (!initialPending) {
        finish();
    } else {
        check();
    }

    if (directPrintButton) {
        window.setTimeout(function () {
            if (initialPending) updateDirectPrintButton(5);
        }, 5000);

        directPrintButton.addEventListener('click', function () {
            if (alternativeRunning || completed) return;
            if (!window.confirm('Se va încerca listarea directă, fără bold și fără taguri. Continui?')) return;

            alternativeRunning = true;
            directPrintButton.disabled = true;
            directPrintButton.textContent = 'Se trimite direct...';
            statusNode.classList.remove('alert');
            statusNode.textContent = 'Lotul este rezervat și trimis direct către imprimanta configurată...';

            var body = new URLSearchParams();
            body.set('token', directPrintToken);
            fetch('offline_print_alternative.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('Endpointul nu a returnat un răspuns valid.');
                    });
                })
                .then(function (result) {
                    if (!result.success) {
                        throw new Error(result.message || 'Listarea directă nu a reușit.');
                    }

                    completed = true;
                    titleNode.textContent = result.code === 'empty'
                        ? 'Lotul a fost preluat de scanner'
                        : 'Listarea directă a fost acceptată';
                    signalNode.textContent = '✓';
                    signalNode.classList.add('done');
                    statusNode.classList.remove('alert');
                    statusNode.textContent = result.message;
                    directPrintButton.textContent = 'Trimis către imprimantă';
                    window.setTimeout(function () { window.location.replace(returnPage); }, 1400);
                })
                .catch(function (error) {
                    alternativeRunning = false;
                    directPrintButton.disabled = false;
                    directPrintButton.textContent = 'Încearcă listarea directă';
                    statusNode.classList.add('alert');
                    statusNode.textContent = error.message + ' Operația comercială rămâne salvată. Nu repetați operația.';
                    window.setTimeout(check, 1500);
                });
        });
    }
}());
</script>
</body>
</html>
