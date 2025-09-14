<?php
require __DIR__.'/api_boot.php';
require __DIR__.'/db.php';

$pdo = pdo();

$sql = "
  SELECT 
    id,
    COALESCE(nome, razao_social) AS nome,
    cnpj,
    logradouro, numero, complemento, bairro, cidade, uf, cep,
    telefone,
    tipo_contrato
  FROM empresas
  ORDER BY nome IS NULL, nome
";
$rows = $pdo->query($sql)->fetchAll();

$data = array_map(function($r){
  return [
    'id'   => (string)$r['id'],
    'nome' => (string)($r['nome'] ?? ('Empresa '.$r['id'])),
    'cnpj' => $r['cnpj'] ?? null,
    'logradouro' => $r['logradouro'] ?? null,
    'numero'     => $r['numero'] ?? null,
    'complemento'=> $r['complemento'] ?? null,
    'bairro'     => $r['bairro'] ?? null,
    'cidade'     => $r['cidade'] ?? null,
    'uf'         => $r['uf'] ?? null,
    'cep'        => $r['cep'] ?? null,
    'telefone'   => $r['telefone'] ?? null,
    'tipo_contrato' => $r['tipo_contrato'] ?? null,
  ];
}, $rows);

api_out(true, $data, null);
?>