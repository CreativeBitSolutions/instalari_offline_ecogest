<?php
// vanzare_metode_plata.php
include('session.php');
$nr_bon = $_GET['nr_bon'] ?? $_SESSION['nr_bon'] ?? 0;

// Logica pentru afișarea/ascunderea butoanelor a fost păstrată
// 1. Condiții pentru afișarea butoanelor de plată
$stmt = $pdo->prepare("SELECT COUNT(*) FROM det_note WHERE nr_bon = :nr_bon AND t_list = 0 AND cod_p != -1");
$stmt->execute([':nr_bon' => $nr_bon]);
$count_t_list = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT listat_nota_plata FROM note WHERE nrbon = :nrbon");
$stmt->execute([':nrbon' => $nr_bon]);
$listat_nota_plata = (int)$stmt->fetchColumn();

$classToHide = ($count_t_list > 0 || $listat_nota_plata == 0) ? 'hide' : '';

// 2. Condiții pentru butonul "Trimite Comanda"
$stmt = $pdo->prepare("SELECT COUNT(*) FROM det_note WHERE nr_bon = :nr_bon AND cod_p != -1");
$stmt->execute([':nr_bon' => $nr_bon]);
$totalProducts = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM det_note WHERE nr_bon = :nr_bon AND t_list = 1 AND cod_p != -1");
$stmt->execute([':nr_bon' => $nr_bon]);
$count_listed = (int)$stmt->fetchColumn();

$classHideTrimite = ($totalProducts > 0 && $totalProducts == $count_listed) || $totalProducts == 0 ? 'hide' : '';

// 3. Condiții pentru butonul "Nota de plată"
$classHideNota = ($count_t_list > 0) ? 'hide' : '';

$client_agecs = $_SESSION['client_id'] ?? null;
?>

<button id="plata_numerar" class="action-btn btn-success <?= $classToHide ?> <?= $client_agecs == 1 ? 'confirmable' : '' ?>" form="plata" type="submit" name="finaliz_bon" value="numerar">
    <i class="fas fa-money-bill-wave"></i> Numerar
</button>
<button id="plata_card" form="plata" type="submit" name="finaliz_bon" value="card" class="action-btn btn-primary <?= $classToHide ?> <?= $client_agecs == 1 ? 'confirmable' : '' ?>">
    <i class="far fa-credit-card"></i> Card
</button>
<button id="plata_numerar_si_card" class="action-btn btn-info <?= $classToHide ?>">
    <i class="fas fa-coins"></i> Plată Mixtă
</button>
<button id="plata_protocol" form="plata" type="submit" name="finaliz_bon" value="protocol" class="action-btn btn-secondary <?= $classToHide ?>">
    <i class="fas fa-file-signature"></i> Proto
</button>
<button id="plata_glovo" form="plata" type="submit" name="finaliz_bon" value="glovo" class="action-btn btn-warning <?= $classToHide ?>" style="<?= $client_agecs == 9 ? 'display:none;' : '' ?>; color:#212529;">
    <i class="fas fa-globe"></i> Online
</button>

<button type="button" class="action-btn btn-primary <?= $classHideNota ?>" id="nota_informativa_de_plata">
    <i class="fas fa-receipt"></i> Notă de plată
</button>
<button type="button" class="action-btn btn-success <?= $classHideTrimite ?>" id="trimite_comanda_bar_buc">
    <i class="fas fa-paper-plane"></i> Trimite Comanda
</button>

<?php
// Butoane pentru masa goală
$verificare_stmt = $pdo->prepare("SELECT 1 FROM $tabel_final_note WHERE nrbon = :nrbon AND status = 'S' LIMIT 1");
$verificare_stmt->execute([':nrbon' => $nr_bon]);
$notaDeschisa = (bool)$verificare_stmt->fetchColumn();

$detalii_stmt = $pdo->prepare("SELECT 1 FROM $tabel_final_det_note WHERE nr_bon = :nrbon LIMIT 1");
$detalii_stmt->execute([':nrbon' => $nr_bon]);
$areDetalii = (bool)$detalii_stmt->fetchColumn();

if ($notaDeschisa && !$areDetalii) {
    echo "
    <form method='POST' action='vanzare_inchide_masa_goala.php' class='w-100'>
        <input type='hidden' name='nrbon' value='$nr_bon'>
        <button type='submit' class='action-btn btn-danger'><i class='fas fa-times-circle'></i> Închide Masa Goală</button>
    </form>";
}
else{
    echo '<button type="button" class="action-btn btn-danger" data-toggle="modal" data-target="#MutaMasaModal">
    <i class="fas fa-exchange-alt mr-1"></i> Mută Masa
</button>';
}
?>

<script>
// Refactorizare proxy butoane pentru a evita dublarea logicilor
// Aceste triggere sunt deja în vanzare_javascript.php, deci pot fi scoase de aici pentru a evita redundanța.
// Păstrăm totuși pentru siguranță, dacă fișierul este încărcat independent.
$(document).ready(function() {
    $('#trimite_comanda_bar_buc').off('click').on('click', function() {
        $('#trimite_produsele_noi').trigger('click');
    });
    $('#nota_informativa_de_plata').off('click').on('click', function() {
        $('#nota_de_plata_client').trigger('click');
    });
});
</script>
