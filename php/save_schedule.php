<?php
// php/save_schedule.php
declare(strict_types=1);
require_once __DIR__.'/auth_admin.php';
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$alunoId = (int)($payload['aluno_id'] ?? 0);
$agenda  = $payload['agenda'] ?? null;

if ($alunoId <= 0 || !is_array($agenda)) {
  echo json_encode(['success'=>false,'message'=>'Dados inválidos']); exit;
}

try {
  $okDb = false;
  $pdo = $pdo ?? null;
  if ($pdo instanceof PDO) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS aluno_agendas (
      aluno_id INT NOT NULL PRIMARY KEY,
      teorica JSON NULL,
      pratica JSON NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $st = $pdo->prepare('REPLACE INTO aluno_agendas (aluno_id, teorica, pratica) VALUES (?, ?, ?)');
    $okDb = $st->execute([
      $alunoId,
      json_encode($agenda['teorica'] ?? [] , JSON_UNESCAPED_UNICODE),
      json_encode($agenda['pratica'] ?? [] , JSON_UNESCAPED_UNICODE)
    ]);
  }

  if (!$okDb) {
    // fallback arquivo
    $dir = __DIR__ . '/../data/schedules';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $path = $dir . '/' . $alunoId . '.json';
    file_put_contents($path, json_encode($agenda, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
  }

  echo json_encode(['success'=>true]);
} catch(Throwable $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>