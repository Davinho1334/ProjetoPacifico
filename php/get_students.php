<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// PROTEGE ENDPOINT
if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
}

// se receber id -> retorna objeto
if(isset($_GET['id']) && $_GET['id'] !== ''){
  $id = intval($_GET['id']);
  $stmt = $mysqli->prepare("SELECT id, ra, nome, cpf, ano_nascimento, curso, turno, serie, status, cargaSemanal, bolsa, escola, criado_em FROM alunos WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  echo json_encode(['success'=>true,'data'=>$row]);
  $stmt->close();
  $mysqli->close();
  exit;
}

// sem id -> retorna array
$res = $mysqli->query("SELECT id, ra, nome, cpf, ano_nascimento, curso, turno, serie, status, cargaSemanal, bolsa, escola, contato_aluno, idade, relatorio, observacao, empresa_id, inicio_trabalho, fim_trabalho, renovou_contrato, criado_em FROM alunos ORDER BY criado_em DESC");
$data = [];
if($res){
  while($row = $res->fetch_assoc()){
    $data[] = $row;
  }
}
echo json_encode(['success'=>true,'data'=>$data]);
$mysqli->close();
?>