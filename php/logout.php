<?php
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

$adminId = $_SESSION['admin_id'] ?? null;
if ($adminId){
  // fecha a última sessão aberta (sem logout_at)
  $stmt = pdo()->prepare("UPDATE admin_sessions SET logout_at=NOW() WHERE admin_id=? AND logout_at IS NULL ORDER BY id DESC LIMIT 1");
  $stmt->execute([$adminId]);
  audit('ADMIN_LOGOUT');
}

session_unset();
session_destroy();

echo json_encode(['success'=>true]);
?>