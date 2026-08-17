<?php
session_start();
unset($_SESSION['nr_bon']);
echo json_encode(['unset' => true]);
?>
