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

// Buscar empresas
$sql = "SELECT id, razao_social, cnpj, endereco, cep, telefone, tipo_contrato, criado_em
        FROM empresas
        ORDER BY razao_social ASC";
$res = $mysqli->query($sql);

$companies = [];
while($row = $res->fetch_assoc()){
    $companies[] = $row;
}

echo json_encode(['success'=>true,'data'=>$companies]);
$mysqli->close();
?>
