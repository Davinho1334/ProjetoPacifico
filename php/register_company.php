<?php
// php/register_company.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

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

// entrada
$in = $_POST;

$razao_social   = v($in,'razao_social') ?: v($in,'nome_empresa');
$cnpj           = v($in,'cnpj');
$cep            = mask_cep(v($in,'cep'));
$endereco_rua   = v($in,'endereco_rua');
$endereco_num   = v($in,'endereco_numero');
$endereco_bairro= v($in,'endereco_bairro');
$endereco_cidade= v($in,'endereco_cidade');
$endereco_estado= norm_uf(v($in,'endereco_estado'));
$telefone       = v($in,'telefone');
$tipo_contrato  = v($in,'tipo_contrato');

// validações
if (!$razao_social) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Razão social obrigatória']); exit; }
if (!$cnpj)         { http_response_code(400); echo json_encode(['success'=>false,'error'=>'CNPJ obrigatório']); exit; }

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $sql = "INSERT INTO empresas
      (razao_social, cnpj, cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado, telefone, tipo_contrato)
      VALUES (:razao_social, :cnpj, :cep, :rua, :num, :bairro, :cidade, :estado, :telefone, :tipo_contrato)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':razao_social'=>$razao_social,
      ':cnpj'=>$cnpj,
      ':cep'=>$cep,
      ':rua'=>$endereco_rua,
      ':num'=>$endereco_num,
      ':bairro'=>$endereco_bairro,
      ':cidade'=>$endereco_cidade,
      ':estado'=>$endereco_estado,
      ':telefone'=>$telefone,
      ':tipo_contrato'=>$tipo_contrato
    ]);
    if(!$ok) throw new Exception('Falha ao inserir (PDO)');
    echo json_encode(['success'=>true,'message'=>'Empresa cadastrada com sucesso!']); exit;
  }

  $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : null);
  if ($mysqli) {
    $sql = "INSERT INTO empresas
      (razao_social, cnpj, cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado, telefone, tipo_contrato)
      VALUES (?,?,?,?,?,?,?,?,?,?)";
    $st = $mysqli->prepare($sql);
    if(!$st) throw new Exception('Erro no prepare (MySQLi)');
    $st->bind_param("ssssssssss",
      $razao_social, $cnpj, $cep, $endereco_rua, $endereco_num, $endereco_bairro, $endereco_cidade, $endereco_estado, $telefone, $tipo_contrato
    );
    $ok = $st->execute();
    if(!$ok) throw new Exception('Erro no execute (MySQLi)');
    echo json_encode(['success'=>true,'message'=>'Empresa cadastrada com sucesso!']); exit;
  }

  throw new Exception('Nenhuma conexão de banco ativa.');
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
  exit;
}
?>