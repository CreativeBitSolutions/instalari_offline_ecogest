<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');
require_once __DIR__ . '/offline_transaction_reset_lib.php';

$config = require __DIR__ . '/offline_config.local.php';
if (!is_array($config) || strtolower((string)($config['driver'] ?? 'sqlite')) !== 'sqlite') {
    throw new RuntimeException('Pagina de curatare este disponibila numai pentru instalarea SQLite offline.');
}
$databasePath = (string)($config['sqlite_path'] ?? '');
$pdo = new PDO('sqlite:' . $databasePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA busy_timeout = 10000');

$error = '';
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['confirmation_password'] ?? '');
    $confirmed = isset($_POST['confirm_local_reset']) && $_POST['confirm_local_reset'] === '1';
    if (!offline_transaction_reset_password_is_valid($password)) {
        usleep(700000);
        $error = 'Parola de confirmare nu este corecta.';
    } elseif (!$confirmed) {
        $error = 'Confirmarea explicita a stergerii locale este obligatorie.';
    } else {
        try {
            $success = offline_transaction_reset_execute($pdo, $config);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$preview = offline_transaction_reset_preview($pdo);
$appName = trim((string)($config['app_name'] ?? 'ECOGEST RESTAURANT OFFLINE'));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Curatare date locale</title>
    <style>
        :root { color-scheme: light; --ink:#17202a; --muted:#647184; --line:#d8dee7; --paper:#fff; --bg:#edf1f4; --danger:#b42318; --danger-dark:#821b13; --green:#177245; --amber:#9a6700; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:"Segoe UI",Tahoma,sans-serif; letter-spacing:0; }
        .shell { width:min(1040px, calc(100% - 32px)); margin:32px auto; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; }
        .topbar h1 { margin:0; font-size:26px; letter-spacing:0; }
        .app-name { margin:5px 0 0; color:var(--muted); font-size:14px; }
        .back { color:#26364a; text-decoration:none; border:1px solid #aeb8c5; background:#fff; padding:10px 14px; border-radius:5px; font-weight:600; }
        .panel { background:var(--paper); border:1px solid var(--line); border-radius:7px; box-shadow:0 12px 30px rgba(23,32,42,.08); overflow:hidden; }
        .notice { margin:0; padding:18px 22px; border-left:5px solid var(--danger); background:#fff4f2; line-height:1.55; }
        .notice strong { display:block; color:var(--danger-dark); margin-bottom:3px; }
        .success { margin:18px 22px 0; padding:15px 17px; border-left:5px solid var(--green); background:#edf9f2; line-height:1.5; }
        .error { margin:18px 22px 0; padding:15px 17px; border-left:5px solid var(--danger); background:#fff1ef; color:var(--danger-dark); }
        .summary { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:1px; background:var(--line); border-bottom:1px solid var(--line); }
        .metric { background:#fff; padding:18px 22px; min-width:0; }
        .metric span { display:block; color:var(--muted); font-size:13px; margin-bottom:5px; }
        .metric strong { font-size:24px; }
        .metric.warning strong { color:var(--amber); }
        .content { padding:22px; }
        h2 { font-size:18px; margin:0 0 14px; letter-spacing:0; }
        .groups { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .group { border:1px solid var(--line); border-radius:6px; overflow:hidden; }
        .group-head { display:flex; justify-content:space-between; gap:12px; padding:11px 13px; background:#f6f8fa; font-weight:700; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        td { padding:8px 13px; border-top:1px solid #edf0f3; text-align:left; }
        td:last-child { width:80px; text-align:right; font-variant-numeric:tabular-nums; }
        .confirm { margin-top:22px; padding-top:20px; border-top:1px solid var(--line); }
        .field { display:grid; gap:7px; max-width:430px; margin-bottom:14px; }
        label { font-weight:650; }
        input[type=password] { width:100%; border:1px solid #aeb8c5; border-radius:5px; padding:11px 12px; font-size:16px; }
        .check { display:flex; align-items:flex-start; gap:10px; font-weight:500; line-height:1.4; margin:14px 0 18px; }
        .check input { width:18px; height:18px; margin-top:1px; flex:0 0 auto; }
        .danger-button { border:0; border-radius:5px; background:var(--danger); color:#fff; padding:12px 17px; font-size:14px; font-weight:750; cursor:pointer; }
        .danger-button:hover { background:var(--danger-dark); }
        .danger-button:disabled { opacity:.55; cursor:not-allowed; }
        .preserved { color:var(--muted); margin:14px 0 0; line-height:1.5; font-size:13px; }
        code { overflow-wrap:anywhere; }
        @media (max-width:760px) { .shell{width:min(100% - 20px,1040px);margin:16px auto}.topbar{align-items:flex-start}.summary{grid-template-columns:1fr}.groups{grid-template-columns:1fr}.content{padding:16px}.notice{padding:15px 16px}.topbar h1{font-size:22px} }
    </style>
    <?php include __DIR__ . '/i18n/i18n_bootstrap.php'; ?>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div>
            <h1>Curatare date tranzactionale locale</h1>
            <p class="app-name"><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a class="back" href="agecs_login.php">Inapoi la login</a>
    </header>

    <section class="panel">
        <p class="notice">
            <strong>Operatie locala protejata</strong>
            Datele deja transmise in aplicatia online nu sunt sterse sau modificate. Inainte de curatare se creeaza automat un backup local, apoi identitatea tranzactiilor este schimbata pentru ca noile operatiuni sa nu intre in conflict cu cele vechi. Identitatea folosita de licenta ramane neschimbata.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (is_array($success)): ?>
            <div class="success" role="status">
                <strong>Curatarea locala s-a incheiat.</strong><br>
                Randuri eliminate: <?php echo (int)$success['deleted_rows']; ?>.<br>
                Backup baza: <code><?php echo htmlspecialchars((string)$success['backup']['database'], ENT_QUOTES, 'UTF-8'); ?></code><br>
                Identitate tranzactii noua: <code><?php echo htmlspecialchars((string)$success['new_transaction_uuid'], ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
        <?php endif; ?>

        <div class="summary">
            <div class="metric"><span>Randuri tranzactionale gasite</span><strong><?php echo (int)$preview['total']; ?></strong></div>
            <div class="metric warning"><span>Pachete neconfirmate in coada</span><strong><?php echo (int)$preview['queue_pending']; ?></strong></div>
            <div class="metric"><span>Grupe verificate</span><strong><?php echo count($preview['groups']); ?></strong></div>
        </div>

        <?php if ((int)$preview['queue_pending'] > 0): ?>
            <div class="error" role="alert">
                Exista <?php echo (int)$preview['queue_pending']; ?> pachete care nu au fost confirmate online. Curatarea le elimina numai din coada locala. Continutul lor ramane disponibil in backupul creat automat.
            </div>
        <?php endif; ?>

        <div class="content">
            <h2>Preview date care vor fi eliminate</h2>
            <div class="groups">
                <?php foreach ($preview['groups'] as $group): ?>
                    <section class="group">
                        <div class="group-head"><span><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int)$group['total']; ?></span></div>
                        <table><tbody>
                        <?php foreach ($group['tables'] as $table): ?>
                            <tr><td><?php echo htmlspecialchars((string)$table['table'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int)$table['count']; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </section>
                <?php endforeach; ?>
            </div>

            <form method="post" class="confirm" autocomplete="off">
                <div class="field">
                    <label for="confirmation_password">Parola de confirmare</label>
                    <input type="password" id="confirmation_password" name="confirmation_password" required autocomplete="new-password">
                </div>
                <label class="check">
                    <input type="checkbox" name="confirm_local_reset" value="1" required>
                    <span>Confirm ca doresc stergerea datelor tranzactionale numai din aceasta baza locala.</span>
                </label>
                <button class="danger-button" type="submit">STERGE DATELE LOCALE SI PREGATESTE INSTALAREA CURATA</button>
                <p class="preserved">Se pastreaza produsele, categoriile, retetele, gestiunile, datele firmei, operatorii, configurarea, cheia API si licenta locala.</p>
            </form>
        </div>
    </section>
</main>
</body>
</html>
