<?php // modal_sume_zi_curenta.php ?>
<div class="modal fade" id="sume_zi_curenta_modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sume Totale Zi Curentă (Înainte de Raport Z)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class='table table-bordered'>
                    <?php
                        // --- START MODIFICARE ---
                        // Interogare pentru a prelua sumele totale, înlocuind protocol cu glovo (ONLINE)
                        $sume_zi_sql = "
                            SELECT 
                                COALESCE(SUM(COALESCE(numerar, 0) - COALESCE(rest, 0)), 0) as total_numerar_zi, 
                                COALESCE(SUM(card), 0) as total_card_zi, 
                                COALESCE(SUM(tichete), 0) as total_tichete_zi, 
                                COALESCE(SUM(glovo), 0) as total_online_zi 
                            FROM $tabel_final_note 
                            WHERE status = 'F' AND COALESCE(nr_raport_z, 0) = 0 AND locatie = :locatie";
                        $sume_zi_stmt = $pdo->prepare($sume_zi_sql);
                        $sume_zi_stmt->execute(['locatie' => $cod_locatie]);
                        $sume_zi = $sume_zi_stmt->fetch(PDO::FETCH_ASSOC);
                        // Calculam totalul încasărilor inclusiv online ca asa e corect
                        $total_zi_incasari = $sume_zi['total_numerar_zi'] + $sume_zi['total_card_zi'] + $sume_zi['total_tichete_zi']+$sume_zi['total_online_zi'];
                        // --- END MODIFICARE ---
                    ?>
                    <tr>
                        <td>Numerar</td>
                        <td><?php echo number_format($sume_zi['total_numerar_zi'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>Card</td>
                        <td><?php echo number_format($sume_zi['total_card_zi'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>Tichete</td>
                        <td><?php echo number_format($sume_zi['total_tichete_zi'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr>
                        <td>ONLINE</td>
                        <td><?php echo number_format($sume_zi['total_online_zi'], 2, '.', ''); ?> RON</td>
                    </tr>
                    <tr style="font-weight: bold; background-color: #f8f9fa; font-size: 1.1em;">
                        <td>TOTAL ZI</td>
                        <td><?php echo number_format($total_zi_incasari, 2, '.', ''); ?> RON</td>
                    </tr>
                    </table>
                 <small class="form-text text-muted">Notă: Aceste sume reprezintă totalul tuturor bonurilor finalizate pentru care nu s-a generat încă un raport Z în locația curentă.</small>
            </div>
        </div>
    </div>
</div>
