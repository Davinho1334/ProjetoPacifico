<?php
// php/edit_student.php
declare(strict_types=1);

require __DIR__ . '/api_boot.php'; // garante saída JSON mesmo em erro
require __DIR__ . '/db.php';

$pdo = pdo();

/**
 * Lê o corpo JSON (ou form-data) e retorna array.
 */
function read_body(): array {
  if (!empty($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Payload inválido (JSON).');
    return $data;
  }
  return $_POST; // fallback
}

/**
 * Converte data string dd/mm/aaaa -> Y-m-d.
 * Aceita também 'Y-m-d' e retorna como está.
 */
function br_to_sql_date(?string $s): ?string {
  $s = trim((string)$s);
  if ($s === '') return null;
  // já está no formato SQL?
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

  // dd/mm/aaaa
  if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }

  // tenta parse genérico
  $ts = strtotime($s);
  if ($ts === false) return null;
  return date('Y-m-d', $ts);
}

$in = read_body();

// -------- validações básicas --------
$id = isset($in['id']) ? (string)$in['id'] : '';
if ($id === '') {
  api_out(false, null, 'ID do aluno é obrigatório.');
}

$campos = [
  'ra'              => isset($in['ra']) ? trim((string)$in['ra']) : null,
  'curso'           => isset($in['curso']) ? trim((string)$in['curso']) : null,
  'turno'           => isset($in['turno']) ? trim((string)$in['turno']) : null,
  'serie'           => isset($in['serie']) ? trim((string)$in['serie']) : null,
  'status'          => isset($in['status']) ? trim((string)$in['status']) : null,
  'cargaSemanal'    => isset($in['cargaSemanal']) ? (int)$in['cargaSemanal'] : null,

  'empresa_id'      => (isset($in['empresa_id']) && $in['empresa_id'] !== '' && $in['empresa_id'] !== null) ? (int)$in['empresa_id'] : null,
  'inicio_trabalho' => br_to_sql_date($in['inicio_trabalho'] ?? null),
  'fim_trabalho'    => br_to_sql_date($in['fim_trabalho'] ?? null),
  'renovou_contrato'=> isset($in['renovou_contrato']) ? (int)$in['renovou_contrato'] : 0,

  'contato_aluno'   => isset($in['contato_aluno']) ? trim((string)$in['contato_aluno']) : null,
  'idade'           => (isset($in['idade']) && $in['idade'] !== '') ? (int)$in['idade'] : null,
  'relatorio'       => isset($in['relatorio']) ? trim((string)$in['relatorio']) : null,
  'observacao'      => isset($in['observacao']) ? trim((string)$in['observacao']) : null,
  'tipo_contrato'   => isset($in['tipo_contrato']) ? trim((string)$in['tipo_contrato']) : null,

  'recebeu_bolsa'   => (array_key_exists('recebeu_bolsa', $in) ? (($in['recebeu_bolsa'] === '' || $in['recebeu_bolsa'] === null) ? null : (int)$in['recebeu_bolsa']) : null),

  // NOVOS
  'recebe_salario'  => (array_key_exists('recebe_salario', $in) ? (($in['recebe_salario'] === '' || $in['recebe_salario'] === null) ? null : (int)$in['recebe_salario']) : null),
  'salario'         => (array_key_exists('salario', $in) ? (($in['salario'] === '' || $in['salario'] === null) ? null : (float)$in['salario']) : null),
  'cbo'             => isset($in['cbo']) ? trim((string)$in['cbo']) : null,
];

// Monta dinamicamente o UPDATE somente com colunas presentes
$set = [];
$params = [ ':id' => $id ];
foreach ($campos as $col => $val) {
  // Se quisermos permitir NULL explícito, sempre incluímos no SET (mesmo null)
  $set[] = "{$col} = :{$col}";
  $params[":{$col}"] = $val;
}

$sql = "UPDATE alunos SET ".implode(', ', $set)." WHERE id = :id";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // Retorna o registro atualizado (com nome da empresa)
  $q = $pdo->prepare("
    SELECT a.*,
           e.nome AS empresa_nome
      FROM alunos a
 LEFT JOIN empresas e ON e.id = a.empresa_id
     WHERE a.id = :id
     LIMIT 1
  ");
  $q->execute([':id'=>$id]);
  $aluno = $q->fetch();

  api_out(true, $aluno, 'Atualizado com sucesso.');
} catch (Throwable $e) {
  api_out(false, null, 'Erro ao atualizar: '.$e->getMessage());
}
?>