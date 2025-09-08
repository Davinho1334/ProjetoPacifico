<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

if(!isset($_SESSION['admin_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autorizado']);
  exit;
}

$nome = $_POST['nome'] ?? '';
$cnpj = $_POST['cnpj'] ?? null;
$contato = $_POST['contato'] ?? null;
$endereco = $_POST['endereco'] ?? null;

if(!$nome){
  echo json_encode(['success'=>false,'message'=>'Nome da empresa é obrigatório']);
  exit;
}

$stmt = $mysqli->prepare("INSERT INTO empresas (nome, cnpj, contato, endereco) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nome, $cnpj, $contato, $endereco);

if($stmt->execute()){
  echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
}else{
  echo json_encode(['success'=>false,'message'=>$stmt->error]);
}

$stmt->close();
$mysqli->close();
