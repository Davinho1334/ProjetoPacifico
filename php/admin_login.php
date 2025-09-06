<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

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
    // cria sessão do admin
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_nome'] = $nome;

    echo json_encode(['success'=>true,'message'=>'Login OK']);
} else {
    echo json_encode(['success'=>false,'message'=>'Credenciais inválidas.']);
}

$stmt->close();
$mysqli->close();
?>
