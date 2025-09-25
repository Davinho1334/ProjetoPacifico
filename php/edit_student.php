<?php
// php/edit_student.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true) ?: [];

// helpers
function v(array $src, string $k): ?string { return isset($src[$k]) ? trim((string)$src[$k]) : null; }
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

$id               = (int)($in['id'] ?? 0);
$nome             = v($in,'nome');
$cpf              = v($in,'cpf');
$ra               = v($in,'ra');
$data_nascimento  = v($in,'data_nascimento');
$contato_aluno    = v($in,'contato_aluno');

$cep              = mask_cep(v($in,'cep'));
$end_rua          = v($in,'endereco_rua');
$end_numero       = v($in,'endereco_numero');
$end_bairro       = v($in,'endereco_bairro');
$end_cidade       = v($in,'endereco_cidade');
$end_estado       = norm_uf(v($in,'endereco_estado'));

$curso            = v($in,'curso');
$turno            = v($in,'turno');
$serie            = v($in,'serie');

$inicio_trabalho  = v($in,'inicio_trabalho');
$fim_trabalho     = v($in,'fim_trabalho');
$status           = v($in,'status');
$empresa_id       = v($in,'empresa_id');
$recebeu_bolsa    = v($in,'recebeu_bolsa');
$renovou_contrato = v($in,'renovou_contrato');
$tipo_contrato    = v($in,'tipo_contrato');
$relatorio        = v($in,'relatorio');
$observacao       = v($in,'observacao');

if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $st = $pdo->prepare("
      UPDATE alunos SET
        nome = COALESCE(:nome, nome),
        cpf  = COALESCE(:cpf,  cpf),
        ra = :ra,
        data_nascimento = :data_nascimento,
        contato_aluno = :contato_aluno,
        cep = :cep, endereco_rua = :endereco_rua, endereco_numero = :endereco_numero,
        endereco_bairro = :endereco_bairro, endereco_cidade = :endereco_cidade, endereco_estado = :endereco_estado,
        curso = :curso, turno = :turno, serie = :serie,
        inicio_trabalho = :inicio_trabalho, fim_trabalho = :fim_trabalho,
        status = COALESCE(:status, status),
        empresa_id = :empresa_id,
        recebeu_bolsa = :recebeu_bolsa,
        renovou_contrato = :renovou_contrato,
        tipo_contrato = :tipo_contrato,
        relatorio = :relatorio,
        observacao = :observacao
      WHERE id = :id
      LIMIT 1
    ");
    $ok = $st->execute([
      ':nome'=>$nome, ':cpf'=>$cpf, ':ra'=>$ra, ':data_nascimento'=>$data_nascimento, ':contato_aluno'=>$contato_aluno,
      ':cep'=>$cep, ':endereco_rua'=>$end_rua, ':endereco_numero'=>$end_numero, ':endereco_bairro'=>$end_bairro, ':endereco_cidade'=>$end_cidade, ':endereco_estado'=>$end_estado,
      ':curso'=>$curso, ':turno'=>$turno, ':serie'=>$serie,
      ':inicio_trabalho'=>$inicio_trabalho, ':fim_trabalho'=>$fim_trabalho, ':status'=>$status,
      ':empresa_id'=>$empresa_id, ':recebeu_bolsa'=>$recebeu_bolsa, ':renovou_contrato'=>$renovou_contrato,
      ':tipo_contrato'=>$tipo_contrato, ':relatorio'=>$relatorio, ':observacao'=>$observacao,
      ':id'=>$id
    ]);
    echo json_encode(['success'=>$ok?true:false]); exit;
  }

  $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : null);
  if ($mysqli) {
    $sql = "
      UPDATE alunos SET
        nome = IFNULL(?, nome),
        cpf  = IFNULL(?, cpf),
        ra = ?, data_nascimento = ?, contato_aluno = ?,
        cep = ?, endereco_rua = ?, endereco_numero = ?, endereco_bairro = ?, endereco_cidade = ?, endereco_estado = ?,
        curso = ?, turno = ?, serie = ?,
        inicio_trabalho = ?, fim_trabalho = ?,
        status = IFNULL(?, status),
        empresa_id = ?,
        recebeu_bolsa = ?,
        renovou_contrato = ?,
        tipo_contrato = ?,
        relatorio = ?,
        observacao = ?
      WHERE id = ?
      LIMIT 1
    ";
    $st = $mysqli->prepare($sql);
    $st->bind_param(
      "ssssssssssssssssisssssi",
      $nome, $cpf,
      $ra, $data_nascimento, $contato_aluno,
      $cep, $end_rua, $end_numero, $end_bairro, $end_cidade, $end_estado,
      $curso, $turno, $serie,
      $inicio_trabalho, $fim_trabalho,
      $status,
      $empresa_id,
      $recebeu_bolsa,
      $renovou_contrato,
      $tipo_contrato,
      $relatorio,
      $observacao,
      $id
    );
    $ok = $st->execute();
    echo json_encode(['success'=>$ok?true:false]); exit;
  }

  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Nenhuma conexão de banco ativa (PDO/MySQLi).']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>