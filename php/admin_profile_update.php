<?php
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');
require_admin();

$adminId = $_SESSION['admin_id'];
$name  = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$tel = trim($_POST['tel'] ?? '');

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';

if(!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)){
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Dados inválidos']);
  exit;
}

pdo()->beginTransaction();
try{
  // Se for trocar o e-mail/telefone/nome
  $stmt = pdo()->prepare("UPDATE admins SET name=?, email=?, tel=? WHERE id=?");
  $stmt->execute([$name,$email ?: null,$tel ?: null,$adminId]);

  // Senha opcional
  if($new){
    // valida senha atual
    $stmt = pdo()->prepare("SELECT password_hash FROM admins WHERE id=?");
    $stmt->execute([$adminId]);
    $hash = $stmt->fetchColumn();
    if(!$hash || !password_verify($current, $hash)){
      throw new Exception('Senha atual incorreta');
    }
    pdo()->prepare("UPDATE admins SET password_hash=? WHERE id=?")
        ->execute([ password_hash($new, PASSWORD_DEFAULT), $adminId ]);
  }

  audit('UPDATE_ADMIN','admins',$adminId,['name'=>$name,'email'=>$email,'changed_password'=> (bool)$new]);
  pdo()->commit();
  echo json_encode(['success'=>true,'message'=>'Dados salvos']);
}catch(Exception $e){
  pdo()->rollBack();
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>