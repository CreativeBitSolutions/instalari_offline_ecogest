<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_fiscal_flow_helper.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
$queuePath = bestmixt_fiscal_queue_path($clientId, $locationId);
$archiveDirectory = dirname($queuePath) . DIRECTORY_SEPARATOR . 'bonuri_neprocesate';
$waitKey = $clientId . '_' . $locationId;
$maximumWait = 300;

function bestmixt_fiscal_signature(string $path): string
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

function bestmixt_fiscal_clear_wait(string $key): void
{
    unset($_SESSION['bestmixt_fiscal_wait'][$key]);
}

function bestmixt_fiscal_status(string $path, string $archive, string $key, int $maximumWait): array
{
    $signature = bestmixt_fiscal_signature($path);
    if ($signature === '') {
        bestmixt_fiscal_clear_wait($key);
        return ['pending' => false, 'archived' => false, 'remaining' => 0, 'elapsed' => 0];
    }

    $state = $_SESSION['bestmixt_fiscal_wait'][$key] ?? [];
    if (($state['signature'] ?? '') !== $signature) {
        $state = ['signature' => $signature, 'started' => time()];
        $_SESSION['bestmixt_fiscal_wait'][$key] = $state;
    }
    $elapsed = max(0, time() - (int)$state['started']);
    if ($elapsed < $maximumWait) {
        return [
            'pending' => true,
            'archived' => false,
            'remaining' => $maximumWait - $elapsed,
            'elapsed' => $elapsed,
        ];
    }

    return [
        'pending' => true, 'archived' => false, 'remaining' => 0, 'elapsed' => $elapsed,
        'error' => 'Bonul este încă în coadă. Verificați scannerul sau folosiți trimiterea directă. Nu repetați încasarea.',
    ];
}

function bestmixt_fiscal_skip_pending(string $queuePath, string $archiveDirectory, string $waitKey): array
{
    $queueHelper = bestmixt_offline_api_root() . DIRECTORY_SEPARATOR . 'printer_queue_atomic_helper.php';
    if (!is_file($queueHelper)) {
        throw new RuntimeException('Lipsește componenta care protejează coada casei de marcat.');
    }
    require_once $queueHelper;

    $claim = agecs_printer_queue_claim($queuePath);
    if (($claim['status'] ?? '') === 'empty') {
        bestmixt_fiscal_clear_wait($waitKey);
        return ['code' => 'already_processed', 'message' => 'Coada fusese deja preluată. Se continuă fără o nouă trimitere.'];
    }
    if (($claim['status'] ?? '') !== 'claimed') {
        throw new RuntimeException('Coada fiscală nu a putut fi oprită în siguranță.');
    }

    $claimedPath = (string)($claim['path'] ?? '');
    if (!is_dir($archiveDirectory)
        && !@mkdir($archiveDirectory, 0777, true)
        && !is_dir($archiveDirectory)) {
        agecs_printer_queue_restore_claim($claimedPath, $queuePath);
        throw new RuntimeException('Bonul oprit nu a putut fi arhivat. Coada normală a fost restaurată.');
    }

    $archivePath = $archiveDirectory . DIRECTORY_SEPARATOR
        . 'bon_omis_manual_' . date('Ymd_His') . '_' . substr(hash('sha256', $claimedPath), 0, 10) . '.json';
    if (!@rename($claimedPath, $archivePath)) {
        $restored = agecs_printer_queue_restore_claim($claimedPath, $queuePath);
        throw new RuntimeException($restored
            ? 'Bonul nu a putut fi arhivat. Coada normală a fost restaurată.'
            : 'Bonul rezervat necesită verificare manuală înainte de continuare.');
    }

    bestmixt_fiscal_clear_wait($waitKey);
    return [
        'code' => 'skipped',
        'message' => 'Trimiterea fiscală a fost oprită, iar bonul rămas în coadă a fost arhivat.',
        'archive' => basename($archivePath),
    ];
}

if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        bestmixt_fiscal_status($queuePath, $archiveDirectory, $waitKey, $maximumWait),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$afterUrl = trim((string)($_SESSION['bestmixt_fiscal_after_url'] ?? ''));
$generator = 'casa_marcat_vanzare.php';
$directFiscalEnabled = $clientId === 21 && $locationId > 0;
if ($directFiscalEnabled && empty($_SESSION['bestmixt_direct_fiscal_csrf'])) {
    try {
        $_SESSION['bestmixt_direct_fiscal_csrf'] = bin2hex(random_bytes(24));
    } catch (Throwable $error) {
        $_SESSION['bestmixt_direct_fiscal_csrf'] = hash('sha256', session_id() . microtime(true));
    }
}
$directFiscalToken = $directFiscalEnabled
    ? (string)($_SESSION['bestmixt_direct_fiscal_csrf'] ?? '')
    : '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['action'] ?? '') === 'continue_without_fiscal') {
    header('Content-Type: application/json; charset=utf-8');
    $remoteAddress = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
    if (!$directFiscalEnabled
        || !in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Operația este disponibilă numai în instalarea locală Bestmixt.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $receivedToken = (string)($_POST['csrf_token'] ?? '');
    if ($directFiscalToken === '' || $receivedToken === '' || !hash_equals($directFiscalToken, $receivedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cererea a expirat. Reîncărcați pagina și încercați din nou.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $result = bestmixt_fiscal_skip_pending($queuePath, $archiveDirectory, $waitKey);
        $nextUrl = $afterUrl !== '' ? $afterUrl : $generator;
        unset($_SESSION['bestmixt_fiscal_after_url']);
        echo json_encode(array_merge([
            'success' => true,
            'next_url' => $nextUrl,
        ], $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => $error->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if (!is_file($queuePath)) {
    unset($_SESSION['bestmixt_fiscal_after_url']);
    header('Location: ' . ($afterUrl !== '' ? $afterUrl : $generator));
    exit;
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Așteptare casă de marcat</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:14px;background:#172026;font-family:Arial,sans-serif;color:#17232c}
        .panel{width:min(980px,100%);max-height:calc(100vh - 28px);overflow:auto;padding:24px;background:#fff;border-top:7px solid #dca51d;border-radius:8px;box-shadow:0 18px 50px #0006}
        .spinner{width:46px;height:46px;margin:0 auto 12px;border:5px solid #dbe4e8;border-top-color:#2878b5;border-radius:50%;animation:spin 1s linear infinite}
        .spinner.done{animation:none;border-color:#23845f;position:relative}
        .spinner.done:after{content:'✓';position:absolute;inset:0;display:grid;place-items:center;color:#23845f;font-size:24px;font-weight:900}
        h1{text-align:center;margin:0 0 8px}
        .lead{text-align:center;color:#60707c}
        .saved{margin:18px 0;padding:12px;border-left:5px solid #23845f;background:#eaf7f1;font-weight:700}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .card{padding:14px;border:1px solid #d8dee3;border-radius:6px}
        .warning{background:#fff6d8;border-color:#dfbd58}
        .card h2{margin:0 0 8px;font-size:18px}
        .card p,.card ol{margin:0;line-height:1.45}
        .status{text-align:center;margin-top:17px;font-weight:700;color:#52636f}
        .status.error{color:#a52a24}
        .actions{display:none;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:14px}
        .actions.visible{display:flex}
        .direct-fiscal{min-height:44px;padding:11px 16px;border:0;border-radius:5px;background:#2378a8;color:#fff;font:700 14px Arial,sans-serif;cursor:pointer}
        .skip-fiscal{min-height:44px;padding:11px 16px;border:1px solid #a52a24;border-radius:5px;background:#fff;color:#92251f;font:700 14px Arial,sans-serif;cursor:pointer}
        .direct-fiscal:disabled{cursor:wait;opacity:.65}
        .skip-fiscal:disabled{cursor:wait;opacity:.65}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:650px){
            .panel{padding:16px}
            .grid{grid-template-columns:1fr}
            .direct-fiscal{width:100%}
        }
    </style>
</head>
<body>
<main class="panel" aria-live="polite">
    <div class="spinner" id="spinner"></div>
    <h1 id="title">Așteptați. Nu încasați nota din nou</h1>
    <p class="lead">Casa de marcat nu a preluat încă bonul. Vânzarea este deja înregistrată.</p>
    <div class="saved">Această pagină controlează numai trimiterea bonului la casa de marcat.</div>
    <section class="grid">
        <div class="card warning">
            <h2>Ce trebuie să faceți</h2>
            <ol>
                <li>Așteptați cel puțin 5 secunde.</li>
                <li>Verificați aplicația scannerului casei de marcat.</li>
                <li>Dacă scannerul nu preia bonul, folosiți trimiterea directă.</li>
                <li>Nu repetați încasarea.</li>
            </ol>
        </div>
        <div class="card">
            <h2>Metoda alternativă</h2>
            <p>Fișierul INP poate fi pus direct în folderul configurat. Dacă bonul a fost deja fiscalizat, puteți continua fără o nouă trimitere.</p>
        </div>
    </section>
    <div class="status" id="status">Verificare automată în curs...</div>
    <?php if ($directFiscalEnabled): ?>
        <div class="actions" id="directFiscalActions">
            <button class="direct-fiscal" id="directFiscal" type="button">Încearcă trimiterea fiscală directă</button>
            <button class="skip-fiscal" id="skipFiscal" type="button">Continuă fără a mai trimite la casa de marcat</button>
        </div>
    <?php endif; ?>
</main>
<script>
(function(){
    var running=false,directRunning=false,completing=false,finished=false;
    var after=<?= json_encode($afterUrl, JSON_UNESCAPED_SLASHES) ?>;
    var generator=<?= json_encode($generator, JSON_UNESCAPED_SLASHES) ?>;
    var csrfToken=<?= json_encode($directFiscalToken, JSON_UNESCAPED_SLASHES) ?>;
    var status=document.getElementById('status');
    var button=document.getElementById('directFiscal');
    var skipButton=document.getElementById('skipFiscal');
    var buttonActions=document.getElementById('directFiscalActions');
    var title=document.getElementById('title');
    var spinner=document.getElementById('spinner');

    function next(){
        if(finished)return;
        finished=true;
        location.replace(after||generator);
    }
    function revealButton(elapsed){
        if(button&&buttonActions&&Number(elapsed||0)>=5&&!completing&&!finished){
            buttonActions.classList.add('visible');
        }
    }
    function schedule(delay){
        if(!completing&&!finished)setTimeout(check,delay);
    }
    function complete(message,delay){
        if(completing||finished)return;
        completing=true;
        if(buttonActions)buttonActions.classList.remove('visible');
        title.textContent='Fișierul fiscal a fost preluat';
        spinner.classList.add('done');
        status.classList.remove('error');
        status.textContent=message;
        setTimeout(next,delay||1800);
    }
    function check(){
        if(running||directRunning||completing||finished)return;
        running=true;
        fetch('asteapta_casa_marcat.php?status=1&t='+Date.now(),{cache:'no-store',credentials:'same-origin'})
            .then(function(response){if(!response.ok)throw new Error();return response.json()})
            .then(function(state){
                if(directRunning){schedule(1000);return}
                if(!state.pending){
                    complete(state.archived
                        ?'Fișierul nepreluat a fost păstrat separat. Se continuă...'
                        :'Scannerul casei de marcat a preluat fișierul. Se continuă...',state.archived?900:500);
                    return;
                }
                var elapsed=Number(state.elapsed||0);
                revealButton(elapsed);
                status.classList.toggle('error',elapsed>=5||Boolean(state.error));
                status.textContent=state.error||(elapsed<5
                    ?'Fișierul fiscal este în curs de preluare.'
                    :'Puteți încerca trimiterea fiscală directă. Verificarea automată continuă încă '+Number(state.remaining||0)+' secunde.');
                schedule(1000);
            })
            .catch(function(){
                status.classList.add('error');
                status.textContent='Verificarea nu a răspuns. Se încearcă din nou automat.';
                schedule(2000);
            })
            .finally(function(){running=false});
    }
    function directFiscal(){
        if(!button||directRunning||completing||finished)return;
        directRunning=true;
        button.disabled=true;
        button.textContent='Se pregătește fișierul INP...';
        status.classList.remove('error');
        status.textContent='Bonul este rezervat și copiat în folderul configurat al casei de marcat.';
        fetch('trimitere_fiscala_directa_bestmixt.php',{
            method:'POST',
            cache:'no-store',
            credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
            body:'csrf_token='+encodeURIComponent(csrfToken)
        })
            .then(function(response){
                return response.json().catch(function(){throw new Error('Răspunsul metodei alternative nu este valid.')});
            })
            .then(function(result){
                if(result.success&&(result.code==='written'||result.code==='written_cleanup_warning')){
                    complete(result.message+' Se continuă...',2600);
                    return;
                }
                if(result.success&&(result.code==='already_claimed'||result.code==='empty')){
                    complete(result.message+' Se continuă...',1200);
                    return;
                }
                throw new Error(result.message||'Trimiterea fiscală directă nu a reușit.');
            })
            .catch(function(error){
                status.classList.add('error');
                status.textContent=error&&error.message?error.message:'Trimiterea directă nu a reușit. Bonul rămâne în coada normală.';
                button.disabled=false;
                button.textContent='Încearcă trimiterea fiscală directă';
            })
            .finally(function(){
                directRunning=false;
                schedule(1500);
            });
    }

    function continueWithoutFiscal(){
        if(!skipButton||directRunning||completing||finished)return;
        if(!window.confirm('Continuați numai dacă bonul a fost deja fiscalizat. Fișierul rămas nu va mai fi trimis la casa de marcat.'))return;
        directRunning=true;
        if(button)button.disabled=true;
        skipButton.disabled=true;
        skipButton.textContent='Se oprește trimiterea...';
        status.classList.remove('error');
        status.textContent='Coada fiscală este oprită în siguranță.';
        fetch('asteapta_casa_marcat.php',{
            method:'POST',
            cache:'no-store',
            credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
            body:'action=continue_without_fiscal&csrf_token='+encodeURIComponent(csrfToken)
        })
            .then(function(response){
                return response.json().catch(function(){throw new Error('Răspunsul operației nu este valid.');})
                    .then(function(result){
                        if(!response.ok||!result.success)throw new Error(result.message||'Coada fiscală nu a putut fi oprită.');
                        return result;
                    });
            })
            .then(function(result){
                finished=true;
                status.textContent=result.message||'Se continuă fără o nouă trimitere fiscală.';
                location.replace(result.next_url||after||generator);
            })
            .catch(function(error){
                directRunning=false;
                status.classList.add('error');
                status.textContent=error&&error.message?error.message:'Coada fiscală nu a putut fi oprită.';
                if(button)button.disabled=false;
                skipButton.disabled=false;
                skipButton.textContent='Continuă fără a mai trimite la casa de marcat';
                schedule(1500);
            });
    }

    if(button)button.addEventListener('click',directFiscal);
    if(skipButton)skipButton.addEventListener('click',continueWithoutFiscal);
    check();
})();
</script>
</body>
</html>
