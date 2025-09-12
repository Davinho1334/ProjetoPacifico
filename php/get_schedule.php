<?php
// php/get_schedule.php
declare(strict_types=1);
require_once __DIR__.'/auth_admin.php';
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

$alunoId = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;
if ($alunoId <= 0) { echo json_encode(['success'=>false,'message'=>'aluno_id inválido']); exit; }

$agenda = ['teorica'=>[], 'pratica'=>[]];

try {
  // tenta BD
  $pdo = $pdo ?? null;
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('SELECT teorica, pratica FROM aluno_agendas WHERE aluno_id = ?');
    $st->execute([$alunoId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $agenda['teorica'] = $row['teorica'] ? json_decode($row['teorica'], true) : [];
      $agenda['pratica'] = $row['pratica'] ? json_decode($row['pratica'], true) : [];
      echo json_encode(['success'=>true,'data'=>$agenda]); exit;
    }
  }

  // fallback arquivo
  $path = __DIR__ . '/../data/schedules/'. $alunoId .'.json';
  if (is_file($path)) {
    $agenda = json_decode(file_get_contents($path), true) ?: $agenda;
  }
  echo json_encode(['success'=>true,'data'=>$agenda]);
} catch(Throwable $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>