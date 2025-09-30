<?php
require_once __DIR__.'/db.php';

$key = $_POST['access_key'] ?? '';
if (hash_equals(ACCESS_SETUP_KEY, $key)){
  $_SESSION['allow_admin_signup'] = true;
  echo json_encode(['success'=>true]);
} else {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Senha de acesso inválida']);
}
?>