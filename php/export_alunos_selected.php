<?php
// php/export_alunos_selected.php  (CORRIGIDO — SUBSTITUA COMPLETO)
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/db.php';

// Detecta conexão
$mysqli   = $mysqli ?? (isset($conn) && $conn instanceof mysqli ? $conn : null);
$isPDO    = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = $mysqli instanceof mysqli;

// Consulta (ajuste os nomes das colunas caso sejam diferentes no seu banco)
$sql = "
  SELECT
    nome,
    contato,
    ano_nascimento,
    inicio_contrato,
    termino_contrato,
    status,
    empresa,
    relatorio,
    serie,
    curso,
    turno,
    observacao
  FROM alunos
  ORDER BY nome ASC
";

$rows = [];
try {
  if ($isPDO) {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } elseif ($isMySQLi) {
    $res = $mysqli->query($sql);
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
  } else {
    http_response_code(500);
    echo "Nenhuma conexão encontrada. Garanta \$pdo (PDO) ou \$mysqli/\$conn (MySQLi) no db.php.";
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Erro ao consultar: " . $e->getMessage();
  exit;
}

$headers = [
  'Nome','Contato','Ano de Nascimento','Início do Contrato','Término do Contrato',
  'Status','Empresa','Relatório','Série','Curso','Turno','Observação'
];

$format = strtolower($_GET['format'] ?? 'csv');
$filenameBase = 'alunos_campos_' . date('Y-m-d_H-i-s');

if ($format === 'xlsx') {
  // Carrega autoload apenas quando precisar do XLSX
  require_once __DIR__ . '/../vendor/autoload.php';

  // Cria planilha (sem usar "use", chamando classes com FQN)
  $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sh = $ss->getActiveSheet();

  // Cabeçalhos
  $c = 1;
  foreach ($headers as $h) {
    $sh->setCellValueByColumnAndRow($c++, 1, $h);
  }

  // Dados
  $r = 2;
  foreach ($rows as $row) {
    $c = 1;
    foreach ([
      'nome','contato','ano_nascimento','inicio_contrato','termino_contrato',
      'status','empresa','relatorio','serie','curso','turno','observacao'
    ] as $f) {
      $sh->setCellValueByColumnAndRow($c++, $r, $row[$f] ?? '');
    }
    $r++;
  }

  // Estilo
  $sh->getStyle('A1:L1')->getFont()->setBold(true);
  for ($i = 'A'; $i <= 'L'; $i++) {
    $sh->getColumnDimension($i)->setAutoSize(true);
  }

  // Saída
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$filenameBase.'.xlsx"');
  header('Cache-Control: max-age=0');

  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
  $writer->save('php://output');
  exit;
}

/* ===== CSV (padrão) ===== */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filenameBase.'.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// BOM UTF-8 para Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
$delim = ';';

// Cabeçalho
fputcsv($out, $headers, $delim);

// Linhas
foreach ($rows as $row) {
  fputcsv($out, [
    $row['nome'] ?? '',
    $row['contato'] ?? '',
    $row['ano_nascimento'] ?? '',
    $row['inicio_contrato'] ?? '',
    $row['termino_contrato'] ?? '',
    $row['status'] ?? '',
    $row['empresa'] ?? '',
    $row['relatorio'] ?? '',
    $row['serie'] ?? '',
    $row['curso'] ?? '',
    $row['turno'] ?? '',
    $row['observacao'] ?? '',
  ], $delim);
}

fclose($out);
exit;
?>