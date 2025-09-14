<?php
// php/register_company.php
header('Content-Type: application/json; charset=utf-8');

function out($ok, $msg=null, $extra=[]){
  echo json_encode(array_merge(['success'=>$ok, 'message'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require __DIR__ . '/db.php';
  $pdo = pdo();
} catch (Throwable $e) {
  out(false, 'Falha de conexão: '.$e->getMessage());
}

// Aceita application/json ou multipart/form-data
if (!empty($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $_POST = json_decode($raw, true) ?: [];
}

// Campos
$nome          = trim($_POST['razao_social'] ?? $_POST['nome'] ?? '');
$cnpj          = trim($_POST['cnpj'] ?? '');
$logradouro    = trim($_POST['logradouro'] ?? '');
$numero        = trim($_POST['numero'] ?? '');
$complemento   = trim($_POST['complemento'] ?? '');
$bairro        = trim($_POST['bairro'] ?? '');
$cidade        = trim($_POST['cidade'] ?? '');
$uf            = strtoupper(trim($_POST['uf'] ?? ''));
$cep           = trim($_POST['cep'] ?? '');
$telefone      = trim($_POST['telefone'] ?? '');
$tipo_contrato = trim($_POST['tipo_contrato'] ?? '');

if ($nome === '')          out(false, 'Informe o nome/razão social.');
if ($cnpj === '')          out(false, 'Informe o CNPJ.');
if ($tipo_contrato === '') out(false, 'Informe o tipo de contrato.');

try {
  $stmt = $pdo->prepare("
    INSERT INTO empresas
    (nome, razao_social, cnpj, logradouro, numero, complemento, bairro, cidade, uf, cep, telefone, tipo_contrato)
    VALUES (:nome, :razao, :cnpj, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :cep, :telefone, :tipo_contrato)
  ");
  $stmt->execute([
    ':nome'          => $nome,
    ':razao'         => $nome,
    ':cnpj'          => $cnpj,
    ':logradouro'    => $logradouro,
    ':numero'        => $numero,
    ':complemento'   => $complemento,
    ':bairro'        => $bairro,
    ':cidade'        => $cidade,
    ':uf'            => $uf,
    ':cep'           => $cep,
    ':telefone'      => $telefone,
    ':tipo_contrato' => $tipo_contrato,
  ]);

  $id = (int)$pdo->lastInsertId();
  out(true, 'Empresa cadastrada com sucesso.', ['id'=>$id]);
} catch (Throwable $e) {
  out(false, 'Erro ao salvar empresa: '.$e->getMessage());
}
?>