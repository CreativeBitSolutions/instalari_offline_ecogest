<?php
// update_factura.php
require_once __DIR__.'/database_connection.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Curăță textul pentru comparații fără diacritice.
 */
function cleanRomanianTextFactura($value) {
    $value = trim((string)$value);

    $value = str_replace(
        ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ', 'Ă', 'Â', 'Î', 'Ș', 'Ş', 'Ț', 'Ţ'],
        ['a', 'a', 'i', 's', 's', 't', 't', 'A', 'A', 'I', 'S', 'S', 'T', 'T'],
        $value
    );

    $value = strtoupper($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

/**
 * Verifică dacă județul este București.
 */
function esteJudetBucurestiFactura($judet) {
    $judet = cleanRomanianTextFactura($judet);

    if (strpos($judet, 'BUC') !== false) {
        return true;
    }

    if (strpos($judet, 'BUCURESTI') !== false) {
        return true;
    }

    if (strpos($judet, 'MUNICIPIUL BUCURESTI') !== false) {
        return true;
    }

    return false;
}

/**
 * Extrage sectorul din texte de tip:
 * Sector 3 Mun. București
 * Sectorul 3
 * SECTOR 3
 * SECTOR3
 * Sect. 3
 */
function extrageSectorFactura($localitate) {
    $localitate = cleanRomanianTextFactura($localitate);

    if ($localitate === '') {
        return '';
    }

    if (preg_match('/(?:^|[^A-Z])SECTOR(?:UL)?\.?\s*([1-6])(?:[^0-9]|$)/', $localitate, $m)) {
        return $m[1];
    }

    if (preg_match('/(?:^|[^A-Z])SECT\.?\s*([1-6])(?:[^0-9]|$)/', $localitate, $m)) {
        return $m[1];
    }

    if (preg_match('/^SECTOR([1-6])$/', $localitate, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Normalizează localitatea București în format SECTOR1...SECTOR6.
 */
function normalizeazaLocalitateBucurestiFactura($localitate, $judet = '') {
    $sector = extrageSectorFactura($localitate);

    if (!$sector && esteJudetBucurestiFactura($judet)) {
        $localitateCurata = cleanRomanianTextFactura($localitate);

        if (preg_match('/^[1-6]$/', $localitateCurata)) {
            $sector = $localitateCurata;
        }
    }

    if ($sector) {
        return 'SECTOR' . $sector;
    }

    return trim((string)$localitate);
}

// Obținem id_factura din datele POST
$id_factura = isset($_POST['id_factura']) ? intval($_POST['id_factura']) : 0;

if ($id_factura > 0) {
    // Obținem nr_factura și serie_factura din POST
    $nr_factura = isset($_POST['nr_factura']) ? $_POST['nr_factura'] : '';
    $serie_factura = isset($_POST['serie_factura']) ? $_POST['serie_factura'] : '';
    // ———————— default țară dacă nu a fost completată ————————
if (!isset($_POST['adresa_tara']) || trim($_POST['adresa_tara']) === '') {
    $_POST['adresa_tara'] = 'ROMANIA';
}

// Normalizează localitatea București înainte de orice salvare în baza de date
if (isset($_POST['adresa_localitate'])) {
    $judetPentruNormalizare = isset($_POST['adresa_judet']) ? $_POST['adresa_judet'] : '';
    $_POST['adresa_localitate'] = normalizeazaLocalitateBucurestiFactura($_POST['adresa_localitate'], $judetPentruNormalizare);
}

// Verificare obligatorie: dacă județul este București, localitatea trebuie să fie SECTOR1...SECTOR6
if (
    isset($_POST['adresa_judet']) &&
    isset($_POST['adresa_localitate']) &&
    esteJudetBucurestiFactura($_POST['adresa_judet'])
) {
    $localitateCheck = cleanRomanianTextFactura($_POST['adresa_localitate']);
    $localitateCheck = str_replace(' ', '', $localitateCheck);

    if (!preg_match('/^SECTOR[1-6]$/', $localitateCheck)) {
        echo json_encode([
            'success' => false,
            'error' => 'Pentru Municipiul București, localitatea trebuie să fie SECTOR1, SECTOR2, SECTOR3, SECTOR4, SECTOR5 sau SECTOR6.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_POST['adresa_localitate'] = $localitateCheck;
}


// Verificare obligatorie: numărul facturii trebuie să fie consecutiv, fără sărituri.
// Exemplu: dacă ultima factură este 1037, următoarea trebuie să fie 1038, nu 1039.
$nr_factura_int = intval($nr_factura);
if ($nr_factura_int < 1) {
    echo json_encode([
        'success' => false,
        'error' => 'Numărul facturii nu poate fi 0. Valoarea minimă permisă este 1.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalizăm valoarea salvată la număr întreg pozitiv.
$_POST['nr_factura'] = $nr_factura_int;
$nr_factura = $nr_factura_int;

if (trim((string)$serie_factura) !== '') {
    $current_factura_sql = "SELECT nr_factura, serie_factura FROM facturi WHERE id_factura = :id_factura LIMIT 1";
    $current_factura_stmt = $pdo->prepare($current_factura_sql);
    $current_factura_stmt->execute([':id_factura' => $id_factura]);
    $current_factura = $current_factura_stmt->fetch(PDO::FETCH_ASSOC);

    $este_acelasi_numar_deja_salvat = false;
    if ($current_factura) {
        $este_acelasi_numar_deja_salvat = (
            intval($current_factura['nr_factura']) === $nr_factura_int
            && (string)$current_factura['serie_factura'] === (string)$serie_factura
        );
    }

    if (!$este_acelasi_numar_deja_salvat) {
        $max_nr_sql = "SELECT MAX(CAST(nr_factura AS INTEGER)) AS max_nr
                       FROM facturi
                       WHERE serie_factura = :serie_factura
                         AND id_factura != :id_factura
                         AND nr_factura REGEXP '^[0-9]+$'";
        $max_nr_stmt = $pdo->prepare($max_nr_sql);
        $max_nr_stmt->execute([
            ':serie_factura' => $serie_factura,
            ':id_factura' => $id_factura
        ]);
        $max_nr_row = $max_nr_stmt->fetch(PDO::FETCH_ASSOC);
        $max_nr = isset($max_nr_row['max_nr']) ? intval($max_nr_row['max_nr']) : 0;

        if ($max_nr > 0) {
            $nr_factura_corect = $max_nr + 1;
        } else {
            $nr_inceput_sql = "SELECT nr_inceput FROM casa_online_series WHERE serie = :serie_factura LIMIT 1";
            $nr_inceput_stmt = $pdo->prepare($nr_inceput_sql);
            $nr_inceput_stmt->execute([':serie_factura' => $serie_factura]);
            $nr_inceput_row = $nr_inceput_stmt->fetch(PDO::FETCH_ASSOC);
            $nr_inceput = $nr_inceput_row ? intval($nr_inceput_row['nr_inceput']) : 1;
            $nr_factura_corect = max(1, $nr_inceput);
        }

        if ($nr_factura_corect > 0 && $nr_factura_int !== $nr_factura_corect) {
            echo json_encode([
                'success' => false,
                'error' => 'Numărul facturii trebuie să fie consecutiv. Următorul număr permis pentru seria ' . $serie_factura . ' este ' . $nr_factura_corect . ', nu ' . $nr_factura_int . '. Exemplu: dacă ultima factură este 1037, următoarea trebuie să fie 1038, nu 1039.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$data_factura=isset($_POST['data_factura']) ? $_POST['data_factura'] : '0000-00-00';
    // Verificăm dacă există deja o factură cu același nr_factura și serie_factura, dar cu un id_factura diferit
    $check_sql = "SELECT COUNT(*) FROM facturi WHERE nr_factura = :nr_factura AND serie_factura = :serie_factura AND id_factura != :id_factura";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':nr_factura', $nr_factura);
    $check_stmt->bindValue(':serie_factura', $serie_factura);
    $check_stmt->bindValue(':id_factura', $id_factura);
    $check_stmt->execute();
    $exists = $check_stmt->fetchColumn();

    if ($exists > 0) {
        // Dacă există deja o astfel de factură, returnăm un mesaj de eroare
        echo json_encode(['success' => false, 'error' => 'Există deja o factură cu acest număr și serie.']);
    } else {
        // Începem tranzacția pentru a asigura integritatea operațiunilor
        try {
            $pdo->beginTransaction();
// Dacă s-au trimis date pentru delegat, le combinăm într-un array asociativ și le codăm în JSON
if (
    isset($_POST['delegat_nume']) ||
    isset($_POST['delegat_ci_serie']) ||
    isset($_POST['delegat_ci_numar']) ||
    isset($_POST['delegat_masina']) ||
    isset($_POST['delegat_semnatura'])
) {
    $delegat_data = [
        'nume'       => $_POST['delegat_nume'] ?? '',
        'ci_serie'   => $_POST['delegat_ci_serie'] ?? '',
        'ci_numar'   => $_POST['delegat_ci_numar'] ?? '',
        'masina'     => $_POST['delegat_masina'] ?? '',
        'semnatura'  => $_POST['delegat_semnatura'] ?? ''
    ];
    // Codificăm datele în format JSON
    $_POST['delegat'] = json_encode($delegat_data, JSON_UNESCAPED_UNICODE);
    // Ștergem câmpurile individuale pentru delegat pentru a nu încerca actualizarea unor coloane inexistente
    unset($_POST['delegat_nume'], $_POST['delegat_ci_serie'], $_POST['delegat_ci_numar'], $_POST['delegat_masina'], $_POST['delegat_semnatura']);
}

            // Obținem datele postate și pregătim SQL-ul pentru actualizare
            $fields = [];
            $params = [];
            foreach ($_POST as $key => $value) {
                if ($key != 'id_factura' && in_array($key, array_column($pdo->query("PRAGMA table_info('facturi')")->fetchAll(PDO::FETCH_ASSOC),'name'),true) && !in_array($key,['data_validare','data_incarcare','data_corectare','id_factura_stornare'],true) && !is_array($value)) {
                    $fields[] = "`$key` = :$key";
                    $params[":$key"] = $value;
                }
            }
            $fields_list = implode(", ", $fields);
            $params[':id_factura'] = $id_factura;

            $update_sql = "UPDATE facturi SET $fields_list WHERE id_factura = :id_factura";

            $update_stmt = $pdo->prepare($update_sql);
            foreach ($params as $param => $value) {
                $update_stmt->bindValue($param, $value);
            }
            $update_stmt->execute();

            // După actualizarea facturii, gestionăm inserarea sau actualizarea clientului
            // Obținem cod_fiscal din POST
            $cod_fiscal = isset($_POST['cod_fiscal']) ? $_POST['cod_fiscal'] : '';

            if (!empty($cod_fiscal)) {
                // Verificăm dacă există deja un client cu acest cod_fiscal
                $client_check_sql = "SELECT id_client FROM clienti WHERE cod_fiscal = :cod_fiscal";
                $client_check_stmt = $pdo->prepare($client_check_sql);
                $client_check_stmt->bindValue(':cod_fiscal', $cod_fiscal);
                $client_check_stmt->execute();
                $existing_client = $client_check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_client) {
                    // Dacă clientul există, pregătim actualizarea datelor
                    $id_client = $existing_client['id_client'];

                    // Obținem datele postate și pregătim SQL-ul pentru actualizare
                    $client_fields = [];
                    $client_params = [];
                    foreach ($_POST as $key => $value) {
                        if ($key != 'id_client' && array_key_exists($key, getClientColumns($pdo))) {
                            $client_fields[] = "`$key` = :$key";
                            $client_params[":$key"] = $value;
                        }
                    }
                    $client_fields_list = implode(", ", $client_fields);
                    $client_params[':id_client'] = $id_client;

                    $client_update_sql = "UPDATE clienti SET $client_fields_list WHERE id_client = :id_client";

                    $client_update_stmt = $pdo->prepare($client_update_sql);
                    foreach ($client_params as $param => $value) {
                        $client_update_stmt->bindValue($param, $value);
                    }
                    $client_update_stmt->execute();
                } else {
                    // Dacă clientul nu există, pregătim inserarea unui nou client

                    // Obținem datele postate și pregătim SQL-ul pentru inserare
                    $client_columns = [];
                    $client_placeholders = [];
                    $client_params = [];
                    foreach ($_POST as $key => $value) {
                        if (array_key_exists($key, getClientColumns($pdo))) {
                            $client_columns[] = "`$key`";
                            $client_placeholders[] = ":$key";
                            $client_params[":$key"] = $value;
                        }
                    }
                    $client_columns_list = implode(", ", $client_columns);
                    $client_placeholders_list = implode(", ", $client_placeholders);

                    $client_insert_sql = "INSERT INTO clienti ($client_columns_list) VALUES ($client_placeholders_list)";

                    $client_insert_stmt = $pdo->prepare($client_insert_sql);
                    foreach ($client_params as $param => $value) {
                        $client_insert_stmt->bindValue($param, $value);
                    }
                    $client_insert_stmt->execute();

                    // Obținem ID-ul noului client inserat
                    $id_client = $pdo->lastInsertId();
                }

                // Actualizăm `id_client` în tabela `facturi`
                $update_factura_client_sql = "UPDATE facturi SET id_client = :id_client WHERE id_factura = :id_factura";
                $update_factura_client_stmt = $pdo->prepare($update_factura_client_sql);
                $update_factura_client_stmt->bindValue(':id_client', $id_client);
                $update_factura_client_stmt->bindValue(':id_factura', $id_factura);
                $update_factura_client_stmt->execute();

              
$update_vanzari_sql = "UPDATE vanzari SET nr_factura = :nr_factura, serie_factura = :serie_factura WHERE id_factura = :id_factura";
$update_vanzari_stmt = $pdo->prepare($update_vanzari_sql);
$update_vanzari_stmt->bindValue(':nr_factura', $nr_factura);
$update_vanzari_stmt->bindValue(':serie_factura', $serie_factura);
$update_vanzari_stmt->bindValue(':id_factura', $id_factura);
$update_vanzari_stmt->execute();

// Selectăm toate id_vanz din vanzari aferente acestei facturi
$selectVanzari = "SELECT id_vanz FROM vanzari WHERE id_factura = :id_factura";
$stmtSelectVanzari = $pdo->prepare($selectVanzari);
$stmtSelectVanzari->execute([':id_factura' => $id_factura]);
$vanzariIds = $stmtSelectVanzari->fetchAll(PDO::FETCH_COLUMN);

if (!empty($vanzariIds)) {
    // Actualizăm nr_doc în tabela miscari pentru fiecare id_vanz găsit, doar unde fel_doc = 'FAC'
    $updateMiscari = "UPDATE miscari 
                      SET nr_doc = :nr_factura,data=:data_factura 
                      WHERE id_vanz_fact IN (" . implode(',', array_map('intval', $vanzariIds)) . ")
                      AND fel_doc = 'FAC'";
    $stmtMiscari = $pdo->prepare($updateMiscari);
    $stmtMiscari->execute([
        ':nr_factura' => $nr_factura,
        ':data_factura' => $data_factura
    ]);
    

    
}


            }

            // Commit tranzacția
            $pdo->commit();
            $_SESSION['factura_actualizata']=1;
            // Returnăm răspunsul de succes pentru actualizarea facturii (și implicit pentru client)
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // Rollback tranzacția în caz de eroare
            $pdo->rollBack();
            // Logăm eroarea și returnăm răspunsul de eroare
            file_put_contents('error_log.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'A apărut o eroare. Verifică logul pentru detalii.']);
        }
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID Factură invalid.']);
}

/**
 * Funcție pentru a obține coloanele din tabela `clienti`
 * Acest lucru este util pentru a asigura că doar coloanele existente sunt utilizate
 */
function getClientColumns($pdo) {
    static $columns = null;
    if ($columns === null) {
        $stmt = $pdo->prepare("SELECT name AS Field FROM pragma_table_info('clienti')");
        $stmt->execute();
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = true;
        }
    }
    return $columns;
}
?>
