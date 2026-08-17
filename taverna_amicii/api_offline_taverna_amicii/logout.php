<?php // logout.php
session_start();

$params = [
    'cod_locatie'              => $_SESSION['cod_locatie'] ?? null,
    'mod_tableta'              => $_SESSION['mod_tableta'] ?? null,
    'interfata_pos'            => $_SESSION['interfata_pos'] ?? null,
    'no_session_validation'    => $_SESSION['no_session_validation'] ?? null,
    'bucatarie'                => $_SESSION['bucatarie'] ?? null,
    'interfata_restaurant_v2'  => $_SESSION['interfata_restaurant_v2'] ?? null,
    'login_vanzare'            => $_SESSION['login_vanzare'] ?? null,
    'autologin_tableta'        => $_SESSION['autologin_tableta'] ?? null,
	    'viz_admin'        => $_SESSION['viz_admin'] ?? null,

];

$params = array_filter($params, static function ($value) {
    return $value !== null && $value !== '';
});

if (isset($_GET['lock_conflict'])) {
    $params['lock_conflict'] = '1';
}

session_unset();

$redirectUrl = 'conectare.php?' . http_build_query($params);
echo "<script src='js/agecs-tab-lock.js' data-agecs-tab-mode='reset'></script>";
echo "<script>location.href=" . json_encode($redirectUrl) . ";</script>";
