<?php
// Lorand OFFLINE: comenzi din AGECS Storefront -> bonul curent.
// Verificarea serverului are loc doar cat timp aceasta pagina este accesata.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/eroare_comenzi_site.log');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Bucharest');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/site_orders_offline_lib.php';
require_once __DIR__ . '/det_note_departament_listare_schema.php';

if ((int)(isset($_SESSION['client_id']) ? $_SESSION['client_id'] : 0) !== 1019) {
    http_response_code(403);
    exit('Pagina este disponibila doar pentru Lorand.');
}
if (!isset($pdo) || !($pdo instanceof PDO) || !isset($_SESSION['cod_locatie'], $_SESSION['admin_id'])) {
    header('Location: agecs_login.php');
    exit;
}

$tabelNote = isset($tabel_final_note) ? $tabel_final_note : 'note';
$tabelDet = isset($tabel_final_det_note) ? $tabel_final_det_note : 'det_note';
$tabelProduse = isset($tabel_final_nomenclator) ? $tabel_final_nomenclator : 'produse_servicii';
$operatorId = (int)$_SESSION['admin_id'];
$codLocatie = max(1, (int)$_SESSION['cod_locatie']);
$codMasa = $codLocatie;

if (empty($_SESSION['csrf_site_orders_offline'])) {
    $_SESSION['csrf_site_orders_offline'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_site_orders_offline'];
$flash = isset($_SESSION['site_order_offline_flash']) ? $_SESSION['site_order_offline_flash'] : null;
unset($_SESSION['site_order_offline_flash']);

function vso_off_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function vso_off_money($value)
{
    return number_format((float)$value, 2, ',', ' ');
}

function vso_off_badge($status)
{
    $status = strtolower(trim((string)$status));
    $classes = array(
        'noua' => 'warning', 'acceptata' => 'info', 'in_pregatire' => 'primary',
        'gata' => 'success', 'finalizata' => 'secondary', 'anulata' => 'danger',
        'nepreluata' => 'warning', 'sincronizata' => 'warning', 'rezervata' => 'info',
        'preluata_local' => 'primary', 'preluata' => 'success'
    );
    $class = isset($classes[$status]) ? $classes[$status] : 'secondary';
    return '<span class="badge badge-' . $class . '">' . vso_off_h($status !== '' ? str_replace('_', ' ', $status) : '-') . '</span>';
}

function vso_off_current_note(PDO $pdo, $table, $operator, $location)
{
    $stmt = $pdo->prepare("SELECT nrbon FROM {$table} WHERE status='S' AND operator=? AND locatie=? ORDER BY nrbon DESC LIMIT 1");
    $stmt->execute(array((int)$operator, (int)$location));
    $value = $stmt->fetchColumn();
    return $value === false ? 0 : (int)$value;
}

function vso_off_get_or_create_note(PDO $pdo, $table, $operator, $location, $tableNo)
{
    $nrBon = vso_off_current_note($pdo, $table, $operator, $location);
    if ($nrBon > 0) {
        $_SESSION['nr_bon'] = $nrBon;
        return $nrBon;
    }
    $identity = offline_raport_z_current_identification($pdo, (int)$location);
    $stmt = $pdo->prepare("INSERT INTO {$table}
        (operator, locatie, cod_masa, status, cod_inchidere, nr_raport_z, cod_locatie,
         serie_casa_marcat, nui, serie_memorie_fiscala, valoare_vanzare_cu_tva, tva_colectata,
         discount, numerar, card, tichete, rest, protocol, glovo)
        VALUES(?,?,?,'S',0,0,?,?,?,?,0,0,0,0,0,0,0,0,0)");
    $stmt->execute(array(
        (int)$operator, (int)$location, (int)$tableNo, (int)$location,
        (string)$identity['serie_casa_marcat'], (int)$identity['nui'], (string)$identity['serie_memorie_fiscala']
    ));
    $nrBon = (int)$pdo->lastInsertId();
    $_SESSION['nr_bon'] = $nrBon;
    return $nrBon;
}

function vso_off_note_item_count(PDO $pdo, $table, $nrBon)
{
    if ((int)$nrBon <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE nr_bon=?");
    $stmt->execute(array((int)$nrBon));
    return (int)$stmt->fetchColumn();
}

function vso_off_recalc_note(PDO $pdo, $noteTable, $detTable, $nrBon)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valoare_vanzare_cu_tva),0) total, COALESCE(SUM(tva_col),0) tva, COALESCE(SUM(discount),0) discount FROM {$detTable} WHERE nr_bon=?");
    $stmt->execute(array((int)$nrBon));
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $up = $pdo->prepare("UPDATE {$noteTable} SET valoare_vanzare_cu_tva=?, tva_colectata=?, discount=? WHERE nrbon=?");
    $up->execute(array(
        round((float)$totals['total'], 2), round((float)$totals['tva'], 2),
        round((float)$totals['discount'], 2), (int)$nrBon
    ));
}

function vso_off_validate_order(PDO $pdo, $order, $lines, $productTable)
{
    if (!$lines) {
        throw new RuntimeException('Comanda nu contine produse.');
    }
    if ((int)$order['nr_bon_pos'] > 0 || in_array((string)$order['status_sync_pos'], array('preluata_local', 'preluata'), true)) {
        throw new RuntimeException('Comanda este deja importata in POS.');
    }
    if ((string)$order['status_comanda'] === 'anulata') {
        throw new RuntimeException('Comanda este anulata si nu poate fi importata.');
    }
    if (abs((float)$order['taxa_serviciu']) > 0.001 || abs((float)$order['taxa_livrare']) > 0.001 || abs((float)$order['discount_total']) > 0.001) {
        throw new RuntimeException('Comanda contine taxa/discount la nivel de comanda care nu are inca produs fiscal mapat. Importul este blocat pentru a evita diferente intre site si bon.');
    }
    $sum = 0.0;
    $check = $pdo->prepare("SELECT cod_produs, activ FROM {$productTable} WHERE cod_produs=? LIMIT 1");
    foreach ($lines as $line) {
        $cod = (int)$line['cod_produs'];
        $check->execute(array($cod));
        $product = $check->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new RuntimeException('Produsul cu codul ' . $cod . ' nu exista in nomenclatorul offline. Sincronizeaza nomenclatorul inainte de import.');
        }
        if ((int)$product['activ'] !== 1) {
            throw new RuntimeException('Produsul cu codul ' . $cod . ' este inactiv in nomenclatorul offline.');
        }
        if ((float)$line['cantitate'] <= 0) {
            throw new RuntimeException('Comanda contine o cantitate invalida.');
        }
        $sum += (float)$line['valoare_cu_tva'];
    }
    if (abs(round($sum, 2) - round((float)$order['subtotal'], 2)) > 0.02 || abs(round($sum, 2) - round((float)$order['total'], 2)) > 0.02) {
        throw new RuntimeException('Totalul produselor nu corespunde totalului comenzii. Importul este blocat pentru verificare.');
    }
}

function vso_off_import(PDO $pdo, $idLocal, $operatorId, $codLocatie, $codMasa, $tabelNote, $tabelDet, $tabelProduse)
{
    $stmt = $pdo->prepare('SELECT * FROM site_comenzi WHERE id_local=? AND cod_locatie=? LIMIT 1');
    $stmt->execute(array((int)$idLocal, (int)$codLocatie));
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new RuntimeException('Comanda locala nu exista pentru aceasta locatie.');
    }
    $linesStmt = $pdo->prepare('SELECT * FROM site_comenzi_produse WHERE id_comanda_online=? ORDER BY id_linie_online');
    $linesStmt->execute(array((int)$order['id_comanda_online']));
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
    vso_off_validate_order($pdo, $order, $lines, $tabelProduse);

    $nrBon = vso_off_get_or_create_note($pdo, $tabelNote, $operatorId, $codLocatie, $codMasa);
    if (vso_off_note_item_count($pdo, $tabelDet, $nrBon) > 0) {
        throw new RuntimeException('Bonul curent #' . $nrBon . ' contine deja produse. Finalizeaza sau goleste bonul inainte de import.');
    }

    $claim = site_orders_offline_claim((string)$order['uuid_comanda'], $operatorId, $codLocatie);
    if (!$claim['ok']) {
        throw new RuntimeException('Comanda nu a putut fi rezervata pe server: ' . $claim['error']);
    }
    $claimToken = trim((string)(isset($claim['data']['claim_token']) ? $claim['data']['claim_token'] : ''));
    if ($claimToken === '') {
        throw new RuntimeException('Serverul nu a returnat tokenul de rezervare.');
    }

    $pdo->beginTransaction();
    try {
        $again = $pdo->prepare('SELECT * FROM site_comenzi WHERE id_local=? LIMIT 1');
        $again->execute(array((int)$idLocal));
        $order = $again->fetch(PDO::FETCH_ASSOC);
        if (!$order || (int)$order['nr_bon_pos'] > 0) {
            throw new RuntimeException('Comanda a fost deja importata local.');
        }
        if (vso_off_note_item_count($pdo, $tabelDet, $nrBon) > 0) {
            throw new RuntimeException('Bonul curent s-a modificat intre timp. Importul a fost oprit.');
        }

        $insert = $pdo->prepare("INSERT INTO {$tabelDet}
            (nr_bon,cod_p,nume_produs,cantitate,cota_tva,tva_col,pret_vanzare,valoare_vanzare,valoare_vanzare_cu_tva,discount,pachet,preparat,t_list,data,ora,cod_meniu,observatie_produs,preluat_osp,prioritate,cod_locatie)
            VALUES(?,?,?,?,?,?,?,?,?,?,0,0,0,date('now','localtime'),time('now','localtime'),0,?,0,0,?)");
        foreach ($lines as $line) {
            $insert->execute(array(
                (int)$nrBon,
                (int)$line['cod_produs'],
                (string)$line['nume_produs_snapshot'],
                (float)$line['cantitate'],
                (int)round((float)$line['cota_tva']),
                round((float)$line['tva'], 5),
                round((float)$line['pret_unitar_cu_tva'], 5),
                round((float)$line['valoare_fara_tva'], 5),
                round((float)$line['valoare_cu_tva'], 5),
                round((float)$line['discount_linie'], 5),
                substr((string)$line['observatie_produs'], 0, 100),
                (int)$codLocatie
            ));
        }
        agecs_snapshot_det_note_departamente($pdo, (int)$nrBon, $tabelDet, $tabelProduse);
        vso_off_recalc_note($pdo, $tabelNote, $tabelDet, $nrBon);

        $update = $pdo->prepare("UPDATE site_comenzi SET status_sync_pos='preluata_local', status_comanda=CASE WHEN status_comanda='noua' THEN 'acceptata' ELSE status_comanda END, nr_bon_pos=?, operator_import=?, claim_token=?, importata_la=datetime('now','localtime'), sincronizata_la=datetime('now','localtime') WHERE id_local=?");
        $update->execute(array((int)$nrBon, (int)$operatorId, $claimToken, (int)$idLocal));
        $order['nr_bon_pos'] = (int)$nrBon;
        $order['claim_token'] = $claimToken;
        site_orders_offline_queue_imported($pdo, $order, $claimToken, $nrBon, $operatorId, $codLocatie);

        $_SESSION['nr_bon'] = (int)$nrBon;
        $_SESSION['trimis_comanda'] = 0;
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    site_orders_offline_flush_outbox($pdo, $codLocatie, 10);
    $check = $pdo->prepare('SELECT status_sync_pos FROM site_comenzi WHERE id_local=?');
    $check->execute(array((int)$idLocal));
    $status = (string)$check->fetchColumn();
    return array('nr_bon' => (int)$nrBon, 'confirmed' => $status === 'preluata');
}

// Fiecare accesare a paginii incearca ACK-urile restante si cere doar evenimentele noi.
$syncResult = site_orders_offline_sync($pdo, $codLocatie);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_site_order') {
    try {
        if (!hash_equals($csrf, (string)(isset($_POST['csrf']) ? $_POST['csrf'] : ''))) {
            throw new RuntimeException('Sesiune invalida. Reincarca pagina.');
        }
        $idLocal = (int)(isset($_POST['id_local']) ? $_POST['id_local'] : 0);
        if ($idLocal <= 0) {
            throw new RuntimeException('Comanda invalida.');
        }
        $result = vso_off_import($pdo, $idLocal, $operatorId, $codLocatie, $codMasa, $tabelNote, $tabelDet, $tabelProduse);
        $message = 'Comanda a fost importata in bonul #' . (int)$result['nr_bon'] . '.';
        if (!$result['confirmed']) {
            $message .= ' Confirmarea catre server este in coada si va fi retrimisa automat din aceasta pagina.';
        }
        $_SESSION['site_order_offline_flash'] = array('type' => $result['confirmed'] ? 'success' : 'warning', 'message' => $message);
    } catch (Throwable $e) {
        $_SESSION['site_order_offline_flash'] = array('type' => 'danger', 'message' => $e->getMessage());
    }
    header('Location: vanzare_comenzi_site.php');
    exit;
}

$currentNote = vso_off_current_note($pdo, $tabelNote, $operatorId, $codLocatie);
$currentItems = vso_off_note_item_count($pdo, $tabelDet, $currentNote);
$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM site_comenzi_produse l WHERE l.id_comanda_online=c.id_comanda_online) nr_linii FROM site_comenzi c WHERE c.cod_locatie=? ORDER BY CASE c.status_comanda WHEN 'noua' THEN 0 WHEN 'acceptata' THEN 1 WHEN 'in_pregatire' THEN 2 WHEN 'gata' THEN 3 ELSE 4 END, c.id_comanda_online DESC LIMIT 150");
$stmt->execute(array($codLocatie));
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$state = site_orders_offline_state($pdo);
$outboxPending = (int)$pdo->query("SELECT COUNT(*) FROM site_comenzi_outbox WHERE status='pending'")->fetchColumn();
?>
<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="45">
<title>Comenzi site Lorand - Offline</title>
<link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css">
<link rel="stylesheet" href="vendor/offline/fontawesome5/css/all.min.css">
<style>
body{background:#f4f6f8;color:#17313d}.topbar{position:sticky;top:0;z-index:20;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08)}.order-card{border:0;border-radius:16px;overflow:hidden}.order-new{border-left:6px solid #f2bd22}.order-total{font-size:1.25rem;font-weight:800}.sync-chip{border-radius:999px;padding:.35rem .7rem;background:#eef3f5;font-size:.85rem}.card-meta{color:#65757c;font-size:.9rem}.empty-state{border:2px dashed #ced8dc;border-radius:16px;background:#fff;padding:3rem 1rem}.btn-import{font-weight:700;border-radius:10px}.warn-fees{font-size:.85rem;color:#b45b00}.badge{font-size:.78rem}.statusline{gap:.45rem}
</style>
</head>
<body>
<div class="topbar"><div class="container-fluid py-3 d-flex flex-wrap align-items-center">
<a class="btn btn-dark mr-2" href="vanzare_magazin.php"><i class="fas fa-arrow-left"></i> Inapoi la vanzare</a>
<h4 class="mb-0 mr-auto">Comenzi site Lorand <small class="text-muted">offline</small></h4>
<span class="sync-chip mr-2"><i class="fas fa-database"></i> cursor <?php echo (int)$state['last_event_id']; ?></span>
<a class="btn btn-outline-primary" href="vanzare_comenzi_site.php"><i class="fas fa-sync"></i> Verifica acum</a>
</div></div>
<div class="container-fluid py-4">
<?php if ($flash): ?><div class="alert alert-<?php echo vso_off_h($flash['type']); ?> shadow-sm"><?php echo vso_off_h($flash['message']); ?></div><?php endif; ?>
<?php if (!$syncResult['ok']): ?><div class="alert alert-warning shadow-sm"><strong>Lucrezi pe cache-ul local.</strong> Nu s-au putut verifica acum comenzile noi: <?php echo vso_off_h($syncResult['error']); ?> Comenzile deja sincronizate raman disponibile.</div><?php else: ?><div class="alert alert-success py-2 shadow-sm"><i class="fas fa-cloud-download-alt"></i> Sincronizare reusita. Comenzi actualizate: <strong><?php echo (int)$syncResult['count']; ?></strong>.<?php if ($outboxPending > 0): ?> Confirmari in asteptare: <strong><?php echo $outboxPending; ?></strong>.<?php endif; ?></div><?php endif; ?>
<div class="alert <?php echo $currentItems > 0 ? 'alert-warning' : 'alert-info'; ?> shadow-sm">Bon curent: <strong>#<?php echo (int)$currentNote; ?></strong> · produse in bon: <strong><?php echo (int)$currentItems; ?></strong>. <?php echo $currentItems > 0 ? 'Importul este blocat pana finalizezi sau golesti bonul curent.' : 'Bonul este liber si poate primi o comanda online.'; ?></div>
<div class="row">
<?php foreach ($orders as $order):
    $hasFees = abs((float)$order['taxa_serviciu']) > .001 || abs((float)$order['taxa_livrare']) > .001 || abs((float)$order['discount_total']) > .001;
    $already = (int)$order['nr_bon_pos'] > 0 || in_array((string)$order['status_sync_pos'], array('preluata_local','preluata'), true);
    $canImport = $currentItems === 0 && !$already && (string)$order['status_comanda'] !== 'anulata' && !$hasFees;
?>
<div class="col-xl-4 col-lg-6 mb-3"><div class="card shadow-sm order-card <?php echo $order['status_comanda'] === 'noua' ? 'order-new' : ''; ?>"><div class="card-body">
<div class="d-flex"><div><h5 class="mb-1"><?php echo vso_off_h($order['numar_comanda']); ?></h5><div class="card-meta"><?php echo vso_off_h($order['creata_la']); ?></div></div><div class="ml-auto text-right"><div class="order-total"><?php echo vso_off_money($order['total']); ?> <?php echo vso_off_h($order['moneda']); ?></div><div class="d-flex flex-wrap justify-content-end statusline"><?php echo vso_off_badge($order['status_comanda']); ?> <?php echo vso_off_badge($order['status_sync_pos']); ?></div></div></div>
<hr><p class="mb-1"><strong><?php echo vso_off_h($order['client_nume_snapshot']); ?></strong> · <?php echo vso_off_h($order['client_telefon_snapshot']); ?></p>
<p class="mb-1">Predare: <strong><?php echo vso_off_h($order['tip_predare']); ?></strong> · Plata: <strong><?php echo vso_off_h($order['metoda_plata']); ?></strong></p>
<p class="mb-2">Produse: <strong><?php echo (int)$order['nr_linii']; ?></strong><?php if (trim((string)$order['observatii']) !== ''): ?><br><small>Obs: <?php echo vso_off_h($order['observatii']); ?></small><?php endif; ?></p>
<?php if ($hasFees): ?><p class="warn-fees"><i class="fas fa-exclamation-triangle"></i> Taxa/discount nemapat fiscal: importul este blocat.</p><?php endif; ?>
<?php if ($canImport): ?><form method="post"><input type="hidden" name="csrf" value="<?php echo vso_off_h($csrf); ?>"><input type="hidden" name="action" value="import_site_order"><input type="hidden" name="id_local" value="<?php echo (int)$order['id_local']; ?>"><button class="btn btn-success btn-block btn-import"><i class="fas fa-download"></i> Importa in bon</button></form>
<?php elseif ($already): ?><button class="btn btn-secondary btn-block" disabled><i class="fas fa-check"></i> Importata in bon #<?php echo (int)$order['nr_bon_pos']; ?></button>
<?php elseif ((string)$order['status_comanda'] === 'anulata'): ?><button class="btn btn-danger btn-block" disabled>Anulata</button>
<?php elseif ($hasFees): ?><button class="btn btn-warning btn-block" disabled>Necesita mapare taxa/discount</button>
<?php else: ?><button class="btn btn-warning btn-block" disabled>Finalizeaza bonul curent inainte</button><?php endif; ?>
</div></div></div>
<?php endforeach; ?>
<?php if (!$orders): ?><div class="col-12"><div class="empty-state text-center"><i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i><h5>Nu exista comenzi sincronizate</h5><p class="text-muted mb-0">Pagina va verifica din nou serverul AGECS la urmatoarea actualizare.</p></div></div><?php endif; ?>
</div>
<p class="text-muted text-center mt-3"><small>Serverul este verificat la 45 secunde numai cat timp aceasta pagina este deschisa. Daca internetul cade dupa import, confirmarea ramane in outbox si este retrimisa fara a dubla bonul.</small></p>
</div>
</body></html>
