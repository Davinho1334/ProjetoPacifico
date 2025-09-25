<?php
// php/generate_declaracao.php
declare(strict_types=1);
error_reporting(E_ALL);

// --- Localiza template ---
$BASE_DIR = __DIR__ . '/..';
$cands = [
  __DIR__ . '/templates/Declaracao_Matricula_TEMPLATE_FINAL.docx',
  $BASE_DIR . '/templates/Declaracao_Matricula_TEMPLATE_FINAL.docx',
];
$TEMPLATE = null; foreach ($cands as $c) if (is_file($c)) { $TEMPLATE = $c; break; }
if(!$TEMPLATE){ header('Content-Type:text/plain; charset=utf-8'); echo "Template não encontrado:\n- ".implode("\n- ",$cands); exit; }

$OUTPUT_DIR = $BASE_DIR.'/documentos/declaracoes/'; if(!is_dir($OUTPUT_DIR)) @mkdir($OUTPUT_DIR,0777,true);

// --- Composer + DB ---
require_once $BASE_DIR.'/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

require_once $BASE_DIR.'/php/db.php'; // deve definir $pdo (PDO) ou $mysqli/$conn

// --- Helpers ---
function hhmm_ok($v){ return is_string($v) && preg_match('/^\d{2}:\d{2}$/',$v); }
function min_between($ini,$fim){
  if(!hhmm_ok($ini)||!hhmm_ok($fim)) return 0;
  [$h1,$m1]=array_map('intval',explode(':',$ini));
  [$h2,$m2]=array_map('intval',explode(':',$fim));
  return max(0, ($h2*60+$m2)-($h1*60+$m1));
}
function hours_int($min){ return (int)round($min/60); }
function fmtDateBR(?string $iso){ if(!$iso) return '____/____/_____'; $dt=DateTime::createFromFormat('Y-m-d',substr($iso,0,10)); return $dt?$dt->format('d/m/Y'):'____/____/_____'; }
function data_extenso(DateTime $d){
  $meses=['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
  return $d->format('j').' de '.$meses[(int)$d->format('n')-1].' de '.$d->format('Y');
}

// --- Entrada ---
$alunoId = 0;
if (isset($_GET['id'])) $alunoId=(int)$_GET['id'];
elseif(isset($_GET['aluno_id'])) $alunoId=(int)$_GET['aluno_id'];
elseif(isset($_POST['id'])) $alunoId=(int)$_POST['id'];
elseif(isset($_POST['aluno_id'])) $alunoId=(int)$_POST['aluno_id'];
if($alunoId<=0){ header('Content-Type:text/plain'); exit('Informe ?id= ou ?aluno_id='); }

// --- Carregar aluno ---
$aluno=null; $pdo = isset($pdo)&&$pdo instanceof PDO?$pdo:null; $mysqli = isset($mysqli)&&$mysqli instanceof mysqli?$mysqli:(isset($conn)&&$conn instanceof mysqli?$conn:null);

if($pdo){
  $st=$pdo->prepare("SELECT * FROM alunos WHERE id=? LIMIT 1"); $st->execute([$alunoId]); $aluno=$st->fetch(PDO::FETCH_ASSOC);
}elseif($mysqli){
  $st=$mysqli->prepare("SELECT * FROM alunos WHERE id=? LIMIT 1"); $st->bind_param('i',$alunoId); $st->execute(); $aluno=$st->get_result()->fetch_assoc();
}
if(!$aluno){ header('Content-Type:text/plain'); exit('Aluno não encontrado.'); }

// --- Agenda semanal ---
$Tmin=0; $Pmin=0;
if($pdo){
  try{
    $s=$pdo->prepare("SELECT teorica,pratica FROM aluno_agendas WHERE aluno_id=?"); $s->execute([$alunoId]);
    if($r=$s->fetch(PDO::FETCH_ASSOC)){
      $teo=json_decode($r['teorica']?:'[]',true) ?: [];
      $pra=json_decode($r['pratica']?:'[]',true) ?: [];
      foreach($teo as $row){ $Tmin += min_between($row['ini']??'',$row['fim']??''); }
      foreach($pra as $row){ $Pmin += min_between($row['ini']??'',$row['fim']??''); }
    }
  }catch(Throwable $e){}
}elseif($mysqli){
  $q=$mysqli->prepare("SELECT teorica,pratica FROM aluno_agendas WHERE aluno_id=?"); $q->bind_param('i',$alunoId); $q->execute();
  if($r=$q->get_result()->fetch_assoc()){
    $teo=json_decode($r['teorica']?:'[]',true) ?: [];
    $pra=json_decode($r['pratica']?:'[]',true) ?: [];
    foreach($teo as $row){ $Tmin += min_between($row['ini']??'',$row['fim']??''); }
    foreach($pra as $row){ $Pmin += min_between($row['ini']??'',$row['fim']??''); }
  }
}
$hor_teo = hours_int($Tmin);
$hor_pra = hours_int($Pmin);
$hor_sem = $hor_teo + $hor_pra;

// --- Datas do contrato (mesma lógica do contrato) ---
$ini = $aluno['inicio_trabalho'] ?? null;
$fim = $aluno['fim_trabalho'] ?? null;
if($pdo){
  try{
    $c=$pdo->prepare("SELECT * FROM contracts WHERE student_id=? ORDER BY id DESC LIMIT 1");
    $c->execute([$alunoId]);
    if($row=$c->fetch(PDO::FETCH_ASSOC)){
      $ini = $row['inicio'] ?: $ini;
      $fim = $row['fim']    ?: $fim;
    }
  }catch(Throwable $e){}
}

// --- Preenche template ---
$tpl = new TemplateProcessor($TEMPLATE);
$tpl->setValue('ALUNO_NOME', $aluno['nome'] ?? '');
$tpl->setValue('ALUNO_RA', $aluno['ra'] ?? '');
$tpl->setValue('CURSO_NOME', $aluno['curso'] ?? '');
$tpl->setValue('CBO', $aluno['cbo'] ?? '');
$tpl->setValue('DATA_INICIO', fmtDateBR($ini));
$tpl->setValue('DATA_TERMINO', fmtDateBR($fim));
$tpl->setValue('HORAS_TEO_SEMANAIS', (string)$hor_teo);
$tpl->setValue('HORAS_PRAT_SEMANAIS', (string)$hor_pra);
$tpl->setValue('HORAS_SEMANAIS_TOTAL', (string)$hor_sem);

// Data de emissão por extenso (hoje)
$hoje = new DateTime('now');
$tpl->setValue('DATA_EMISSAO_EXTENSO', data_extenso($hoje));

// --- Salvar e baixar ---
$nomeAluno = preg_replace('/\s+/', '_', trim($aluno['nome'] ?? 'aluno'));
$out = $OUTPUT_DIR . "Declaracao_Matricula_{$nomeAluno}_{$alunoId}.docx";
$tpl->saveAs($out);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($out).'"');
header('Content-Length: '.filesize($out));
readfile($out);
exit;
?>