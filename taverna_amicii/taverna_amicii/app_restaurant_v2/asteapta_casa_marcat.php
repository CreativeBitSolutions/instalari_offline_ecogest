<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

date_default_timezone_set('Europe/Bucharest');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$allowedGenerators = ['casa_marcat_vanzare.php', 'dwred_restaurant_cu_listare.php'];
$generator = isset($_POST['nota_de_relistat'])
    ? 'casa_marcat_vanzare.php'
    : (string)($_SESSION['app_restaurant_v2_generator_casa_marcat'] ?? 'casa_marcat_vanzare.php');
if (!in_array($generator, $allowedGenerators, true) || !is_file(__DIR__ . DIRECTORY_SEPARATOR . $generator)) {
    $generator = 'casa_marcat_vanzare.php';
}

$forwardPost = [];
if (isset($_POST['nota_de_relistat']) && (int)$_POST['nota_de_relistat'] > 0) {
    $forwardPost['nota_de_relistat'] = (int)$_POST['nota_de_relistat'];
}
if ($forwardPost === [] && (int)($_SESSION['casa_marcat_relistare_in_asteptare'] ?? 0) > 0) {
    $forwardPost['nota_de_relistat'] = (int)$_SESSION['casa_marcat_relistare_in_asteptare'];
}
unset($_SESSION['casa_marcat_relistare_in_asteptare']);

$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
$queuePath = rtrim((string)RESTAURANT_OFFLINE_API_DIR, '/\\')
    . DIRECTORY_SEPARATOR . $clientId
    . DIRECTORY_SEPARATOR . $locationId
    . DIRECTORY_SEPARATOR . 'bon_casa_marcat.json';
$archiveDirectory = dirname($queuePath) . DIRECTORY_SEPARATOR . 'bonuri_neprocesate';
$waitKey = $clientId . '_' . $locationId;
$maximumWait = 300;
$directFiscalEnabled = in_array($clientId, [1008, 1021], true);
if ($directFiscalEnabled && empty($_SESSION['offline_direct_fiscal_token'])) {
    try {
        $_SESSION['offline_direct_fiscal_token'] = bin2hex(random_bytes(24));
    } catch (Throwable $exception) {
        $_SESSION['offline_direct_fiscal_token'] = hash('sha256', session_id() . microtime(true));
    }
}
$directFiscalToken = $directFiscalEnabled
    ? (string)($_SESSION['offline_direct_fiscal_token'] ?? '')
    : '';

function agecs_fiscal_queue_signature(string $path): string
{
    clearstatcache(true, $path);
    if (!is_file($path)) {
        return '';
    }
    $hash = @hash_file('sha256', $path);
    return is_string($hash) && $hash !== ''
        ? $hash
        : (string)@filemtime($path) . ':' . (string)@filesize($path);
}

function agecs_fiscal_clear_wait(string $waitKey): void
{
    unset($_SESSION['asteptare_casa_marcat'][$waitKey]);
    if (empty($_SESSION['asteptare_casa_marcat'])) {
        unset($_SESSION['asteptare_casa_marcat']);
    }
}

function agecs_fiscal_receipt_number(string $path): string
{
    $raw = @file_get_contents($path);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    $number = is_array($payload) ? ($payload['data'][0]['nrbon'] ?? 'necunoscut') : 'necunoscut';
    $safe = preg_replace('/[^0-9A-Za-z_-]/', '_', (string)$number);
    return $safe !== '' ? $safe : 'necunoscut';
}

function agecs_fiscal_status(
    string $queuePath,
    string $archiveDirectory,
    string $waitKey,
    int $maximumWait
): array {
    $signature = agecs_fiscal_queue_signature($queuePath);
    if ($signature === '') {
        agecs_fiscal_clear_wait($waitKey);
        return ['pending' => false, 'archived' => false, 'remaining' => 0];
    }

    $state = $_SESSION['asteptare_casa_marcat'][$waitKey] ?? [];
    if (($state['signature'] ?? '') !== $signature) {
        $state = ['signature' => $signature, 'started' => time()];
        $_SESSION['asteptare_casa_marcat'][$waitKey] = $state;
    }

    $elapsed = max(0, time() - (int)$state['started']);
    $remaining = max(0, $maximumWait - $elapsed);
    if ($remaining > 0) {
        return ['pending' => true, 'archived' => false, 'remaining' => $remaining, 'elapsed' => $elapsed];
    }

    if (!is_dir($archiveDirectory)
        && !@mkdir($archiveDirectory, 0777, true)
        && !is_dir($archiveDirectory)
    ) {
        return [
            'pending' => true,
            'archived' => false,
            'remaining' => 0,
            'error' => 'Bonul precedent nu a putut fi păstrat separat. Se încearcă din nou automat.',
        ];
    }

    $currentSignature = agecs_fiscal_queue_signature($queuePath);
    if ($currentSignature === '') {
        agecs_fiscal_clear_wait($waitKey);
        return ['pending' => false, 'archived' => false, 'remaining' => 0];
    }
    if ($currentSignature !== $signature) {
        $_SESSION['asteptare_casa_marcat'][$waitKey] = [
            'signature' => $currentSignature,
            'started' => time(),
        ];
        return ['pending' => true, 'archived' => false, 'remaining' => $maximumWait, 'elapsed' => 0];
    }

    $base = 'bon_' . agecs_fiscal_receipt_number($queuePath) . '_' . date('Ymd_His');
    $archivePath = $archiveDirectory . DIRECTORY_SEPARATOR . $base . '.json';
    $suffix = 2;
    while (is_file($archivePath)) {
        $archivePath = $archiveDirectory . DIRECTORY_SEPARATOR . $base . '_' . $suffix . '.json';
        $suffix++;
    }

    if (@rename($queuePath, $archivePath)) {
        agecs_fiscal_clear_wait($waitKey);
        return ['pending' => false, 'archived' => true, 'remaining' => 0, 'file' => basename($archivePath)];
    }

    if (!is_file($queuePath)) {
        agecs_fiscal_clear_wait($waitKey);
        return ['pending' => false, 'archived' => false, 'remaining' => 0];
    }

    return [
        'pending' => true,
        'archived' => false,
        'remaining' => 0,
        'error' => 'Bonul precedent nu a putut fi mutat. Verificați aplicația scannerului.',
    ];
}

if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(
        agecs_fiscal_status($queuePath, $archiveDirectory, $waitKey, $maximumWait),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!is_file($queuePath) && $forwardPost === []) {
    header('Location: ' . $generator);
    exit;
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Așteptare casă de marcat</title>
    <style>
        :root { --ink:#17232c; --muted:#52636f; --paper:#fffdf8; --amber:#dfa414; --green:#16745d; --red:#a52a24; }
        * { box-sizing:border-box; }
        body {
            margin:0; min-height:100vh; display:grid; place-items:center; padding:14px;
            background:#13212b; color:var(--ink); font-family:"Trebuchet MS",Tahoma,sans-serif;
        }
        .panel {
            width:min(980px,100%); max-height:calc(100vh - 28px); overflow:auto;
            border:1px solid #40515b; border-top:7px solid var(--amber); border-radius:10px;
            background:var(--paper); box-shadow:0 18px 50px rgba(0,0,0,.32);
        }
        header { text-align:center; padding:18px 24px 12px; }
        .spinner {
            width:42px; height:42px; margin:0 auto 9px; border:5px solid #e7e2d6;
            border-top-color:#2676c9; border-radius:50%; animation:spin .9s linear infinite;
        }
        h1 { margin:0 0 6px; font-size:clamp(24px,3vw,34px); }
        .lead { margin:0; color:var(--muted); font-size:17px; line-height:1.4; }
        .saved {
            margin:0 24px 14px; padding:11px 13px; border-left:5px solid var(--green);
            background:#eaf7f1; color:#145342; font-weight:900;
        }
        .grid { display:grid; grid-template-columns:1.15fr .85fr; gap:14px; margin:0 24px; }
        .card { padding:14px 16px; border:1px solid #d9d2c3; border-radius:8px; background:#fff; }
        .card.warning { border-color:#dfbd58; background:#fff2c4; }
        h2 { margin:0 0 8px; font-size:19px; }
        ol { margin:0; padding-left:22px; line-height:1.42; }
        li + li { margin-top:6px; }
        .restart { margin:0; line-height:1.46; }
        .danger {
            margin:14px 24px 0; padding:9px 12px; border:2px solid #d66a62;
            border-radius:7px; background:#fff1ef; color:#7d1f1a; font-weight:900; text-align:center;
        }
        .status { margin:0; padding:15px 24px 19px; color:var(--muted); font-weight:900; text-align:center; }
        .status.error { color:var(--red); }
        .fiscal-actions {
            display:flex; align-items:center; justify-content:center; gap:10px;
            padding:0 24px 20px;
        }
        .direct-fiscal {
            display:none; padding:11px 17px; border:2px solid var(--amber); border-radius:6px;
            background:#fff8df; color:#473300; font:inherit; font-weight:900; cursor:pointer;
        }
        .direct-fiscal.visible { display:inline-block; }
        .direct-fiscal:disabled { cursor:wait; opacity:.65; }
        @keyframes spin { to { transform:rotate(360deg); } }
        @media (max-width:720px), (max-height:650px) {
            body { padding:6px; }
            .panel { max-height:calc(100vh - 12px); }
            header { padding:10px 13px 7px; }
            .spinner { width:30px; height:30px; border-width:4px; margin-bottom:4px; }
            .lead { font-size:14px; }
            .saved { margin:0 13px 8px; padding:7px 9px; font-size:14px; }
            .grid { grid-template-columns:1fr; gap:7px; margin:0 13px; }
            .card { padding:8px 10px; font-size:13px; }
            h2 { margin-bottom:4px; font-size:16px; }
            li + li { margin-top:2px; }
            .danger { margin:7px 13px 0; padding:6px 8px; font-size:13px; }
            .status { padding:8px 13px 10px; font-size:13px; }
            .fiscal-actions { padding:0 13px 12px; }
            .direct-fiscal { width:100%; padding:9px 11px; }
        }
    </style>
</head>
<body>
<main class="panel" aria-live="polite">
    <header>
        <div class="spinner" aria-hidden="true"></div>
        <h1>Așteptați. Nu încasați nota din nou</h1>
        <p class="lead">Casa de marcat nu a preluat încă bonul precedent. Bonul curent este oprit temporar pentru a nu suprascrie fișierul existent.</p>
    </header>
    <div class="saved">Vânzarea este deja înregistrată în aplicație. Această pagină controlează numai trimiterea bonului la casa de marcat.</div>
    <section class="grid">
        <div class="card warning">
            <h2>Ce trebuie să faceți</h2>
            <ol>
                <li>Așteptați cel puțin 10 secunde.</li>
                <li>Verificați dacă aplicația scannerului casei de marcat este pornită și vizibilă.</li>
                <li>Lăsați pagina deschisă. Bonul curent pleacă automat după preluarea celui precedent.</li>
                <li>Dacă scannerul nu pornește sau este blocat, reporniți calculatorul.</li>
            </ol>
        </div>
        <div class="card">
            <h2>După repornirea calculatorului</h2>
            <p class="restart"><strong>Nu încasați nota din nou.</strong> Deschideți aplicația și folosiți <strong>Note / Retrimite note la casa de marcat</strong> numai dacă bonul nu a fost fiscalizat.</p>
        </div>
    </section>
    <div class="danger">Nu închideți aplicația și nu repetați încasarea cât timp verificați scannerul.</div>
    <div class="status" id="fiscalStatus">Verificare automată în curs...</div>
    <?php if ($directFiscalEnabled): ?>
        <div class="fiscal-actions">
            <button type="button" class="direct-fiscal" id="directFiscalButton">CLICK PENTRU A TRIMITE DIRECT LA CASA DE MARCAT</button>
        </div>
    <?php endif; ?>

    <?php if ($forwardPost !== []): ?>
        <form id="continueFiscalForm" method="post" action="<?php echo htmlspecialchars($generator, ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($forwardPost as $name => $value): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
</main>
<script>
(function () {
    var running = false;
    var generator = <?php echo json_encode($generator, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var form = document.getElementById('continueFiscalForm');
    var statusNode = document.getElementById('fiscalStatus');
    var directFiscalButton = document.getElementById('directFiscalButton');
    var directFiscalToken = <?php echo json_encode($directFiscalToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var pageStartedAt = Date.now();
    var alternativeRunning = false;
    var completed = false;

    function updateDirectFiscalButton(queueAge) {
        if (!directFiscalButton || completed || alternativeRunning) return;
        var pageAge = Math.floor((Date.now() - pageStartedAt) / 1000);
        if (Math.max(Number(queueAge || 0), pageAge) >= 5) {
            directFiscalButton.classList.add('visible');
            directFiscalButton.disabled = false;
        }
    }

    function continueFiscal() {
        if (form) {
            form.submit();
        } else {
            window.location.replace(generator);
        }
    }

    function check() {
        if (running) return;
        running = true;
        fetch('asteapta_casa_marcat.php?status=1&t=' + Date.now(), {
            cache:'no-store',
            credentials:'same-origin'
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
                    completed = true;
                    statusNode.classList.remove('error');
                    statusNode.textContent = state.archived
                        ? 'Bonul precedent a fost păstrat separat. Se pregătește bonul curent...'
                        : 'Bonul precedent a fost preluat. Se pregătește bonul curent...';
                    window.setTimeout(continueFiscal, state.archived ? 700 : 0);
                    return;
                }

                updateDirectFiscalButton(Number(state.elapsed || 0));

                if (state.error) {
                    statusNode.classList.add('error');
                    statusNode.textContent = state.error;
                } else if (Number(state.elapsed || 0) < 10) {
                    statusNode.classList.remove('error');
                    statusNode.textContent = 'Bonul precedent este încă în curs de preluare.';
                } else {
                    statusNode.classList.add('error');
                    statusNode.textContent = 'Verificați acum scannerul casei de marcat. Se continuă verificarea încă ' + Number(state.remaining || 0) + ' secunde.';
                }
                window.setTimeout(check, 1000);
            })
            .catch(function () {
                statusNode.classList.add('error');
                statusNode.textContent = 'Verificarea nu a răspuns. Se încearcă din nou automat.';
                window.setTimeout(check, 2000);
            })
            .finally(function () { running = false; });
    }

    check();

    if (directFiscalButton) {
        window.setTimeout(function () {
            updateDirectFiscalButton(5);
        }, 5000);

        directFiscalButton.addEventListener('click', function () {
            if (alternativeRunning || completed) return;
            if (!window.confirm('Fișierul INP va fi pus direct în folderul configurat al casei de marcat. Continui?')) return;

            alternativeRunning = true;
            directFiscalButton.disabled = true;
            directFiscalButton.textContent = 'Se pregătește INP fără BOM...';
            statusNode.classList.remove('error');
            statusNode.textContent = 'Bonul este rezervat și scris direct în folderul configurat...';

            var body = new URLSearchParams();
            body.set('token', directFiscalToken);
            fetch('offline_fiscal_inp_alternative.php', {
                method:'POST',
                credentials:'same-origin',
                cache:'no-store',
                headers:{ 'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8' },
                body:body.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('Endpointul fiscal nu a returnat un răspuns valid.');
                    });
                })
                .then(function (result) {
                    if (!result.success) {
                        throw new Error(result.message || 'Fișierul INP nu a putut fi trimis direct.');
                    }

                    completed = true;
                    statusNode.classList.remove('error');
                    statusNode.textContent = result.message + ' Verificați preluarea lui de către FiscalWire.';
                    directFiscalButton.textContent = 'INP pus în folder';
                    window.setTimeout(continueFiscal, 1800);
                })
                .catch(function (error) {
                    alternativeRunning = false;
                    directFiscalButton.disabled = false;
                    directFiscalButton.textContent = 'CLICK PENTRU A TRIMITE DIRECT LA CASA DE MARCAT';
                    statusNode.classList.add('error');
                    statusNode.textContent = error.message + ' Vânzarea rămâne salvată. Nu încasați nota din nou.';
                    window.setTimeout(check, 1500);
                });
        });
    }
}());
</script>
</body>
</html>
