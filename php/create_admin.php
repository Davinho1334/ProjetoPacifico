<?php
require 'db.php'; // usa as credenciais do db.php abaixo
$cpf = '12345678901';
$senha = '123456';
$nome = 'Administrador Demo';
$hash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare("INSERT INTO admins (cpf, senha_hash, nome) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $cpf, $hash, $nome);
if($stmt->execute()){
  echo "Admin criado. CPF: $cpf / senha: $senha";
} else {
  echo "Erro: " . $stmt->error;
}
?>
