<?php 
include('header.php');

// Verificăm dacă id_factura este setat în URL
if (!isset($_GET['id_factura'])) {
    printf("<script>location.href='facturi.php'</script>");
    exit(); // Oprim execuția scriptului după redirecționare
}

$id_factura = $_GET['id_factura']; // Preluăm id_factura din URL

// Inițializăm variabilele pentru datele facturii
$serie_factura = '';
$den_tert = '';
$adresa = '';
$cui_cif = '';
$cont_banca = '';
$judet = '';
$nr_reg_com = '';
$banca = '';
$data_factura = '';
$data_scadenta = '';

// Preluăm datele facturii din baza de date folosind id_factura
$csql = "SELECT * FROM facturi WHERE id_factura=:id_factura";
$cstmt = $pdo->prepare($csql);  
$cstmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
$cstmt->execute(); 

while ($row = $cstmt->fetch(PDO::FETCH_ASSOC)) { 
    $den_tert = $row['nume'];
    $adresa = $row['adresa']; 
    $cui_cif = $row['cod_fiscal']; 
    $cont_banca = $row['iban']; 
    $judet = $row['adresa_judet'];
    $nr_reg_com = $row['cod_inmatriculare'];
    $banca = $row['banca'];
    $serie_factura = $row['serie_factura']; // Extragem seria facturii
    $nr_factura = $row['nr_factura']; // Extragem numărul facturii
    $data_factura = $row['data_factura'];
    $data_scadenta = $row['data_scadenta'];
    $tip_factura = $row['tip_factura'];
    $den_tip_factura = '';
    
    switch ($tip_factura) {
        case 380:
            $den_tip_factura = 'Factură de vânzare-cumpărare';
            break;
        case 381:
            $den_tip_factura = 'Factură storno sau notă de credit';
            break;
        case 384:
            $den_tip_factura = 'Factură care corectează o altă factură emisă anterior cu greșeli';
            break;
        case 389:
            $den_tip_factura = 'Autofactură';
            break;
        case 751:
            $den_tip_factura = 'Factură pentru bonuri fiscale';
            break;
        default:
            $den_tip_factura = 'Tip de factură necunoscut';
            break;
    }
   
    
    
}
// Verificăm dacă există înregistrări în istoric_incarcari pentru id_factura CU status = 'încărcat'
$check_sql = "SELECT COUNT(*) FROM istoric_incarcari WHERE id_factura = :id_factura AND status = 'încărcat'";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
$check_stmt->execute();
$factura_trimisa_anaf = $check_stmt->fetchColumn() > 0;

// Preluam emailul firmei pentru pre-completarea modalului
$df_ef_stmt = $pdo->query("SELECT email FROM casa_online_company LIMIT 1");
$df_ef_row  = $df_ef_stmt->fetch(PDO::FETCH_ASSOC);
$email_firma_detalii = htmlspecialchars($df_ef_row['email'] ?? '');
?>

<!-- Begin Page Content --> 
<div class="container-fluid"> 

    <!-- Page Heading -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"></h6>
        </div>
        <div class="card-body">
            <style>.Optiuni{width:8em;}</style>     

            <div class='table-responsive'>
                <link href="vendor/offline/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
                <link href="vendor/offline/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
                <table class='table table-bordered'>   
                    <tr>
                        <td><h4>Datele Clientului:</h4></td>
                        <td>
                            <?php
                            echo "<b>Denumire</b>  $den_tert 
                            <br><b>Adresa</b>  $adresa
                            <br>
                            <b>Judet</b>$judet
                            <br>
                            <b>CUI/CIF</b>  $cui_cif
                            <br>
                            <b>Nr.ord.reg.com</b>:$nr_reg_com
                            <br>
                            <b>Cont IBAN</b>  $cont_banca
                            <br>
                            <b>Banca</b>$banca";
                            ?>
                        </td>
                    </tr> 
                    <tr> 
                        <td colspan='2'><h4>Seria <?php echo $serie_factura; ?>  Numar <?php echo $nr_factura; ?> Tip: <?php echo $den_tip_factura;?></h4></td>
                    </tr>
                    <tr> 
                        <td><h4>Data Facturii</h4></td>
                        <td><?php echo date('d-m-Y', strtotime($data_factura)); ?></td>
                    </tr> 
                    <tr> 
                        <td><h4>Data Scadenta:</h4></td>
                        <td><?php echo date('d-m-Y', strtotime($data_scadenta)); ?></td>
                    </tr> 
                </table>

                <table class='table table-bordered'>
                    <tr>
                        <th rowspan="2">Nr.Crt.</th>
                        <th width="15%" rowspan="2">Denumire<br>Produs</th>
                        <th rowspan="2">U.M.</th>
                        <th rowspan="2">Cant.</th>
                        <th colspan="2">Vanzare</th>
                        <th rowspan="2">TVA</th>
                        <th rowspan="2">Valoare<br>(cu TVA)</th>
                    </tr>
                    <tr>
                        <td><b>P.U.<br>(fara TVA)</b></td>
                        <td><b>Valoare<br>(fara TVA)</b></td>
                    </tr>
                    <tr>
                        <td><b>0</b></td>
                        <td width="15%"><b>1</b></td>
                        <td><b>2</b></td>
                        <td><b>3</b></td>
                        <td><b>4</b></td>
                        <td><b>5(=3x4)</b></td>
                        <td><b>6</b></td>
                        <td><b>7(=5+6)</b></td>
                    </tr>
                    
                    <?php
                    // Afișare rânduri vanzari filtrate după id_factura
                    $nr_crt1 = 1;
                    $f_sql = "SELECT * FROM vanzari WHERE id_factura=:id_factura";    
                    $f_stmt = $pdo->prepare($f_sql);  
                    $f_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
                    $f_stmt->execute(); 

                    while ($row = $f_stmt->fetch(PDO::FETCH_ASSOC)) { 
                        $produs = $row['den_p'];
                        $um = $row['um'];
                        $cantitate = $row['cantitate'];
                        $tva_col = $row['tva_col'];
                        $pret_vanzare = $row['pret_vanzare'];
                        $valoare_vanzare = $row['valoare_vanzare'];
                        $id_vanz = $row['id_vanz'];
                        $valoare_vanzare_c_tva = $row['valoare_vanzare_cu_tva'];
                        $cota = $row['cota_tva'];
                        $d = $row['discount'];
                        $pret_fara_tva = round(($valoare_vanzare / $cantitate), 2);

                        echo "
                        <tr>
                            <td width='10%'>$nr_crt1</td>
                            <td>$produs</td>
                            <td>$um</td>
                            <td>
                                <button disabled style='display:inline-block;' name='$id_vanz' value='$id_vanz' data-value='$cantitate' class='btn btn-primary btn-block modif_cant' data-toggle='modal' data-target='#Cantitate'>X  $cantitate</button>
                            </td>    
                            <td>$pret_fara_tva</td>
                            <td>$valoare_vanzare</td>
                            <td>$tva_col</td>
                            <td><div>$valoare_vanzare_c_tva</div></td>
                        </tr>";

                        $nr_crt1++;
                    }
                    ?>
                    <th colspan="5">Total</th>  
                    <th>
                    <?php 
                    $f_tot_sql = "SELECT SUM(valoare_vanzare) as a FROM vanzari WHERE id_factura=:id_factura";    
                    $f_tot_stmt = $pdo->prepare($f_tot_sql);  
                    $f_tot_stmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
                    $f_tot_stmt->execute();
                    $valoare_f_vz = $f_tot_stmt->fetchColumn();
                    echo $valoare_f_vz; 
                    ?>
                    </th> 
                    <th>
                    <?php 
                    $total_tva_col = $pdo->query("SELECT SUM(tva_col) as b FROM vanzari WHERE id_factura='$id_factura';")->fetchColumn();
                    echo $total_tva_col;
                    ?>
                    </th> 
                    <th>
                    <?php 
                    $total_val_vz_cu_tva = $pdo->query("SELECT SUM(valoare_vanzare_cu_tva) as d FROM vanzari WHERE id_factura='$id_factura';")->fetchColumn();
                    $total_discount = $pdo->query("SELECT SUM(discount) as c FROM vanzari WHERE id_factura='$id_factura';")->fetchColumn();
                    echo $total_val_vz_cu_tva;
                    ?>
                    </th>
                </table>

   <?php 
// Afișăm butonul "Modifică Factura" doar dacă factura nu a fost trimisă la ANAF
if (!$factura_trimisa_anaf && !casa_invoice_readonly($pdo,(int)$id_factura) && !casa_invoice_anaf_locked($pdo,(int)$id_factura)) {
    echo '<a href="factura.php?id_factura=' . $id_factura . '" class="btn btn-primary btn-block">Modifică Factura</a>';


} else {
    echo '<p class="alert alert-info">Copia offline este pentru consultare. Modificările și trimiterea la ANAF se fac din aplicația online.</p>';
    if(!empty($casaPageMeta['online_id']))echo '<a class="btn btn-primary btn-block" target="_blank" rel="noopener" href="'.casa_h(casa_config()['online_base_url']).'/detalii_factura.php?id_factura='.(int)$casaPageMeta['online_id'].'">Deschide factura online</a>';
    echo '<a href="duplicare_factura_corectare.php?id_factura=' . urlencode($id_factura) . '" class="btn btn-warning btn-block" style="display: none;" 
          onclick="return confirm(\'Ești sigur că vrei să generezi o factură corectată?\');">
                Generează Factură Corectare Antet
          </a>';
}
?>


                <br>
        <a href='listeaza_fact.php?id_factura=<?php echo $id_factura; ?>' class='btn btn-primary btn-block'>Listează factură</a>
        <a href='descarca_xml_saga_factura.php?id_factura=<?php echo urlencode((string)$id_factura); ?>' class='btn btn-outline-secondary btn-block'>
            <i class='fas fa-file-code'></i> Descarcă XML import SAGA
        </a>

        <button class='btn btn-info btn-block' data-toggle='modal' data-target='#modal_email_factura'>
            <i class='fas fa-envelope'></i> Trimite pe email
        </button>

        <?php 
        if (isset($_POST['list_fact'])) {
            printf("<script>location.href='listeaza_fact.php?id_factura=$id_factura'</script>");
        }


              
    if(!casa_invoice_readonly($pdo,(int)$id_factura))echo '<a href="genereaza_bon.php?id_factura=' . urlencode($id_factura) . '" class="btn btn-success btn-block" 
          onclick="return confirm(\'Ești sigur că vrei să trimiți această factură la casa de marcat?\');">
                Trimite la Casa de Marcat
          </a>
          
          
          <a href="https://docs.google.com/document/d/1BF0YKedrpc_gj6B0VRHGLdnnn0pylhVXZ7Tj6lRyU4M/edit?usp=sharing" class="btn btn-danger btn-block" target="_blank" rel="noopener noreferrer">
Nu a iesit bonul la casa de marcat? Click pentru a verifica urmatoarele. Fisco
</a>';

                ?>
                
 <a href="preview_factura_stornare.php?id_factura=<?php echo urlencode($id_factura); ?>" class="btn btn-danger btn-block <?= $hideStep2 ?>">
    Vezi facturi / vânzări / mișcări + Generează stornare
</a>
            </div>
        </div>
    </div>

</div>
<!-- /.container-fluid -->

</div>
<!-- End of Main Content -->

<!-- Footer -->
<?php include('footer.php');?>
<!-- End of Footer -->

</div>
<!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Logout Modal-->
<!-- Bootstrap core JavaScript-->



<!-- Core plugin JavaScript-->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="js/sb-admin-2.min.js"></script>

<!-- Page level plugins -->



<!-- Page level custom scripts -->


<script>
$(document).ready(function () {
    $('#example').DataTable({
        "pageLength": 50
    });
});
</script>
<script>
$(document).ready(function() {
    $(document).on('click', '#ef_btn_trimite', function() {
        var email = $('#ef_email_dest').val().trim();
        var $btn  = $(this);
        var $msg  = $('#ef_msg');

        if (!email) {
            $msg.html('<div class="alert alert-danger py-1 mt-2">Completați adresa de email.</div>');
            return;
        }

        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            $msg.html('<div class="alert alert-danger py-1 mt-2">Adresă de email invalidă.</div>');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Se trimite...');
        $msg.html('');

        $.post('email_factura.php', {
            id_factura: <?php echo (int)$id_factura; ?>,
            email: email
        }, function(resp) {
            if (resp.ok) {
                $msg.html('<div class="alert alert-success py-1 mt-2"><i class="fas fa-check"></i> Email trimis cu succes!</div>');
                $btn.html('<i class="fas fa-check"></i> Trimis');
                setTimeout(function() {
                    $('#modal_email_factura').modal('hide');
                }, 800);
            } else {
                $msg.html('<div class="alert alert-danger py-1 mt-2">' + $('<div>').text(resp.err).html() + '</div>');
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.log('AJAX FAIL:', status, error);
            console.log('HTTP STATUS:', xhr.status);
            console.log('RESPONSE TEXT:', xhr.responseText);

            var msg = 'Eroare de comunicare cu serverul.';
            if (xhr.responseText) {
                msg += '<br><small style="word-break:break-word;">' + $('<div>').text(xhr.responseText).html() + '</small>';
            }

            $msg.html('<div class="alert alert-danger py-1 mt-2">' + msg + '</div>');
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');
        });
    });

    $('#modal_email_factura').on('hidden.bs.modal', function() {
    $('#ef_msg').html('');
    $('#ef_btn_trimite').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Trimite');

    $('body').removeClass('modal-open');
    $('.modal-backdrop').remove();
    $('body').css('padding-right', '');
    $('body').css('overflow', '');

    $('.btn-info[data-target="#modal_email_factura"]').trigger('focus');
    });
});
</script>
<script>
$(document).ready(function() {
    $('#filtru_an').on('change', function() {
        var filtru_an = $(this).val();
        $('#filtru_an_gen_raport').val(filtru_an);
    });

    $('#filtru_activ').on('change', function() {
        var filtru_activ = $(this).val();
        $('#filtru_activ_gen_raport').val(filtru_activ);
    });

    $('#filtru_legitimatie').on('change', function() {
        var filtru_legitimatie = $(this).val();
        $('#filtru_legitimatie_gen_raport').val(filtru_legitimatie);
    });

    $('.clasa_filtru_grupa').on('change', function() { 
        var form = document.getElementById("form_filtru"),
            inputs = form.getElementsByTagName("input"),
            arr = [];
        var filtru_de_grupa = "";
        for (var i = 0, max = inputs.length; i < max; i += 1) {
            if (inputs[i].type === "checkbox" && inputs[i].checked) {
                filtru_de_grupa = filtru_de_grupa + inputs[i].value + "/";
                arr.push(inputs[i].value);
            }
        }
        $('#filtru_grupa_gen_raport').val(filtru_de_grupa);
    });
});
</script>	
<!-- Modal Trimite Email Factura -->
<div class="modal fade" id="modal_email_factura" tabindex="-1" role="dialog" aria-labelledby="emailFacturaLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="emailFacturaLabel"><i class="fas fa-envelope"></i> Trimite factura pe email</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Factura <strong><?php echo htmlspecialchars($serie_factura . ' ' . $nr_factura); ?></strong> va fi trimisă ca atașament PDF.</p>
        <div class="form-group">
          <label for="ef_email_dest">Adresă email destinatar:</label>
          <input type="email" class="form-control" id="ef_email_dest"
                 value="<?php echo $email_firma_detalii; ?>"
                 placeholder="exemplu@email.ro">
        </div>
        <div id="ef_msg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="button" class="btn btn-info" id="ef_btn_trimite">
          <i class="fas fa-paper-plane"></i> Trimite
        </button>
      </div>
    </div>
  </div>
</div>
<!-- End Modal Trimite Email Factura -->
</body>
</html>
