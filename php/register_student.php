<?php
// php/register_student.php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');
session_start();

// ---------- DEBUG LIGADO ----------
const DEBUG = true;

function J($ok,$msg,$extra=[]) {
  static $sent=false; if ($sent) return; $sent=true;
  echo json_encode(array_merge(['success'=>$ok,'message'=>$msg], $extra));
  exit;
}
set_exception_handler(function(Throwable $e){
  J(false,'Erro no servidor', ['error'=>$e->getMessage()]);
});
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e) J(false,'Erro fatal no servidor', ['error'=>$e['message'].' @ '.$e['file'].':'.$e['line']]);
});

// ---------- ENTRADA ----------
$nome  = trim($_POST['nome'] ?? '');
$cpf   = trim($_POST['cpf'] ?? '');
$ra    = trim($_POST['ra'] ?? '');
$ano_n = trim($_POST['ano_nascimento'] ?? '');
$curso = trim($_POST['curso'] ?? '');
$turno = trim($_POST['turno'] ?? '');
$serie = trim($_POST['serie'] ?? '');

if ($nome==='' || $cpf==='' || $curso==='' || $turno==='' || $serie==='') {
  J(false,'Preencha todos os campos obrigatórios.');
}

// ---------- ISOLA db.php ----------
ob_start();
$ok_inc = @include __DIR__.'/db.php';
if (file_exists(__DIR__.'/_pdo_boot.php')) @include __DIR__.'/_pdo_boot.php';
if (file_exists(__DIR__.'/_db_bridge.php')) @include __DIR__.'/_db_bridge.php';
$include_output = ob_get_clean();

if (!$ok_inc) {
  J(false,'Falha ao incluir db.php', ['include_output'=>mb_substr($include_output,0,400)]);
}
if ($include_output !== '') {
  J(false,'db.php gerou saída inesperada', ['include_output'=>mb_substr($include_output,0,400)]);
}

// ---------- DETECTA CONEXÃO ----------
$driver=null; $dbh=null;
if (isset($pdo) && $pdo instanceof PDO) { $driver='pdo'; $dbh=$pdo; }
elseif (isset($conn) && $conn instanceof mysqli) { $driver='mysqli'; $dbh=$conn; }
elseif (isset($db) && $db instanceof mysqli) { $driver='mysqli'; $dbh=$db; }
elseif (function_exists('getPDO')) { $tmp=@getPDO(); if ($tmp instanceof PDO){ $driver='pdo'; $dbh=$tmp; } }

if (!$dbh) {
  J(false,'Conexão com o banco não encontrada após incluir db.php.',
    ['globals'=>array_keys($GLOBALS)]
  );
}

// ---------- INTROSPECÇÃO (SEM get_result) ----------
function table_exists($driver,$dbh,$table){
  if ($driver==='pdo') {
    $st = $dbh->query("SHOW TABLES LIKE " . $dbh->quote($table));
    return (bool)$st->fetchColumn();
  } else {
    $t = $dbh->real_escape_string($table);
    $res = $dbh->query("SHOW TABLES LIKE '{$t}'");
    return $res && $res->num_rows>0;
  }
}
function col_exists($driver,$dbh,$table,$col){
  if ($driver==='pdo') {
    $st = $dbh->query("SHOW COLUMNS FROM `{$table}` LIKE " . $dbh->quote($col));
    return (bool)$st->fetchColumn();
  } else {
    $t = $dbh->real_escape_string($table);
    $c = $dbh->real_escape_string($col);
    $res = $dbh->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $res && $res->num_rows>0;
  }
}

$table = 'alunos';
if (!table_exists($driver,$dbh,$table)) {
  J(false,"Tabela '{$table}' não encontrada.", ['driver'=>$driver]);
}

// coluna do ano
$colAno = null;
if (col_exists($driver,$dbh,$table,'ano_nascimento')) $colAno='ano_nascimento';
elseif (col_exists($driver,$dbh,$table,'ano'))        $colAno='ano';
if (!$colAno) J(false,"Nenhuma coluna de ano encontrada (esperado 'ano_nascimento' ou 'ano').",['driver'=>$driver]);

$hasRecebeu = col_exists($driver,$dbh,$table,'recebeu_bolsa');

// CPF único?
if (col_exists($driver,$dbh,$table,'cpf')) {
  if ($driver==='pdo') {
    $st = $dbh->prepare("SELECT id FROM `{$table}` WHERE cpf=? LIMIT 1");
    $st->execute([$cpf]);
    if ($st->fetch()) J(false,'Já existe um aluno com este CPF.');
  } else {
    $sql = "SELECT id FROM `{$table}` WHERE cpf=? LIMIT 1";
    $st = $dbh->prepare($sql);
    if (!$st) J(false,'Erro prepare (mysqli)', ['sql'=>$sql,'mysqli_error'=>$dbh->error,'driver'=>$driver]);
    $st->bind_param('s',$cpf);
    $ok = $st->execute();
    if (!$ok) { $err=$st->error; $st->close(); J(false,'Erro execute (mysqli)',['sql'=>$sql,'mysqli_error'=>$err,'driver'=>$driver]); }
    $st->store_result();
    if ($st->num_rows>0) { $st->close(); J(false,'Já existe um aluno com este CPF.'); }
    $st->close();
  }
}

// ---------- INSERT ----------
$cols = ['nome','cpf','ra',$colAno,'curso','turno','serie'];
$vals = [$nome,$cpf,($ra!==''?$ra:null),($ano_n!==''?(int)$ano_n:null),$curso,$turno,$serie];
if ($hasRecebeu) { $cols[]='recebeu_bolsa'; $vals[] = null; }

$placeholders = implode(',', array_fill(0, count($cols), '?'));
$sql = "INSERT INTO `{$table}` (".implode(',', $cols).") VALUES ({$placeholders})";

try {
  if ($driver==='pdo') {
    $st = $dbh->prepare($sql);
    $st->execute($vals);
  } else {
    $st = $dbh->prepare($sql);
    if (!$st) J(false,'Erro prepare (mysqli)', ['sql'=>$sql,'mysqli_error'=>$dbh->error,'driver'=>$driver,'cols'=>$cols,'vals_preview'=>$vals]);
    $types = str_repeat('s', count($vals)); // simples e suficiente
    $st->bind_param($types, ...$vals);
    $ok = $st->execute();
    if (!$ok) { $err=$st->error; $st->close(); J(false,'Erro execute (mysqli)',['sql'=>$sql,'mysqli_error'=>$err,'driver'=>$driver]); }
    $st->close();
  }
  J(true,'Aluno cadastrado com sucesso.', ['driver'=>$driver,'colAno'=>$colAno,'temRecebeuBolsa'=>$hasRecebeu]);

} catch (Throwable $e) {
  J(false,'Erro ao cadastrar', ['error'=>$e->getMessage(),'sql'=>$sql,'driver'=>$driver]);
}
?>