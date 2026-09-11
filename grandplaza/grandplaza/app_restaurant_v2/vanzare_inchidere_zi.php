<?php
// Include fișierul de sesiune (conține conexiunea la BD și obiectul $pdo)
include('session.php');

// Preluare valori din formular (trimise prin POST)
$numerar          = isset($_POST['numerar']) ? $_POST['numerar'] : 0;
$card             = isset($_POST['card']) ? $_POST['card'] : 0;
$credit           = isset($_POST['credit']) ? $_POST['credit'] : 0;
$tichete_masa     = isset($_POST['tichete_masa']) ? $_POST['tichete_masa'] : 0;
$tichete_valorice = isset($_POST['tichete_valorice']) ? $_POST['tichete_valorice'] : 0;
$voucher          = isset($_POST['voucher']) ? $_POST['voucher'] : 0;
$plata_moderna    = isset($_POST['plata_moderna']) ? $_POST['plata_moderna'] : 0;
$avans_in_numerar = isset($_POST['avans_in_numerar']) ? $_POST['avans_in_numerar'] : 0;
$alte_metode      = isset($_POST['alte_metode']) ? $_POST['alte_metode'] : 0;
$user_nr_raport_z = isset($_POST['nr_raport_z']) ? $_POST['nr_raport_z'] : 0;

// Preluare variabile din sesiune
$cod_locatie = isset($_SESSION['cod_locatie']) ? $_SESSION['cod_locatie'] : 0;
$adm_id      = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;

$raportZIdentity = restaurant_sqlite_raport_z_current_identification($pdo, (int)$cod_locatie);
$serie_casa_marcat = $raportZIdentity['serie_casa_marcat'];
$nui = $raportZIdentity['nui'];
$serie_memorie_fiscala = $raportZIdentity['serie_memorie_fiscala'];
$user_nr_raport_z = (int)$user_nr_raport_z;
if ($user_nr_raport_z <= 0) {
    $user_nr_raport_z = restaurant_sqlite_raport_z_next_number($pdo, (int)$cod_locatie, $nui, $serie_memorie_fiscala);
}

// Se preia "serie_casa_marcat" din tabela "loc_mese_12" pe baza cod_locatie
try {
    $sql = "SELECT serie_casa_marcat FROM loc_mese_12 WHERE cod_locatie = :cod_locatie LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cod_locatie' => $cod_locatie]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $serie_casa_marcat = $row ? $row['serie_casa_marcat'] : '';
} catch (PDOException $e) {
    die("Eroare la preluarea seriei casei: " . $e->getMessage());
}

$serie_casa_marcat = $raportZIdentity['serie_casa_marcat'];
$nui = $raportZIdentity['nui'];
$serie_memorie_fiscala = $raportZIdentity['serie_memorie_fiscala'];

// Inserare raportul Z în tabela "rapoarte_z"
try {
    $duplicateStmt = $pdo->prepare("SELECT 1 FROM rapoarte_z
        WHERE cod_locatie = ? AND COALESCE(serie_casa_marcat, '') = ?
          AND COALESCE(nui, 0) = ? AND COALESCE(serie_memorie_fiscala, '') = ?
          AND nr_raport_z = ? LIMIT 1");
    $duplicateStmt->execute([$cod_locatie, $serie_casa_marcat, $nui, $serie_memorie_fiscala, $user_nr_raport_z]);
    if ($duplicateStmt->fetchColumn()) {
        throw new RuntimeException('Raportul Z exista deja pentru aceasta identitate fiscala.');
    }

    $insertRaportSql = "INSERT INTO rapoarte_z 
        (nr_raport_z, cod_locatie, serie_casa_marcat, nui, serie_memorie_fiscala, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode)
        VALUES (:nr_raport_z, :cod_locatie, :serie_casa_marcat, :nui, :serie_memorie_fiscala, :numerar, :card, :credit, :tichete_masa, :tichete_valorice, :plata_moderna, :avans_in_numerar, :alte_metode)";
    $stmt = $pdo->prepare($insertRaportSql);
    $stmt->execute([
        'nr_raport_z'      => $user_nr_raport_z,
        'cod_locatie'      => $cod_locatie,
        'serie_casa_marcat'=> $serie_casa_marcat,
        'nui'              => $nui,
        'serie_memorie_fiscala' => $serie_memorie_fiscala,
        'numerar'          => $numerar,
        'card'             => $card,
        'credit'           => $credit,
        'tichete_masa'     => $tichete_masa,
        'tichete_valorice' => $tichete_valorice,
        'plata_moderna'    => $plata_moderna,
        'avans_in_numerar' => $avans_in_numerar,
        'alte_metode'      => $alte_metode
    ]);
} catch (PDOException $e) {
    die("Eroare la inserarea raportului Z: " . $e->getMessage());
}

// Actualizează tabela "note": setăm nr_raport_z pentru notele cu status "F"
// care au nr_raport_z = 0 și sunt din locația curentă
try {
    $updateNoteSql = "UPDATE note 
                      SET nr_raport_z = :nr_raport_z, serie_casa_marcat = :serie_casa_marcat, nui = :nui, serie_memorie_fiscala = :serie_memorie_fiscala
                      WHERE status = 'F' 
                        AND locatie = :locatie 
                        AND nr_raport_z = 0
                        AND cod_inchidere != 0
                        AND (COALESCE(serie_casa_marcat, '') = :filter_series OR COALESCE(serie_casa_marcat, '') = '')
                        AND (COALESCE(nui, 0) = :filter_nui OR COALESCE(nui, 0) = 0)
                        AND (COALESCE(serie_memorie_fiscala, '') = :filter_memory OR COALESCE(serie_memorie_fiscala, '') = '')";
    $stmt = $pdo->prepare($updateNoteSql);
    $stmt->execute([
        'nr_raport_z' => $user_nr_raport_z,
        'serie_casa_marcat' => $serie_casa_marcat,
        'nui'         => $nui,
        'filter_series' => $serie_casa_marcat,
        'serie_memorie_fiscala' => $serie_memorie_fiscala,
        'filter_nui' => $nui,
        'filter_memory' => $serie_memorie_fiscala,
        'locatie'     => $cod_locatie
    ]);
} catch (PDOException $e) {
    die("Eroare la actualizarea notelor: " . $e->getMessage());
}

// Selectează toate codurile de închidere din "note" care tocmai au fost actualizate
try {
    $selectCodSql = "SELECT DISTINCT cod_inchidere 
                     FROM note 
                     WHERE status = 'F' 
                       AND locatie = :locatie 
                       AND nr_raport_z = :nr_raport_z
                       AND COALESCE(serie_casa_marcat, '') = :serie_casa_marcat
                       AND COALESCE(nui, 0) = :nui
                       AND COALESCE(serie_memorie_fiscala, '') = :serie_memorie_fiscala";
    $stmt = $pdo->prepare($selectCodSql);
    $stmt->execute([
        'locatie'     => $cod_locatie,
        'nr_raport_z' => $user_nr_raport_z,
        'serie_casa_marcat' => $serie_casa_marcat,
        'nui'         => $nui,
        'serie_memorie_fiscala' => $serie_memorie_fiscala
    ]);
    $cod_inchideri = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Eroare la selectarea codurilor de închidere: " . $e->getMessage());
}

// Actualizează tabela "inchideri_r_12": setează nr_raport_z pentru toate înregistrările care au un cod_inchidere
// în lista selectată și pentru locația curentă
if (!empty($cod_inchideri)) {
    try {
        // Construiește clauza IN dinamică
        $inClause = implode(',', array_fill(0, count($cod_inchideri), '?'));
        $updateInchideriSql = "UPDATE inchideri_r_12 
                               SET nr_raport_z = ?, serie_casa_marcat = ?, nui = ?, serie_memorie_fiscala = ?
                               WHERE cod_inchidere IN ($inClause)
                                 AND locatie = ?
                                 AND (COALESCE(serie_casa_marcat, '') = ? OR COALESCE(serie_casa_marcat, '') = '')
                                 AND (COALESCE(nui, 0) = ? OR COALESCE(nui, 0) = 0)
                                 AND (COALESCE(serie_memorie_fiscala, '') = ? OR COALESCE(serie_memorie_fiscala, '') = '')";
        // Parametrii: primul element este nr_raport_z, apoi lista de coduri, apoi cod_locatie
        $params = array_merge([$user_nr_raport_z, $serie_casa_marcat, $nui, $serie_memorie_fiscala], $cod_inchideri, [$cod_locatie, $serie_casa_marcat, $nui, $serie_memorie_fiscala]);
        $stmt = $pdo->prepare($updateInchideriSql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        die("Eroare la actualizarea închiderilor: " . $e->getMessage());
    }
}

// ============================================================================
// INTEGRAT: Actualizare tabelă "miscari" (Non-intrusiv / Silent Fail)
// ============================================================================
try {
    $pdo->beginTransaction();

    // 1) BF (ieșiri pe bon fiscal): n.nrbon = m.nr_doc
    $sqlBF = "
        UPDATE miscari
        SET nr_raport_z = (
            SELECT n.nr_raport_z
            FROM note n
            WHERE n.nrbon = miscari.nr_doc
            LIMIT 1
        ), serie_casa_marcat = (
            SELECT COALESCE(n.serie_casa_marcat, '') FROM note n WHERE n.nrbon = miscari.nr_doc LIMIT 1
        ), nui = (
            SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_doc LIMIT 1
        ), serie_memorie_fiscala = (
            SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_doc LIMIT 1
        )
        WHERE tip_miscare = 'O'
          AND fel_doc = 'BF'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_doc
                AND n.nr_raport_z = :bf_nr_z
                AND COALESCE(n.serie_casa_marcat, '') = :bf_series
                AND COALESCE(n.nui, 0) = :bf_nui
                AND COALESCE(n.serie_memorie_fiscala, '') = :bf_memory
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBF = $pdo->prepare($sqlBF);
    $stBF->execute([':bf_nr_z' => $user_nr_raport_z, ':bf_series' => $serie_casa_marcat, ':bf_nui' => $nui, ':bf_memory' => $serie_memorie_fiscala]);

    // 2) BC (consum): n.nrbon = m.nr_nota
    $sqlBC = "
        UPDATE miscari
        SET nr_raport_z = (
            SELECT n.nr_raport_z
            FROM note n
            WHERE n.nrbon = miscari.nr_nota
            LIMIT 1
        ), serie_casa_marcat = (
            SELECT COALESCE(n.serie_casa_marcat, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        ), nui = (
            SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        ), serie_memorie_fiscala = (
            SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        )
        WHERE fel_doc = 'BC'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_nota
                AND n.nr_raport_z = :bc_nr_z
                AND COALESCE(n.serie_casa_marcat, '') = :bc_series
                AND COALESCE(n.nui, 0) = :bc_nui
                AND COALESCE(n.serie_memorie_fiscala, '') = :bc_memory
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBC = $pdo->prepare($sqlBC);
    $stBC->execute([':bc_nr_z' => $user_nr_raport_z, ':bc_series' => $serie_casa_marcat, ':bc_nui' => $nui, ':bc_memory' => $serie_memorie_fiscala]);

    // 3) BT (bon transformare / producție): n.nrbon = m.nr_nota
    $sqlBT = "
        UPDATE miscari
        SET nr_raport_z = (
            SELECT n.nr_raport_z
            FROM note n
            WHERE n.nrbon = miscari.nr_nota
            LIMIT 1
        ), serie_casa_marcat = (
            SELECT COALESCE(n.serie_casa_marcat, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        ), nui = (
            SELECT COALESCE(n.nui, 0) FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        ), serie_memorie_fiscala = (
            SELECT COALESCE(n.serie_memorie_fiscala, '') FROM note n WHERE n.nrbon = miscari.nr_nota LIMIT 1
        )
        WHERE fel_doc = 'BT'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_nota
                AND n.nr_raport_z = :bt_nr_z
                AND COALESCE(n.serie_casa_marcat, '') = :bt_series
                AND COALESCE(n.nui, 0) = :bt_nui
                AND COALESCE(n.serie_memorie_fiscala, '') = :bt_memory
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBT = $pdo->prepare($sqlBT);
    $stBT->execute([':bt_nr_z' => $user_nr_raport_z, ':bt_series' => $serie_casa_marcat, ':bt_nui' => $nui, ':bt_memory' => $serie_memorie_fiscala]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Eroare non-fatală: scriem in error_log (sistem) si continuam
    error_log("WARNING - Eroare update miscari (vanzare_inchidere_zi.php): " . $e->getMessage());
}
// ============================================================================


// După finalizarea operațiunilor, redirecționează utilizatorul (sau afișează un mesaj de succes)
printf("<script>location.href='logout.php'</script>");  
exit();
?>
