<?php
require_once __DIR__.'/database_connection.php';
$data = json_decode(file_get_contents('php://input'), true);
$step = intval($data['step'] ?? 0);
if($step>=1 && $step<=2){
  $_SESSION['completed_steps'][] = $step;
  $_SESSION['current_step'] = min(3, $step+1);
  echo json_encode(['success'=>true]);
} else {
  echo json_encode(['success'=>false,'error'=>'Step invalid']);
}
?>