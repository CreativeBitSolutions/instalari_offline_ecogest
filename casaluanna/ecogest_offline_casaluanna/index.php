<?php require_once __DIR__.'/offline_runtime.php';casa_session();header('Location: '.(empty($_SESSION['admin_id'])?'login.php':'facturi.php'));
