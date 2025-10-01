<?php
// php/get_students.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/db.php';
$pdo = function_exists('pdo') ? pdo() : null;

$id = $_GET['id'] ?? null;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        if ($id) {
            $st = $pdo->prepare("
                SELECT
                  id, nome, cpf, ra, data_nascimento, contato_aluno,
                  cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
                  curso, turno, serie,
                  status, empresa_id, inicio_trabalho, fim_trabalho,
                  recebeu_bolsa, renovou_contrato, tipo_contrato, observacao, relatorio
                FROM alunos
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([':id' => $id]);
            $aluno = $st->fetch(PDO::FETCH_ASSOC);
            if (!$aluno) {
                echo json_encode(['success' => false, 'error' => 'Aluno não encontrado.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $aluno]);
            exit;
        } else {
            $rows = $pdo->query("
                SELECT
                  id, nome, cpf, ra, data_nascimento, contato_aluno,
                  cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
                  curso, turno, serie,
                  status, empresa_id, inicio_trabalho, fim_trabalho,
                  recebeu_bolsa, renovou_contrato, tipo_contrato, observacao, relatorio
                FROM alunos
                ORDER BY nome ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }
    }

    // --- MySQLi fallback ---
    $mysqli = (isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : ((isset($conn) && $conn instanceof mysqli) ? $conn : null);
    if ($mysqli) {
        if ($id) {
            $st = $mysqli->prepare("
                SELECT
                  id, nome, cpf, ra, data_nascimento, contato_aluno,
                  cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
                  curso, turno, serie,
                  status, empresa_id, inicio_trabalho, fim_trabalho,
                  recebeu_bolsa, renovou_contrato, tipo_contrato, observacao, relatorio
                FROM alunos
                WHERE id = ?
                LIMIT 1
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $res = $st->get_result();
            $aluno = $res->fetch_assoc();
            if (!$aluno) {
                echo json_encode(['success' => false, 'error' => 'Aluno não encontrado.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $aluno]);
            exit;
        } else {
            $res = $mysqli->query("
                SELECT
                  id, nome, cpf, ra, data_nascimento, contato_aluno,
                  cep, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado,
                  curso, turno, serie,
                  status, empresa_id, inicio_trabalho, fim_trabalho,
                  recebeu_bolsa, renovou_contrato, tipo_contrato, observacao, relatorio
                FROM alunos
                ORDER BY nome ASC
            ");
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }
    }

    throw new Exception("Nenhuma conexão de banco ativa.");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>