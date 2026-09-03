<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/offline_printer_flow_helper.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$clientId = (int)($_SESSION['client_id'] ?? 0);
$locationId = (int)($_SESSION['cod_locatie'] ?? 0);
$returnPage = lorand_printer_safe_return((string)($_GET['return'] ?? 'vanzare_magazin.php'));
$context = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_GET['context'] ?? 'document'))) ?: 'document';

if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(lorand_printer_pending($clientId, $locationId), JSON_UNESCAPED_UNICODE);
    exit;
}

$messages = [
    'nota_plata' => 'Bonul este salvat, iar nota de plată a fost pusă în coada imprimantei BAR.',
    'inchidere_z' => 'Raportul Z este salvat. Cele trei documente au fost puse separat în coada imprimantei BAR.',
    'raport' => 'Raportul a fost pus în coada imprimantei BAR.',
    'document' => 'Documentul a fost pus în coada imprimantei BAR.',
];
$message = $messages[$context] ?? $messages['document'];
$initial = lorand_printer_pending($clientId, $locationId);
$printerError = trim((string)($_SESSION['offline_printer_error'] ?? ''));
unset($_SESSION['offline_printer_error']);

$directPrintEnabled = $clientId === 1019 && $locationId > 0 && $printerError === '';
if ($directPrintEnabled
    && (!isset($_SESSION['lorand_direct_print_csrf']) || !is_string($_SESSION['lorand_direct_print_csrf']))) {
    $_SESSION['lorand_direct_print_csrf'] = bin2hex(random_bytes(24));
}
$directPrintToken = $directPrintEnabled ? (string)$_SESSION['lorand_direct_print_csrf'] : '';
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Așteptare imprimantă BAR</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:14px;background:#172026;color:#17232c;font-family:Arial,sans-serif}
        .panel{width:min(900px,100%);background:#fff;border-top:7px solid #dca51d;border-radius:8px;box-shadow:0 18px 50px #0006;padding:24px}
        .top{display:flex;gap:16px;align-items:center}
        .signal{width:48px;height:48px;flex:0 0 48px;border:5px solid #dbe4e8;border-top-color:#23845f;border-radius:50%;animation:spin 1s linear infinite}
        .signal.done{animation:none;border-color:#23845f;position:relative}
        .signal.done:after{content:'✓';position:absolute;inset:0;display:grid;place-items:center;color:#23845f;font-size:25px;font-weight:900}
        .signal.failed{animation:none;border-color:#b52e28;position:relative}
        .signal.failed:after{content:'!';position:absolute;inset:0;display:grid;place-items:center;color:#b52e28;font-size:25px;font-weight:900}
        h1{margin:0 0 5px;font-size:28px}
        .lead{margin:0;color:#60707c}
        .saved{margin:18px 0;padding:12px 14px;border-left:5px solid #23845f;background:#eaf7f1;font-weight:700}
        .saved.error{border-left-color:#b52e28;background:#fff0ef;color:#8d201b}
        .help{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .card{padding:14px;border:1px solid #d8dee3;border-radius:6px}
        .card.warning{background:#fff6d8;border-color:#dfbd58}
        .card h2{margin:0 0 8px;font-size:18px}
        .card p,.card ol{margin:0;line-height:1.45}
        .footer{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:16px}
        .footer-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}
        .status{font-weight:700;color:#52636f}
        .status.alert{color:#a52a24}
        .continue,.direct-print{min-height:42px;padding:10px 14px;border:0;border-radius:5px;color:#fff;font:700 14px Arial,sans-serif;text-decoration:none;cursor:pointer}
        .continue{background:#17232c}
        .direct-print{background:#2378a8}
        .direct-print:disabled{cursor:wait;opacity:.65}
        .direct-print[hidden]{display:none}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:650px){
            .panel{padding:16px}
            .help{grid-template-columns:1fr}
            .footer{align-items:flex-start;flex-direction:column}
            .footer-actions{width:100%;justify-content:flex-start}
            .continue,.direct-print{width:100%;text-align:center}
        }
    </style>
</head>
<body>
<main class="panel" aria-live="polite">
    <div class="top">
        <div class="signal<?= $printerError !== '' ? ' failed' : ($initial['pending'] ? '' : ' done') ?>" id="signal"></div>
        <div>
            <h1 id="title"><?= $printerError !== '' ? 'Listarea nu a intrat în coadă' : ($initial['pending'] ? 'Așteptați preluarea listării' : 'Listarea a fost preluată') ?></h1>
            <p class="lead">Operațiunea este deja salvată. Pagina urmărește numai coada imprimantei.</p>
        </div>
    </div>
    <div class="saved<?= $printerError !== '' ? ' error' : '' ?>"><?= htmlspecialchars($printerError !== '' ? $printerError : $message, ENT_QUOTES, 'UTF-8') ?></div>
    <section class="help">
        <div class="card warning">
            <h2>Dacă hârtia nu apare</h2>
            <ol>
                <li>Așteptați cel puțin 5 secunde.</li>
                <li>Verificați aplicația imprimantei, hârtia și imprimanta BAR.</li>
                <li>Lăsați pagina deschisă. Coada continuă automat.</li>
            </ol>
        </div>
        <div class="card">
            <h2>Nu repetați operațiunea</h2>
            <p>Vânzarea sau închiderea este deja salvată. După repornirea calculatorului, scannerul va prelua fișierele rămase.</p>
        </div>
    </section>
    <footer class="footer">
        <div class="status<?= $printerError !== '' ? ' alert' : '' ?>" id="status"><?= $printerError !== '' ? 'Verificați aplicația imprimantei și folosiți relistarea după remediere.' : 'Verificare automată în curs...' ?></div>
        <div class="footer-actions">
            <?php if ($directPrintEnabled): ?>
                <button class="direct-print" id="directPrint" type="button" hidden>Încearcă listarea directă</button>
            <?php endif; ?>
            <a class="continue" href="<?= htmlspecialchars($returnPage, ENT_QUOTES, 'UTF-8') ?>">Continuă fără să aștepți</a>
        </div>
    </footer>
</main>
<script>
(function(){
    var failed=<?= $printerError !== '' ? 'true' : 'false' ?>;
    var done=false,running=false,directRunning=false;
    var returnPage=<?= json_encode($returnPage, JSON_UNESCAPED_SLASHES) ?>;
    var csrfToken=<?= json_encode($directPrintToken, JSON_UNESCAPED_SLASHES) ?>;
    var status=document.getElementById('status');
    var title=document.getElementById('title');
    var signal=document.getElementById('signal');
    var directButton=document.getElementById('directPrint');

    function revealDirectButton(age){
        if(directButton&&Number(age||0)>=5&&!done){directButton.hidden=false}
    }
    function finish(message,delay){
        if(done)return;
        done=true;
        if(directButton)directButton.hidden=true;
        title.textContent='Listarea a fost preluată';
        signal.classList.remove('failed');
        signal.classList.add('done');
        status.classList.remove('alert');
        status.textContent=message||'Scannerul a preluat toate documentele. Revenim în aplicație...';
        setTimeout(function(){location.replace(returnPage)},delay||900);
    }
    function scheduleCheck(delay){
        if(!done&&!failed)setTimeout(check,delay);
    }
    function check(){
        if(done||running||directRunning||failed)return;
        running=true;
        fetch('asteapta_imprimanta.php?status=1&t='+Date.now(),{cache:'no-store',credentials:'same-origin'})
            .then(function(response){if(!response.ok)throw new Error();return response.json()})
            .then(function(queue){
                if(directRunning){scheduleCheck(1000);return}
                if(!queue.pending){finish();return}
                var documents=Number(queue.documents||0),age=Number(queue.age||0);
                revealDirectButton(age);
                status.classList.toggle('alert',age>=5);
                status.textContent=age>=5
                    ?'Au trecut '+age+' secunde. Puteți încerca listarea directă.'
                    :(documents===1?'Un document așteaptă preluarea.':documents+' documente distincte așteaptă preluarea.');
                scheduleCheck(1000);
            })
            .catch(function(){
                status.classList.add('alert');
                status.textContent='Verificarea nu a răspuns. Fișierele rămân în coadă.';
                scheduleCheck(2000);
            })
            .finally(function(){running=false});
    }
    function directPrint(){
        if(!directButton||done||directRunning)return;
        directRunning=true;
        directButton.disabled=true;
        directButton.textContent='Se trimite la imprimantă...';
        status.classList.remove('alert');
        status.textContent='Lotul este preluat în siguranță și trimis direct către imprimanta BAR.';
        fetch('imprimare_directa_lorand.php',{
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
                if(result.code==='printed'){
                    finish(result.message+' Revenim în aplicație...',1800);
                    return;
                }
                if(result.code==='already_claimed'){
                    finish(result.message+' Revenim în aplicație...',1200);
                    return;
                }
                throw new Error(result.message||'Listarea directă nu a reușit.');
            })
            .catch(function(error){
                status.classList.add('alert');
                status.textContent=error&&error.message?error.message:'Listarea directă nu a reușit. Lotul rămâne în coada normală.';
                directButton.disabled=false;
                directButton.textContent='Încearcă listarea directă';
            })
            .finally(function(){
                directRunning=false;
                scheduleCheck(1500);
            });
    }
    if(directButton)directButton.addEventListener('click',directPrint);
    if(!failed){
        <?= $initial['pending'] ? 'revealDirectButton(' . (int)$initial['age'] . ');check();' : 'finish();' ?>
    }
})();
</script>
</body>
</html>
