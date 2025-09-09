<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// Recebe campos do form (FormData)
$nome = trim($_POST['nome'] ?? '');
$cpf = preg_replace('/\D/','', $_POST['cpf'] ?? '');
$ra = trim($_POST['ra'] ?? null);
$ano = $_POST['ano_nascimento'] !== '' ? intval($_POST['ano_nascimento']) : null;
$curso = trim($_POST['curso'] ?? '');
$turno = trim($_POST['turno'] ?? '');
$serie = trim($_POST['serie'] ?? '1º');
$contato = trim($_POST['contato_aluno'] ?? '');
$idade = isset($_POST['idade']) && $_POST['idade'] !== '' ? intval($_POST['idade']) : null;
$relatorio = trim($_POST['relatorio'] ?? '');
$observacao = trim($_POST['observacao'] ?? '');
$empresa_id = isset($_POST['empresa_id']) && $_POST['empresa_id'] !== '' ? intval($_POST['empresa_id']) : null;

if(!$nome || !$cpf || !$curso){
  echo json_encode(['success'=>false,'message'=>'Campos obrigatórios ausentes.']);
  exit;
}

// verifica CPF duplicado
$stmt = $mysqli->prepare("SELECT id FROM alunos WHERE cpf = ?");
$stmt->bind_param('s', $cpf);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
  echo json_encode(['success'=>false,'message'=>'CPF já cadastrado.']);
  $stmt->close();
  exit;
}
$stmt->close();

// se empresa_id foi enviado, validar existência
if($empresa_id !== null){
  $c = $mysqli->prepare("SELECT id FROM empresas WHERE id = ?");
  if(!$c){ echo json_encode(['success'=>false,'message'=>'Erro DB: '.$mysqli->error]); exit; }
  $c->bind_param('i',$empresa_id);
  $c->execute();
  $c->store_result();
  if($c->num_rows === 0){
    echo json_encode(['success'=>false,'message'=>'Empresa selecionada inválida.']);
    $c->close();
    exit;
  }
  $c->close();
}

// gerar RA se vazio
if(!$ra){
  $ra = 'RA'.time();
}

// montar insert dinâmico (empresa_id pode ser NULL)
$columns = ['ra','nome','cpf','ano_nascimento','curso','turno','serie','status','contato_aluno','idade','relatorio','observacao'];
$placeholders = array_fill(0, count($columns), '?');
$params = [];
$types = '';

// valores (na ordem das colunas)
$values = [
  $ra, $nome, $cpf,
  $ano !== null ? $ano : null,
  $curso, $turno, $serie,
  'Em andamento',
  $contato,
  $idade !== null ? $idade : null,
  $relatorio,
  $observacao
];

// se empresa_id enviado, adiciona coluna e placeholder
if($empresa_id !== null){
  $columns[] = 'empresa_id';
  $placeholders[] = '?';
  $values[] = $empresa_id;
}

// contrói SQL
$sql = "INSERT INTO alunos (".implode(',', $columns).") VALUES (".implode(',', $placeholders).")";
$stmt = $mysqli->prepare($sql);
if(!$stmt){
  echo json_encode(['success'=>false,'message'=>'Erro prepare: '.$mysqli->error,'sql'=>$sql]);
  exit;
}

// montar tipos e bind dinamicamente
$types = '';
foreach($columns as $col){
  // mapear tipo
  if(in_array($col, ['ano_nascimento','idade','empresa_id'])) $types .= 'i';
  else $types .= 's';
}
$bind_params = [];
$bind_params[] = $types;
for($i=0; $i < count($values); $i++){
  // precisa ser variável (referência)
  ${"param".$i} = $values[$i];
  $bind_params[] = &${"param".$i};
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);

$ok = $stmt->execute();
if($ok){
  echo json_encode(['success'=>true,'message'=>'Cadastro realizado com sucesso!']);
} else {
  echo json_encode(['success'=>false,'message'=>'Erro ao inserir: '.$stmt->error]);
}
$stmt->close();
$mysqli->close();
