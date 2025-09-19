<?php
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  echo json_encode(['success'=>false, 'message'=>'ID inválido']); exit;
}

try {
  // ajuste para o seu jeito de conectar (PDO recomendado)
  require_once __DIR__ . '/db.php'; // deve criar $pdo (PDO)

  $sql = "SELECT COALESCE(contato_aluno, contato) AS contato FROM alunos WHERE id = :id LIMIT 1";
  $st  = $pdo->prepare($sql);
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if ($row && !empty($row['contato'])) {
    echo json_encode(['success'=>true, 'contato'=>$row['contato']]);
  } else {
    echo json_encode(['success'=>true, 'contato'=>'']);
  }
} catch (Throwable $e) {
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>