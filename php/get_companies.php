<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// Proteção
if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
}

$sql = "SELECT id, nome, cnpj, contato, telefone, created_at FROM companies ORDER BY nome ASC";
$res = $mysqli->query($sql);

$companies = [];
while($row = $res->fetch_assoc()){
    $companies[] = $row;
}

echo json_encode(['success'=>true,'data'=>$companies]);
$mysqli->close();
?>
