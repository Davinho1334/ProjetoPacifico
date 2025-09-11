<?php
// php/_db_bridge.php
declare(strict_types=1);

/**
 * Este arquivo tenta descobrir a conexão que seu db.php já criou,
 * SEM alterar o db.php. Ele procura por:
 * - Variáveis: $pdo (PDO), $conn/$con/$mysqli/$link/$db/$database/$connection (mysqli)
 * - Funções: getConnection(), connection(), connect(), conectar(), conexao(),
 *            db(), pdo(), getPDO(), get_pdo(), getMysqli(), getMYSQLI()
 * - Constantes: DB_HOST, DB_NAME/DB_DATABASE, DB_USER/DB_USERNAME, DB_PASS/DB_PASSWORD
 *
 * No fim, ele expõe:
 *   $DB = ['type' => 'pdo'|'mysqli', 'pdo' => PDO|null, 'mysqli' => mysqli|null]
 */

require_once __DIR__ . '/db.php'; // NÃO modificar seu db.php

$DB = ['type' => null, 'pdo' => null, 'mysqli' => null];

// 1) Variáveis comuns
if (isset($pdo) && $pdo instanceof PDO) {
  $DB['type'] = 'pdo'; $DB['pdo'] = $pdo; return;
}
$mysqliCandidates = ['conn','con','mysqli','link','db','database','connection'];
foreach ($mysqliCandidates as $var) {
  if (isset($$var) && $$var instanceof mysqli) {
    $DB['type'] = 'mysqli'; $DB['mysqli'] = $$var; return;
  }
}

// 2) Funções comuns
$fnCandidates = [
  'getConnection','connection','connect','conectar','conexao','db',
  'pdo','getPDO','get_pdo','getMysqli','getMYSQLI'
];
foreach ($fnCandidates as $fn) {
  if (function_exists($fn)) {
    try {
      $h = $fn();
      if ($h instanceof PDO) {
        $DB['type'] = 'pdo'; $DB['pdo'] = $h; return;
      }
      if ($h instanceof mysqli) {
        $DB['type'] = 'mysqli'; $DB['mysqli'] = $h; return;
      }
    } catch (Throwable $e) { /* ignora e tenta o próximo */ }
  }
}

// 3) Constantes conhecidas (opcional)
$host = null; $name = null; $user = null; $pass = null;
if (defined('DB_HOST')) $host = DB_HOST;
if (defined('DB_NAME')) $name = DB_NAME;
if (defined('DB_DATABASE')) $name = $name ?? DB_DATABASE;
if (defined('DB_USER')) $user = DB_USER;
if (defined('DB_USERNAME')) $user = $user ?? DB_USERNAME;
if (defined('DB_PASS')) $pass = DB_PASS;
if (defined('DB_PASSWORD')) $pass = $pass ?? DB_PASSWORD;

if ($host && $name && $user !== null && $pass !== null) {
  // Tenta PDO primeiro
  try {
    $tmp = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", (string)$user, (string)$pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $DB['type'] = 'pdo'; $DB['pdo'] = $tmp; return;
  } catch (Throwable $e) { /* tenta mysqli abaixo */ }

  // Tenta mysqli
  try {
    $tmp = @new mysqli((string)$host, (string)$user, (string)$pass, (string)$name);
    if (!($tmp instanceof mysqli) || $tmp->connect_errno) {
      throw new Exception('Falha mysqli: ' . ($tmp ? $tmp->connect_error : 'objeto inválido'));
    }
    $tmp->set_charset('utf8mb4');
    $DB['type'] = 'mysqli'; $DB['mysqli'] = $tmp; return;
  } catch (Throwable $e) { /* segue para erro final */ }
}

// 4) Nada encontrado
throw new Exception('db.php não expôs uma conexão. Esperado $pdo (PDO), $conn/$con/$mysqli/$link/$db (mysqli), função que retorne uma conexão, ou constantes DB_HOST/DB_NAME/DB_USER/DB_PASS.');
?>