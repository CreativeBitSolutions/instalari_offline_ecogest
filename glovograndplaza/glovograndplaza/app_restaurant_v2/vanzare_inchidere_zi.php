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

// Inserare raportul Z în tabela "rapoarte_z"
try {
    $insertRaportSql = "INSERT INTO rapoarte_z 
        (nr_raport_z, cod_locatie, serie_casa_marcat, numerar, card, credit, tichete_masa, tichete_valorice, plata_moderna, avans_in_numerar, alte_metode)
        VALUES (:nr_raport_z, :cod_locatie, :serie_casa_marcat, :numerar, :card, :credit, :tichete_masa, :tichete_valorice, :plata_moderna, :avans_in_numerar, :alte_metode)";
    $stmt = $pdo->prepare($insertRaportSql);
    $stmt->execute([
        'nr_raport_z'      => $user_nr_raport_z,
        'cod_locatie'      => $cod_locatie,
        'serie_casa_marcat'=> $serie_casa_marcat,
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
                      SET nr_raport_z = :nr_raport_z 
                      WHERE status = 'F' 
                        AND locatie = :locatie 
                        AND nr_raport_z = 0";
    $stmt = $pdo->prepare($updateNoteSql);
    $stmt->execute([
        'nr_raport_z' => $user_nr_raport_z,
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
                       AND nr_raport_z = :nr_raport_z";
    $stmt = $pdo->prepare($selectCodSql);
    $stmt->execute([
        'locatie'     => $cod_locatie,
        'nr_raport_z' => $user_nr_raport_z
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
                               SET nr_raport_z = ? 
                               WHERE cod_inchidere IN ($inClause)
                                 AND locatie = ?";
        // Parametrii: primul element este nr_raport_z, apoi lista de coduri, apoi cod_locatie
        $params = array_merge([$user_nr_raport_z], $cod_inchideri, [$cod_locatie]);
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
        )
        WHERE tip_miscare = 'O'
          AND fel_doc = 'BF'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_doc
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBF = $pdo->prepare($sqlBF);
    $stBF->execute();

    // 2) BC (consum): n.nrbon = m.nr_nota
    $sqlBC = "
        UPDATE miscari
        SET nr_raport_z = (
            SELECT n.nr_raport_z
            FROM note n
            WHERE n.nrbon = miscari.nr_nota
            LIMIT 1
        )
        WHERE fel_doc = 'BC'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_nota
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBC = $pdo->prepare($sqlBC);
    $stBC->execute();

    // 3) BT (bon transformare / producție): n.nrbon = m.nr_nota
    $sqlBT = "
        UPDATE miscari
        SET nr_raport_z = (
            SELECT n.nr_raport_z
            FROM note n
            WHERE n.nrbon = miscari.nr_nota
            LIMIT 1
        )
        WHERE fel_doc = 'BT'
          AND EXISTS (
              SELECT 1
              FROM note n
              WHERE n.nrbon = miscari.nr_nota
                AND miscari.nr_raport_z <> n.nr_raport_z
          )
    ";
    $stBT = $pdo->prepare($sqlBT);
    $stBT->execute();

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
