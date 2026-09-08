<?php
require_once __DIR__.'/offline_runtime.php';casa_session();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    casa_csrf();$row=casa_one(casa_db(),'SELECT * FROM utilizatori WHERE adresa_de_email=? AND dezactivat=0',[trim((string)($_POST['email_address']??''))]);
    $password=trim((string)($_POST['password']??''));$hash=(string)($row['parola']??'');
    if($row&&(password_verify($password,$hash)||(preg_match('/^[a-f0-9]{32}$/i',$hash)&&hash_equals(strtolower($hash),md5($password))))){
        session_regenerate_id(true);$_SESSION['admin_id']=(int)$row['id_utilizator'];$_SESSION['adminloggedin']=$row['adresa_de_email'];$_SESSION['rang']=$row['rang'];header('Location: facturi.php');exit;
    }$error='Date de autentificare incorecte.';
}
?><!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="casa-csrf" content="<?=casa_h($_SESSION['casa_csrf'])?>"><title>Casa Luanna, autentificare</title><link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css"><script src="offline_ui.js" defer></script></head><body class="bg-light"><main class="card p-4 mx-auto mt-5" style="max-width:460px"><h1 class="h3">ECOGEST · Casa Luanna</h1><p>Facturare offline</p><form method="post"><input type="hidden" name="casa_csrf" value="<?=casa_h($_SESSION['casa_csrf'])?>"><label>Adresa de email</label><input class="form-control mb-3" type="email" name="email_address" required autocomplete="username"><label>Parola</label><input class="form-control mb-3" type="password" name="password" required autocomplete="current-password"><button class="btn btn-primary">Autentificare</button></form><p class="text-danger mt-3"><?=casa_h($error)?></p><small id="casa-sync">Sincronizare automată</small></main></body></html>
