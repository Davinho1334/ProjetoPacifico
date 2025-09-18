<?php
// php/aluno_login.php
declare(strict_types=1);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$pdo    = (isset($pdo)    && $pdo    instanceof PDO)    ? $pdo    : null;
$mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : null;
if (!$mysqli && isset($conn) && $conn instanceof mysqli) $mysqli = $conn;

$cpf  = trim($_POST['cpf'] ?? '');
$nome = trim($_POST['nome'] ?? '');

if ($cpf === '') {
  echo json_encode(['success'=>false,'message'=>'Informe o CPF.']);
  exit;
}

try {
  $row = null;
  if ($pdo) {
    $sql  = "SELECT id, cpf, nome FROM alunos WHERE cpf = :cpf LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
  } elseif ($mysqli) {
    $sql  = "SELECT id, cpf, nome FROM alunos WHERE cpf = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      echo json_encode(['success'=>false,'message'=>'Erro ao preparar consulta','error'=>$mysqli->error]);
      exit;
    }
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
  }
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>'Erro ao consultar aluno','error'=>$e->getMessage()]);
  exit;
}

if (!$row) {
  echo json_encode(['success'=>false,'message'=>'Aluno não encontrado para o CPF informado.']);
  exit;
}

// Se quiser validar o nome também, descomente:
// if ($nome !== '' && strcasecmp(trim($row['nome']), $nome) !== 0) {
//   echo json_encode(['success'=>false,'message'=>'Nome não confere com o CPF.']);
//   exit;
// }

// Cria sessão do jeito que get_me.php espera
$_SESSION['aluno_id']  = (int)$row['id'];
$_SESSION['aluno_cpf'] = $row['cpf'];
session_regenerate_id(true);

echo json_encode(['success'=>true,'message'=>'Login efetuado.']);
?>