<?php
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

// precisa ter validado a senha de acesso antes
if (empty($_SESSION['allow_admin_signup'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Acesso negado. Valide a senha de acesso primeiro.']);
  exit;
}

$name  = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$tel = trim($_POST['tel'] ?? '');
$pass  = $_POST['password'] ?? '';

if(!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6){
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Dados inválidos']);
  exit;
}

try {
  $stmt = pdo()->prepare("INSERT INTO admins (name,email,password_hash,tel) VALUES (?,?,?,?)");
  $stmt->execute([
    $name,
    $email,
    password_hash($pass, PASSWORD_DEFAULT),
    $tel ?: null
  ]);

  unset($_SESSION['allow_admin_signup']); // só vale 1 vez

  audit('CREATE_ADMIN','admins', pdo()->lastInsertId(), [
    'email'=>$email,
    'name'=>$name
  ]);

  echo json_encode(['success'=>true]);
} catch(PDOException $e) {
  if ($e->getCode() == 23000) { // chave duplicada (email já existe)
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'E-mail já cadastrado']);
  } else {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro no servidor: '.$e->getMessage()]);
  }
}
?>