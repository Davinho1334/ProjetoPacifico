<?php
// php/generate_contrato.php (versão alinhada ao template FINAL)
// Gera contrato a partir do template DOCX com placeholders.

declare(strict_types=1);
error_reporting(E_ALL);

// ---------- Config / Autoload ----------
$BASE_DIR = __DIR__ . '/..'; // raiz do projeto (ajuste se necessário)
$templateCandidates = [
  __DIR__ . '/templates/Contrato_Aprendizagem_TEMPLATE_FINAL_v2.docx',
  __DIR__ . '/templates/Contrato_Aprendizagem_TEMPLATE_PLACEHOLDERS_PHPWORD.docx',
  $BASE_DIR . '/templates/Contrato_Aprendizagem_TEMPLATE_FINAL_v2.docx',
  $BASE_DIR . '/templates/Contrato_Aprendizagem_TEMPLATE_PLACEHOLDERS_PHPWORD.docx',
];

$TEMPLATE = null;
foreach ($templateCandidates as $cand) {
  if (is_file($cand)) { $TEMPLATE = $cand; break; }
}
if (!$TEMPLATE) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Template não encontrado. Procurei em:\n- ".implode("\n- ", $templateCandidates);
  exit;
}

$OUTPUT_DIR = $BASE_DIR . '/documentos/contratos/';
if (!is_dir($OUTPUT_DIR)) @mkdir($OUTPUT_DIR, 0777, true);

// DB + Composer
require_once $BASE_DIR.'/php/db.php'; // deve definir $pdo (PDO) ou $conn/$mysqli
$autoload = $BASE_DIR.'/vendor/autoload.php';
if (!is_file($autoload)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Composer autoload não encontrado em vendor/autoload.php. Rode: composer require phpoffice/phpword";
  exit;
}
require_once $autoload;

use PhpOffice\PhpWord\TemplateProcessor;

// ---------- Helpers ----------
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function hhmm_ok($v){ return is_string($v) && preg_match('/^\d{2}:\d{2}$/',$v); }
function min_between($ini,$fim){
  if (!hhmm_ok($ini) || !hhmm_ok($fim)) return 0;
  [$h1,$m1] = array_map('intval', explode(':',$ini));
  [$h2,$m2] = array_map('intval', explode(':',$fim));
  return max(0, ($h2*60+$m2) - ($h1*60+$m1));
}
function hours_label($min){
  $h = $min/60;
  if (abs($h - round($h)) < 0.01) return (string)round($h) . ' h';
  return number_format($h, 2, ',', '.') . ' h';
}
function fmtDateBR(?string $iso){
  if (!$iso) return '____/____/_____';
  $dt = DateTime::createFromFormat('Y-m-d', substr($iso,0,10));
  return $dt ? $dt->format('d/m/Y') : '____/____/_____';
}
function weekdays_count_map(DateTime $start, DateTime $end){
  if ($end < $start) { $tmp=$start; $start=$end; $end=$tmp; }
  $map = ['1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0];
  for ($d=clone $start; $d <= $end; $d->modify('+1 day')) {
    $w = (int)$d->format('N');
    if ($w >= 1 && $w <= 5) $map[(string)$w]++;
  }
  return $map;
}
function segsig($i){ return ['SEG','TER','QUA','QUI','SEX'][$i] ?? 'SEG'; }

// Accept ?id= or ?aluno_id=
$alunoId = 0;
if (isset($_GET['id']))            $alunoId = (int)$_GET['id'];
elseif (isset($_GET['aluno_id']))  $alunoId = (int)$_GET['aluno_id'];
elseif (isset($_POST['id']))       $alunoId = (int)$_POST['id'];
elseif (isset($_POST['aluno_id'])) $alunoId = (int)$_POST['aluno_id'];
if ($alunoId <= 0) { header('Content-Type:text/plain'); exit('Informe ?id= ou ?aluno_id='); }

// ---------- DB connections ----------
$pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$mysqli = isset($mysqli) && $mysqli instanceof mysqli ? $mysqli : (isset($conn) && $conn instanceof mysqli ? $conn : null);

// ---------- 1) Buscar aluno + empresa ----------
$aluno = null; $empresa = null;
if ($pdo) {
  $st = $pdo->prepare("SELECT * FROM alunos WHERE id = ? LIMIT 1");
  $st->execute([$alunoId]);
  $aluno = $st->fetch(PDO::FETCH_ASSOC);
} elseif ($mysqli) {
  $stmt = $mysqli->prepare("SELECT * FROM alunos WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $alunoId); $stmt->execute();
  $aluno = $stmt->get_result()->fetch_assoc();
}
if (!$aluno) { header('Content-Type:text/plain'); exit('Aluno não encontrado.'); }

$empresaId = $aluno['empresa_id'] ?? null;
if ($empresaId) {
  if ($pdo) {
    $se = $pdo->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
    $se->execute([$empresaId]);
    $empresa = $se->fetch(PDO::FETCH_ASSOC) ?: null;
  } elseif ($mysqli) {
    $stmte = $mysqli->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
    $stmte->bind_param('i',$empresaId); $stmte->execute();
    $empresa = $stmte->get_result()->fetch_assoc() ?: null;
  }
}

// ---------- 2) Montar placeholders ----------
// EMPREGADOR
$EMP_NOME = $empresa['razao_social'] ?? ($empresa['nome'] ?? '');
$EMP_CNPJ = $empresa['cnpj'] ?? '';
$EMP_END  = trim(implode(', ', array_filter([
  $empresa['logradouro'] ?? ($empresa['endereco'] ?? ''),
  !empty($empresa['numero']) ? 'Nº '.$empresa['numero'] : '',
  $empresa['bairro'] ?? '',
  ($empresa['cidade'] ?? '') . (isset($empresa['uf']) && $empresa['uf'] ? ' - '.$empresa['uf'] : ''),
  !empty($empresa['cep']) ? 'CEP '.$empresa['cep'] : ''
])));

// ALUNO
$ALU_END = trim(implode('; ', array_filter([
  trim(implode(', ', array_filter([
    $aluno['endereco_rua'] ?? '',
    !empty($aluno['endereco_numero']) ? 'Nº '.$aluno['endereco_numero'] : '',
    $aluno['endereco_bairro'] ?? ''
  ]))),
  trim(implode(' - ', array_filter([
    $aluno['endereco_cidade'] ?? '',
    $aluno['endereco_estado'] ?? ''
  ]))),
  !empty($aluno['cep']) ? 'CEP '.$aluno['cep'] : ''
])));

// Datas
$dtIni = $aluno['inicio_trabalho'] ?? null;
$dtFim = $aluno['fim_trabalho'] ?? null;
// Contracts table (prioridade) se existir
if ($pdo) {
  try {
    $c = $pdo->prepare("SELECT * FROM contracts WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $c->execute([$alunoId]);
    if ($row = $c->fetch(PDO::FETCH_ASSOC)) {
      $dtIni = $row['inicio'] ?: $dtIni;
      $dtFim = $row['fim']    ?: $dtFim;
    }
  } catch (Throwable $e) {}
}

// ---------- 3) Agenda (aluno_agendas) ----------
$agenda = ['teorica'=>[], 'pratica'=>[]];
if ($pdo) {
  try {
    $s = $pdo->prepare("SELECT teorica, pratica FROM aluno_agendas WHERE aluno_id = ?");
    $s->execute([$alunoId]);
    if ($r = $s->fetch(PDO::FETCH_ASSOC)) {
      $agenda['teorica'] = $r['teorica'] ? json_decode($r['teorica'], true) : [];
      $agenda['pratica'] = $r['pratica'] ? json_decode($r['pratica'], true) : [];
    }
  } catch (Throwable $e) {}
}

// Mapear para placeholders T_/P_
$map = ['0'=>'SEG','1'=>'TER','2'=>'QUA','3'=>'QUI','4'=>'SEX'];
$T = ['SEG'=>['--:--','--:--'], 'TER'=>['--:--','--:--'], 'QUA'=>['--:--','--:--'], 'QUI'=>['--:--','--:--'], 'SEX'=>['--:--','--:--']];
$P = ['SEG'=>['--:--','--:--'], 'TER'=>['--:--','--:--'], 'QUA'=>['--:--','--:--'], 'QUI'=>['--:--','--:--'], 'SEX'=>['--:--','--:--']];

foreach ($agenda['teorica'] as $row) {
  $sig = $map[(string)($row['dia'] ?? '')] ?? null;
  if ($sig) { $T[$sig][0] = $row['ini'] ?? $T[$sig][0]; $T[$sig][1] = $row['fim'] ?? $T[$sig][1]; }
}
foreach ($agenda['pratica'] as $row) {
  $sig = $map[(string)($row['dia'] ?? '')] ?? null;
  if ($sig) { $P[$sig][0] = $row['ini'] ?? $P[$sig][0]; $P[$sig][1] = $row['fim'] ?? $P[$sig][1]; }
}

// Totais semanais
$T_min_sem = 0; $P_min_sem = 0;
$T_day_tot = []; $P_day_tot = [];
foreach ($T as $sig=>$par){ $m = min_between($par[0], $par[1]); $T_day_tot[$sig]=$m; $T_min_sem += $m; }
foreach ($P as $sig=>$par){ $m = min_between($par[0], $par[1]); $P_day_tot[$sig]=$m; $P_min_sem += $m; }

// Totais do período (datas)
$CARGA_TEO = 0; $CARGA_PRAT = 0;
if ($dtIni && $dtFim) {
  $di = DateTime::createFromFormat('Y-m-d', $dtIni);
  $df = DateTime::createFromFormat('Y-m-d', $dtFim);
  if ($di && $df) {
    $rep = weekdays_count_map($di, $df); // '1'..'5'
    $rev = ['SEG'=>'1','TER'=>'2','QUA'=>'3','QUI'=>'4','SEX'=>'5'];
    foreach ($rev as $sig=>$k) {
      $CARGA_TEO += ($T_day_tot[$sig] ?? 0) * ($rep[$k] ?? 0);
      $CARGA_PRAT+= ($P_day_tot[$sig] ?? 0) * ($rep[$k] ?? 0);
    }
  }
}
$CARGA_TOTAL = $CARGA_TEO + $CARGA_PRAT;

// ---------- 4) Preencher template ----------
$tpl = new TemplateProcessor($TEMPLATE);

// empregador
$tpl->setValue('EMPREGADOR_NOME', $EMP_NOME ?: '');
$tpl->setValue('EMPREGADOR_ENDERECO_COMPLETO', $EMP_END ?: '');
$tpl->setValue('EMPREGADOR_CNPJ', $EMP_CNPJ ?: '');

// aluno
$tpl->setValue('ALUNO_NOME', $aluno['nome'] ?? '');
$tpl->setValue('ALUNO_ENDERECO', $ALU_END ?: '');
$tpl->setValue('ALUNO_CPF', $aluno['cpf'] ?? '');

// curso + datas + cargas
$tpl->setValue('CURSO_NOME', $aluno['curso'] ?? '');
$tpl->setValue('DATA_INICIO', fmtDateBR($dtIni));
$tpl->setValue('DATA_TERMINO', fmtDateBR($dtFim));
$tpl->setValue('CARGA_TEO', (string)round($CARGA_TEO/60));
$tpl->setValue('CARGA_PRAT', (string)round($CARGA_PRAT/60));
$tpl->setValue('CARGA_TOTAL', (string)round($CARGA_TOTAL/60));

// Tabelas
foreach (['SEG','TER','QUA','QUI','SEX'] as $d){
  $tpl->setValue("T_INICIO_$d", $T[$d][0]);
  $tpl->setValue("T_FIM_$d",    $T[$d][1]);
  $tpl->setValue("T_TOTAL_$d",  hours_label($T_day_tot[$d] ?? 0));
  $tpl->setValue("P_INICIO_$d", $P[$d][0]);
  $tpl->setValue("P_FIM_$d",    $P[$d][1]);
  $tpl->setValue("P_TOTAL_$d",  hours_label($P_day_tot[$d] ?? 0));
}
$tpl->setValue('T_TOTAL_SEM', hours_label($T_min_sem));
$tpl->setValue('P_TOTAL_SEM', hours_label($P_min_sem));

// ---------- 5) Salvar e enviar ----------
$nomeAluno = preg_replace('/\s+/', '_', trim($aluno['nome'] ?? 'aluno'));
$outPath   = $OUTPUT_DIR . "/Contrato_Aprendizagem_{$nomeAluno}_{$alunoId}.docx";

try { $tpl->saveAs($outPath); }
catch (Throwable $e) {
  header('Content-Type:text/plain; charset=utf-8');
  echo "Falha ao salvar DOCX: ".$e->getMessage();
  exit;
}

if (!is_file($outPath)) { header('Content-Type:text/plain; charset=utf-8'); echo "Arquivo não gerado."; exit; }

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($outPath).'"');
header('Content-Length: '.filesize($outPath));
readfile($outPath);
exit;
