<?php
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
$nome = $_POST['nome'] ?? '';
$cpf = preg_replace('/\D/','', $_POST['cpf'] ?? '');
$ra = $_POST['ra'] ?? '';
$ano = $_POST['ano_nascimento'] ?? '';
$curso = $_POST['curso'] ?? '';
$turno = $_POST['turno'] ?? '';
$serie = $_POST['serie'] ?? '';

if(!$id || !$nome || !$cpf || !$curso){
  echo json_encode(['success'=>false,'message'=>'Campos obrigatórios ausentes.']);
  exit;
}

// Atualiza dados do aluno
$stmt = $mysqli->prepare("UPDATE alunos SET ra=?, nome=?, cpf=?, ano_nascimento=?, curso=?, turno=?, serie=? WHERE id=?");
$stmt->bind_param('sssssssi', $ra, $nome, $cpf, $ano, $curso, $turno, $serie, $id);

if($stmt->execute()){
  echo json_encode(['success'=>true,'message'=>'Aluno atualizado com sucesso.']);
} else {
  echo json_encode(['success'=>false,'message'=>'Erro ao atualizar: '.$stmt->error]);
}
$stmt->close();
$mysqli->close();
?>