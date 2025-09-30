<?php
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

$stmt = pdo()->prepare("SELECT * FROM admins WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if(!$admin || !password_verify($pass, $admin['password_hash'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Credenciais inválidas']);
  exit;
}

$_SESSION['admin_id'] = (int)$admin['id'];
$_SESSION['admin_name'] = $admin['name'];

pdo()->prepare("UPDATE admins SET last_login_at=NOW() WHERE id=?")->execute([$admin['id']]);

[$ip,$ua] = client_meta();
pdo()->prepare("INSERT INTO admin_sessions (admin_id, ip, user_agent) VALUES (?,?,?)")
    ->execute([$admin['id'],$ip,$ua]);

audit('ADMIN_LOGIN');

echo json_encode(['success'=>true]);
?>