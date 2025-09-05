<?php
header('Content-Type: application/json');
require 'php/db.php';

$cpf = preg_replace('/\D/','', $_POST['cpf'] ?? '');
$senha = $_POST['senha'] ?? '';

if(!$cpf || !$senha){
  echo json_encode(['success'=>false,'message'=>'CPF e senha obrigatórios.']);
  exit;
}

$stmt = $mysqli->prepare("SELECT id, senha_hash, nome FROM admins WHERE cpf = ?");
$stmt->bind_param('s', $cpf);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows === 0){
  echo json_encode(['success'=>false,'message'=>'Credenciais inválidas.']);
  exit;
}
$stmt->bind_result($id, $senha_hash, $nome);
$stmt->fetch();
if(password_verify($senha, $senha_hash)){
  // Para demo: token simples (não use em produção sem JWT/sessões)
  $token = base64_encode($id . '|' . time());
  echo json_encode(['success'=>true,'message'=>'OK','token'=>$token]);
} else {
  echo json_encode(['success'=>false,'message'=>'Credenciais inválidas.']);
}
$stmt->close();
$mysqli->close();
?>