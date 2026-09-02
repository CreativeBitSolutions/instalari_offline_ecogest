<?php ini_set('display_errors', 0); // Nu afișează erorile utilizatorului
ini_set('log_errors', 1); // Activează logarea erorilor
ini_set('error_log', 'error_log.log'); // Specifică calea către fișierul de log
error_reporting(E_ALL); // Raportează toate tipurile de erori
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Sesiune invalidă.');
}

include 'db.php';?>
<?php // Funcție pentru recalcularea și actualizarea totalurilor în tabela NIR
function actualizeaza_nir($pdo, $nr_nir) {
    // Sumați valorile din tabela achizitii pentru acest nr_nir
    $sql_sum = "
        SELECT 
            SUM(valoare_achizitie) AS total_valoare_fara_tva,
            SUM(tva_adaos_comercial) AS total_tva_neex,
            SUM(valoare_tva_achizitie) AS total_tva_ded,
            SUM(valoare_adaos) AS total_ad_unit,
            SUM(valoare_adaos) AS total_valoare_adaos,
            SUM(tva_adaos_comercial) AS total_tva_neex_ad_com
        FROM achizitii
        WHERE nr_nir = :nr_nir
    ";
    $stmt_sum = $pdo->prepare($sql_sum);
    $stmt_sum->execute(['nr_nir' => $nr_nir]);
    $sum_data = $stmt_sum->fetch(PDO::FETCH_ASSOC);

    // Calculați valorile totale
    $total_valoare_fara_tva = $sum_data['total_valoare_fara_tva'] ?? 0;
    $total_tva_neex = $sum_data['total_tva_neex'] ?? 0;
    $total_tva_ded = $sum_data['total_tva_ded'] ?? 0;
    $total_ad_unit = $sum_data['total_ad_unit'] ?? 0;
    $total_valoare_adaos = $sum_data['total_valoare_adaos'] ?? 0;
    $total_tva_neex_ad_com = $sum_data['total_tva_neex_ad_com'] ?? 0;

    // Calculați valoarea totală fără TVA
    $valoare_totala = $total_valoare_fara_tva + $total_ad_unit;

    // Calculați valoarea totală cu TVA
    $valoare_totala_cu_tva = $valoare_totala + $total_tva_neex_ad_com;

    // Actualizați tabela NIR
    $sql_update_nir = "
        UPDATE nir SET
            ad_com_total = :ad_com_total,
            tva_neex_ad_com = :tva_neex_ad_com,
            tva_ded = :tva_ded,
            val_nir_ftva = :val_nir_ftva,
            valoare_totala = :valoare_totala,
            valoare_totala_cu_tva = :valoare_totala_cu_tva
        WHERE nr_nir = :nr_nir
    ";
    $stmt_update_nir = $pdo->prepare($sql_update_nir);
    $stmt_update_nir->execute([
        'ad_com_total' => $total_ad_unit,
        'tva_neex_ad_com' => $total_tva_neex_ad_com,
        'tva_ded' => $total_tva_ded,
        'val_nir_ftva' => $total_valoare_fara_tva,
        'valoare_totala' => $valoare_totala,
        'valoare_totala_cu_tva' => $valoare_totala_cu_tva,
        'nr_nir' => $nr_nir
    ]);
}
// Handle form submission to add product
if (isset($_POST['adaug_produs'])) {

    if (!isset($_SESSION['nr_nir'])) {
        http_response_code(400);
        exit('Lipsește numărul NIR din sesiune.');
    }

    $nr_nir = $_SESSION['nr_nir'];

// Fetch NIR data
$sql_nir = "SELECT * FROM nir WHERE nr_nir = :nr_nir";
$stmt_nir = $pdo->prepare($sql_nir);
$stmt_nir->execute(['nr_nir' => $nr_nir]);
$nir_data = $stmt_nir->fetch(PDO::FETCH_ASSOC);

if (!$nir_data) {
    echo "NIR nu există.";
    exit;
}
    // Începeți o tranzacție
    $pdo->beginTransaction();

    try {
        // Preluarea datelor din formular
        $produs =  $_POST['cod_produs']; // Codul produsului selectat

        // Preluăm valoarea pretului de vânzare (cu TVA) din formular
$pret_cu_tva_input = $_POST['pret_cu_tva'];

$sql_update_pret_vanzare = "UPDATE produse_servicii SET pret_cu_tva = :pret_cu_tva WHERE cod_produs = :cod_produs";
$stmt_update_pret_vanzare = $pdo->prepare($sql_update_pret_vanzare);
$stmt_update_pret_vanzare->execute([
    'pret_cu_tva' => $pret_cu_tva_input,
    'cod_produs' => $produs,
]);
        $pret_achiz = $_POST['pret_achiz'];
        $cota_tva_achiz = $_POST['cota_tva_achiz'];
        $cant_doc = $_POST['cant_doc'];
        $cant_prim = $_POST['cant_prim'];
        $nr_nir = $_SESSION['nr_nir'];
        $data_nir = $nir_data['data_nir'];
        $cod_locatie_nir = 2;

        // Fetch denumire_produs, UM, gestiune și cota_tva_vz + pret_vanzare din produse_servicii
        $sql_fetch_produs = "
             SELECT 
        ps.nume AS denumire_produs, 
        ps.um AS unitate_masura,
        ps.pret_achizitie,
        ps.sgr, 
        ps.sgr_pet, 
        ps.sgr_alumin, 
        ps.sgr_sticla,
        g.denumire_gestiune, 
        ps.pret_cu_tva AS pret_vanzare, 
        ps.cota_tva AS cota_tva_vz
    FROM 
        produse_servicii ps
    INNER JOIN 
        gestiuni g ON ps.id_gestiune = g.id_gestiune
    WHERE 
        ps.cod_produs = :cod_p
        ";
        $stmt_fetch_produs = $pdo->prepare($sql_fetch_produs);
        $stmt_fetch_produs->execute(['cod_p' => $produs]);
        $produs_data = $stmt_fetch_produs->fetch(PDO::FETCH_ASSOC);

        if (!$produs_data) {
            throw new Exception("Produsul selectat nu există în baza de date.");
        }
// Dacă prețul de achiziție introdus diferă de cel din BD și s-a setat flag-ul de actualizare,
// actualizăm prețul în tabela produse_servicii
if ($pret_achiz != $produs_data['pret_achizitie'] && isset($_POST['update_price']) && $_POST['update_price'] == '1') {
    $sql_update_price = "UPDATE produse_servicii SET pret_achizitie = :pret_achiz WHERE cod_produs = :cod_produs";
    $stmt_update_price = $pdo->prepare($sql_update_price);
    $stmt_update_price->execute([
        'pret_achiz' => $pret_achiz,
        'cod_produs' => $produs,
    ]);
}




        $den_p         = $produs_data['denumire_produs'];
        $um            = $produs_data['unitate_masura'];
        $den_gestiune  = $produs_data['denumire_gestiune'];
        $pret_vanzare  = $produs_data['pret_vanzare'];  // Preț cu TVA la vânzare
        $cota_tva_vz   = $produs_data['cota_tva_vz'];

        // ----------------------------
        // ----------------------------

   $valoare_achizitie             = (float)($_POST['val_fara_tva'] ?? 0);
$tva_unitar_achizitie          = (float)($_POST['tva_unitar_achizitie'] ?? 0);
$valoare_tva_achizitie         = (float)($_POST['valoare_tva_achizitie'] ?? 0);
$valoare_achizitie_cu_tva      = (float)($_POST['valoare_achizitie_cu_tva'] ?? 0);

$procent_adaos                 = (float)($_POST['procent_adaos'] ?? 0);
$adaos_unitar                  = (float)($_POST['adaos_unitar'] ?? 0);
$valoare_adaos                 = (float)($_POST['valoare_adaos'] ?? 0);
$pret_cu_adaos_unitar_fara_tva = (float)($_POST['pret_cu_adaos_unitar_fara_tva'] ?? 0);
$tva_adaos_comercial           = (float)($_POST['tva_adaos_comercial'] ?? 0);
$pret_unitar_cu_amanuntul_cu_tva = (float)($_POST['pret_unitar_cu_amanuntul_cu_tva'] ?? 0);
$valoare_pret_amanunt          = (float)($_POST['valoare_pret_amanunt'] ?? 0);
$tva_total_unitar              = (float)($_POST['tva_total_unitar'] ?? 0);
$valoare_tva_totala            = (float)($_POST['valoare_tva_totala'] ?? 0);

        // -------------------------------
        // Verifică dacă deja există în achizitii
        // -------------------------------
        $sql_check = "
            SELECT id_achiz 
            FROM achizitii 
            WHERE nr_nir = :nr_nir 
              AND cod_p = :cod_p 
              AND pret_unitar_achizitie = :pret_achiz and cota_tva=:cota_tva_achiz
            LIMIT 1
        ";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([
            'nr_nir'   => $nr_nir,
            'cod_p'    => $produs,
            'pret_achiz' => $pret_achiz,
            'cota_tva_achiz' => $cota_tva_achiz

        ]);
        $existing_achiz_id = $stmt_check->fetchColumn();

      
if ($existing_achiz_id === false) {
    // INSERT
    $sql_insert = "
        INSERT INTO achizitii (
            nr_nir,
            cod_p,
            cota_tva,
            denumire_produs,
            unitate_masura,
            cantitate,
            pret_unitar_achizitie,
            valoare_achizitie,
            tva_unitar_achizitie,
            valoare_tva_achizitie,
            valoare_achizitie_cu_tva,
            procent_adaos,
            adaos_unitar,
            valoare_adaos,
            pret_cu_adaos_unitar_fara_tva,
            tva_adaos_comercial,
            pret_unitar_cu_amanuntul_cu_tva,
            valoare_pret_amanunt,
            tva_total_unitar,
            valoare_tva_totala,
            data,
            cod_locatie
        ) VALUES (
            :nr_nir,
            :cod_p,
            :cota_tva,
            :denumire_produs,
            :unitate_masura,
            :cantitate,
            :pret_unitar_achizitie,
            :valoare_achizitie,
            :tva_unitar_achizitie,
            :valoare_tva_achizitie,
            :valoare_achizitie_cu_tva,
            :procent_adaos,
            :adaos_unitar,
            :valoare_adaos,
            :pret_cu_adaos_unitar_fara_tva,
            :tva_adaos_comercial,
            :pret_unitar_cu_amanuntul_cu_tva,
            :valoare_pret_amanunt,
            :tva_total_unitar,
            :valoare_tva_totala,
            :data,
            :cod_locatie
        )
    ";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        'nr_nir' => $nr_nir,
        'cod_p'  => $produs,
        'cota_tva' => $cota_tva_achiz,
        'denumire_produs' => $den_p,
        'unitate_masura'  => $um,
        'cantitate'       => $cant_prim,
        'pret_unitar_achizitie' => $pret_achiz,
        'valoare_achizitie'     => $valoare_achizitie,
        'tva_unitar_achizitie'  => $tva_unitar_achizitie,
        'valoare_tva_achizitie' => $valoare_tva_achizitie,
        'valoare_achizitie_cu_tva' => $valoare_achizitie_cu_tva,
        'procent_adaos'          => $procent_adaos,
        'adaos_unitar'           => $adaos_unitar,
        'valoare_adaos'          => $valoare_adaos,
        'pret_cu_adaos_unitar_fara_tva' => $pret_cu_adaos_unitar_fara_tva,
        'tva_adaos_comercial'            => $tva_adaos_comercial,
        'pret_unitar_cu_amanuntul_cu_tva' => $pret_unitar_cu_amanuntul_cu_tva,
        'valoare_pret_amanunt'           => $valoare_pret_amanunt,
        'tva_total_unitar'               => $tva_total_unitar,
        'valoare_tva_totala'             => $valoare_tva_totala,
        'data'                           => $data_nir,
        'cod_locatie'                    => $cod_locatie_nir
    ]);

    // Obține id_achiz al rândului inserat
    $id_achiz_inserat_sau_actualizat = $pdo->lastInsertId();

    // (Opțional) Verificare dacă ID-ul a fost obținut corect
    if (!$id_achiz_inserat_sau_actualizat) {
        die("Eroare: Nu s-a putut obține id_achiz după inserare.");
    }
} else {
    // UPDATE
    $id_achiz_inserat_sau_actualizat = $existing_achiz_id;
    $sql_update = "
        UPDATE achizitii
        SET 
            cantitate = cantitate + :cantitate,
            valoare_achizitie = valoare_achizitie + :valoare_achizitie,
            valoare_tva_achizitie = valoare_tva_achizitie + :valoare_tva_achizitie,
            valoare_achizitie_cu_tva = valoare_achizitie_cu_tva + :valoare_achizitie_cu_tva,
            valoare_adaos = valoare_adaos + :valoare_adaos,
            valoare_pret_amanunt = valoare_pret_amanunt + :valoare_pret_amanunt,
            valoare_tva_totala = valoare_tva_totala + :valoare_tva_totala,
            cod_locatie = :cod_locatie
        WHERE nr_nir = :nr_nir
          AND cod_p = :cod_p
          AND pret_unitar_achizitie = :pret_unitar_achizitie
    ";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        'cantitate' => $cant_prim,
        'valoare_achizitie' => $valoare_achizitie,
        'valoare_tva_achizitie' => $valoare_tva_achizitie,
        'valoare_achizitie_cu_tva' => $valoare_achizitie_cu_tva,
        'valoare_adaos' => $valoare_adaos,
        'valoare_pret_amanunt' => $valoare_pret_amanunt,
        'valoare_tva_totala' => $valoare_tva_totala,
        'nr_nir' => $nr_nir,
        'cod_p' => $produs,
        'pret_unitar_achizitie' => $pret_achiz,
        'cod_locatie' => $cod_locatie_nir
    ]);

    // (Opțional) Verificare dacă ID-ul a fost obținut corect
    if (!$id_achiz_inserat_sau_actualizat) {
        die("Eroare: Nu s-a putut obține id_achiz după actualizare.");
    }
}

        // Inserare în tabela miscari (nemodificat)
        $sql_insert_miscare = "
            INSERT INTO miscari (
                data, cod_p, denumire_produs, cantitate_misc, tip_miscare, fel_doc, id_doc,
                nr_doc, pu, cota_tva, pret_vanzare,gestiune,id_achiz,cod_locatie,ora_miscarii
            ) VALUES (
                :data, :cod_p, :denumire_produs, :cantitate_misc, 'I', 'NIR', :id_doc,
                :nr_nir, :pu, :cota_tva, :pret_vanzare,:gestiune,:id_achiz_inserat_sau_actualizat,:cod_locatie,'10:00:00'
            )
        ";
        $stmt_insert_miscare = $pdo->prepare($sql_insert_miscare);
        $stmt_insert_miscare->execute([
            'data' => $data_nir,
            'cod_p' => $produs,
            'denumire_produs' => $den_p,
            'cantitate_misc' => $cant_prim,
            'id_doc' => $nir_data['id_nir'], // Inserăm id_nir în id_doc
            'nr_nir' => $nr_nir,
            'pu' => $pret_achiz,
            'cota_tva' => $cota_tva_achiz,
            'pret_vanzare' => $pret_vanzare,
            'gestiune'=> $den_gestiune,
            'id_achiz_inserat_sau_actualizat'=>$id_achiz_inserat_sau_actualizat,
            'cod_locatie'=>$cod_locatie_nir
        ]);

        // Actualizează tabela NIR
        actualizeaza_nir($pdo, $nr_nir);

        // Confirmați tranzacția
        $pdo->commit();

       // echo "Produsul a fost adăugat cu succes!";





    } catch (Exception $e) {
        // Anulează tranzacția în caz de eroare
        $pdo->rollBack();
        echo "Eroare la adăugarea produsului: " . htmlspecialchars($e->getMessage());
    }
    // Funcţie auxiliară pentru inserare SGR (aceeaşi structură ca la produsul principal)
function inserareWarranty($pdo, $nr_nir, $data_nir, $nir_data, $den_gestiune, $warrantyProduct, $warrantyCode, $warrantyQuantity) {
    $cod_locatie_nir = 2;
    // Extragem datele produsului garanţie
    $warranty_pret    = $warrantyProduct['pret_cu_tva'];
    $warranty_cota    = $warrantyProduct['cota_tva'];
    $warranty_denumire= $warrantyProduct['nume'];
    $warranty_um      = $warrantyProduct['um'];

    // Calculăm valorile aferente (aici se calculează similar ca la produsul principal; 
    // poţi adapta formula dacă pentru garanţii se aplică altă logică de preţ)
    $warranty_valoare_achizitie       = $warrantyQuantity * $warranty_pret;
    $warranty_tva_unitar              = $warranty_pret * ($warranty_cota / 100);
    $warranty_valoare_tva             = $warrantyQuantity * $warranty_tva_unitar;
    $warranty_achizitie_cu_tva        = $warranty_valoare_achizitie + $warranty_valoare_tva;

    // Pentru garanţii presupunem că nu se aplică adaos (sau sunt 0)
    $warranty_procent_adaos           = 0;
    $warranty_adaos_unitar            = 0;
    $warranty_valoare_adaos           = 0;
    $warranty_pret_cu_adaos_unitar_fara_tva = $warranty_pret;
    $warranty_tva_adaos_comercial     = 0;
    $warranty_pret_unitar_cu_amanuntul_cu_tva = $warranty_pret;
    $warranty_valoare_pret_amanunt    = $warranty_achizitie_cu_tva;
    $warranty_tva_total_unitar        = $warranty_tva_unitar;
    $warranty_valoare_tva_totala      = $warranty_valoare_tva;

    // Inserăm în tabela achizitii
    $sql_insert_warranty = "
        INSERT INTO achizitii (
            nr_nir,
            cod_p,
            cota_tva,
            denumire_produs,
            unitate_masura,
            cantitate,
            pret_unitar_achizitie,
            valoare_achizitie,
            tva_unitar_achizitie,
            valoare_tva_achizitie,
            valoare_achizitie_cu_tva,
            procent_adaos,
            adaos_unitar,
            valoare_adaos,
            pret_cu_adaos_unitar_fara_tva,
            tva_adaos_comercial,
            pret_unitar_cu_amanuntul_cu_tva,
            valoare_pret_amanunt,
            tva_total_unitar,
            valoare_tva_totala,
            data,
            cod_locatie
        ) VALUES (
            :nr_nir,
            :cod_p,
            :cota_tva,
            :denumire_produs,
            :unitate_masura,
            :cantitate,
            :pret_unitar_achizitie,
            :valoare_achizitie,
            :tva_unitar_achizitie,
            :valoare_tva_achizitie,
            :valoare_achizitie_cu_tva,
            :procent_adaos,
            :adaos_unitar,
            :valoare_adaos,
            :pret_cu_adaos_unitar_fara_tva,
            :tva_adaos_comercial,
            :pret_unitar_cu_amanuntul_cu_tva,
            :valoare_pret_amanunt,
            :tva_total_unitar,
            :valoare_tva_totala,
            :data,
            :cod_locatie
        )
    ";
    $stmt_insert_warranty = $pdo->prepare($sql_insert_warranty);
    $stmt_insert_warranty->execute([
        'nr_nir'                           => $nr_nir,
        'cod_p'                            => $warrantyCode,
        'cota_tva'                         => $warranty_cota,
        'denumire_produs'                  => $warranty_denumire,
        'unitate_masura'                   => $warranty_um,
        'cantitate'                        => $warrantyQuantity,
        'pret_unitar_achizitie'            => $warranty_pret,
        'valoare_achizitie'                => $warranty_valoare_achizitie,
        'tva_unitar_achizitie'             => $warranty_tva_unitar,
        'valoare_tva_achizitie'            => $warranty_valoare_tva,
        'valoare_achizitie_cu_tva'           => $warranty_achizitie_cu_tva,
        'procent_adaos'                    => $warranty_procent_adaos,
        'adaos_unitar'                     => $warranty_adaos_unitar,
        'valoare_adaos'                    => $warranty_valoare_adaos,
        'pret_cu_adaos_unitar_fara_tva'      => $warranty_pret_cu_adaos_unitar_fara_tva,
        'tva_adaos_comercial'              => $warranty_tva_adaos_comercial,
        'pret_unitar_cu_amanuntul_cu_tva'    => $warranty_pret_unitar_cu_amanuntul_cu_tva,
        'valoare_pret_amanunt'             => $warranty_valoare_pret_amanunt,
        'tva_total_unitar'                 => $warranty_tva_total_unitar,
        'valoare_tva_totala'               => $warranty_valoare_tva_totala,
        'data'                             => $data_nir,
        'cod_locatie'                      => $cod_locatie_nir
    ]);
    $id_warranty = $pdo->lastInsertId();

    // Inserăm și în tabela miscari pentru garanţie:
    $sql_insert_miscare_warranty = "
        INSERT INTO miscari (
            data, cod_p, denumire_produs, cantitate_misc, tip_miscare, fel_doc, id_doc,
            nr_doc, pu, cota_tva, pret_vanzare, gestiune, id_achiz,cod_locatie,ora_miscarii
        ) VALUES (
            :data, :cod_p, :denumire_produs, :cantitate_misc, 'I', 'NIR', :id_doc,
            :nr_nir, :pu, :cota_tva, :pret_vanzare, :gestiune, :id_achiz,:cod_locatie,'10:00:00'
        )
    ";
    $stmt_insert_miscare_warranty = $pdo->prepare($sql_insert_miscare_warranty);
    $stmt_insert_miscare_warranty->execute([
        'data'         => $data_nir,
        'cod_p'        => $warrantyCode,
        'denumire_produs'=> $warranty_denumire,
        'cantitate_misc'=> $warrantyQuantity,
        'id_doc'       => $nir_data['id_nir'], // presupunând că acesta este ID-ul documentului NIR
        'nr_nir'       => $nr_nir,
        'pu'           => $warranty_pret,
        'cota_tva'     => $warranty_cota,
        'pret_vanzare' => $warranty_pret,
        'gestiune'     => $den_gestiune,
        'id_achiz'     => $id_warranty,
        'cod_locatie'  => $cod_locatie_nir
    ]);
}

// Pentru fiecare tip de garanţie se face interogarea produsului cu codul specific
// (presupunem că în tabela 'produse_servicii' sunt definite produsele de garanţie cu coduri negative)
$warrantyTypes = [
    'sgr'       => -2,
    'sgr_pet'   => -3,
    'sgr_alumin'=> -4,
    'sgr_sticla'=> -5
];

// Pentru fiecare tip, dacă flag-ul este activ, se preia produsul de garanţie și se inserează
foreach ($warrantyTypes as $field => $warrantyCode) {
    if (!empty($produs_data[$field]) && $produs_data[$field] == 1) {
        // Preluăm datele produsului garanţie
        $sql_warranty = "
        SELECT ps.*, g.denumire_gestiune 
        FROM produse_servicii ps
        INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
        WHERE ps.cod_produs = :warrantyCode
    ";
            $stmt_warranty = $pdo->prepare($sql_warranty);
        $stmt_warranty->execute(['warrantyCode' => $warrantyCode]);
        if ($warrantyProduct = $stmt_warranty->fetch(PDO::FETCH_ASSOC)) {
            // Pentru garanţii, cantitatea va fi aceeaşi ca la produsul principal
            $warrantyQuantity = $cant_prim; // folosește variabila din calculul principal
            
            // Extragem denumirea gestiunii specifică warranty-ului
    $warrantyGestiune = $warrantyProduct['denumire_gestiune'];
    
    // Apelăm funcția inserareWarranty, acum transmițând gestiunea specifică
    inserareWarranty($pdo, $nr_nir, $data_nir, $nir_data, $warrantyGestiune, $warrantyProduct, $warrantyCode, $warrantyQuantity);
        }
    }
}

}

?>
