<?php
// php/doc_utils.php
declare(strict_types=1);

function getAluno(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare('SELECT * FROM alunos WHERE id = ?');
  $st->execute([$id]);
  $aluno = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  return $aluno;
}

function getEmpresa(PDO $pdo, $empresaIdOrNome): array {
  // aceita id numérico ou string nome
  if (is_numeric($empresaIdOrNome)) {
    $st = $pdo->prepare('SELECT * FROM empresas WHERE id = ?');
    $st->execute([(int)$empresaIdOrNome]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) return $r;
  } else if (is_string($empresaIdOrNome) && $empresaIdOrNome !== '') {
    $st = $pdo->prepare('SELECT * FROM empresas WHERE nome = ? OR razao_social = ? LIMIT 1');
    $st->execute([$empresaIdOrNome, $empresaIdOrNome]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) return $r;
  }
  return [];
}

function carregarAgenda(PDO $pdo=null, int $alunoId=0): array {
  $agenda = ['teorica'=>[], 'pratica'=>[]];
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare('SELECT teorica, pratica FROM aluno_agendas WHERE aluno_id = ?');
    $st->execute([$alunoId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $agenda['teorica'] = $row['teorica'] ? json_decode($row['teorica'], true) : [];
      $agenda['pratica'] = $row['pratica'] ? json_decode($row['pratica'], true) : [];
      return $agenda;
    }
  }
  $path = __DIR__ . '/../data/schedules/'. $alunoId .'.json';
  if (is_file($path)) $agenda = json_decode(file_get_contents($path), true) ?: $agenda;
  return $agenda;
}

function somaSemanal(array $blocos): float {
  $sum = 0.0;
  foreach($blocos as $b){
    if (!isset($b['ini'],$b['fim'])) continue;
    $sum += diffHoras($b['ini'],$b['fim']);
  }
  return round($sum, 2);
}

function diffHoras(string $ini, string $fim): float {
  if (!$ini || !$fim) return 0.0;
  [$hi,$mi] = array_map('intval', explode(':',$ini));
  [$hf,$mf] = array_map('intval', explode(':',$fim));
  $min = max(0, ($hf*60+$mf) - ($hi*60+$mi));
  return round($min/60, 2);
}
?>