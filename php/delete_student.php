<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

// Protege o endpoint
if(!isset($_SESSION['admin_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autorizado']);
  exit;
}

$id = $_POST['id'] ?? null;
if(!$id){
  echo json_encode(['success'=>false,'message'=>'ID do aluno não informado.']);
  exit;
}

$stmt = $mysqli->prepare("DELETE FROM alunos WHERE id=?");
$stmt->bind_param('i', $id);

if($stmt->execute()){
  echo json_encode(['success'=>true,'message'=>'Aluno excluído com sucesso.']);
} else {
  echo json_encode(['success'=>false,'message'=>'Erro ao excluir: '.$stmt->error]);
}
$stmt->close();
$mysqli->close();
?>