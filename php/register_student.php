<?php
// php/register_student.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Captura qualquer saída do db.php para expor em caso de erro (depuração)
$__include_output = '';
ob_start();
try {
  require_once __DIR__ . '/db.php';
} catch (Throwable $e) {
  $__include_output = ob_get_clean();
  echo json_encode([
    'success' => false,
    'message' => 'Falha ao incluir db.php',
    'error'   => $e->getMessage(),
    'include_output' => $__include_output
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
$__include_output = ob_get_clean();

// Detecta conectores disponíveis
$pdo    = (isset($pdo)    && $pdo    instanceof PDO)    ? $pdo    : null;
$mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : null;
// Compatibilidade com projetos que usam $conn
if (!$mysqli && isset($conn) && $conn instanceof mysqli) $mysqli = $conn;

function jexit(array $payload): void {
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// -------------------- Entrada --------------------
$nome  = trim($_POST['nome']  ?? '');
$cpf   = trim($_POST['cpf']   ?? '');
$ra    = trim($_POST['ra']    ?? '');
$curso = trim($_POST['curso'] ?? '');
$turno = trim($_POST['turno'] ?? '');
$serie = trim($_POST['serie'] ?? '');

// Data de nascimento (vem como dd/mm/aaaa do formulário)
$dataNascBr = trim($_POST['data_nascimento'] ?? '');
$data_nascimento = null;
if ($dataNascBr !== '') {
  if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dataNascBr, $m)) {
    $dd = (int)$m[1]; $mm = (int)$m[2]; $yy = (int)$m[3];
    if (checkdate($mm, $dd, $yy)) {
      $data_nascimento = sprintf('%04d-%02d-%02d', $yy, $mm, $dd); // aaaa-mm-dd
    } else {
      jexit([
        'success' => false,
        'message' => 'Data de nascimento inválida.',
        'include_output' => $__include_output
      ]);
    }
  } else {
    jexit([
      'success' => false,
      'message' => 'Formato de data inválido. Use dd/mm/aaaa.',
      'include_output' => $__include_output
    ]);
  }
}

// Validações mínimas
if ($nome === '' || $cpf === '' || $curso === '' || $turno === '' || $serie === '') {
  jexit([
    'success' => false,
    'message' => 'Preencha todos os campos obrigatórios.',
    'include_output' => $__include_output
  ]);
}

// -------------------- Insert --------------------
$sql = "INSERT INTO alunos
          (nome, cpf, ra, data_nascimento, curso, turno, serie)
        VALUES
          (:nome, :cpf, :ra, :data_nascimento, :curso, :turno, :serie)";

try {
  if ($pdo) {
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
      ':nome'             => $nome,
      ':cpf'              => $cpf,
      ':ra'               => ($ra !== '' ? $ra : null),
      ':data_nascimento'  => $data_nascimento, // pode ser null
      ':curso'            => $curso,
      ':turno'            => $turno,
      ':serie'            => $serie,
    ]);

    if (!$ok) {
      jexit([
        'success' => false,
        'message' => 'Erro ao salvar aluno (PDO).',
        'error'   => $stmt->errorInfo()[2] ?? null,
        'sql'     => $sql,
        'include_output' => $__include_output
      ]);
    }

  } elseif ($mysqli) {
    $sql = "INSERT INTO alunos
              (nome, cpf, ra, data_nascimento, curso, turno, serie)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      jexit([
        'success' => false,
        'message' => 'Falha ao preparar statement (MySQLi).',
        'error'   => $mysqli->error,
        'sql'     => $sql,
        'include_output' => $__include_output
      ]);
    }

    // Observação: passar NULL em bind_param é aceito quando a coluna permite NULL
    $raParam  = ($ra !== '' ? $ra : null);
    $dataParam = $data_nascimento; // pode ser null

    $stmt->bind_param(
      "sssssss",
      $nome,
      $cpf,
      $raParam,
      $dataParam,
      $curso,
      $turno,
      $serie
    );

    $ok = $stmt->execute();
    if (!$ok) {
      jexit([
        'success' => false,
        'message' => 'Erro ao salvar aluno (MySQLi).',
        'error'   => $stmt->error,
        'sql'     => $sql,
        'include_output' => $__include_output
      ]);
    }
  } else {
    jexit([
      'success' => false,
      'message' => 'Nenhum conector de banco disponível (PDO/MySQLi).',
      'include_output' => $__include_output
    ]);
  }

  jexit([
    'success' => true,
    'message' => 'Aluno cadastrado com sucesso.'
  ]);

} catch (Throwable $e) {
  jexit([
    'success' => false,
    'message' => 'Exceção ao salvar aluno.',
    'error'   => $e->getMessage(),
    'sql'     => $sql,
    'include_output' => $__include_output
  ]);
}
?>