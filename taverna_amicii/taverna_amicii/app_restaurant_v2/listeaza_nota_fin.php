<?php //listeaza_nota_fin.php
require_once 'session.php';      // conține $pdo și denumirile de tabele
date_default_timezone_set('Europe/Bucharest');

// --- FUNCȚII PENTRU ECRANUL DE AȘTEPTARE ---
function init_loading_screen() {
    while (ob_get_level()) { ob_end_clean(); }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<style>body{background:#f4f7f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif}.card{background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);text-align:center}.spinner{margin-bottom:20px;color:#007bff;font-size:40px;display:inline-block;animation:spin 1.5s linear infinite;} @keyframes spin { 100% { transform: rotate(360deg); } } .text-muted{color:#6c757d;font-size:16px;margin-top:15px;}</style>';
    echo '</head><body>';
    echo '<div class="card"><div class="spinner">⏳</div>';
    echo '<h3>Vă rugăm așteptați...</h3>';
    echo '<p class="text-muted" id="loading-status">Se pregătesc datele...</p>';
    echo '</div>';
    echo '<script>function updateStatus(msg) { document.getElementById("loading-status").innerHTML = msg; }</script>';
    echo '</body></html>';
    flush();
}

function update_loading_status($msg) {
    echo '<script>updateStatus("' . addslashes($msg) . '");</script>';
    flush();
}
// -------------------------------------------

// 1. Generare fișier „de_listat_la_imprimanta.json” – NOTĂ DE PLATĂ
ini_set('display_errors', 0);
ini_set('log_errors',    1);
ini_set('error_log',     'error_log.log');
error_reporting(E_ALL);

// Verificăm dacă există nr_bon în sesiune
if (empty($_SESSION['nr_bon'])) {
    error_log('listeaza_nota_fin.php: nr_bon lipsă din sesiune.');
} else {
    
    // Afișăm ecranul de așteptare utilizatorului!
    init_loading_screen();
    update_loading_status("Generăm nota de plată din sistem...");

    $nr_bon     = (int)$_SESSION['nr_bon'];

    /* ----------------------------------------------------------------
       1.1   Date din tabela NOTE (antet, operator, metode de plată)
    ---------------------------------------------------------------- */
    $sqlNota = "SELECT * FROM note WHERE nrbon = :nrbon LIMIT 1";
    $stmtNota = $pdo->prepare($sqlNota);
    $stmtNota->execute([':nrbon' => $nr_bon]);
    $nota = $stmtNota->fetch(PDO::FETCH_ASSOC);

    $skipProtocolExtraPrint = !empty($_SESSION['skip_protocol_extra_print'])
        && in_array((int)($_SESSION['client_id'] ?? 0), [25, 26], true)
        && $nota
        && (float)($nota['protocol'] ?? 0) != 0.0;
    unset($_SESSION['skip_protocol_extra_print']);

    if ($nota && !$skipProtocolExtraPrint) {
        $data_bon    = $nota['data_bon'];
        $ora_bon     = $nota['ora_bon'];
        $cod_locatie = (int)$nota['locatie'];
        $cif_client  = $nota['cif_client'];

        $admin_firstname = $admin_lastname = '';
        $sqlOp = "SELECT admin_firstname, admin_lastname 
                  FROM $tabel_final_admins
                  WHERE admin_id = :id LIMIT 1";
        $stmtOp = $pdo->prepare($sqlOp);
        $stmtOp->execute([':id' => $nota['operator']]);
        if ($op = $stmtOp->fetch(PDO::FETCH_ASSOC)) {
            $admin_firstname = $op['admin_firstname'];
            $admin_lastname  = $op['admin_lastname'];
        }

        $pseudonim_firma = '';
        $stmtFirma = $pdo->query("SELECT pseudonim_firma FROM date_firma LIMIT 1");
        if ($rowF = $stmtFirma->fetch(PDO::FETCH_ASSOC)) {
            $pseudonim_firma = $rowF['pseudonim_firma'];
        }

        /* ----------------------------------------------------------------
           1.2   Produse din detaliile bonului
        ---------------------------------------------------------------- */
        $sqlDet = "
            SELECT  dn.observatie_produs,
                    dn.cantitate,
                    dn.pret_vanzare,
                    dn.valoare_vanzare_cu_tva,
                    dn.tva_col,
                    ps.nume,
                    ps.cota_tva
            FROM    {$tabel_final_det_note}  dn
            JOIN    {$tabel_final_nomenclator} ps ON dn.cod_p = ps.cod_produs
            WHERE   dn.nr_bon = :nrbon
                AND dn.pret_vanzare > 0
        ";
        $stmtDet = $pdo->prepare($sqlDet);
        $stmtDet->execute([':nrbon' => $nr_bon]);
        $rowsDet = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        $produseGrupate = [];
        foreach ($rowsDet as $row) {
            $cheie = trim($row['nume'])
                   . (trim($row['observatie_produs']) !== '' ? '_'.$row['observatie_produs'] : '');
            if (!isset($produseGrupate[$cheie])) {
                $produseGrupate[$cheie] = $row;
            } else {
                $produseGrupate[$cheie]['cantitate']             += $row['cantitate'];
                $produseGrupate[$cheie]['valoare_vanzare_cu_tva'] += $row['valoare_vanzare_cu_tva'];
                $produseGrupate[$cheie]['tva_col']               += $row['tva_col'];
            }
        }

        /* ----------------------------------------------------------------
           1.3   Construim conținutul text pentru imprimantă
        ---------------------------------------------------------------- */
        $cr       = "\n";
        $titluNota = (float)($nota['protocol'] ?? 0) != 0.0
            ? 'NOTA PROTOCOL'
            : 'NOTA DE PLATA';
        $continut = $titluNota . $cr;
        if (isset($_SESSION['camera_nota'])) {
            $continut .= 'CAMERA ' . $_SESSION['camera_nota'] . $cr;
        }

        $continut .= $pseudonim_firma.$cr;
        $continut .= "{$data_bon} {$ora_bon}{$cr}";
        $continut .= "OPERATOR: {$admin_firstname} {$admin_lastname}{$cr}";
        $continut .= str_repeat('-', 20).$cr;

        $total_nota = 0;
        foreach ($produseGrupate as $prod) {
            $linie = $prod['nume'];
            if (trim($prod['observatie_produs']) !== '') {
                $linie .= ' '.$prod['observatie_produs'];
            }
            $linie .= $cr;
            $linie .= '  '.$prod['cantitate'].' x '.number_format($prod['pret_vanzare'], 2)
                   .' = '.number_format($prod['valoare_vanzare_cu_tva'], 2).' LEI'
                   .' (TVA '.$prod['cota_tva'].'%)';
            $total_nota += $prod['valoare_vanzare_cu_tva'];
            $continut  .= $linie.$cr;
        }

        $continut .= str_repeat('-', 20).$cr;
        $continut .= 'TOTAL: '.number_format($total_nota, 2).' LEI'.$cr;

        $continut .= str_repeat('-', 20).$cr;

        $metodePlata = [
            'Numerar'          => $nota['numerar'],
            'Card'             => $nota['card'],
            'Tichete'          => $nota['tichete'],
            'Prot.'            => $nota['protocol'],
            'Glovo'            => $nota['glovo'],
            'Virament bancar'  => $nota['virament_bancar'],
            'Rest'             => $nota['rest'],
        ];
        foreach ($metodePlata as $eticheta => $valoare) {
            if ($valoare != 0) {
                $continut .= $eticheta.': '.number_format($valoare, 2).' LEI'.$cr;
            }
        }

        $sqlTotalCota = "SELECT cota_tva, SUM(valoare_vanzare_cu_tva) AS total_cota
                         FROM   {$tabel_final_det_note}
                         WHERE  nr_bon = :nrbon
                         GROUP  BY cota_tva";
        $stmtTotalCota = $pdo->prepare($sqlTotalCota);
        $stmtTotalCota->execute([':nrbon' => $nr_bon]);
        while ($c = $stmtTotalCota->fetch(PDO::FETCH_ASSOC)) {
            $continut .= 'TOTAL '.$c['cota_tva'].'%: '.number_format($c['total_cota'], 2).' LEI'.$cr;
        }

        $continut .= str_repeat('-', 20).$cr;
        $continut .= 'Nr. nota: '.$nr_bon.$cr;
        $continut .= 'Va multumim!'.$cr;

        /* ----------------------------------------------------------------
           1.4   Salvăm JSON de listare
        ---------------------------------------------------------------- */
        $client_id = $_SESSION['client_id'] ?? 'default';
        $folder = RESTAURANT_OFFLINE_API_DIR . "/{$client_id}/{$cod_locatie}";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $printData = [[
            'data'                   => date('Y-m-d'),
            'ora'                    => date('H:i:s'),
            'id'                     => 0,
            'de_trimis_la_imprimanta'=> 1,
            'nrbon'                  => (int)$nr_bon,
            'locatie'                => (int)$cod_locatie,
            'departament_listare'    => 'BAR',
            'continut'               => $continut
        ]];

        $jsonArray = [
            'status'  => 'success',
            'message' => 'Nota de plată generată cu succes.',
            'data'    => $printData
        ];

        // Se asteapta imprimanta (dacă există un alt fișier în curs de procesare)
        update_loading_status("Așteptăm preluarea datelor de către imprimanta BAR (Notă plată)...");
        
        $jsonPath  = $folder.'/de_listat_la_imprimanta.json';
        $totalWait = 0;
        while (file_exists($jsonPath) && $totalWait < 60) {
            sleep(5); // Verificăm la fiecare 5 secunde, pentru un răspuns mai rapid
            $totalWait += 5;
        }

        // Prima și SINGURA scriere
        file_put_contents(
            $jsonPath,
            json_encode($jsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

    } elseif (!$nota) {
        error_log("listeaza_nota_fin.php: Nota {$nr_bon} nu a fost găsită.");
    }
}
// ------------------------------------------------------------
// 2. Blocul cerut „identic” din scriptul original – NEMODIFICAT
// ------------------------------------------------------------
?>
<?php

// Verificăm dacă variabilele de sesiune există înainte de a le șterge
if(isset($_SESSION['nr_bon'])) {
    unset($_SESSION['nr_bon']);
}
if(isset($_SESSION['numerarprim'])) {
    unset($_SESSION['numerarprim']);
}
if(isset($_SESSION['cardprim'])) {
    unset($_SESSION['cardprim']);
}
if(isset($_SESSION['glovo'])) {
    unset($_SESSION['glovo']);
}
if(isset($_SESSION['cif_client'])) {
    unset($_SESSION['cif_client']);
}
if(isset($_SESSION['rest_tichete'])) {
    unset($_SESSION['rest_tichete']);
}
if(isset($_SESSION['total_tichete'])) {
    unset($_SESSION['total_tichete']);
}
if(isset($_SESSION['nota_noua'])) {
    unset($_SESSION['nota_noua']);
}
if(isset($_SESSION['camera_nota'])) {
    unset($_SESSION['camera_nota']);
}
if (isset($_SESSION['cod_locatie']) && $_SESSION['cod_locatie'] == 2) {

    if (!isset($_SESSION['nextbon']) || $_SESSION['nextbon'] == 0) {
        if (isset($_SESSION['masa_curenta'])) {
            unset($_SESSION['masa_curenta']);
        }
    } else {
        $_SESSION['nr_bon'] = $_SESSION['nextbon'];
        unset($_SESSION['nextbon']);
    }

}
else{
                unset($_SESSION['masa_curenta']);

}

update_loading_status("Listare completă! Vă redirecționăm...");

// Redirecționăm utilizatorul către pagina "vanzare_restaurant.php"
printf("<script>location.href='vanzare_restaurant.php'</script>");
?>
