<?php
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autorizado']);
  exit;
}

$stmt = pdo()->prepare("SELECT id,name,email,tel,created_at,last_login_at FROM admins WHERE id=?");
$stmt->execute([$_SESSION['admin_id']]);
echo json_encode(['success'=>true,'data'=>$stmt->fetch()]);
?>