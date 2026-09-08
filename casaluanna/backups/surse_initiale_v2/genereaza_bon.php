<?php
session_start();

// Include conexiunea la baza de date
include('database_connection.php');

// Setează fusul orar la Europe/Bucharest
date_default_timezone_set('Europe/Bucharest');

// Verifică dacă `id_factura` este setat în GET și este numeric
if (!isset($_GET['id_factura']) || !is_numeric($_GET['id_factura'])) {
    die("Parametru invalid.");
}

$id_factura = (int)$_GET['id_factura'];
//Redirecționare specială pentru client_id = 20
if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == 20) {
    header("Location: genereaza_bon_dep_casa_personalizat.php?id_factura=" . urlencode($id_factura));
    exit();
}
// Definirea caracterului de sfârșit de linie
$cr = chr(13) . chr(10);

// Verifică dacă este cerere de descărcare
if (isset($_GET['download']) && $_GET['download'] == '1') {
    if (!isset($_SESSION['bon_content'][$id_factura])) {
        die("Bonul nu a fost generat sau a expirat.");
    }
    $myBuffer = $_SESSION['bon_content'][$id_factura];
    // Generează un nume unic pentru fișierul bonului
    $timestamp = date('Ymd_His'); // Format: YYYYMMDD_HHMMSS
    $filename = "bon_factura_{$id_factura}_{$timestamp}.txt";

    // Setează headerele pentru descărcare
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Content-Type: text/plain");
    header("Content-Length: " . strlen($myBuffer));
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    header("Expires: 0");

    echo $myBuffer;
    // După descărcare, puteți elimina bonul din sesiune dacă doriți
    // unset($_SESSION['bon_content'][$id_factura]);
    exit();
}

// Verifică dacă formularul a fost trimis (metoda POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preluarea metodelor de plată selectate
    if (!isset($_POST['metode_plata']) || !is_array($_POST['metode_plata'])) {
        die("Trebuie să selectați cel puțin o metodă de plată.");
    }

    $metode_plata = $_POST['metode_plata']; // Array de array-uri: ['tip' => '0', 'valoare' => '19.50']

    // Validare metode de plată
    $valid_metode = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    foreach ($metode_plata as $metoda) {
        if (!in_array($metoda['tip'], $valid_metode)) {
            die("Metodă de plată invalidă.");
        }
        if (!is_numeric($metoda['valoare']) || $metoda['valoare'] < 0) {
            die("Valoare de plată invalidă.");
        }
    }

    // Preluarea noilor inputuri pentru CUI și opțiunea de a elimina CUI
    $sterge_cui = isset($_POST['sterge_cui']) ? true : false;
    $cui_input = isset($_POST['cui']) ? trim($_POST['cui']) : '';

    // Pregătirea buffer-ului pentru bon
    $K = "K,1,______,_,__;";
    $myBuffer = "";

    // Pregătirea interogării pentru factura specificată
    $factura_sql = "SELECT * FROM facturi WHERE id_factura = :id_factura LIMIT 1;";
    $factura_stmt = $pdo->prepare($factura_sql);
    $factura_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $factura_stmt->execute();
    $factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        die("Factura nu a fost găsită.");
    }

    // Verifică dacă există un cod fiscal pentru client
    $cod_fiscal_client = isset($factura['cod_fiscal']) ? trim($factura['cod_fiscal']) : '';

    if (!$sterge_cui && $cui_input !== '') {
        // Include K linie doar dacă nu se dorește ștergerea CUI și CUI este prezent
        $K .= $cui_input;
        $myBuffer .= $K . $cr;
    }

    // Obține mapping-ul cota_tva -> cod_listare_cota_casa din coduri_casa_tva
    $dep_tva_sql = "SELECT cota_tva, cod_listare_cota_casa FROM coduri_casa_tva";
    $dep_tva_stmt = $pdo->prepare($dep_tva_sql);
    $dep_tva_stmt->execute();
    $coduri_casa_tva_map = [];
    while ($dep_tva = $dep_tva_stmt->fetch(PDO::FETCH_ASSOC)) {
        $coduri_casa_tva_map[$dep_tva['cota_tva']] = $dep_tva['cod_listare_cota_casa'];
    }

    // Pregătirea interogării pentru vânzările asociate facturii
    $vanzari_sql = "
        SELECT 
            den_p, um, cantitate, pret_vanzare, cota_tva, 
            valoare_vanzare, valoare_vanzare_cu_tva, discount, id_vanz
        FROM vanzari 
        WHERE id_factura = :id_factura;
    ";
    $vanzari_stmt = $pdo->prepare($vanzari_sql);
    $vanzari_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $vanzari_stmt->execute();

    // Variabile suplimentare
    $total_vanzari_cu_tva = 0;
    $total_discount = 0;

    // Iterarea prin fiecare vânzare pentru a construi liniile de tip S
    while ($row = $vanzari_stmt->fetch(PDO::FETCH_ASSOC)) {
        // 2. Denumirea produsului (max 72 caractere, fără ;)
        $den_p = substr(str_replace(';', '', trim($row['den_p'])), 0, 72);

        // 3. Pretul produsului (fără TVA)
        $pret_vanzare = number_format($row['pret_vanzare'], 2, '.', '');

        // 4. Cantitatea vândută (max 3 zecimale)
        $cantitate = number_format($row['cantitate'], 3, '.', '');

        // 7. Codul cotei TVA (1,2,3,5)
        $cota_tva_vanzare = $row['cota_tva'];
        $cod_listare_cota_casa = isset($coduri_casa_tva_map[$cota_tva_vanzare]) ? $coduri_casa_tva_map[$cota_tva_vanzare] : 0;

        // Regula specială pentru clientul 19 și den_p = "MASA SERVITA 11%"
        if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == 19 && trim($row['den_p']) === "MASA SERVITA 11%") {
            $cod_listare_cota_casa = 4;
        }

        // 5. Codul departamentului (presupunem 1 dacă nu există departament specific)
        $cod_departament = 1;

        // 10. Unitatea de măsură (max 6 caractere, default "buc")
        $um = substr($row['um'], 0, 6) ?: 'buc';
        if (isset($_SESSION['client_id'])) {
            $client_id = $_SESSION['client_id'];
            if($client_id==4){
                $um='';
            }
        }
        // Calcul discount per produs
        $discount = isset($row['discount']) ? $row['discount'] : 0;
        $total_discount += $discount;

        // Construirea liniei S
        // Format: S,1,______,_,__;Denumire Produs;Pret Fara TVA;Cantitate;Cod Departament;Ignorat;Cod TVA;Ignorat;Ignorat;Unitate
        $myBuffer .= "S,1,______,_,__;$den_p;$pret_vanzare;$cantitate;$cod_departament;1;$cod_listare_cota_casa;0;0;$um" . $cr;

        $total_vanzari_cu_tva += $row['valoare_vanzare_cu_tva'];
    }

    // Adăugarea discount-ului dacă este aplicabil
    if ($total_discount > 0) {
        // Format: C,1,______,_,__;Tip Discount;Valoare Discount;;;;;
        $myBuffer .= "C,1,______,_,__;1;" . number_format($total_discount, 2, '.', '') . ";;;;" . $cr;
    }

    // Calcularea totalului de plată
    $total_plata = $total_vanzari_cu_tva - $total_discount;
    $total_plata_formatted = number_format($total_plata, 2, '.', '');

    // Gestionarea metodelor de plată
    $buffer_plata = "";
    $total_metode_plata = 0;

    foreach ($metode_plata as $metoda) {
        $tip = $metoda['tip'];
        $valoare = number_format($metoda['valoare'], 2, '.', '');
        $buffer_plata .= "T,1,______,_,__;$tip;$valoare;;;;" . $cr;
        $total_metode_plata += floatval($valoare);
    }

    // Verificarea dacă totalul metodelor de plată acoperă totalul bonului
    if ($total_metode_plata < $total_plata) {
        // Adăugăm numerar pentru diferență
        $diferenta = number_format($total_plata - $total_metode_plata, 2, '.', '');
        $buffer_plata .= "T,1,______,_,__;0;$diferenta;;;;" . $cr;
    } elseif ($total_metode_plata > $total_plata) {
        // Adăugăm o linie de rest numerar
        $diferenta = number_format($total_metode_plata - $total_plata, 2, '.', '');
        if (floatval($diferenta) > 1) {
            $buffer_plata .= "R,1,______,_,__;0;$diferenta;;;;" . $cr;
        }
    }

    // Adăugarea liniilor de plată în buffer
    $myBuffer .= $buffer_plata;
    // Dacă clientul e 4, pune linia suplimentară T la final
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == 4) {
        $myBuffer .= "T,1,______,_,__;" . $cr;
    }

    // VERIFICARE: Dacă nu s-a confirmat încă trimiterea, se afișează modalul pentru confirmare
    if (!isset($_POST['confirm_send'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Confirmare Trimitere Bon Fiscal</title>
            <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
            <style>
                /* Asigură-te că modalul este vizibil peste tot */
                .modal-backdrop {
                    opacity: 0.5 !important;
                }
                /* Adaugă stilul pentru a permite scroll-ul în pre */
                .modal-body pre {
                    max-height: 400px;
                    overflow-y: auto;
                }
            </style>
        </head>
        <body>
            <div class="modal show" tabindex="-1" role="dialog" id="confirmModal" style="display:block;">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirmare Trimitere Bon Fiscal</h5>
                        </div>
                        <div class="modal-body">
                            <p>Acesta este conținutul bonului generat pentru trimiterea la casa de marcat:</p>
                            <pre style="background-color:#f8f9fa; padding:10px; border:1px solid #ddd;"><?php echo htmlspecialchars($myBuffer); ?></pre>
                        </div>
                        <div class="modal-footer">
                            <form method="POST" action="genereaza_bon.php?id_factura=<?php echo urlencode($id_factura); ?>">
                                <?php
                                // Re-includem toate datele POST originale ca câmpuri ascunse
                                if(isset($_POST['metode_plata']) && is_array($_POST['metode_plata'])) {
                                    foreach($_POST['metode_plata'] as $index => $metoda) {
                                        echo '<input type="hidden" name="metode_plata['.$index.'][tip]" value="'.htmlspecialchars($metoda['tip']).'">';
                                        echo '<input type="hidden" name="metode_plata['.$index.'][valoare]" value="'.htmlspecialchars($metoda['valoare']).'">';
                                    }
                                }
                                if(isset($_POST['cui'])) {
                                    echo '<input type="hidden" name="cui" value="'.htmlspecialchars($_POST['cui']).'">';
                                }
                                if(isset($_POST['sterge_cui'])) {
                                    echo '<input type="hidden" name="sterge_cui" value="'.htmlspecialchars($_POST['sterge_cui']).'">';
                                }
                                // Adăugăm câmpul care marchează confirmarea
                                ?>
                                <input type="hidden" name="confirm_send" value="1">
                                <button type="submit" class="btn btn-primary">Confirmă Trimiterea</button>
                                <a href="factura.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-secondary">Anulează</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
            <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
        </body>
        </html>
        <?php
        exit();
    }
    // FIN bloc confirmare

    date_default_timezone_set("Europe/Bucharest");
    // --- Începutul inserției în tabela bonuri_casa_marcat ---
    try {
        // Pregătește SQL-ul de inserție
        $insert_sql = "INSERT INTO bonuri_casa_marcat (data, ora, continut_bon, de_trimis_la_casa_marcat, id_factura, locatie)
                         VALUES (:data, :ora, :continut_bon, :de_trimis, :id_factura, :locatie)";

        $insert_stmt = $pdo->prepare($insert_sql);

        // Obține data și ora curentă
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');

        // Setează valoarea pentru 'de_trimis_la_casa_marcat'
        $de_trimis = 1; 
        $cod_locatie = 1;
        // Execută inserția
        $insert_stmt->execute([
            ':data'       => $current_date,
            ':ora'        => $current_time,
            ':continut_bon'   => $myBuffer,
            ':de_trimis'      => $de_trimis,
            ':id_factura'     => $id_factura,
            ':locatie'        => $cod_locatie
        ]);
    } catch (PDOException $e) {
        // Loghează eroarea fără a o afișa utilizatorului
        error_log("Eroare la inserția bonului: " . $e->getMessage());
        // Poți adăuga alte acțiuni aici, dacă este necesar
    }

    // *** NOU: Salvează datele din tabela bonuri_casa_marcat într-un fișier JSON ***
    if (isset($_SESSION['client_id'])) {
        $client_id = $_SESSION['client_id'];
        
        // Preluăm din baza de date datele din tabela bonuri_casa_marcat pentru factura curentă
        $select_sql = "SELECT * FROM bonuri_casa_marcat WHERE id_factura = :id_factura AND de_trimis_la_casa_marcat = 1 and locatie=:cod_locatie";
        $select_stmt = $pdo->prepare($select_sql);
        $select_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $select_stmt->bindParam(':cod_locatie', $cod_locatie, PDO::PARAM_INT);
        $select_stmt->execute();
        $bons = $select_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construim structura JSON dorită
        $json_array = [
            "status"  => "success",
            "message" => "Bonuri preluate cu succes.",
            "data"    => $bons
        ];
        $json_data = json_encode($json_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Preluăm valoarea pentru 'locatie' din primul element (dacă există)
        if (!empty($bons) && isset($bons[0]['locatie'])) {
            $locatie_val = $bons[0]['locatie'];
        } else {
            $locatie_val = "default";
        }
        
        // Creează calea către folderul: api/{client_id}/{locatie}
        $folder_path = __DIR__ . "/api/" . $client_id . "/" . $locatie_val;
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }

        // Salvăm fișierul JSON în folderul specific
        $json_file_path = $folder_path . "/bon_casa_marcat.json";
        file_put_contents($json_file_path, $json_data);

        // --- Nou: Update în baza de date pentru a seta de_trimis_la_casa_marcat la 0 ---
        $update_sql = "UPDATE bonuri_casa_marcat 
                          SET de_trimis_la_casa_marcat = 0 
                          WHERE id_factura = :id_factura AND de_trimis_la_casa_marcat = 1";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
        $update_stmt->execute();
    } else {
        error_log("Client_id nu este setat în sesiune.");
    }

    // Salvează bonul fiscal în sesiune pentru descărcare ulterioară
    $_SESSION['bon_content'][$id_factura] = $myBuffer;

    // Răspunsul final: se afișează o pagină care inițiază descărcarea bonului și redirecționarea
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Bon Fiscal Generat</title>
    </head>
    <body>
        <script>
            alert('Bonul fiscal a fost trimis la casa de marcat.');
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = 'genereaza_bon.php?id_factura=<?php echo $id_factura; ?>&download=1';
            document.body.appendChild(iframe);
            setTimeout(function(){
                window.location.href = 'factura.php?id_factura=<?php echo $id_factura; ?>';
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit();
} else {
    // Dacă metoda este GET, afișăm formularul pentru selectarea metodelor de plată și CUI

    // Pregătirea interogării pentru factura specificată
    $factura_sql = "SELECT * FROM facturi WHERE id_factura = :id_factura LIMIT 1;";
    $factura_stmt = $pdo->prepare($factura_sql);
    $factura_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $factura_stmt->execute();
    $factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        die("Factura nu a fost găsită.");
    }
    // Actualizare tip_factura în tabelul facturi
    $update_factura_sql = "UPDATE facturi SET tip_factura = 751 WHERE id_factura = :id_factura";
    $update_factura_stmt = $pdo->prepare($update_factura_sql);
    $update_factura_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $update_factura_stmt->execute();

    // Pregătirea interogării pentru vânzările asociate facturii
    $vanzari_sql = "SELECT * FROM vanzari WHERE id_factura = :id_factura;";
    $vanzari_stmt = $pdo->prepare($vanzari_sql);
    $vanzari_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
    $vanzari_stmt->execute();
    $vanzari = $vanzari_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul total vânzări cu TVA și total discount
    $total_plata = 0;
    $total_discount = 0;

    foreach ($vanzari as $vanzare) {
        $total_plata += $vanzare['valoare_vanzare_cu_tva'];
        $total_discount += $vanzare['discount'];
    }

    $total_plata_final = $total_plata - $total_discount;
    $total_plata_formatted = number_format($total_plata_final, 2, '.', '');

    // Obține codul fiscal client pentru a precompleta în form
    $cod_fiscal_client = isset($factura['cod_fiscal']) ? trim($factura['cod_fiscal']) : '';
    $nr_factura = isset($factura['nr_factura']) ? trim($factura['nr_factura']) : '';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Generare Bon Fiscal</title>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .produs-table th, .produs-table td {
                text-align: center;
            }
        </style>
    </head>
    <body>
    <div class="container mt-5">
        <h2>Generare Bon Fiscal pentru Factura #<?php echo htmlspecialchars($nr_factura); ?></h2>
        <p>Total de plată: <strong><?php echo $total_plata_formatted; ?> RON</strong></p>

        <h4>Produse pe Factură:</h4>
        <table class="table table-bordered produs-table">
            <thead>
                <tr>
                    <th>Nr.Crt.</th>
                    <th>Denumire Produs</th>
                    <th>U.M.</th>
                    <th>Cantitate</th>
                    <th>P.U. (cu TVA)</th>
                    <th>Cota TVA</th>
                    <th>Valoare (fără TVA)</th>
                    <th>TVA</th>
                    <th>Valoare (cu TVA)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $nr_crt = 1;
                foreach ($vanzari as $vanzare) {
                    $den_p = htmlspecialchars($vanzare['den_p']);
                    $um = htmlspecialchars($vanzare['um']);
                    $cantitate = number_format($vanzare['cantitate'], 3, '.', '');
                    $pret_cu_tva = number_format($vanzare['pret_vanzare'], 2, '.', '');
                    $cota_tva_vanzare = number_format($vanzare['cota_tva'], 2, '.', '');
                    $valoare_fara_tva = number_format($vanzare['valoare_vanzare'], 2, '.', '');
                    $tva_col = number_format($vanzare['tva_col'], 2, '.', '');
                    $valoare_cu_tva = number_format($vanzare['valoare_vanzare_cu_tva'], 2, '.', '');
                    echo "
                    <tr>
                        <td>{$nr_crt}</td>
                        <td>{$den_p}</td>
                        <td>{$um}</td>
                        <td>{$cantitate}</td>
                        <td>{$pret_cu_tva}</td>
                        <td>{$cota_tva_vanzare}%</td>
                        <td>{$valoare_fara_tva}</td>
                        <td>{$tva_col}</td>
                        <td>{$valoare_cu_tva}</td>
                    </tr>";
                    $nr_crt++;
                }
                ?>
            </tbody>
        </table>

        <form method="POST" action="genereaza_bon.php?id_factura=<?php echo urlencode($id_factura); ?>">
            <div class="form-group">
                <label for="metode_plata">Selectați metodele de plată:</label>
                <div id="metode_plata_container">
                    <div class="form-row align-items-end metode_plata">
                        <div class="col">
                            <label for="metode_plata_tip_1">Tipul metodei de plată:</label>
                            <select name="metode_plata[0][tip]" class="form-control" required>
                                <option value="">Selectați</option>
                                <option value="0">NUMERAR</option>
                                <option value="1">CARD</option>
                                <option value="2">CREDIT</option>
                                <option value="3">TICHETE MASA</option>
                                <option value="4">TICHETE VALORICE</option>
                                <option value="5">VOUCHER</option>
                                <option value="6">PLATA MODERNA</option>
                                <option value="7">CARD + AVANS IN NUMERAR</option>
                                <option value="8">ALTE METODE</option>
                                <option value="9">Monedă străină</option>
                            </select>
                        </div>
                        <div class="col">
                            <label for="metode_plata_valoare_1">Valoare:</label>
                            <input type="number" step="0.01" name="metode_plata[0][valoare]" class="form-control" required value="<?php echo $total_plata_formatted; ?>">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success add_metoda">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="cui">CUI Client:</label>
                <input type="text" name="cui" id="cui" class="form-control" value="<?php echo htmlspecialchars($cod_fiscal_client); ?>">
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="sterge_cui" name="sterge_cui" value="1">
                <label class="form-check-label" for="sterge_cui">Șterge CUI-ul de pe bon fiscal</label>
            </div>
        <p>Asigurați-vă că există o conexiune între casa de marcat și PC (verificați Fisco/FiscalWire). Dacă nu există apăsați tasta 6 (pentru modelele DATECS)<p>
            <button type="submit" class="btn btn-primary">Generează Bon Fiscal</button>
            <a href="detalii_factura.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-secondary">Înapoi</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function(){
        var metodaIndex = 1;
        $('.add_metoda').click(function(){
            var newMetoda = `
                <div class="form-row align-items-end metode_plata mt-2">
                    <div class="col">
                        <label for="metode_plata_tip_${metodaIndex}">Tipul metodei de plată:</label>
                        <select name="metode_plata[${metodaIndex}][tip]" class="form-control" required>
                            <option value="">Selectați</option>
                            <option value="0">NUMERAR</option>
                            <option value="1">CARD</option>
                            <option value="2">CREDIT</option>
                            <option value="3">TICHETE MASA</option>
                            <option value="4">TICHETE VALORICE</option>
                            <option value="5">VOUCHER</option>
                            <option value="6">PLATA MODERNA</option>
                            <option value="7">CARD + AVANS IN NUMERAR</option>
                            <option value="8">ALTE METODE</option>
                            <option value="9">Monedă străină</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="metode_plata_valoare_${metodaIndex}">Valoare:</label>
                        <input type="number" step="0.01" name="metode_plata[${metodaIndex}][valoare]" class="form-control" required value="">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-danger remove_metoda">-</button>
                    </div>
                </div>
            `;
            $('#metode_plata_container').append(newMetoda);
            metodaIndex++;
        });
        
        $(document).on('click', '.remove_metoda', function(){
            $(this).closest('.metode_plata').remove();
        });
    });
    </script>
    
    <script>
    $(document).ready(function(){
        $('form').on('submit', function(e){
            var totalPlata = parseFloat("<?php echo $total_plata_formatted; ?>");
            var sumaPlati = 0;
            $('input[name^="metode_plata"][name$="[valoare]"]').each(function(){
                var valoare = parseFloat($(this).val());
                if(!isNaN(valoare)){
                    sumaPlati += valoare;
                }
            });

            if(sumaPlati < totalPlata){
                alert('Suma totală a metodelor de plată este mai mică decât totalul de plată.');
                e.preventDefault();
            } else if(sumaPlati > totalPlata){
                if(!confirm('Suma totală a metodelor de plată depășește totalul de plată. Doriți să continuați?')) {
                    e.preventDefault();
                }
            }
        });
    });
    </script>
    
    </body>
    </html>
    <?php
    exit();
}
?>