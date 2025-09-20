<?php
// php/export_excel.php
declare(strict_types=1);

/* ================== DIAGNÓSTICO OPCIONAL ================== */
// Use ?debug=1 na URL para ver erros
$__DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($__DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); }
else          { ini_set('display_errors','0'); error_reporting(E_ALL); }

/* ================== AUTENTICAÇÃO (se existir) ================== */
@require_once __DIR__ . '/auth_admin.php';

/* ================== CONEXÃO ================== */
require_once __DIR__ . '/db.php';
$mysqli  = $mysqli ?? (isset($conn) && $conn instanceof mysqli ? $conn : null);
$isPDO    = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = $mysqli instanceof mysqli;
if (!$isPDO && !$isMySQLi) {
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: text/plain; charset=utf-8');
  http_response_code(500);
  echo "Nenhuma conexão disponível. Verifique se db.php define \$pdo ou \$mysqli.";
  exit;
}

/* ================== HELPERS ================== */
function str_to_lower(string $s): string {
  return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s);
}
function iso_date(?string $x): ?string {
  if (!$x) return null; $x = trim($x); if ($x==='') return null;
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/',$x)) { [$d,$m,$y]=explode('/',$x); return checkdate((int)$m,(int)$d,(int)$y)?sprintf('%04d-%02d-%02d',$y,$m,$d):null; }
  if (preg_match('/^\d{4}-\d{2}-\d{2}/',$x))   { return substr($x,0,10); }
  $ts = strtotime($x); return $ts?date('Y-m-d',$ts):null;
}
function yn($v): string {
  if ($v === null || $v === '') return '-';
  $s = str_to_lower(trim((string)$v));
  if (in_array($s,['1','true','sim','s'],true))  return 'Sim';
  if (in_array($s,['0','false','nao','não','n'],true)) return 'Não';
  if (is_numeric($s)) return ((int)$s)!==0 ? 'Sim':'Não';
  return ucfirst($s);
}

/* ================== FILTROS OPCIONAIS ================== */
$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
$cursoFilter  = isset($_GET['curso'])  ? trim((string)$_GET['curso'])  : null;

/* ================== NOME DO BANCO ================== */
try {
  if ($isPDO)    { $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
  else           { $res = $mysqli->query("SELECT DATABASE()"); $dbName = $res ? (string)($res->fetch_row()[0] ?? '') : ''; }
} catch (Throwable $e) { $dbName = ''; }

/* ================== CHECAR COLUNA EXISTENTE ================== */
function findCol($isPDO,$pdo,$mysqli,string $db,string $table,array $cands):?string{
  if(!$db) return null;
  $in = implode("','", array_map(fn($c)=>str_replace("'","''",$c), $cands));
  $sql="SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME IN ('{$in}')
        ORDER BY FIELD(COLUMN_NAME,'{$in}') LIMIT 1";
  try{
    if($isPDO){ $st=$pdo->prepare($sql); $st->execute([$db,$table]); $c=$st->fetchColumn(); return $c? (string)$c : null; }
    else      { $st=$mysqli->prepare($sql); $st->bind_param("ss",$db,$table); $st->execute(); $rs=$st->get_result(); $row=$rs?$rs->fetch_row():null; $st->close(); return $row[0] ?? null; }
  }catch(Throwable $e){ return null; }
}

/* ================== ENDEREÇO DINÂMICO DA EMPRESA ================== */
$empCols=[];
$empCols['logradouro']=findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['logradouro','rua','endereco']);
$empCols['numero']    =findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['numero','nro','num']);
$empCols['bairro']    =findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['bairro']);
$empCols['cidade']    =findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['cidade','municipio','município']);
$empCols['uf']        =findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['uf','estado','sigla_uf']);
$empCols['cep']       =findCol($isPDO,$pdo,$mysqli,$dbName,'empresas',['cep']);

$empSelect=[];
foreach(['logradouro','numero','bairro','cidade','uf','cep'] as $a){
  $c=$empCols[$a]??null;
  $empSelect[] = $c ? "e.`{$c}` AS empresa_{$a}" : "NULL AS empresa_{$a}";
}
$empSelectSql = implode(",\n  ", $empSelect);

/* ================== CONSULTA (sem bolsa/recebe_salario) ================== */
$sql = "
SELECT
  a.id,
  a.nome,
  a.cpf,
  a.ra,
  a.data_nascimento,
  a.curso,
  a.turno,
  a.serie,
  a.status,
  a.contato_aluno,
  a.inicio_trabalho,
  a.fim_trabalho,
  a.tipo_contrato,
  a.recebeu_bolsa,
  a.renovou_contrato,
  COALESCE(e.nome, e.razao_social, a.empresa) AS empresa_nome,
  e.cnpj     AS empresa_cnpj,
  e.telefone AS empresa_telefone,
  {$empSelectSql},
  a.relatorio,
  a.observacao
FROM alunos a
LEFT JOIN empresas e ON e.id = a.empresa_id
";

$where=[]; $params=[];
if ($statusFilter){ $where[]="a.status = ?"; $params[]=$statusFilter; }
if ($cursoFilter) { $where[]="a.curso  = ?"; $params[]=$cursoFilter; }
if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY a.nome ASC";

/* ================== EXECUTA ================== */
$rows=[];
try{
  if($isPDO){ $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
  else {
    $st=$mysqli->prepare($sql);
    if(!$st){ while (ob_get_level()) ob_end_clean(); header('Content-Type: text/plain; charset=utf-8'); http_response_code(500); echo "Falha ao preparar consulta (MySQLi): ".$mysqli->error; exit; }
    if($params){ $types=str_repeat('s',count($params)); $st->bind_param($types,...$params); }
    $st->execute(); $rs=$st->get_result(); while($rs && ($r=$rs->fetch_assoc())) $rows[]=$r; $st->close();
  }
}catch(Throwable $e){
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: text/plain; charset=utf-8'); http_response_code(500);
  echo "Erro ao consultar: ".$e->getMessage(); exit;
}

/* ================== TENTA PhpSpreadsheet ================== */
$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; }
$have = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);

/* ================== Cabeçalho (sem Recebe Salário e sem Valor/Info Bolsa) ================== */
$cols = [
  'A'=>'ID','B'=>'Nome','C'=>'CPF','D'=>'RA','E'=>'Nascimento','F'=>'Curso','G'=>'Turno','H'=>'Série',
  'I'=>'Status','J'=>'Contato','K'=>'Início Trabalho','L'=>'Fim Trabalho','M'=>'Tipo de Contrato',
  'N'=>'Recebeu Bolsa','O'=>'Renovou Contrato',
  'P'=>'Empresa','Q'=>'CNPJ','R'=>'Telefone','S'=>'Endereço (linha 1)','T'=>'Endereço (linha 2)',
  'U'=>'Relatório','V'=>'Observação'
];

$buildEndereco = function(array $r): array {
  $l1 = '';
  if (!empty($r['empresa_logradouro'])) $l1 = (string)$r['empresa_logradouro'];
  if (!empty($r['empresa_numero']))     $l1 = $l1 ? ($l1.', '.$r['empresa_numero']) : (string)$r['empresa_numero'];
  $p2=[]; foreach(['empresa_bairro','empresa_cidade','empresa_uf','empresa_cep'] as $k){ if(!empty($r[$k])) $p2[]=(string)$r[$k]; }
  $l2 = $p2 ? implode(' • ', $p2) : ''; return [$l1,$l2];
};

/* ================== CSV FALLBACK (sempre válido) ================== */
if (!$have) {
  while (ob_get_level()) ob_end_clean();
  $filename='alunos_export_'.date('Ymd_His').'.csv';
  header('Content-Type: text/csv; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"{$filename}\"");
  header('Cache-Control: max-age=0');
  $out=fopen('php://output','w');
  fputcsv($out, array_values($cols), ';');
  foreach($rows as $r){
    $nasc=iso_date($r['data_nascimento']??null);
    $ini =iso_date($r['inicio_trabalho']??null);
    $fim =iso_date($r['fim_trabalho']??null);
    [$end1,$end2] = $buildEndereco($r);
    fputcsv($out, [
      $r['id']??'',$r['nome']??'',$r['cpf']??'',$r['ra']??'',
      $nasc ? date('d/m/Y',strtotime($nasc)) : '',
      $r['curso']??'',$r['turno']??'',$r['serie']??'',$r['status']??'',$r['contato_aluno']??'',
      $ini ? date('d/m/Y',strtotime($ini)) : '',
      $fim ? date('d/m/Y',strtotime($fim)) : '',
      $r['tipo_contrato']??'',
      yn($r['recebeu_bolsa']??null),
      yn($r['renovou_contrato']??null),
      $r['empresa_nome']??'',$r['empresa_cnpj']??'',$r['empresa_telefone']??'',
      $end1,$end2,$r['relatorio']??'',$r['observacao']??'',
    ], ';');
  }
  fclose($out); exit;
}

/* ================== XLSX (PhpSpreadsheet) ================== */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Cell\DataType; // <— IMPORT DO TIPO

$xlsx = new Spreadsheet();
$sheet = $xlsx->getActiveSheet();
$sheet->setTitle('Alunos');

/* Cabeçalho */
foreach($cols as $c=>$label){ $sheet->setCellValue("{$c}1",$label); }
$headerRange='A1:V1'; // agora vai até V
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(22);

/* Dados (célula por célula) */
$row=2; $dateFmt='dd/mm/yyyy';
foreach($rows as $r){
  [$end1,$end2] = $buildEndereco($r);
  $map = [
    'A'=>$r['id']??'','B'=>$r['nome']??'','C'=>$r['cpf']??'','D'=>$r['ra']??'',
    'E'=>iso_date($r['data_nascimento']??null),
    'F'=>$r['curso']??'','G'=>$r['turno']??'','H'=>$r['serie']??'','I'=>$r['status']??'','J'=>$r['contato_aluno']??'',
    'K'=>iso_date($r['inicio_trabalho']??null),'L'=>iso_date($r['fim_trabalho']??null),
    'M'=>$r['tipo_contrato']??'',
    'N'=>yn($r['recebeu_bolsa']??null),
    'O'=>yn($r['renovou_contrato']??null),
    'P'=>$r['empresa_nome']??'','Q'=>$r['empresa_cnpj']??'','R'=>$r['empresa_telefone']??'',
    'S'=>$end1,'T'=>$end2,'U'=>$r['relatorio']??'','V'=>$r['observacao']??'',
  ];
  foreach($map as $col=>$val){
    if (in_array($col,['E','K','L'],true) && $val) {
      $sheet->setCellValue("{$col}{$row}", ExcelDate::PHPToExcel(strtotime($val)));
      $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($dateFmt);
      $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    } else {
      // grava como TEXTO (preserva zeros à esquerda)
      $sheet->setCellValueExplicit("{$col}{$row}", (string)$val, DataType::TYPE_STRING);
    }
  }
  if ($row % 2 === 0) {
    $sheet->getStyle("A{$row}:V{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F9FC');
  }
  $row++;
}

/* Estilos finais */
$last=$row-1; $range="A1:V{$last}";
$sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCCCCC');
$sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("H2:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("U2:V{$last}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$sheet->setAutoFilter($range);
$sheet->freezePane('A2');
foreach(array_keys($cols) as $c){ $sheet->getColumnDimension($c)->setAutoSize(true); }

/* Saída XLSX (binário limpo) */
while (ob_get_level()) ob_end_clean();
$filename='alunos_export_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');
$writer = new Xlsx($xlsx);
$writer->save('php://output');
exit;
?>