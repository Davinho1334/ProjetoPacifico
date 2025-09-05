<?php
<?php
session_start();
header('Content-Type: application/json');
require 'php/db.php';

// Protege o endpoint: só admins logados podem acessar
if(!isset($_SESSION['admin_id'])){
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autorizado']);
  exit;
}

$res = $mysqli->query("SELECT id, ra, nome, cpf, ano_nascimento, curso, turno, serie, status, criado_em FROM alunos ORDER BY criado_em DESC");
$data = [];
if($res){
  while($row = $res->fetch_assoc()){
    $data[] = $row;
  }
}
echo json_encode(['success'=>true,'data'=>$data]);
$mysqli->close();
?>