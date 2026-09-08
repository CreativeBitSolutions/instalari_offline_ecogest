<?php session_start();
date_default_timezone_set("Europe/Bucharest");

$session_login_redirect = isset($session_login_redirect) ? $session_login_redirect : 'agecs_login.php';

if (!isset($_SESSION['admin_id'])) {
    printf("<script>location.href='%s'</script>", $session_login_redirect);
    exit();
}

include('db.php');
$live_id = 12;

function offline_sales_only_enabled(): bool
{
    return (bool)offline_config_value('offline_mode', true);
}

function offline_sales_only_guard(): void
{
    if (!offline_sales_only_enabled()) {
        return;
    }

    $script = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $blockedExact = [
        'add_admin.php',
        'add_category.php',
        'add_furnizor.php',
            'adauga_produs.php',
            'adauga_tableta.php',
            'admin_categories.php',
            'admin_list.php',
            'bonuri_de_consum.php',
            'bonuri_de_consum_personalizate.php',
            'categorii_admin.php',
            'chitante.php',
            'client_chitanta.php',
            'config_firma.php',
            'consum.php',
        'creare_bon_simplu.php',
        'creare_locatie_mese.php',
        'creare_monetar.php',
        'delete_admin.php',
        'dispp1.php',
        'dispp2.php',
        'dispozitii.php',
        'documente.php',
        'edit_admin.php',
        'edit_category.php',
            'edit_profile.php',
            'factura_bon.php',
            'facturi_bonuri.php',
            'fisa_furnizor.php',
            'furnizor.php',
            'furnizori_admin.php',
            'intrari_in_gestiune_valoric.php',
            'lista_note.php',
            'lista_note_hotel.php',
            'modifica_produs.php',
            'note_de_receptie.php',
            'nomenclator.php',
            'product_details.php',
            'raport_nomenclator.php',
            'terti.php',
        'update_stoc_produse.php',
        'update_sgr.php',
        'user_register.php',
    ];

    $blockedPrefixes = [
        'nir',
        'creare_nir',
        'detalii_nir',
        'genereaza_etichete_nir',
        'importa_nir',
        'listeaza_nir',
        'load_nir',
        'modifica_nir',
        'raport_nir',
        'sterge_nir',
    ];

    if (in_array($script, $blockedExact, true)) {
        $_SESSION['offline_blocked_message'] = 'Funcția este disponibilă doar în aplicația online.';
        header('Location: vanzare_magazin.php');
        exit();
    }

    foreach ($blockedPrefixes as $prefix) {
        if (strpos($script, $prefix) === 0) {
            $_SESSION['offline_blocked_message'] = 'Funcția este disponibilă doar în aplicația online.';
            header('Location: vanzare_magazin.php');
            exit();
        }
    }
}

offline_sales_only_guard();

$user_check = $_SESSION['admin_id'];

$zsql = "SELECT admin_id FROM $tabel_final_admins WHERE admin_id = :id AND admin_id IN (SELECT admin_id FROM offline_online_operators)";
$zstmt = $pdo->prepare($zsql);
$zstmt->execute([':id' => $user_check]);

$row = $zstmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    printf("<script>location.href='%s'</script>", $session_login_redirect);
    exit();
}

$live_id = 12;
?>
