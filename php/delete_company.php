<?php
// php/delete_company.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php'; // precisa expor $pdo (PDO)

function jexit($ok, $msg = '', $extra = []) {
  echo json_encode(array_merge(['success'=>$ok, 'message'=>$msg], $extra));
  exit;
}

try {
  // aceita form-data ou JSON
  $data = [];
  if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
  } else {
    $data = $_POST;
  }
  $id = $data['id'] ?? null;
  if (!$id) jexit(false, 'ID obrigatório.');

  // (opcional) verificação de sessão:
  // session_start();
  // if (empty($_SESSION['admin_id'])) jexit(false, 'Acesso negado.');

  $stmt = $pdo->prepare('DELETE FROM empresas WHERE id = :id LIMIT 1');
  $stmt->execute([':id'=>$id]);

  jexit(true, 'Excluída com sucesso.');
} catch (Throwable $e) {
  jexit(false, 'Erro: '.$e->getMessage());
}
?>