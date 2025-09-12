<?php
// php/export_excel.php  (SUBSTITUA COMPLETO)
declare(strict_types=1);

// 1) Autorização centralizada
require_once __DIR__ . '/auth_admin.php';

// 2) Conexão
require_once __DIR__ . '/db.php';

// Detecta conexão: PDO ($pdo) OU MySQLi ($mysqli/$conn)
$mysqli = $mysqli ?? (isset($conn) && $conn instanceof mysqli ? $conn : null);
$isPDO = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = $mysqli instanceof mysqli;

if (!$isPDO && !$isMySQLi) {
  http_response_code(500);
  echo "Nenhuma conexão encontrada. Garanta \$pdo (PDO) ou \$mysqli/\$conn (MySQLi) no db.php.";
  exit;
}

// 3) PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
  http_response_code(500);
  echo "PhpSpreadsheet ausente. Rode: composer require phpoffice/phpspreadsheet";
  exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 4) Busque os dados (aqui exporta TODAS as colunas da tabela alunos)
//    Se quiser apenas campos específicos, troque o SELECT * por um SELECT nome, contato, ...
$sql = "SELECT * FROM alunos ORDER BY nome ASC";
$rows = [];
$columns = [];

try {
  if ($isPDO) {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!empty($rows)) {
      $columns = array_keys($rows[0]);
    } else {
      $cols = $pdo->query("DESCRIBE alunos")->fetchAll(PDO::FETCH_ASSOC);
      $columns = array_map(fn($c) => $c['Field'], $cols);
    }
  } else {
    $res = $mysqli->query($sql);
    if (!$res) throw new RuntimeException("Falha MySQLi: " . $mysqli->error);
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    if (!empty($rows)) {
      $columns = array_keys($rows[0]);
    } else {
      $resCols = $mysqli->query("DESCRIBE alunos");
      while ($c = $resCols->fetch_assoc()) $columns[] = $c['Field'];
    }
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Erro ao consultar: " . $e->getMessage();
  exit;
}

// 5) Monta planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if (empty($columns)) {
  $columns = ['Mensagem'];
  $rows = [['Mensagem' => 'Sem dados na tabela alunos']];
}

// cabeçalho
$ci = 1;
foreach ($columns as $col) {
  $sheet->setCellValueByColumnAndRow($ci++, 1, $col);
}
// linhas
$ri = 2;
foreach ($rows as $row) {
  $ci = 1;
  foreach ($columns as $col) {
    $sheet->setCellValueByColumnAndRow($ci++, $ri, $row[$col] ?? '');
  }
  $ri++;
}

// estética
$last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
$sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
for ($i = 1; $i <= count($columns); $i++) {
  $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// 6) saída
$filename = 'alunos_export_' . date('Y-m-d_H-i-s') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>