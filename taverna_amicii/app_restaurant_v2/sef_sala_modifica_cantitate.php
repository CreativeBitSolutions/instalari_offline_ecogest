<?php
include('session.php');

function normalizeazaDepartamenteListare($departamentRaw, $fallback = 'BAR') {
    $departamente = [];

    foreach (explode(',', (string)$departamentRaw) as $departament) {
        $departament = trim($departament);
        if ($departament !== '' && !in_array($departament, $departamente, true)) {
            $departamente[] = $departament;
        }
    }

    if (empty($departamente)) {
        $departamente[] = $fallback;
    }

    return $departamente;
}

function obtineDepartamenteProdus($pdo, $codProdus) {
    $stmtDept = $pdo->prepare("SELECT departament FROM produse_servicii WHERE cod_produs = ? LIMIT 1");
    $stmtDept->execute([$codProdus]);
    $departamentRaw = $stmtDept->fetchColumn();

    return normalizeazaDepartamenteListare($departamentRaw, 'BAR');
}

function scrieFisierImprimantaSefSala($client_id, $cod_locatie, $printData, $message) {
    $client_id = intval($client_id);
    $cod_locatie = intval($cod_locatie);

    if ($client_id === 0 || $cod_locatie === 0 || empty($printData)) {
        return;
    }

    $folder_path = RESTAURANT_OFFLINE_API_DIR . "/" . $client_id . "/" . $cod_locatie;
    if (!is_dir($folder_path)) {
        mkdir($folder_path, 0777, true);
    }

    $json_file_path = $folder_path . "/de_listat_la_imprimanta.json";
    $totalWait = 0;

    // Păstrăm aceeași logică de protecție ca la listarea notei:
    // dacă există deja un fișier neprocesat, așteptăm înainte să scriem unul nou.
    while (file_exists($json_file_path) && $totalWait < 60) {
        sleep(5);
        $totalWait += 5;
    }

    if (file_exists($json_file_path)) {
        error_log("Atenție: de_listat_la_imprimanta.json nu a fost procesat în 60s. Se suprascrie pentru operațiunea șef sală.");
    }

    $json_array = [
        "status" => "success",
        "message" => $message,
        "data" => array_values($printData)
    ];

    file_put_contents($json_file_path, json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function genereazaFoaieEliberare($pdo, $infoNota) {
    $client_id = intval($_SESSION['client_id'] ?? 0);
    $cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;

    if ($client_id === 0 || $cod_locatie === 0) return;

    $stmtInfo = $pdo->prepare("
        SELECT n.cod_masa, m.nume_masa, a.admin_firstname, a.admin_lastname, p.departament
        FROM note n
        LEFT JOIN mese m ON n.cod_masa = m.cod_masa
        LEFT JOIN admins_12 a ON a.admin_id = ?
        LEFT JOIN produse_servicii p ON p.cod_produs = ?
        WHERE n.nrbon = ?
    ");
    $stmtInfo->execute([$infoNota['admin_id'], $infoNota['cod_produs'], $infoNota['nrbon']]);
    $dataBon = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    $nume_masa = $dataBon['nume_masa'] ?? 'N/A';
    $operator = trim(($dataBon['admin_firstname'] ?? '') . ' ' . ($dataBon['admin_lastname'] ?? ''));
    $departamente = normalizeazaDepartamenteListare($dataBon['departament'] ?? 'BAR', 'BAR');

    $header = str_pad("NOTA STORNARE", 32, "-", STR_PAD_BOTH);
    $continut = $header . "\n";
    $continut .= "Data: " . date('d.m.Y H:i') . "\n";
    $continut .= "Masa: " . $nume_masa . " / Op: " . $operator . "\n";
    $continut .= "Nr. nota: " . $infoNota['nrbon']. "\n";
    $continut .= str_repeat("-", 32) . "\n";

    if ($infoNota['tip_operatie'] === 'modificare') {
        $diferenta = $infoNota['cant_noua'] - $infoNota['cant_veche'];
        $semn = $diferenta >= 0 ? '+' : '';
        $linieProdus = $infoNota['produs'] . " x " . $semn . number_format($diferenta, 2);
        $continut .= "CORECTIE: " . $linieProdus . "\n";
        $continut .= "Cant. Veche: " . number_format($infoNota['cant_veche'], 2) . " -> Cant. Noua: " . number_format($infoNota['cant_noua'], 2) . "\n";
    } elseif ($infoNota['tip_operatie'] === 'stergere') {
        $linieProdus = $infoNota['produs'] . " x -" . number_format($infoNota['cant_stearsa'], 2);
        $continut .= "ANULARE: " . $linieProdus . "\n";
    }

    $continut .= "Motiv: " . $infoNota['motiv'] . "\n";
    $continut .= str_repeat("-", 32) . "\n";

    $printData = [];
    foreach ($departamente as $departament) {
        $printData[] = [
            'data' => date('Y-m-d'),
            'ora' => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => $infoNota['nrbon'],
            'locatie' => $cod_locatie,
            'departament_listare' => $departament,
            'continut' => $continut
        ];
    }

    scrieFisierImprimantaSefSala(
        $client_id,
        $cod_locatie,
        $printData,
        count($printData) > 1
            ? "Date pentru foaie eliberare generate pentru imprimante multiple."
            : "Date pentru foaie eliberare generate."
    );
}

function genereazaFoaieEliberareMulti($pdo, $nrbon, $motiv, $admin_id, $produseSterse) {
    $client_id = intval($_SESSION['client_id'] ?? 0);
    $cod_locatie = isset($_SESSION['cod_locatie']) ? intval($_SESSION['cod_locatie']) : 0;
    if ($client_id === 0 || $cod_locatie === 0 || empty($produseSterse)) return;

    $stmtInfo = $pdo->prepare("
        SELECT m.nume_masa, a.admin_firstname, a.admin_lastname
        FROM note n
        LEFT JOIN mese m ON n.cod_masa = m.cod_masa
        LEFT JOIN admins_12 a ON a.admin_id = ?
        WHERE n.nrbon = ?
    ");
    $stmtInfo->execute([$admin_id, $nrbon]);
    $dataBon = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    $nume_masa = $dataBon['nume_masa'] ?? 'N/A';
    $operator = trim(($dataBon['admin_firstname'] ?? '') . ' ' . ($dataBon['admin_lastname'] ?? ''));

    // Grupăm produsele pe fiecare departament/imprimantă.
    // Dacă un produs are departamente multiple, apare în fiecare job aferent.
    $produsePeDepartament = [];
    foreach ($produseSterse as $produs) {
        $departamente = obtineDepartamenteProdus($pdo, $produs['cod_p'] ?? 0);
        foreach ($departamente as $departament) {
            if (!isset($produsePeDepartament[$departament])) {
                $produsePeDepartament[$departament] = [];
            }
            $produsePeDepartament[$departament][] = $produs;
        }
    }

    if (empty($produsePeDepartament)) {
        return;
    }

    $printData = [];
    foreach ($produsePeDepartament as $departament => $produseDepartament) {
        $continut = str_pad("ANULARE COMPLETA BON", 32, "-", STR_PAD_BOTH) . "\n";
        $continut .= "Data: " . date('d.m.Y H:i') . "\n";
        $continut .= "Masa: " . $nume_masa . " / Op: " . $operator . "\n";
        $continut .= "Nr. nota: " . $nrbon. "\n";
        $continut .= str_repeat("-", 32) . "\n";

        foreach ($produseDepartament as $produs) {
            $continut .= $produs['nume_produs'] . " x -" . number_format($produs['cantitate'], 2) . "\n";
        }

        $continut .= str_repeat("-", 32) . "\n";
        $continut .= "Motiv: " . $motiv . "\n";
        $continut .= str_repeat("-", 32) . "\n";

        $printData[] = [
            'data' => date('Y-m-d'),
            'ora' => date('H:i:s'),
            'de_trimis_la_imprimanta' => 1,
            'nrbon' => $nrbon,
            'locatie' => $cod_locatie,
            'departament_listare' => $departament,
            'continut' => $continut
        ];
    }

    scrieFisierImprimantaSefSala(
        $client_id,
        $cod_locatie,
        $printData,
        count($printData) > 1
            ? "Date pentru anulare totala generate pentru imprimante multiple."
            : "Date pentru anulare totala generate."
    );
}


// ------ SCRIPT PRINCIPAL ------

header('Content-Type: application/json');

if (!isset($_POST['action'], $_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acțiune nevalidă sau sesiune expirată.']);
    exit;
}

$action = $_POST['action'];
$admin_id = $_SESSION['admin_id'];

if ($action === 'get_details') {
    header('Content-Type: text/html');
    $nrbon = intval($_POST['nrbon']);

    $sqlDet = "SELECT id_vanz, nume_produs, cantitate, valoare_vanzare_cu_tva, data, ora FROM det_note WHERE nr_bon = ? ORDER BY id_vanz";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$nrbon]);
    $details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $sqlHistory = "SELECT motiv, detalii_eliberare FROM eliberari_mese WHERE nrbon = ? ORDER BY id_eliberare DESC";
    $stmtHistory = $pdo->prepare($sqlHistory);
    $stmtHistory->execute([$nrbon]);
    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    if (count($details) > 0) {
        echo '<div class="card note-details-card" style="display: block;">';
        echo '<div class="card-header card-header-flex">
                <h4>Detalii Bon #' . $nrbon . '</h4>
                <button class="btn btn-warning btn-sm delete-all-btn" data-nrbon="' . $nrbon . '"><i class="fas fa-exclamation-triangle"></i> Șterge toate produsele</button>
              </div>';
        echo '<div class="card-body">';
        echo '<div class="table-responsive"><table class="table table-striped table-hover"><thead>';
        echo '<tr><th>Produs</th><th>Cantitate</th><th>Valoare</th><th>Dată/Oră</th><th>Acțiuni</th></tr>';
        echo '</thead><tbody>';
        foreach ($details as $row) {
            echo '<tr id="row-' . $row['id_vanz'] . '">';
            echo '<td>' . htmlspecialchars($row['nume_produs']) . '</td>';
            echo '<td>' . number_format($row['cantitate'], 2) . '</td>';
            echo '<td>' . number_format($row['valoare_vanzare_cu_tva'], 2) . ' RON</td>';
            echo '<td>' . date("d.m.Y", strtotime($row['data'])) . '<br>' . $row['ora'] . '</td>';
            echo '<td>
                    <button class="btn btn-primary btn-sm modify-btn" data-id="' . $row['id_vanz'] . '"><i class="fas fa-edit"></i> Modifică</button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="' . $row['id_vanz'] . '"><i class="fas fa-trash"></i> Șterge</button>
                  </td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div></div>';
    } else {
        echo '<div class="card note-details-card" style="display: block;">
                <div class="card-header"><h4>Detalii Bon #' . $nrbon . '</h4></div>
                <div class="card-body text-center">
                    <p>Acest bon nu mai are produse.</p>
                    <button class="btn btn-success close-table-btn" data-nrbon="' . $nrbon . '"><i class="fas fa-check-circle"></i> Închide Masa Acum</button>
                </div>
              </div>';
    }

    if (count($history) > 0) {
        echo '<div class="card mt-4">';
        echo '<div class="card-header"><h5><i class="fas fa-history"></i> Istoric Modificări Bon #' . $nrbon . '</h5></div>';
        echo '<div class="card-body" style="max-height: 200px; overflow-y: auto;">';
        echo '<ul class="list-group list-group-flush">';
        foreach ($history as $entry) {
            $log = json_decode($entry['detalii_eliberare'], true);
            $motiv = htmlspecialchars($entry['motiv']);
            $text = 'Operațiune necunoscută.';

            if ($log && isset($log['tip_operatie']) && $log['tip_operatie'] === 'modificare_cantitate') {
                $text = '<strong>Modificare:</strong> ' . htmlspecialchars($log['produs']) .
                        ' de la <strong>' . number_format($log['cantitate_veche'], 2) . '</strong> la <strong>' . number_format($log['cantitate_noua'], 2) . '</strong>. Motiv: ' . $motiv;
            } elseif ($log && isset($log['tip_operatie']) && $log['tip_operatie'] === 'stergere_produs') {
                 $text = '<strong>Ștergere:</strong> ' . htmlspecialchars($log['detalii_sterse']['nume_produs']) .
                         ' (Cant: <strong>' . number_format($log['detalii_sterse']['cantitate'], 2) . '</strong>). Motiv: ' . $motiv;
            } elseif ($log && isset($log['tip_operatie']) && $log['tip_operatie'] === 'stergere_totala_bon') {
                 $text = '<strong>Anulare Bon Complet.</strong> Motiv: ' . $motiv;
            }
            echo '<li class="list-group-item">' . $text . '</li>';
        }
        echo '</ul></div></div>';
    }
    exit;
}

$pdo->beginTransaction();
try {
    switch ($action) {
        case 'modify_quantity':
            $id_vanz = intval($_POST['id_vanz']);
            $new_quantity = floatval($_POST['new_quantity']);
            $motiv = trim($_POST['motiv']);
            $stmt = $pdo->prepare("SELECT * FROM det_note WHERE id_vanz = ?");
            $stmt->execute([$id_vanz]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Produsul nu a fost găsit.");

            // ===== NOU: operatorul notei (note.operator) =====
            $stmtOp = $pdo->prepare("SELECT operator FROM note WHERE nrbon = ? LIMIT 1");
            $stmtOp->execute([$row['nr_bon']]);
            $operator_nota = (int)$stmtOp->fetchColumn();
            // ================================================

            $cantitate_veche = $row['cantitate'];
            $pret_unitar = $row['pret_vanzare'];
            $cota_tva = $row['cota_tva'];
            $valoare_vanzare_cu_tva = round($pret_unitar * $new_quantity, 2);
            $tva_col = round($valoare_vanzare_cu_tva * $cota_tva / (100 + $cota_tva), 2);
            $valoare_vanzare = round($valoare_vanzare_cu_tva - $tva_col, 2);
            $updateStmt = $pdo->prepare("UPDATE det_note SET cantitate = ?, valoare_vanzare = ?, tva_col = ?, valoare_vanzare_cu_tva = ? WHERE id_vanz = ?");
            if($new_quantity > 0) {
                 $updateStmt->execute([$new_quantity, $valoare_vanzare, $tva_col, $valoare_vanzare_cu_tva, $id_vanz]);
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM det_note WHERE id_vanz = ?");
                $deleteStmt->execute([$id_vanz]);
            }

            // ===== MODIFICAT: adăugăm operator_nota în JSON =====
            $detalii_modificare = json_encode([
                'tip_operatie' => 'modificare_cantitate',
                'id_vanz' => $id_vanz,
                'operator_nota' => $operator_nota,
                'produs' => $row['nume_produs'],
                'cantitate_veche' => $cantitate_veche,
                'cantitate_noua' => $new_quantity,
                'detalii_vechi' => $row
            ], JSON_UNESCAPED_UNICODE);
            // ====================================================

            $logStmt = $pdo->prepare("INSERT INTO eliberari_mese (nrbon, motiv, sters_de, detalii_eliberare) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$row['nr_bon'], $motiv, $admin_id, $detalii_modificare]);
            genereazaFoaieEliberare($pdo, ['tip_operatie' => 'modificare', 'nrbon' => $row['nr_bon'], 'admin_id' => $admin_id, 'cod_produs' => $row['cod_p'], 'produs' => $row['nume_produs'], 'cant_veche' => $cantitate_veche, 'cant_noua' => $new_quantity, 'motiv' => $motiv]);
            echo json_encode(['status' => 'success']);
            break;

        case 'delete_item':
            $id_vanz = intval($_POST['id_vanz']);
            $motiv = trim($_POST['motiv']);
            $stmt = $pdo->prepare("SELECT * FROM det_note WHERE id_vanz = ?");
            $stmt->execute([$id_vanz]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Produsul nu mai există.");

            // ===== NOU: operatorul notei (note.operator) =====
            $stmtOp = $pdo->prepare("SELECT operator FROM note WHERE nrbon = ? LIMIT 1");
            $stmtOp->execute([$row['nr_bon']]);
            $operator_nota = (int)$stmtOp->fetchColumn();
            // ================================================

            $nr_bon = $row['nr_bon'];

            // ===== MODIFICAT: adăugăm operator_nota în JSON =====
            $detalii_stergere = json_encode([
                'tip_operatie' => 'stergere_produs',
                'id_vanz' => $id_vanz,
                'operator_nota' => $operator_nota,
                'detalii_sterse' => $row
            ], JSON_UNESCAPED_UNICODE);
            // ====================================================

            $logStmt = $pdo->prepare("INSERT INTO eliberari_mese (nrbon, motiv, sters_de, detalii_eliberare) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$nr_bon, $motiv, $admin_id, $detalii_stergere]);
            $deleteStmt = $pdo->prepare("DELETE FROM det_note WHERE id_vanz = ?");
            $deleteStmt->execute([$id_vanz]);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM det_note WHERE nr_bon = ?");
            $countStmt->execute([$nr_bon]);
            $items_left = $countStmt->fetchColumn();
            genereazaFoaieEliberare($pdo, ['tip_operatie' => 'stergere', 'nrbon' => $row['nr_bon'], 'admin_id' => $admin_id, 'cod_produs' => $row['cod_p'], 'produs' => $row['nume_produs'], 'cant_stearsa' => $row['cantitate'], 'motiv' => $motiv]);
            echo json_encode(['status' => 'success', 'items_left' => $items_left]);
            break;

        case 'delete_all_items':
            $nrbon = intval($_POST['nrbon']);
            $motiv = trim($_POST['motiv']);

            // ===== NOU: operatorul notei (note.operator) =====
            $stmtOp = $pdo->prepare("SELECT operator FROM note WHERE nrbon = ? LIMIT 1");
            $stmtOp->execute([$nrbon]);
            $operator_nota = (int)$stmtOp->fetchColumn();
            // ================================================

            $stmt = $pdo->prepare("SELECT * FROM det_note WHERE nr_bon = ?");
            $stmt->execute([$nrbon]);
            $produse_de_sters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($produse_de_sters)) throw new Exception("Bonul este deja gol.");

            $logStmt = $pdo->prepare("INSERT INTO eliberari_mese (nrbon, motiv, sters_de, detalii_eliberare) VALUES (?, ?, ?, ?)");
            foreach($produse_de_sters as $row) {
                // ===== MODIFICAT: adăugăm operator_nota în JSON =====
                $detalii_stergere = json_encode([
                    'tip_operatie' => 'stergere_produs',
                    'id_vanz' => $row['id_vanz'],
                    'operator_nota' => $operator_nota,
                    'detalii_sterse' => $row
                ], JSON_UNESCAPED_UNICODE);
                // ====================================================
                $logStmt->execute([$nrbon, $motiv, $admin_id, $detalii_stergere]);
            }

            $logTotalStmt = $pdo->prepare("INSERT INTO eliberari_mese (nrbon, motiv, sters_de, detalii_eliberare) VALUES (?, ?, ?, ?)");
            // ===== MODIFICAT: adăugăm operator_nota în JSON =====
            $detalii_totale = json_encode([
                'tip_operatie' => 'stergere_totala_bon',
                'operator_nota' => $operator_nota
            ], JSON_UNESCAPED_UNICODE);
            // ====================================================
            $logTotalStmt->execute([$nrbon, $motiv, $admin_id, $detalii_totale]);

            $deleteStmt = $pdo->prepare("DELETE FROM det_note WHERE nr_bon = ?");
            $deleteStmt->execute([$nrbon]);

            genereazaFoaieEliberareMulti($pdo, $nrbon, $motiv, $admin_id, $produse_de_sters);

            echo json_encode(['status' => 'success']);
            break;

        case 'close_table':
            $nrbon = intval($_POST['nrbon']);
            $stmtMasa = $pdo->prepare("SELECT cod_masa FROM note WHERE nrbon = ?");
            $stmtMasa->execute([$nrbon]);
            $rowMasa = $stmtMasa->fetch(PDO::FETCH_ASSOC);
            if (!$rowMasa) throw new Exception('Bonul nu a fost găsit.');
            $cod_masa = $rowMasa['cod_masa'];
            $deleteNota = $pdo->prepare("DELETE FROM note WHERE nrbon = ?");
            $deleteNota->execute([$nrbon]);
            $updateMasa = $pdo->prepare("UPDATE mese SET stare = 0 WHERE cod_masa = ?");
            $updateMasa->execute([$cod_masa]);
            echo json_encode(['status' => 'success']);
            break;

        default:
            throw new Exception("Acțiune necunoscută.");
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
