<?php
session_start();
require 'db.php';
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    exit("Não autorizado");
}

$id = $_GET['id'] ?? null;
if(!$id) exit("ID ausente");

$stmt = $mysqli->prepare("SELECT * FROM alunos WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();
$res = $stmt->get_result();
$aluno = $res->fetch_assoc();

$html = "<h1>Relatório do Aluno</h1>";
foreach($aluno as $k=>$v){
  $html .= "<p><b>$k:</b> $v</p>";
}

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream("aluno_{$id}.pdf", ["Attachment"=>true]);
?>
