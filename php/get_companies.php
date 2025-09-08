<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

if(!isset($_SESSION['admin_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autorizado']);
  exit;
}

$res = $mysqli->query("SELECT id, nome, cnpj, contato, endereco FROM empresas ORDER BY nome");
$empresas = [];
while($row = $res->fetch_assoc()){
  $empresas[] = $row;
}
echo json_encode(['success'=>true,'data'=>$empresas]);
$mysqli->close();
?>