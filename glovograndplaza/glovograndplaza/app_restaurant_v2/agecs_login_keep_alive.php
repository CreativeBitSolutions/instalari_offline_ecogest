<?php
// agecs_login_keep_alive.php
session_start();
// nu includem nimic altceva – doar repornim sesiunea și trimitem OK
echo json_encode([
    'status'  => 'success',
    'message' => 'Session is still alive'
]);
