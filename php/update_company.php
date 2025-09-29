<?php
// php/update_company.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // deve expor $pdo (PDO) OU $conn (mysqli)

function jexit($ok, $msg = '', $extra = []) {
  echo json_encode(array_merge(['success'=>$ok, 'message'=>$msg]), JSON_UNESCAPED_UNICODE);
  if (!empty($extra)) echo json_encode($extra, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Lê JSON ou form-data
  $data = [];
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
  } else {
    $data = $_POST;
  }

  // id obrigatório
  $id = isset($data['id']) ? (int)$data['id'] : 0;
  if ($id <= 0) jexit(false, 'ID obrigatório.');

  // Normaliza telefone: aceita 'tel' mas salva em 'telefone'
  if (isset($data['tel']) && !isset($data['telefone'])) {
    $data['telefone'] = $data['tel'];
  }

  // Campos permitidos
  $fields_allowed = [
    'razao_social','cnpj','cep',
    'endereco_rua','endereco_numero','endereco_bairro',
    'endereco_cidade','endereco_estado',
    'telefone',
    'tipo_contrato'
  ];

  // Monta lista de SET com somente o que foi enviado
  $sets = [];
  $values = [];
  foreach ($fields_allowed as $f) {
    if (array_key_exists($f, $data)) {
      $sets[] = "`$f` = ?";
      $values[] = is_null($data[$f]) ? '' : trim((string)$data[$f]);
    }
  }

  if (empty($sets)) jexit(false, 'Nada para atualizar.');

  // ===== Execução (PDO ou mysqli) =====
  if (isset($pdo) && $pdo instanceof PDO) {
    // PDO
    $sql = 'UPDATE empresas SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute(array_merge($values, [$id]));
    if (!$ok) jexit(false, 'Falha ao atualizar (PDO).');
    jexit(true, 'Atualizado com sucesso.');

  } elseif (isset($conn) && ($conn instanceof mysqli || (is_object($conn) && method_exists($conn, 'prepare')))) {
    // mysqli
    $sql = 'UPDATE empresas SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
      jexit(false, 'Falha ao preparar (MySQLi): ' . (isset($conn->error) ? $conn->error : ''));
    }

    // Tipos: todos os campos são strings ('s'), id é inteiro ('i')
    $types = str_repeat('s', count($values)) . 'i';
    $values[] = $id;

    // bind_param exige variáveis por referência
    $bind_params = [];
    $bind_params[] = $types;
    foreach ($values as $key => $val) {
      $bind_params[] = &$values[$key];
    }

    // Chama dinamicamente bind_param
    $ok = call_user_func_array([$stmt, 'bind_param'], $bind_params);
    if (!$ok) {
      jexit(false, 'Falha no bind (MySQLi).');
    }

    $execOk = $stmt->execute();
    if (!$execOk) {
      jexit(false, 'Falha ao atualizar (MySQLi): ' . $stmt->error);
    }
    $stmt->close();
    jexit(true, 'Atualizado com sucesso.');

  } else {
    jexit(false, 'Conexão com o banco não encontrada (esperado $pdo ou $conn em db.php).');
  }

} catch (Throwable $e) {
  jexit(false, 'Erro: ' . $e->getMessage());
}
?>