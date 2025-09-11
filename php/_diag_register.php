<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

$resp = ['ok'=>false, 'steps'=>[]];
function step(&$r,$s){ $r['steps'][]=$s; }
function out($r){ echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }

step($resp,'include db.php');
require_once __DIR__.'/db.php';
if (file_exists(__DIR__.'/_pdo_boot.php')) { step($resp,'include _pdo_boot.php'); @require_once __DIR__.'/_pdo_boot.php'; }
if (file_exists(__DIR__.'/_db_bridge.php')) { step($resp,'include _db_bridge.php'); @require_once __DIR__.'/_db_bridge.php'; }

$driver=null; $dbh=null;
foreach (['pdo','conn','db'] as $v) {
  if (isset($GLOBALS[$v])) {
    if ($GLOBALS[$v] instanceof PDO)    { $driver='pdo'; $dbh=$GLOBALS[$v]; break; }
    if ($GLOBALS[$v] instanceof mysqli) { $driver='mysqli'; $dbh=$GLOBALS[$v]; break; }
  }
}
if (!$dbh) {
  foreach (['getPDO','pdo','get_pdo','getConnection','db','connect','connection'] as $fn) {
    if (function_exists($fn)) {
      try {
        $h = $fn();
        if     ($h instanceof PDO)    { $driver='pdo'; $dbh=$h; break; }
        elseif ($h instanceof mysqli) { $driver='mysqli'; $dbh=$h; break; }
      } catch(Throwable $e){}
    }
  }
}
if (!$dbh){ $resp['error']='Conexão não encontrada (nem PDO nem MySQLi).'; out($resp); }
$resp['driver']=$driver;

try {
  if ($driver==='pdo') {
    $resp['database'] = $dbh->query("SELECT DATABASE()")->fetchColumn();
  } else {
    $r = $dbh->query("SELECT DATABASE()");
    $row = $r ? $r->fetch_row() : null;
    $resp['database'] = $row ? $row[0] : null;
  }
} catch(Throwable $e){
  $resp['error']='Falha ao obter DATABASE(): '.$e->getMessage();
  out($resp);
}

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

$table='alunos';
$resp['table']=$table;

if (!table_exists($driver,$dbh,$table)) {
  $resp['error']="Tabela '{$table}' não existe no banco ".$resp['database'];
  out($resp);
}

// detecta coluna do ano
$colAno = col_exists($driver,$dbh,$table,'ano_nascimento') ? 'ano_nascimento' :
          (col_exists($driver,$dbh,$table,'ano') ? 'ano' : null);
$resp['colAno'] = $colAno ?: '(não encontrada)';

$must = ['nome','cpf','ra',$colAno,'curso','turno','serie'];
$missing = [];
foreach ($must as $c) {
  if (!$c) continue;
  if (!col_exists($driver,$dbh,$table,$c)) $missing[] = $c;
}
$resp['missing_columns'] = $missing;
$resp['has_recebeu_bolsa'] = col_exists($driver,$dbh,$table,'recebeu_bolsa');

if (!$colAno)  { $resp['error']="Nenhuma coluna de ano encontrada (esperado 'ano_nascimento' ou 'ano')."; out($resp); }
if ($missing){ $resp['error']='Faltam colunas: '.implode(', ',$missing); out($resp); }

$resp['ok']=true;
$resp['message']='Estrutura pronta para inserir.';
out($resp);
?>