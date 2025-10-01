<?php
// php/register_student.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
$pdo = function_exists('pdo') ? pdo() : null;

// ---- helpers ----
function body_input(): array {
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
  }
  return $_POST ?? [];
}
function pick(array $src, array $keys): ?string {
  foreach ($keys as $k) {
    if (isset($src[$k]) && trim((string)$src[$k]) !== '') return trim((string)$src[$k]);
  }
  return null;
}
function only_digits(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }
function mask_cep(?string $s): ?string {
  $d = only_digits($s);
  if ($d === '') return null;
  if (strlen($d) !== 8) return null;
  return substr($d,0,5) . '-' . substr($d,5,3);
}
function norm_uf(?string $s): ?string {
  $s = strtoupper(trim((string)$s));
  return $s ? substr($s,0,2) : null;
}

// ---- entrada ----
$in = body_input();

// Aceita vários nomes de campos (garantimos compatibilidade)
$nome            = pick($in, ['nome','nome_aluno','aluno_nome','txt_nome','input_nome']);
$cpf             = pick($in, ['cpf','cpf_aluno','aluno_cpf','documento']);
$ra              = pick($in, ['ra','registro_aluno','aluno_ra']);
$data_nascimento = pick($in, ['data_nascimento','nascimento','data_nasc']);
$contato_aluno   = pick($in, ['contato_aluno','contato','telefone','celular','phone']);

$cep             = mask_cep(pick($in, ['cep']));
$end_rua         = pick($in, ['endereco_rua','rua','logradouro']);
$end_numero      = pick($in, ['endereco_numero','numero','num']);
$end_bairro      = pick($in, ['endereco_bairro','bairro']);
$end_cidade      = pick($in, ['endereco_cidade','cidade']);
$end_estado      = norm_uf(pick($in, ['endereco_estado','estado','uf']));

$curso           = pick($in, ['curso']);
$turno           = pick($in, ['turno']);
$serie           = pick($in, ['serie']);

// ---- validações (evita novos vazios) ----
if (!$nome) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Nome é obrigatório.']); exit; }
if (!$cpf)  { http_response_code(400); echo json_encode(['success'=>false,'error'=>'CPF é obrigatório.']); exit; }
if (!$curso || !$turno || !$serie) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Curso/Turno/Série são obrigatórios.']); exit; }
if (isset($in['cep']) && !$cep) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'CEP inválido.']); exit; }

// ---- insert ----
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $sql = "
      INSERT INTO alunos
        (nome, cpf, ra, data_nascimento, contato_aluno,
         cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
         curso, turno, serie)
      VALUES
        (:nome, :cpf, :ra, :data_nascimento, :contato_aluno,
         :cep, :endereco_rua, :endereco_numero, :endereco_bairro, :endereco_cidade, :endereco_estado,
         :curso, :turno, :serie)
    ";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':nome'=>$nome, ':cpf'=>$cpf, ':ra'=>$ra, ':data_nascimento'=>$data_nascimento, ':contato_aluno'=>$contato_aluno,
      ':cep'=>$cep, ':endereco_rua'=>$end_rua, ':endereco_numero'=>$end_numero, ':endereco_bairro'=>$end_bairro, ':endereco_cidade'=>$end_cidade, ':endereco_estado'=>$end_estado,
      ':curso'=>$curso, ':turno'=>$turno, ':serie'=>$serie
    ]);
    if(!$ok) throw new Exception('Falha ao inserir (PDO).');
    echo json_encode(['success'=>true,'message'=>'Aluno cadastrado com sucesso.']); exit;
  }

  $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : null);
  if ($mysqli) {
    $sql = "
      INSERT INTO alunos
        (nome, cpf, ra, data_nascimento, contato_aluno,
         cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
         curso, turno, serie)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ";
    $st = $mysqli->prepare($sql);
    if (!$st) throw new Exception('Falha ao preparar statement (MySQLi).');
    $st->bind_param(
      "ssssssssssssss",
      $nome, $cpf, $ra, $data_nascimento, $contato_aluno,
      $cep, $end_rua, $end_numero, $end_bairro, $end_cidade, $end_estado,
      $curso, $turno, $serie
    );
    $ok = $st->execute();
    if(!$ok) throw new Exception('Falha ao inserir (MySQLi).');
    echo json_encode(['success'=>true,'message'=>'Aluno cadastrado com sucesso.']); exit;
  }

  throw new Exception('Nenhuma conexão de banco ativa.');
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit;
}
?>