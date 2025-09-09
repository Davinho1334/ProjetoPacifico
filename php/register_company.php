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

// Lê dados enviados
$razao = trim($_POST['razao_social'] ?? '');
$cnpj = preg_replace('/\D/','', $_POST['cnpj'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$cep = preg_replace('/\D/','', $_POST['cep'] ?? '');
$telefone = preg_replace('/\D/','', $_POST['telefone'] ?? '');
$tipo = trim($_POST['tipo_contrato'] ?? '');

// Validação
if(!$razao || !$cnpj){
    echo json_encode(['success'=>false,'message'=>'Campos obrigatórios ausentes (razão social e CNPJ).']);
    exit;
}

// Verifica duplicado
$stmt = $mysqli->prepare("SELECT id FROM empresas WHERE cnpj = ?");
$stmt->bind_param('s', $cnpj);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
    echo json_encode(['success'=>false,'message'=>'CNPJ já cadastrado.']);
    $stmt->close();
    exit;
}
$stmt->close();

// Inserção
$stmt = $mysqli->prepare("
    INSERT INTO empresas (razao_social, cnpj, endereco, cep, telefone, tipo_contrato, criado_em)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param('ssssss', $razao, $cnpj, $endereco, $cep, $telefone, $tipo);

if($stmt->execute()){
    echo json_encode(['success'=>true,'message'=>'Empresa cadastrada com sucesso!']);
} else {
    echo json_encode(['success'=>false,'message'=>'Erro ao cadastrar: '.$stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
