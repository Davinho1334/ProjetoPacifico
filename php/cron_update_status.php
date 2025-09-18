<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$hoje = (new DateTime('today'))->format('Y-m-d');

$isPDO    = isset($pdo) && $pdo instanceof PDO;
$mysqli   = $mysqli ?? (isset($conn) && $conn instanceof mysqli ? $conn : null);
$isMySQLi = $mysqli instanceof mysqli;

// SQLs fixos com os nomes reais das colunas
$sqlEncerrar = "
  UPDATE alunos
  SET status = 'Encerrado'
  WHERE fim_trabalho IS NOT NULL
    AND DATE(fim_trabalho) < :hoje
    AND status <> 'Encerrado'
";

$sqlAndamento = "
  UPDATE alunos
  SET status = 'Em andamento'
  WHERE inicio_trabalho IS NOT NULL AND fim_trabalho IS NOT NULL
    AND DATE(inicio_trabalho) <= :hoje
    AND DATE(fim_trabalho) >= :hoje
    AND status <> 'Em andamento'
";

$sqlDisponivel = "
  UPDATE alunos
  SET status = 'Disponível'
  WHERE (
      inicio_trabalho IS NULL OR DATE(inicio_trabalho) > :hoje
    )
    AND status <> 'Disponível'
";

$afetados = [];

try {
  if ($isPDO) {
    foreach ([
      'Encerrar'    => $sqlEncerrar,
      'EmAndamento' => $sqlAndamento,
      'Disponivel'  => $sqlDisponivel
    ] as $key => $sql) {
      $st = $pdo->prepare($sql);
      $st->execute([':hoje'=>$hoje]);
      $afetados[$key] = $st->rowCount();
    }
  } elseif ($isMySQLi) {
    $h = $mysqli->real_escape_string($hoje);
    foreach ([
      'Encerrar'    => $sqlEncerrar,
      'EmAndamento' => $sqlAndamento,
      'Disponivel'  => $sqlDisponivel
    ] as $key => $sql) {
      $sql = str_replace(':hoje', "'$h'", $sql);
      $mysqli->query($sql);
      $afetados[$key] = $mysqli->affected_rows;
    }
  } else {
    throw new Exception('Nenhuma conexão PDO/MySQLi encontrada em db.php');
  }

  echo json_encode([
    'ok'=>true,
    'date'=>$hoje,
    'afetados'=>$afetados
  ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error'=>$e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
?>