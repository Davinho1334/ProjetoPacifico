<?php
session_start();
require 'db.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    exit("Não autorizado");
}

$res = $mysqli->query("SELECT * FROM alunos ORDER BY nome ASC");
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$cols = ["id","ra","nome","cpf","curso","turno","serie","status","empresa","cargaSemanal","bolsa"];
$sheet->fromArray($cols, null, "A1");

$i=2;
while($row=$res->fetch_assoc()){
    $sheet->fromArray(array_map(fn($c)=>$row[$c],$cols),null,"A".$i);
    $i++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="alunos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
?>
