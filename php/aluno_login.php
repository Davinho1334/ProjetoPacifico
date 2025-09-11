<?php
// php/aluno_login.php
declare(strict_types=1);

// Evita saída antes do JSON
while (ob_get_level() > 0) { ob_end_clean(); }

session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
  require_once __DIR__ . '/_db_bridge.php'; // expõe $DB
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Erro de conexão: '.$e->getMessage()]);
  exit;
}

// Helpers (sem intl/mb obrigatórios)
function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s);
}
function tolower_utf8(string $s): string {
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}
function normalize_name(string $s): string {
  $s = trim($s);
  $s = tolower_utf8($s);
  return preg_replace('/\s+/', ' ', $s);
}

try {
  $nome = $_POST['nome'] ?? '';
  $cpf  = $_POST['cpf']  ?? '';
  $nomeNorm = normalize_name($nome);
  $cpfNorm  = only_digits($cpf);

  if ($nomeNorm === '' || $cpfNorm === '') {
    echo json_encode(['success'=>false,'message'=>'Informe nome e CPF.']); exit;
  }

  $row = null;

  if ($DB['type'] === 'pdo') {
    /** @var PDO $pdo */
    $pdo = $DB['pdo'];
    $st = $pdo->prepare("
      SELECT nome, cpf
      FROM alunos
      WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
      LIMIT 1
    ");
    $st->execute([':cpf'=>$cpfNorm]);
    $row = $st->fetch();

  } elseif ($DB['type'] === 'mysqli') {
    /** @var mysqli $conn */
    $conn = $DB['mysqli'];
    $st = $conn->prepare("
      SELECT nome, cpf
      FROM alunos
      WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = ?
      LIMIT 1
    ");
    if (!$st) { throw new Exception('Falha prepare (mysqli): '.$conn->error); }
    $st->bind_param('s', $cpfNorm);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
  } else {
    throw new Exception('Tipo de conexão desconhecido.');
  }

  if (!$row) {
    echo json_encode(['success'=>false,'message'=>'CPF não encontrado.']); exit;
  }

  if (normalize_name($row['nome'] ?? '') !== $nomeNorm) {
    echo json_encode(['success'=>false,'message'=>'Nome não confere com o CPF.']); exit;
  }

  $_SESSION['cpf'] = $row['cpf'];
  ini_set('session.cookie_httponly','1');
  echo json_encode(['success'=>true]);

} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode(['success'=>false,'message'=>'Erro no servidor: '.$e->getMessage()]);
}
?>