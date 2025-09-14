<?php
require __DIR__.'/api_boot.php';
require __DIR__.'/db.php';

$pdo = pdo();

// Filtro por id (opcional)
$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

// Monte os campos de acordo com sua tabela de alunos
$sqlBase = "
  SELECT 
    a.id,
    a.nome,
    a.cpf,
    a.ra,
    a.curso,
    a.turno,
    a.serie,
    a.status,
    a.escola,
    a.cargaSemanal,
    a.recebeu_bolsa,
    a.tipo_contrato,
    a.empresa_id,
    a.inicio_trabalho,
    a.fim_trabalho,
    a.renovou_contrato,
    a.contato_aluno,
    a.idade,
    a.relatorio,
    a.observacao,
    a.cbo,
    a.recebe_salario,
    a.salario,
    e.nome AS empresa_nome
  FROM alunos a
  LEFT JOIN empresas e ON e.id = a.empresa_id
";

$params = [];
if ($id !== '') {
  $sql = $sqlBase." WHERE a.id = :id LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id'=>$id]);
  $row = $stmt->fetch();
  if (!$row) api_out(true, null, null); // aluno não encontrado -> data = null
  api_out(true, $row, null);
} else {
  $sql = $sqlBase." ORDER BY a.nome";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll();
  api_out(true, $rows, null);
}
?>