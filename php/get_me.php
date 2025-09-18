<?php
// php/get_me.php
declare(strict_types=1);

// Garanta que o cookie de sessão seja válido para todo o site:
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Lax', // se front/back estiverem em domínios diferentes, use 'None' + HTTPS
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Verifica sessão criada no login
if (empty($_SESSION['aluno_id']) && empty($_SESSION['aluno_cpf'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Não autenticado']);
  exit;
}

require_once __DIR__ . '/db.php';

$pdo    = (isset($pdo)    && $pdo    instanceof PDO)    ? $pdo    : null;
$mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : null;
if (!$mysqli && isset($conn) && $conn instanceof mysqli) $mysqli = $conn;

$aluno = null;

try {
  if ($pdo) {
    $sql = "SELECT 
              id, nome, cpf, ra,
              curso, turno, serie, status,
              empresa, empresa_id,
              inicio_trabalho, fim_trabalho, renovou_contrato,
              cargaSemanal,
              data_nascimento,
              tipo_contrato,
              recebeu_bolsa
            FROM alunos
            WHERE " . (!empty($_SESSION['aluno_id']) ? "id = :id" : "cpf = :cpf") . "
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    if (!empty($_SESSION['aluno_id'])) {
      $stmt->bindValue(':id', (int)$_SESSION['aluno_id'], PDO::PARAM_INT);
    } else {
      $stmt->bindValue(':cpf', $_SESSION['aluno_cpf'], PDO::PARAM_STR);
    }
    $stmt->execute();
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

  } elseif ($mysqli) {
    $sql = "SELECT 
              id, nome, cpf, ra,
              curso, turno, serie, status,
              empresa, empresa_id,
              inicio_trabalho, fim_trabalho, renovou_contrato,
              cargaSemanal,
              data_nascimento,
              tipo_contrato,
              recebeu_bolsa
            FROM alunos
            WHERE " . (!empty($_SESSION['aluno_id']) ? "id = ?" : "cpf = ?") . "
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      echo json_encode(['success'=>false,'message'=>'Falha ao preparar consulta','error'=>$mysqli->error]);
      exit;
    }
    if (!empty($_SESSION['aluno_id'])) {
      $stmt->bind_param("i", $_SESSION['aluno_id']);
    } else {
      $stmt->bind_param("s", $_SESSION['aluno_cpf']);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $aluno = $res->fetch_assoc();
  } else {
    echo json_encode(['success'=>false,'message'=>'Nenhum conector de banco disponível (PDO/MySQLi).']);
    exit;
  }
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>'Erro ao buscar aluno','error'=>$e->getMessage()]);
  exit;
}

if (!$aluno) {
  echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
  exit;
}

// Normalização
if (array_key_exists('recebeu_bolsa', $aluno)) {
  $aluno['recebeu_bolsa'] = is_null($aluno['recebeu_bolsa']) ? null : (int)$aluno['recebeu_bolsa'];
}
if (array_key_exists('renovou_contrato', $aluno)) {
  $aluno['renovou_contrato'] = (int)$aluno['renovou_contrato'];
}
if (!isset($aluno['tipo_contrato']) || $aluno['tipo_contrato'] === null) {
  $aluno['tipo_contrato'] = '';
}

echo json_encode(['success' => true, 'data' => $aluno], JSON_UNESCAPED_UNICODE);
?>