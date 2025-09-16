<?php
// php/get_students.php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/_pdo_boot.php')) require_once __DIR__ . '/_pdo_boot.php';
if (file_exists(__DIR__ . '/_db_bridge.php')) require_once __DIR__ . '/_db_bridge.php';

function out($ok,$msg,$extra=[]){ echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra)); exit; }

// Detecta conexão
$driver=null; $dbh=null;
if (isset($pdo) && $pdo instanceof PDO) { $driver='pdo'; $dbh=$pdo; }
elseif (isset($conn) && $conn instanceof mysqli) { $driver='mysqli'; $dbh=$conn; }
elseif (isset($db) && $db instanceof mysqli) { $driver='mysqli'; $dbh=$db; }
if (!$dbh) out(false,'Conexão não encontrada.');

// Monta SELECT básico (inclui campos usados no dashboard)
$baseFields = "id,nome,cpf,ra,curso,turno,serie,status,escola,cargaSemanal,empresa_id,empresa,inicio_trabalho,fim_trabalho,renovou_contrato,tipo_contrato,recebeu_bolsa";

// Buscar por ID?
if (isset($_GET['id']) && $_GET['id'] !== '') {
  $id = (int)$_GET['id'];
  try {
    if ($driver === 'pdo') {
      $st = $dbh->prepare("SELECT {$baseFields} FROM alunos WHERE id = ? LIMIT 1");
      $st->execute([$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
    } else {
      $st = $dbh->prepare("SELECT {$baseFields} FROM alunos WHERE id = ? LIMIT 1");
      $st->bind_param('i', $id);
      $st->execute();
      $res = $st->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $st->close();
    }
    out(true,'ok',['data'=>$row ?: null]);
  } catch(Throwable $e){
    out(false,'Erro ao buscar aluno',['error'=>$e->getMessage()]);
  }
}

// Listar todos
try{
  if ($driver === 'pdo') {
    $rows = $dbh->query("SELECT {$baseFields} FROM alunos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $res = $dbh->query("SELECT {$baseFields} FROM alunos ORDER BY id DESC");
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }
  out(true,'ok',['data'=>$rows]);
} catch(Throwable $e){
  out(false,'Erro ao listar alunos',['error'=>$e->getMessage()]);
}
?>