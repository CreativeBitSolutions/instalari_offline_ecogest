<?php // modal_sume_sertar.php ?>
<div class="modal fade" id="sume_sertar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sume Sertar Tura Curenta</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class='table table-bordered'>
                    <?php
                        // --- START MODIFICARE ---
                        // Interogare pentru a prelua sumele turei, înlocuind protocol cu glovo (ONLINE)
                        $sume_sertar_sql = "
                            SELECT 
                                COALESCE(SUM(COALESCE(numerar, 0) - COALESCE(rest, 0)), 0) as total_numerar, 
                                COALESCE(sum(card), 0) as total_card, 
                                COALESCE(sum(tichete), 0) as total_tichete, 
                                COALESCE(sum(glovo), 0) as total_online 
                            FROM $tabel_final_note 
                            WHERE cod_inchidere=0 AND status='F' AND operator=:adm_id AND locatie=:locatie";
                        $sume_sertar_stmt = $pdo->prepare($sume_sertar_sql);
                        $sume_sertar_stmt->execute(['adm_id' => $adm_id, 'locatie' => $cod_locatie]);
                        $sume = $sume_sertar_stmt->fetch(PDO::FETCH_ASSOC);
                        // Calculam totalul încasărilor inclusiv online ca asa e corect
                        $total_incasari = $sume['total_numerar'] + $sume['total_card'] + $sume['total_tichete']+$sume['total_online'];
                        // --- END MODIFICARE ---
                    ?>
                    <tr>
                        <td>Numerar</td>
                        <td><?php echo number_format($sume['total_numerar'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>Card</td>
                        <td><?php echo number_format($sume['total_card'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>Tichete</td>
                        <td><?php echo number_format($sume['total_tichete'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>ONLINE</td>
                        <td><?php echo number_format($sume['total_online'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr style="font-weight: bold; background-color: #f8f9fa; font-size: 1.1em;">
                        <td>TOTAL TURĂ</td>
                        <td><?php echo number_format($total_incasari, 2, '.', ''); ?> RON</td>
                    </tr>
                    </table>
            </div>
        </div>
    </div>
</div>
