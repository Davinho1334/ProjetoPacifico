<?php
// php/register_student.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
$pdo = function_exists('pdo') ? pdo() : null;

/* ---------------- helpers ---------------- */

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
/** Converte dd/mm/aaaa -> aaaa-mm-dd (ou null se vazio/inválido) */
function toISODate(?string $s): ?string {
  $s = trim((string)$s);
  if ($s === '' || $s === '0000-00-00') return null;
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { // já está em ISO
    [$y,$m,$d] = explode('-', $s);
    return checkdate((int)$m,(int)$d,(int)$y) ? $s : null;
  }
  if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
    [$all,$d,$mm,$y] = $m;
    return checkdate((int)$mm,(int)$d,(int)$y) ? sprintf('%04d-%02d-%02d', $y, $mm, $d) : null;
  }
  return null;
}

/* ---------------- entrada ---------------- */

$in = body_input();

$nome            = pick($in, ['nome','nome_aluno','aluno_nome','txt_nome','input_nome']);
$cpf             = pick($in, ['cpf','cpf_aluno','aluno_cpf','documento']);
$ra              = pick($in, ['ra','registro_aluno','aluno_ra']);
$data_nascimento = pick($in, ['data_nascimento','nascimento','data_nasc','dt_nascimento','dt_nasc']); // <- pegar antes de converter
$data_nascimento_iso = toISODate($data_nascimento);

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

/* ---------------- validações ---------------- */

if (!$nome) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Nome é obrigatório.']); exit; }
if (!$cpf)  { http_response_code(400); echo json_encode(['success'=>false,'error'=>'CPF é obrigatório.']); exit; }
if (!$curso || !$turno || !$serie) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Curso/Turno/Série são obrigatórios.']);
  exit;
}

/* ---------------- persistência ---------------- */

try {
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare("
      INSERT INTO alunos (
        nome, cpf, ra, data_nascimento, contato_aluno,
        cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
        curso, turno, serie
      ) VALUES (
        :nome, :cpf, :ra, :data_nascimento, :contato_aluno,
        :cep, :endereco_rua, :endereco_numero, :endereco_bairro, :endereco_cidade, :endereco_estado,
        :curso, :turno, :serie
      )
    ");
    $ok = $st->execute([
      ':nome'=>$nome, ':cpf'=>$cpf, ':ra'=>$ra,
      ':data_nascimento'=>$data_nascimento_iso, // <- ISO ou NULL
      ':contato_aluno'=>$contato_aluno,
      ':cep'=>$cep, ':endereco_rua'=>$end_rua, ':endereco_numero'=>$end_numero,
      ':endereco_bairro'=>$end_bairro, ':endereco_cidade'=>$end_cidade, ':endereco_estado'=>$end_estado,
      ':curso'=>$curso, ':turno'=>$turno, ':serie'=>$serie
    ]);
    if (!$ok) throw new RuntimeException('Falha ao inserir aluno.');
    $id = (int)$pdo->lastInsertId();
    echo json_encode(['success'=>true,'id'=>$id]);
    exit;
  }

  // --- MySQLi fallback ---
  $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : null);
  if ($mysqli) {
    $sql = "
      INSERT INTO alunos (
        nome, cpf, ra, data_nascimento, contato_aluno,
        cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
        curso, turno, serie
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ";
    $st = $mysqli->prepare($sql);
    $st->bind_param(
      "ssssssssssssss",
      $nome, $cpf, $ra, $data_nascimento_iso,
      $contato_aluno, $cep, $end_rua, $end_numero, $end_bairro, $end_cidade, $end_estado,
      $curso, $turno, $serie
    );
    if (!$st->execute()) throw new RuntimeException('Falha ao inserir aluno (MySQLi).');
    echo json_encode(['success'=>true,'id'=>$mysqli->insert_id]);
    exit;
  }

  throw new RuntimeException('Nenhuma conexão de banco ativa.');
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
  exit;
}
?>