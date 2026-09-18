<?php
// Asigură-te că sesiunea este pornită dacă nu a fost deja pornită în fișierul principal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    echo "<div class='alert alert-danger'>Sesiune invalidă.</div>";
    return;
}

include 'db.php'; // Include conexiunea la DB

// Verifică dacă nr_nir există în sesiune
if (!isset($_SESSION['nr_nir'])) {
    echo "<div class='alert alert-danger'>Eroare: Numărul NIR nu este disponibil în sesiune.</div>";
    // Poți opri execuția aici dacă este necesar
    // exit;
    $nr_nir = null; // Setează $nr_nir la null pentru a evita erori ulterioare
} else {
    $nr_nir = $_SESSION['nr_nir'];
}

?>

<div class="card mb-3">
    <div class="card-header">Produse în NIR</div>
    <div class="card-body">
        <div class="table-responsive"> <table class="table table-bordered table-striped table-hover"> <thead class="table-light"> <tr>
                        <th>Denumire Produs</th>
                        <th class="text-center">UM</th>
                        <th class="text-end">Cantitate</th>
                        <th class="text-end">Preț Unitar Achiz.</th>
                        <th class="text-end">Valoare Achiz.</th>
                        <th class="text-center">Cota TVA</th>
                        <th class="text-end">Valoare TVA Achiz.</th>
                        <th class="text-end">Valoare achizitie cu TVA</th>
                        <th class="text-center">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_products = false; // Flag pentru a verifica dacă există produse
                    if ($nr_nir !== null) { // Execută doar dacă avem un nr_nir valid
                        try {
                            // Fetch products in NIR
                            $sql_products = "SELECT * FROM achizitii WHERE nr_nir = :nr_nir ORDER BY id_achiz ASC"; // Adăugat ORDER BY
                            $stmt_products = $pdo->prepare($sql_products);
                            $stmt_products->execute(['nr_nir' => $nr_nir]);

                            while ($product = $stmt_products->fetch(PDO::FETCH_ASSOC)) {
                                $has_products = true; // S-a găsit cel puțin un produs

                                // Formatare numere pentru afișare consistentă
                                $cantitate = number_format($product['cantitate'], 2, ',', '.');
                                $pret_unitar = number_format($product['pret_unitar_achizitie'], 5, ',', '.');
                                $val_achiz = number_format($product['valoare_achizitie'], 5, ',', '.');
                                $val_tva = number_format($product['valoare_tva_achizitie'], 5, ',', '.');
                                $val_achiz_tva = number_format($product['valoare_achizitie_cu_tva'], 5, ',', '.');

                                echo "<tr>
                                        <td>" . htmlspecialchars($product['denumire_produs']) . "</td>
                                        <td class='text-center'>" . htmlspecialchars($product['unitate_masura']) . "</td>
                                        <td class='text-end'>" . htmlspecialchars($cantitate) . "</td>
                                        <td class='text-end'>" . htmlspecialchars($pret_unitar) . "</td>
                                        <td class='text-end'>" . htmlspecialchars($val_achiz) . "</td>
                                        <td class='text-center'>" . htmlspecialchars($product['cota_tva']) . "%</td>
                                        <td class='text-end'>" . htmlspecialchars($val_tva) . "</td>
                                        <td class='text-end'>" . htmlspecialchars($val_achiz_tva) . "</td>
                                        <td class='text-center'>
                                                <form class='delete-product-form' method='post' style='display:inline-block'>
                                                <input type='hidden' name='id_achiz' value='" . htmlspecialchars($product['id_achiz']) . "'>
                                                <input type='hidden' name='action' value='sterge_produs'>
                                                <button type='submit' class='btn btn-danger btn-sm' title='Șterge produs'>
                                                    <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-trash' viewBox='0 0 16 16'>
                                                      <path d='M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z'/>
                                                      <path fill-rule='evenodd' d='M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z'/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>";
                            }
                        } catch (PDOException $e) {
                             echo '<tr><td colspan="9" class="text-center text-danger">Eroare la încărcarea produselor: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                        }
                    } else {
                         echo '<tr><td colspan="9" class="text-center text-warning">Numărul NIR lipsește. Nu se pot încărca produsele.</td></tr>';
                    }

                     // Afișează un mesaj dacă nu s-au găsit produse
                     if (!$has_products && $nr_nir !== null) {
                         echo '<tr><td colspan="9" class="text-center">Nu există produse înregistrate pentru acest NIR.</td></tr>';
                     }
                    ?>
                </tbody>
            </table>
        </div> </div> </div> 
        <?php
// --- START: Interogare și Afișare Detalii și Totaluri NIR (versiune pe baza 'achizitii') ---

$nir_details = null; 
$db_error_nir = null;

try {
    // 1) Detalii document (le păstrăm din NIR DOAR pentru meta: date/document/gestionar/obs)
    if ($nr_nir !== null) {
        $sql_nir_details = "SELECT nr_nir, data_nir, serie_doc_int, nr_doc_int, data_doc_int, gestionar, observatii
                            FROM nir WHERE nr_nir = :nr_nir";
        $stmt_nir_details = $pdo->prepare($sql_nir_details);
        $stmt_nir_details->execute(['nr_nir' => $nr_nir]);
        $nir_details = $stmt_nir_details->fetch(PDO::FETCH_ASSOC);
    }

    // 2) Totaluri generale din 'achizitii'
    $totals = [
        'total_ftva' => 0, 'total_tva_ded' => 0, 'total_ctva' => 0,
        'total_adaos' => 0, 'total_tva_neex_ad_com' => 0, 'total_vanzare' => 0
    ];

    if ($nr_nir !== null) {
        $sql_sum = "SELECT
                        COALESCE(SUM(valoare_achizitie),0)          AS total_ftva,
                        COALESCE(SUM(valoare_tva_achizitie),0)      AS total_tva_ded,
                        COALESCE(SUM(valoare_achizitie_cu_tva),0)   AS total_ctva,
                        COALESCE(SUM(valoare_adaos),0)              AS total_adaos,
                        COALESCE(SUM(tva_adaos_comercial),0)        AS total_tva_neex_ad_com,
                        COALESCE(SUM(valoare_pret_amanunt),0)       AS total_vanzare
                    FROM achizitii
                    WHERE nr_nir = :nr_nir";
        $stmt_sum = $pdo->prepare($sql_sum);
        $stmt_sum->execute(['nr_nir' => $nr_nir]);
        $totals = $stmt_sum->fetch(PDO::FETCH_ASSOC) ?: $totals;
    }

    // 3) Totaluri pe cote TVA
    $by_tva = [];
    if ($nr_nir !== null) {
        $sql_tva = "SELECT cota_tva,
                           COALESCE(SUM(valoare_achizitie),0)        AS ftva,
                           COALESCE(SUM(valoare_tva_achizitie),0)    AS tva,
                           COALESCE(SUM(valoare_achizitie_cu_tva),0) AS ctva,
                           COALESCE(SUM(valoare_pret_amanunt),0)     AS vanzare
                    FROM achizitii
                    WHERE nr_nir = :nr_nir
                    GROUP BY cota_tva
                    ORDER BY cota_tva";
        $stmt_tva = $pdo->prepare($sql_tva);
        $stmt_tva->execute(['nr_nir' => $nr_nir]);
        $by_tva = $stmt_tva->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4) Totaluri pe gestiuni
    $by_gestiune = [];
    if ($nr_nir !== null) {
        $sql_g = "SELECT g.denumire_gestiune AS gestiune,
                         COALESCE(SUM(a.valoare_achizitie),0)        AS ftva,
                         COALESCE(SUM(a.valoare_tva_achizitie),0)    AS tva,
                         COALESCE(SUM(a.valoare_achizitie_cu_tva),0) AS ctva,
                         COALESCE(SUM(a.valoare_pret_amanunt),0)     AS vanzare
                  FROM achizitii a
                  JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
                  JOIN gestiuni g          ON ps.id_gestiune = g.id_gestiune
                  WHERE a.nr_nir = :nr_nir
                  GROUP BY g.denumire_gestiune
                  ORDER BY g.denumire_gestiune";
        $stmt_g = $pdo->prepare($sql_g);
        $stmt_g->execute(['nr_nir' => $nr_nir]);
        $by_gestiune = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5) (Opțional) Totaluri pe Gestiune × Cota TVA — util dacă vrei detaliu fin
    $by_gx = [];
    if ($nr_nir !== null) {
        $sql_gx = "SELECT g.denumire_gestiune AS gestiune,
                          a.cota_tva,
                          COALESCE(SUM(a.valoare_achizitie),0)        AS ftva,
                          COALESCE(SUM(a.valoare_tva_achizitie),0)    AS tva,
                          COALESCE(SUM(a.valoare_achizitie_cu_tva),0) AS ctva
                   FROM achizitii a
                   JOIN produse_servicii ps ON a.cod_p = ps.cod_produs
                   JOIN gestiuni g          ON ps.id_gestiune = g.id_gestiune
                   WHERE a.nr_nir = :nr_nir
                   GROUP BY g.denumire_gestiune, a.cota_tva
                   ORDER BY g.denumire_gestiune, a.cota_tva";
        $stmt_gx = $pdo->prepare($sql_gx);
        $stmt_gx->execute(['nr_nir' => $nr_nir]);
        $by_gx = $stmt_gx->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $db_error_nir = "Eroare DB: " . htmlspecialchars($e->getMessage());
}
?>

<?php if ($db_error_nir): ?>
    <div class="alert alert-danger"><?= $db_error_nir; ?></div>
<?php else: ?>

    <div class="card mb-3">
        <div class="card-header">Detalii și Totaluri NIR </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <?php if ($nir_details): ?>
                        <p><b>Număr NIR:</b> <?= htmlspecialchars($nir_details['nr_nir']); ?></p>
                        <p><b>Data NIR:</b> <?= htmlspecialchars(date('d-m-Y', strtotime($nir_details['data_nir']))); ?></p>
                        <p><b>Document Intrare:</b> <?= htmlspecialchars($nir_details['serie_doc_int']).' '.htmlspecialchars($nir_details['nr_doc_int']); ?></p>
                        <p><b>Data Document Intrare:</b> <?= htmlspecialchars(date('d-m-Y', strtotime($nir_details['data_doc_int']))); ?></p>
                        <?php if (!empty($nir_details['gestionar'])): ?>
                            <p><b>Gestionar:</b> <?= htmlspecialchars($nir_details['gestionar']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-warning mb-0">Nu s-au găsit detalii generale pentru NIR.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p><b>Valoare Totală Achiziții (fără TVA):</b>
                        <?= htmlspecialchars(number_format($totals['total_ftva'], 5, ',', '.')); ?></p>
                    <p><b>TVA deductibil (achiziții):</b>
                        <?= htmlspecialchars(number_format($totals['total_tva_ded'], 5, ',', '.')); ?></p>
                    <p><b>Valoare Totală cu TVA:</b>
                        <?= htmlspecialchars(number_format($totals['total_ctva'], 5, ',', '.')); ?></p>
                    <hr class="my-2">
                    <p><b>Adaos comercial (total):</b>
                        <?= htmlspecialchars(number_format($totals['total_adaos'], 5, ',', '.')); ?></p>
                    <p><b>TVA neexigibil pe adaos:</b>
                        <?= htmlspecialchars(number_format($totals['total_tva_neex_ad_com'], 5, ',', '.')); ?></p>
                    <p><b>Valoare vânzare la preț cu amănuntul:</b>
                        <?= htmlspecialchars(number_format($totals['total_vanzare'], 5, ',', '.')); ?></p>
                </div>
            </div>

            <?php if (!empty($nir_details['observatii'])): ?>
                <hr>
                <p><b>Observații NIR:</b> <?= nl2br(htmlspecialchars($nir_details['observatii'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Totaluri pe cote TVA -->
    <div class="card mb-3">
        <div class="card-header">Totaluri pe cote TVA</div>
        <div class="card-body">
            <?php if (empty($by_tva)): ?>
                <p class="mb-0">Nu există poziții pentru acest NIR.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th class="text-center">Cota TVA</th>
                            <th class="text-end">Total fără TVA</th>
                            <th class="text-end">TVA</th>
                            <th class="text-end">Total cu TVA</th>
                            <th class="text-end">Vânzare (preț amănunt)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($by_tva as $r): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars((string)$r['cota_tva']); ?>%</td>
                                <td class="text-end"><?= number_format((float)$r['ftva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['tva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['ctva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['vanzare'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Totaluri pe gestiuni -->
    <div class="card mb-3">
        <div class="card-header">Totaluri pe gestiuni</div>
        <div class="card-body">
            <?php if (empty($by_gestiune)): ?>
                <p class="mb-0">Nu există poziții pentru acest NIR.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Gestiune</th>
                            <th class="text-end">Total fără TVA</th>
                            <th class="text-end">TVA</th>
                            <th class="text-end">Total cu TVA</th>
                            <th class="text-end">Vânzare (preț amănunt)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($by_gestiune as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['gestiune']); ?></td>
                                <td class="text-end"><?= number_format((float)$r['ftva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['tva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['ctva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['vanzare'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- (Opțional) Gestiune × Cota TVA: pune display:none dacă vrei doar la nevoie -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Detaliu: Gestiune × Cota TVA</span>
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    onclick="this.closest('.card').querySelector('.card-body').classList.toggle('d-none')">
                Afișează/Ascunde
            </button>
        </div>
        <div class="card-body d-none">
            <?php if (empty($by_gx)): ?>
                <p class="mb-0">Nu există poziții pentru acest NIR.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Gestiune</th>
                            <th class="text-center">Cota TVA</th>
                            <th class="text-end">Total fără TVA</th>
                            <th class="text-end">TVA</th>
                            <th class="text-end">Total cu TVA</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($by_gx as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['gestiune']); ?></td>
                                <td class="text-center"><?= htmlspecialchars((string)$r['cota_tva']); ?>%</td>
                                <td class="text-end"><?= number_format((float)$r['ftva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['tva'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?= number_format((float)$r['ctva'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
// --- END: Interogare și Afișare Detalii și Totaluri NIR (versiune pe baza 'achizitii') ---
?>
