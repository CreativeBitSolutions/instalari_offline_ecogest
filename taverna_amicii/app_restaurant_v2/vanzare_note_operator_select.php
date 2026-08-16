<?php
/* ===================================================================
 *  note_operator_select.php
 *  Primește din POST valoarea "nrbon|cod_masa", actualizează sesiunea
 *  și tabelele necesare, apoi redirecționează la vanzare_restaurant.php
 * =================================================================*/
include "session.php";          // ↳ creează $pdo + pornește/continuă sesiunea

/* ---------------------------------------------------------------
 *  1) Validare date
 * ------------------------------------------------------------- */
if (empty($_POST['nota_selectata'])) {
    header("Location: vanzare_restaurant.php");
    exit;
}

list($nr_bon, $cod_masa) = explode('|', $_POST['nota_selectata'], 2);

/* ---------------------------------------------------------------
 *  2) Setăm în sesiune
 * ------------------------------------------------------------- */
$_SESSION['nr_bon']       = $nr_bon;
$_SESSION['masa_curenta'] = $cod_masa;
$_SESSION['trimis_comanda']=0;

            if (!isset($_SESSION['no_session_validation']) || $_SESSION['no_session_validation'] != 1) {

/* ---------------------------------------------------------------
 *  3) Actualizăm tabelul ultim_bon_conectat
 * ------------------------------------------------------------- */
$dateTime   = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
$updateTime = $dateTime->format('Y-m-d H:i:s');

restaurantTouchUltimBonConectat($pdo, (int)$_SESSION['cod_locatie'], (int)$nr_bon, $updateTime);
            }
/* ---------------------------------------------------------------
 *  4) Înapoi în aplicația de vânzare
 * ------------------------------------------------------------- */
header("Location: vanzare_restaurant.php");
exit;
?>
