<?php
declare(strict_types=1);

/** ===================== Datas ===================== */
function formatDateBR(?string $isoDate): string {
  if(!$isoDate) return '';
  // Aceita 'YYYY-MM-DD' ou 'YYYY-MM-DD HH:MM:SS' ou já em dd/mm/aaaa
  if (strpos($isoDate, '-') !== false) {
    $d = substr($isoDate, 0, 10);
    [$y,$m,$d2] = explode('-', $d);
    if($y && $m && $d2) return sprintf('%02d/%02d/%04d', (int)$d2, (int)$m, (int)$y);
  }
  return $isoDate;
}
function weeksBetween(string $inicio, string $fim): int {
  if (!$inicio || !$fim) return 0;
  $di = new DateTime(substr($inicio,0,10));
  $df = new DateTime(substr($fim,0,10));
  if ($df < $di) return 0;
  $days = (int)$di->diff($df)->format('%a') + 1; // inclusivo
  return (int)ceil($days / 7.0);
}

/** ===================== Agenda / Cargas ===================== */
function diffHoras(string $ini, string $fim): float {
  if (!$ini || !$fim) return 0.0;
  [$hi,$mi] = array_map('intval', explode(':',$ini));
  [$hf,$mf] = array_map('intval', explode(':',$fim));
  $min = max(0, ($hf*60+$mf) - ($hi*60+$mi));
  return round($min/60, 2);
}
function somaSemanal(array $blocos): float {
  $sum = 0.0;
  foreach($blocos as $b){
    if (!isset($b['ini'],$b['fim'])) continue;
    $sum += diffHoras($b['ini'],$b['fim']);
  }
  return round($sum, 2);
}
function linhasHorario(array $blocos): string {
  $dias = ['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo'];
  $out = [];
  foreach($blocos as $b){
    if (!isset($b['dia'])) continue;
    $h = diffHoras($b['ini']??'', $b['fim']??'');
    $diaNome = $dias[(int)$b['dia']] ?? ('Dia '.$b['dia']);
    $out[] = sprintf("%s\t%s\t%s\t%.0f horas", $diaNome, $b['ini']??'', $b['fim']??'', $h);
  }
  return implode("\n", $out);
}
function calcularCargas(array $aluno, array $agenda): array {
  $semTeo = somaSemanal($agenda['teorica'] ?? []);
  $semPra = somaSemanal($agenda['pratica'] ?? []);
  $semTotal = $semTeo + $semPra;

  $inicio = $aluno['inicio_trabalho'] ?? '';
  $fim    = $aluno['fim_trabalho'] ?? '';
  $semanas = ($inicio && $fim) ? weeksBetween($inicio, $fim) : 0;

  if ($semTotal == 0 && !empty($aluno['cargaSemanal'])) { // fallback
    $semTotal = (float)$aluno['cargaSemanal'];
  }
  $totalPrograma = $semanas > 0 ? round($semTotal * $semanas, 2) : 0.0;

  return [
    'sem_teo' => $semTeo,
    'sem_pra' => $semPra,
    'sem_total' => $semTotal,
    'semanas' => $semanas,
    'total_programa' => $totalPrograma,
  ];
}

/** ===================== Curso -> CBO ===================== */
function cursoCboMap(): array {
  return [
    'Enfermagem'                     => '322205',
    'Análises Clínicas'              => '324205',
    'Farmácia'                       => '321105',
    'Técnico em Segurança do Trabalho'=> '351605',
    'Segurança do Trabalho'          => '351605',
    'Desenvolvimento de Sistemas'    => '317110',   // ajuste conforme tabela oficial da escola
    'Técnico em Informática'         => '317105',
    'Edificações'                    => '715315',
    'Mecânica Automotiva'            => '913105',
    'Energias Renováveis'            => '715615',
  ];
}
function cboForCourse(?string $cursoFromAluno, ?string $cboFromAluno = null): string {
  if ($cboFromAluno && trim($cboFromAluno) !== '') return trim($cboFromAluno);
  $curso = trim((string)$cursoFromAluno);
  $map = cursoCboMap();
  return $map[$curso] ?? '';
}

/** ===================== Carregadores ===================== */
function getAluno(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare('SELECT * FROM alunos WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function getEmpresa(PDO $pdo, $empresaIdOrNome): array {
  if (is_numeric($empresaIdOrNome)) {
    $st = $pdo->prepare('SELECT * FROM empresas WHERE id = ?');
    $st->execute([(int)$empresaIdOrNome]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) return $r;
  } else if (is_string($empresaIdOrNome) && $empresaIdOrNome !== '') {
    $st = $pdo->prepare('SELECT * FROM empresas WHERE (nome = ? OR razao_social = ?) LIMIT 1');
    $st->execute([$empresaIdOrNome, $empresaIdOrNome]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) return $r;
  }
  return [];
}
function carregarAgenda(PDO $pdo=null, int $alunoId=0): array {
  $agenda = ['teorica'=>[], 'pratica'=>[]];
  if ($pdo instanceof PDO) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS aluno_agendas (
      aluno_id INT NOT NULL PRIMARY KEY,
      teorica JSON NULL,
      pratica JSON NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $st = $pdo->prepare('SELECT teorica, pratica FROM aluno_agendas WHERE aluno_id = ?');
    $st->execute([$alunoId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $agenda['teorica'] = $row['teorica'] ? json_decode($row['teorica'], true) : [];
      $agenda['pratica'] = $row['pratica'] ? json_decode($row['pratica'], true) : [];
    }
  }
  return $agenda;
}

/** ===================== Endereço empresa formatado ===================== */
function formatEmpresaEnderecoCompleto(array $emp): string {
  $p = [];
  if (!empty($emp['logradouro']))  $p[] = $emp['logradouro'];
  if (!empty($emp['numero']))      $p[] = 'Nº '.$emp['numero'];
  if (!empty($emp['complemento'])) $p[] = $emp['complemento'];
  if (!empty($emp['bairro']))      $p[] = $emp['bairro'];
  if (!empty($emp['cidade']))      $p[] = $emp['cidade'];
  if (!empty($emp['uf']))          $p[] = $emp['uf'];
  if (!empty($emp['cep']))         $p[] = 'CEP '.$emp['cep'];
  return implode(', ', $p);
}
?>