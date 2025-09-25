<?php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

/* ==========================
   LOCALIZADOR DE TEMPLATE
   ========================== */
function listFiles($dir){
  if(!is_dir($dir)) return "⛔ pasta não existe";
  $items = array_values(array_map('basename', glob(rtrim($dir,'\\/').DIRECTORY_SEPARATOR.'*') ?: []));
  return $items ? ("📄 " . implode(", ", $items)) : "📭 pasta vazia";
}
function locateTemplate(): ?string {
  $NAME_PREFS = [
    'Contrato_Aprendizagem_TEMPLATE_PLACEHOLDERS_PHPWORD.docx',
    'Contrato_Aprendizagem_TEMPLATE_PLACEHOLDERS_v2.docx',
    'Contrato_Aprendizagem_TEMPLATE.docx',
  ];
  $DIRS = [
    __DIR__ . '/templates',          // ProjetoPacifico/php/templates
    dirname(__DIR__) . '/templates', // ProjetoPacifico/templates
    __DIR__,                         // por via das dúvidas
  ];

  // 1) tentativa por nomes previstos (case-insensitive)
  foreach($DIRS as $D){
    foreach($NAME_PREFS as $N){
      $cand = rtrim($D,'\\/').DIRECTORY_SEPARATOR.$N;
      if (is_file($cand)) return $cand;
      // case-insensitive
      foreach (glob(rtrim($D,'\\/').DIRECTORY_SEPARATOR.'*.docx') ?: [] as $f){
        if (strcasecmp(basename($f), $N) === 0) return $f;
      }
    }
  }
  // 2) fallback: qualquer .docx que contenha "Contrato" e "PLACEHOLDERS"
  foreach($DIRS as $D){
    foreach (glob(rtrim($D,'\\/').DIRECTORY_SEPARATOR.'*.docx') ?: [] as $f){
      $b = strtolower(basename($f));
      if (strpos($b,'contrato')!==false && strpos($b,'placeholder')!==false) return $f;
    }
  }

  // 3) debug amigável
  http_response_code(500);
  echo "Template não encontrado.\n\n";
  echo "Procurei nas pastas:\n";
  foreach($DIRS as $D){
    echo "- ".$D."\n  ".listFiles($D)."\n";
  }
  echo "\nSe o arquivo estiver lá, verifique:\n";
  echo "• O nome (sem .docx duplicado)\n";
  echo "• Permissões de leitura\n";
  echo "• Se a pasta do projeto é realmente ProjetoPacifico\n";
  return null;
}
$TEMPLATE = locateTemplate();
if(!$TEMPLATE) exit;

// Saída dos contratos
$OUTPUT_DIR = dirname(__DIR__) . '/documentos/contratos/';

/* ==========================
   CONEXÃO DB
   ========================== */
$pdo = new PDO(
  'mysql:host=127.0.0.1;dbname=escola_portal;charset=utf8mb4',
  'root', '',
  [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC ]
);

/* ==========================
   HELPERS
   ========================== */
function safe($v){ return isset($v) ? (string)$v : ''; }
function normTime($t){ $t = trim((string)$t); return preg_match('/^\d{2}:\d{2}$/',$t)?$t:'--:--'; }
function timeDiffHours($ini,$fim){
  if(!preg_match('/^\d{2}:\d{2}$/',$ini) || !preg_match('/^\d{2}:\d{2}$/',$fim)) return 0.0;
  [$h1,$m1] = array_map('intval', explode(':',$ini));
  [$h2,$m2] = array_map('intval', explode(':',$fim));
  $d = max(0, ($h2*60+$m2) - ($h1*60+$m1));
  return round($d/60,2);
}
function countWeekdaysBetween(string $startIso, string $endIso): array {
  $start = new DateTime($startIso); $end = new DateTime($endIso);
  if($end < $start) { [$start,$end] = [$end,$start]; }
  $cnt = ['1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0];
  for($d = clone $start; $d <= $end; $d->modify('+1 day')){
    $w = (int)$d->format('N'); if($w>=1 && $w<=5) $cnt[(string)$w]++;
  }
  return $cnt;
}
function fmtDateBr(?string $iso): string {
  if(!$iso) return '____/____/_____';
  $dt = DateTime::createFromFormat('Y-m-d', substr($iso,0,10));
  return $dt? $dt->format('d/m/Y') : '____/____/_____';
}

/* ==========================
   DADOS DO BANCO
   ========================== */
function getAlunoEmpresaEContrato(PDO $pdo, int $alunoId): array {
  $sql = "
    SELECT a.*,
           e.id           AS emp_id,
           e.razao_social AS emp_razao,
           e.nome         AS emp_fantasia,
           e.cnpj         AS emp_cnpj,
           e.logradouro, e.numero, e.complemento, e.bairro, e.cidade, e.uf, e.cep
    FROM alunos a
    LEFT JOIN empresas e ON e.id = a.empresa_id
    WHERE a.id = ?
  ";
  $stmt = $pdo->prepare($sql); $stmt->execute([$alunoId]);
  $A = $stmt->fetch();
  if(!$A) throw new RuntimeException("Aluno não encontrado.");

  $contrato = null;
  try {
    $c = $pdo->prepare("SELECT * FROM contracts WHERE student_id=? ORDER BY id DESC LIMIT 1");
    $c->execute([$alunoId]);
    $contrato = $c->fetch() ?: null;
  } catch(Throwable $e){ /* tabela pode não existir */ }

  return [$A, $contrato];
}

/* ==========================
   AGENDA (HTTP/SQL/Fallback)
   ========================== */
function getAgendaFromHttp(int $alunoId): ?array {
  // Ajuste/ative se tiver endpoint:
  // $url = "http://localhost/ProjetoPacifico/php/get_schedule.php?aluno_id=".$alunoId;
  // $json = @file_get_contents($url);
  // if($json===false) return null;
  // return json_decode($json,true) ?: null;
  return null;
}
function getAgendaFromSQL(PDO $pdo, int $alunoId): ?array {
  try{
    $base = [
      'T'=>['SEG'=>['inicio'=>'--:--','fim'=>'--:--'],'TER'=>['inicio'=>'--:--','fim'=>'--:--'],'QUA'=>['inicio'=>'--:--','fim'=>'--:--'],'QUI'=>['inicio'=>'--:--','fim'=>'--:--'],'SEX'=>['inicio'=>'--:--','fim'=>'--:--']],
      'P'=>['SEG'=>['inicio'=>'--:--','fim'=>'--:--'],'TER'=>['inicio'=>'--:--','fim'=>'--:--'],'QUA'=>['inicio'=>'--:--','fim'=>'--:--'],'QUI'=>['inicio'=>'--:--','fim'=>'--:--'],'SEX'=>['inicio'=>'--:--','fim'=>'--:--']]
    ];
    $q = $pdo->prepare("SELECT tipo, dia, inicio, fim FROM agendas WHERE aluno_id=?");
    $q->execute([$alunoId]);
    foreach($q as $r){
      $tipo = strtoupper((string)$r['tipo']); $dia = strtoupper((string)$r['dia']);
      if(isset($base[$tipo][$dia])){
        $base[$tipo][$dia]['inicio'] = normTime($r['inicio'] ?? '--:--');
        $base[$tipo][$dia]['fim']    = normTime($r['fim'] ?? '--:--');
      }
    }
    return $base;
  } catch(Throwable $e){
    return null;
  }
}
function getAgenda(PDO $pdo, int $alunoId): array {
  $a = getAgendaFromHttp($alunoId);
  if(is_array($a) && isset($a['T'],$a['P'])) return $a;
  $b = getAgendaFromSQL($pdo,$alunoId);
  if(is_array($b)) return $b;
  return [
    'T'=>['SEG'=>['inicio'=>'--:--','fim'=>'--:--'],'TER'=>['inicio'=>'--:--','fim'=>'--:--'],'QUA'=>['inicio'=>'--:--','fim'=>'--:--'],'QUI'=>['inicio'=>'--:--','fim'=>'--:--'],'SEX'=>['inicio'=>'--:--','fim'=>'--:--']],
    'P'=>['SEG'=>['inicio'=>'--:--','fim'=>'--:--'],'TER'=>['inicio'=>'--:--','fim'=>'--:--'],'QUA'=>['inicio'=>'--:--','fim'=>'--:--'],'QUI'=>['inicio'=>'--:--','fim'=>'--:--'],'SEX'=>['inicio'=>'--:--','fim'=>'--:--']]
  ];
}
function calcularCargas(array $agenda, ?string $inicioISO, ?string $fimISO): array {
  $dias = ['SEG'=>1,'TER'=>2,'QUA'=>3,'QUI'=>4,'SEX'=>5];
  $det = ['T'=>[],'P'=>[]]; $T_sem = 0.0; $P_sem = 0.0;
  foreach(['T','P'] as $tipo){
    foreach($dias as $sig=>$n){
      $ini = $agenda[$tipo][$sig]['inicio'] ?? '--:--';
      $fim = $agenda[$tipo][$sig]['fim'] ?? '--:--';
      $h = timeDiffHours($ini,$fim);
      $det[$tipo][$sig] = ['inicio'=>$ini,'fim'=>$fim,'total'=>$h];
      if($tipo==='T') $T_sem += $h; else $P_sem += $h;
    }
  }
  $T_per=0.0; $P_per=0.0;
  if($inicioISO && $fimISO){
    $rep = countWeekdaysBetween($inicioISO,$fimISO); // '1'..'5'
    $map = ['SEG'=>'1','TER'=>'2','QUA'=>'3','QUI'=>'4','SEX'=>'5'];
    foreach(['T','P'] as $tipo){
      foreach($map as $sig=>$key){
        $add = $det[$tipo][$sig]['total'] * ($rep[$key] ?? 0);
        if($tipo==='T') $T_per += $add; else $P_per += $add;
      }
    }
  }
  return ['T_SEM'=>round($T_sem,2),'P_SEM'=>round($P_sem,2),'T_PER'=>round($T_per,2),'P_PER'=>round($P_per,2),'DET'=>$det];
}

/* ==========================
   MAIN
   ========================== */
// Aceita ?id= ou ?aluno_id= (GET/POST)
$alunoId = 0;
if (isset($_GET['id']))            $alunoId = (int) $_GET['id'];
elseif (isset($_GET['aluno_id']))  $alunoId = (int) $_GET['aluno_id'];
elseif (isset($_POST['id']))       $alunoId = (int) $_POST['id'];
elseif (isset($_POST['aluno_id'])) $alunoId = (int) $_POST['aluno_id'];
if($alunoId<=0){ http_response_code(400); exit('Informe ?id= ou ?aluno_id='); }

list($A, $C) = getAlunoEmpresaEContrato($pdo, $alunoId);

// Datas do contrato: priority contracts -> alunos
$dtIni = $C['inicio'] ?? $A['inicio_trabalho'] ?? null;
$dtFim = $C['fim']    ?? $A['fim_trabalho']    ?? null;

// Endereço aluno
$endAluno = trim(implode(', ', array_filter([
  safe($A['endereco_rua'] ?? ''),
  ($A['endereco_numero'] ? 'Nº '.safe($A['endereco_numero']) : ''),
  safe($A['endereco_bairro'] ?? '')
])));
$endAluno2 = trim(implode(' - ', array_filter([ safe($A['endereco_cidade'] ?? ''), safe($A['endereco_estado'] ?? '') ])));
$endAlunoFull = trim(implode('; ', array_filter([ $endAluno, ($endAluno2?:null), ($A['cep']?'CEP '.$A['cep']:null) ])));

// Endereço empregador
$endEmp = trim(implode(', ', array_filter([
  safe($A['logradouro'] ?? ''),
  ($A['numero'] ? 'Nº '.safe($A['numero']) : ''),
  safe($A['bairro'] ?? '')
])));
$endEmp2 = trim(implode(' - ', array_filter([ safe($A['cidade'] ?? ''), safe($A['uf'] ?? '') ])));
$endEmpFull = trim(implode(', ', array_filter([ $endEmp, $endEmp2, ($A['cep']?'CEP '.$A['cep']:null) ])));

// Agenda & cálculos
$agenda = getAgenda($pdo, $alunoId);
$totais = calcularCargas($agenda, $dtIni, $dtFim);
$CARGA_TEO  = $totais['T_PER'];
$CARGA_PRAT = $totais['P_PER'];
$CARGA_TOTAL= $CARGA_TEO + $CARGA_PRAT;

// Saída
if(!is_dir($OUTPUT_DIR)) @mkdir($OUTPUT_DIR, 0777, true);
$tp = new TemplateProcessor($TEMPLATE);

// EMPREGADOR
$tp->setValue('EMPREGADOR_NOME', safe($A['emp_razao'] ?: ($A['emp_fantasia'] ?? '')));
$tp->setValue('EMPREGADOR_ENDERECO_COMPLETO', $endEmpFull);
$tp->setValue('EMPREGADOR_CNPJ', safe($A['emp_cnpj'] ?? ''));

// ALUNO
$tp->setValue('ALUNO_NOME', safe($A['nome']));
$tp->setValue('ALUNO_ENDERECO', $endAlunoFull);
$tp->setValue('ALUNO_CPF', safe($A['cpf']));

// CURSO + DATAS + CARGAS
$tp->setValue('CURSO_NOME', safe($A['curso']));
$tp->setValue('DATA_INICIO', fmtDateBr($dtIni));
$tp->setValue('DATA_TERMINO', fmtDateBr($dtFim));
$tp->setValue('CARGA_TEO', number_format($CARGA_TEO,0,',','.'));
$tp->setValue('CARGA_PRAT', number_format($CARGA_PRAT,0,',','.'));
$tp->setValue('CARGA_TOTAL', number_format($CARGA_TOTAL,0,',','.'));

// Tabelas (T/P)
$ordem = ['SEG','TER','QUA','QUI','SEX'];
foreach(['T','P'] as $tipo){
  foreach($ordem as $d){
    $tp->setValue("{$tipo}_INICIO_{$d}", $agenda[$tipo][$d]['inicio'] ?? '--:--');
    $tp->setValue("{$tipo}_FIM_{$d}",    $agenda[$tipo][$d]['fim']    ?? '--:--');
    $hor = $totais['DET'][$tipo][$d]['total'] ?? 0;
    $tp->setValue("{$tipo}_TOTAL_{$d}",  ($hor>0? ($hor.' h') : '0 h'));
  }
}
$tp->setValue('T_TOTAL_SEM', ($totais['T_SEM']>0? $totais['T_SEM'].' horas':'0 horas'));
$tp->setValue('P_TOTAL_SEM', ($totais['P_SEM']>0? $totais['P_SEM'].' horas':'0 horas'));

$nomeArq = 'Contrato_Aprendizagem_Aluno_'.$alunoId.'_'.date('Ymd_His').'.docx';
$caminho = $OUTPUT_DIR . $nomeArq;
$tp->saveAs($caminho);

// Download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$nomeArq.'"');
readfile($caminho);
exit;
?>