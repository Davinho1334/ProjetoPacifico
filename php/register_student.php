<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$nome = $_POST['nome'] ?? '';
$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? ''); // normaliza
$ra = $_POST['ra'] ?? null;
$ano = $_POST['ano_nascimento'] ?? null;
$curso = $_POST['curso'] ?? '';
$turno = $_POST['turno'] ?? '';
$serie = $_POST['serie'] ?? '1º';

if(!$nome || !$cpf || !$curso){
  echo json_encode(['success'=>false,'message'=>'Campos obrigatórios ausentes.']);
  exit;
}

// verificar CPF duplicado
$stmt = $mysqli->prepare("SELECT id FROM alunos WHERE cpf = ?");
$stmt->bind_param('s',$cpf);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
  echo json_encode(['success'=>false,'message'=>'CPF já cadastrado.']);
  exit;
}
$stmt->close();

// gerar RA se vazio (prefixo RA + timestamp)
if(!$ra){
  $ra = 'RA'.time();
}

// inserir
$ins = $mysqli->prepare("
  INSERT INTO alunos 
  (ra, nome, cpf, ano_nascimento, curso, turno, serie, status) 
  VALUES (?, ?, ?, ?, ?, ?, ?, 'Em andamento')
");
$ins->bind_param('ssissss', $ra, $nome, $cpf, $ano, $curso, $turno, $serie);

if($ins->execute()){
  echo json_encode(['success'=>true,'message'=>'Cadastro realizado com sucesso!']);
} else {
  echo json_encode(['success'=>false,'message'=>'Erro ao inserir: '.$ins->error]);
}
$ins->close();
$mysqli->close();
?>
