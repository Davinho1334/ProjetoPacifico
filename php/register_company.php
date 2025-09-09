<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// Proteção (somente admin pode cadastrar empresa)
if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
}

$nome       = $_POST['nome']       ?? '';
$cnpj       = $_POST['cnpj']       ?? '';
$contato    = $_POST['contato']    ?? '';
$telefone   = $_POST['telefone']   ?? '';

if(!$nome || !$cnpj){
    echo json_encode(['success'=>false,'message'=>'Nome e CNPJ são obrigatórios']);
    exit;
}

// verifica duplicado
$stmt = $mysqli->prepare("SELECT id FROM companies WHERE cnpj = ?");
$stmt->bind_param('s',$cnpj);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
    echo json_encode(['success'=>false,'message'=>'Empresa já cadastrada com este CNPJ.']);
    exit;
}
$stmt->close();

// insere
$stmt = $mysqli->prepare("INSERT INTO companies (nome, cnpj, contato, telefone, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param('ssss', $nome, $cnpj, $contato, $telefone);

if($stmt->execute()){
    echo json_encode(['success'=>true,'message'=>'Empresa cadastrada com sucesso!']);
} else {
    echo json_encode(['success'=>false,'message'=>'Erro ao cadastrar empresa: '.$stmt->error]);
}
$stmt->close();
$mysqli->close();
?>
