<?php
// php/db.php
declare(strict_types=1);

$DB = [
  'host'    => '127.0.0.1',
  'name'    => 'escola_portal', // <<< TROQUE para o nome real da sua base
  'user'    => 'root',
  'pass'    => '',
  'charset' => 'utf8mb4',
];

// Override opcional por arquivo local
$local = __DIR__.'/config.local.php';
if (is_file($local)) include $local;

// Override por variáveis de ambiente (se houver)
$DB['host']    = getenv('DB_HOST') ?: $DB['host'];
$DB['name']    = getenv('DB_NAME') ?: $DB['name'];
$DB['user']    = getenv('DB_USER') ?: $DB['user'];
$DB['pass']    = getenv('DB_PASS') ?: $DB['pass'];
$DB['charset'] = getenv('DB_CHARSET') ?: $DB['charset'];

function pdo(): PDO {
  global $DB;
  if (empty($DB['name']) || $DB['name']==='SEU_BANCO_AQUI') {
    throw new RuntimeException("DB_NAME não definido. Configure em php/config.local.php.");
  }
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $DB['host'], $DB['name'], $DB['charset']);
  return new PDO($dsn, $DB['user'], $DB['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}
?>