<?php
// stornare_factura.php

include('header.php'); // Asigură-te că header.php include sesiunea și alte configurații necesare
require_once 'database_connection.php'; // Conectarea la baza de date

if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

// Verifică dacă utilizatorul este autentificat
if (!isset($_SESSION['admin_id'])) {
    echo "<script>alert('Vă rugăm să vă autentificați.'); window.location.href='login.php';</script>";
    exit;
}

// Obține seria de stornare din tabela date_firma
try {
    $stmt_firma = $pdo->prepare("SELECT serie_stornare FROM date_firma LIMIT 1");
    $stmt_firma->execute();
    $date_firma = $stmt_firma->fetch(PDO::FETCH_ASSOC);
    if (!$date_firma || empty($date_firma['serie_stornare'])) {
        throw new Exception('Seria de stornare nu este configurată.');
    }
    $serie_stornare = $date_firma['serie_stornare'];
} catch (Exception $e) {
    echo "<script>alert('A apărut o eroare la obținerea seriei de stornare: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    exit;
}

// Procesarea formularului de stornare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exec_stornare'])) {
    // Obține datele din formular
    $id_factura = $_POST['id_factura'];
    $motiv_stornare = trim($_POST['motiv_stornare']);
    $user_created = $_SESSION['admin_id'];

    // Validări
    if (empty($motiv_stornare)) {
        echo "<script>alert('Motivul stornării este obligatoriu.'); window.history.back();</script>";
        exit;
    }

    try {
        // Începe o tranzacție
        $pdo->beginTransaction();

        // Fetch the old invoice based on 'id_factura'
        $stmt = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $id_factura]);
        $old_factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_factura) {
            throw new Exception('Factura veche nu a fost găsită.');
        }

        // Fetch the old invoice based on 'id_factura'
$stmt = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id_factura");
$stmt->execute(['id_factura' => $id_factura]);
$old_factura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$old_factura) {
    throw new Exception('Factura veche nu a fost găsită.');
}

// Pregătește datele pentru noua factură (copie a celei vechi)
$new_factura = $old_factura;
$new_factura['data_incarcare']='0000-00-00 00:00:00';
$new_factura['data_validare']='0000-00-00 00:00:00';
$new_factura['nrbon']=0;
$new_factura['nr_nota']=0;
$new_factura['factura_restaurant']=0;
$new_factura['id_deviz']=0;
$new_factura['data_stornare']=null;
$new_factura['id_factura_stornare']=null;
        unset($new_factura['id_factura']); // Elimină 'id_factura' pentru a permite bazei de date să atribuie una nouă

        // Ajustează 'nr_factura', 'data_factura', 'data_scadenta', 'tip_factura', 'serie_factura'
// Obține ultimul număr de factură din tabelul facturi și setează noul număr ca fiind acel număr + 1
$stmt_last = $pdo->prepare('SELECT MAX(nr_factura) AS last_nr FROM facturi WHERE serie_factura=?');$stmt_last->execute([$serie_stornare]);
$result_last = $stmt_last->fetch(PDO::FETCH_ASSOC);
$last_nr = isset($result_last['last_nr']) ? $result_last['last_nr'] : 0;
$new_factura['nr_factura'] = $last_nr + 1;        $new_factura['data_factura'] = date('Y-m-d'); // Setează data curentă
        $new_factura['data_scadenta'] = date('Y-m-d'); // Ajustează după necesități
        $new_factura['tip_factura'] = 380; // Setează tipul facturii la 380 pentru stornare

        // Setează 'serie_factura' din 'date_firma.serie_stornare'
        $new_factura['serie_factura'] = $serie_stornare;

        // Construiește și execută INSERT-ul pentru 'facturi'
        $columns = array_keys($new_factura);
        $placeholders = ':' . implode(', :', $columns);
        $insert_factura_sql = "INSERT INTO facturi (" . implode(',', $columns) . ") VALUES ($placeholders)";
        $stmt_insert = $pdo->prepare($insert_factura_sql);
        $stmt_insert->execute($new_factura);

        // Obține noul 'id_factura' atribuit de baza de date
        $new_id_factura = $pdo->lastInsertId();

        // Fetch the 'vanzari' records associated with the old invoice
        $stmt = $pdo->prepare("SELECT * FROM vanzari WHERE id_factura = :id_factura");
        $stmt->execute(['id_factura' => $id_factura]);
        $vanzari_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pentru stornare integrala, inversăm toate cantitățile și valorile relevante
        foreach ($vanzari_records as $vanzare) {
            unset($vanzare['id_vanz']); // Elimină 'id_vanz' pentru a permite bazei de date să atribuie una nouă
            $vanzare['id_factura'] = $new_id_factura;
            $vanzare['nr_factura'] = $new_factura['nr_factura'];$vanzare['serie_factura']=$serie_stornare;

            if (isset($vanzare['cantitate'])) {
                $vanzare['cantitate'] = -abs($vanzare['cantitate']);
            }

            if (isset($vanzare['tva_col'])) {
                $vanzare['tva_col'] = -abs($vanzare['tva_col']);
            }

            if (isset($vanzare['valoare_vanzare'])) {
                $vanzare['valoare_vanzare'] = -abs($vanzare['valoare_vanzare']);
            }

            if (isset($vanzare['valoare_vanzare_cu_tva'])) {
                $vanzare['valoare_vanzare_cu_tva'] = -abs($vanzare['valoare_vanzare_cu_tva']);
            }

            // Construiește și execută INSERT-ul pentru 'vanzari'
            $columns_vanzare = array_keys($vanzare);
            $placeholders_vanzare = ':' . implode(', :', $columns_vanzare);
            $insert_vanzare_sql = "INSERT INTO vanzari (" . implode(',', $columns_vanzare) . ") VALUES ($placeholders_vanzare)";
            $stmt_vanzare = $pdo->prepare($insert_vanzare_sql);
            $stmt_vanzare->execute($vanzare);
        }

        // Înregistrează stornarea în tabela 'stornari'
        $insert_stornare_sql = "INSERT INTO stornari (id_factura, id_factura_stornata, data_stornare, motiv_stornare, user_created, created_at, updated_at) 
                                VALUES (:id_factura, :id_factura_stornata, :data_stornare, :motiv_stornare, :user_created, :created_at, :updated_at)";
        $stmt_stornare = $pdo->prepare($insert_stornare_sql);
        $stmt_stornare->execute([
            'id_factura' => $new_id_factura,
            'id_factura_stornata' => $id_factura,
            'data_stornare' => date('Y-m-d H:i:s'),
            'motiv_stornare' => $motiv_stornare,
            'user_created' => $user_created,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Comite tranzacția
        $pdo->commit();

        // Mesaj de succes și redirecționare către factura de stornare
        echo "<script>alert('Factura de stornare a fost creată cu succes.'); </script>";
        printf("<script>location.href='facturi.php'</script>");
        exit;


    } catch (Exception $e) {
        // Rulback în caz de eroare
        $pdo->rollBack();
        echo "<script>alert('A apărut o eroare: " . addslashes($e->getMessage()) . "');</script>";
        printf("<script>location.href='facturi.php'</script>");

        exit;
    }
}

?>
