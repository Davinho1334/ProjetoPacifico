<?php
// php/delete_student.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autenticado. Faça login como administrador.']);
  exit;
}

// ===== carrega conexões (pdo e/ou mysqli) =====
require_once __DIR__ . '/db.php'; // deve definir $pdo (PDO) e/ou $conn / $mysqli (MySQLi)
$mysqli = $mysqli ?? ($conn ?? null);
$isPDO = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = $mysqli instanceof mysqli;

function json_fail(string $msg, int $code = 400){
  http_response_code($code);
  echo json_encode(['success'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = null;
foreach (['id','aluno_id','student_id'] as $k) {
  if (isset($_POST[$k]) && $_POST[$k] !== '') { $id = (int)$_POST[$k]; break; }
}
if (!$id) json_fail('Parâmetro id ausente ou inválido.');

// --- helpers de banco ---
function table_exists_pdo(PDO $pdo, string $table): bool {
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function table_exists_mysqli(mysqli $db, string $table): bool {
  try {
    $tbl = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$tbl}'");
    return $res && $res->num_rows > 0;
  } catch (Throwable $e) { return false; }
}

try {
  if ($isPDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Apagar relacionamentos se as tabelas existirem (opcional)
    $maybeTables = ['agendas','frequencias','documentos_aluno','relatorios_aluno'];
    foreach ($maybeTables as $t) {
      if (table_exists_pdo($pdo, $t)) {
        $stmt = $pdo->prepare("DELETE FROM `{$t}` WHERE aluno_id = ?");
        $stmt->execute([$id]);
      }
    }

    // Apagar o aluno
    $stmt = $pdo->prepare("DELETE FROM `alunos` WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() < 1) {
      // nada apagado: id não existe
      $pdo->rollBack();
      json_fail('Aluno não encontrado (id inexistente).', 404);
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Aluno excluído.']);
    exit;

  } elseif ($isMySQLi) {
    $mysqli->begin_transaction();

    $maybeTables = ['agendas','frequencias','documentos_aluno','relatorios_aluno'];
    foreach ($maybeTables as $t) {
      if (table_exists_mysqli($mysqli, $t)) {
        $stmt = $mysqli->prepare("DELETE FROM `{$t}` WHERE aluno_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
      }
    }

    $stmt = $mysqli->prepare("DELETE FROM `alunos` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
      $mysqli->rollback();
      json_fail('Aluno não encontrado (id inexistente).', 404);
    }

    $mysqli->commit();
    echo json_encode(['success'=>true,'message'=>'Aluno excluído.']);
    exit;

  } else {
    json_fail('Conexão de banco não disponível.');
  }

} catch (Throwable $e) {
  // tenta desfazer transação se aberta
  if ($isPDO && $pdo->inTransaction()) { $pdo->rollBack(); }
  if ($isMySQLi && $mysqli->errno === 0) { /* best effort; mysqli não expõe se está em tx */ }

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Erro no servidor: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
?>