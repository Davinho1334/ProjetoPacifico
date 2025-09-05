<?php
header('Content-Type: application/json');
require 'db.php';

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