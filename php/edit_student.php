<?php
// php/edit_student.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // deve definir $pdo (PDO)

try {
    // Lê JSON do corpo
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success'=>false,'message'=>'Payload inválido']);
        exit;
    }

    // Coleta/normaliza campos
    $id              = isset($data['id']) ? (int)$data['id'] : 0;
    $ra              = trim($data['ra'] ?? '');
    $curso           = trim($data['curso'] ?? '');
    $turno           = trim($data['turno'] ?? '');
    $serie           = trim($data['serie'] ?? '');
    $status          = trim($data['status'] ?? '');
    $cargaSemanal    = isset($data['cargaSemanal']) ? (int)$data['cargaSemanal'] : 0;

    $empresa_id      = $data['empresa_id'] ?? null;
    if ($empresa_id === '' || $empresa_id === null) $empresa_id = null; else $empresa_id = (int)$empresa_id;

    $inicio_trabalho = $data['inicio_trabalho'] ?? null;
    $fim_trabalho    = $data['fim_trabalho'] ?? null;
    if ($inicio_trabalho === '') $inicio_trabalho = null;
    if ($fim_trabalho === '')    $fim_trabalho    = null;

    $renovou_contrato = isset($data['renovou_contrato']) ? (int)$data['renovou_contrato'] : 0;

    $contato_aluno   = trim($data['contato_aluno'] ?? '');
    $idade           = $data['idade'] ?? null;
    if ($idade === '' || $idade === null) $idade = null; else $idade = (int)$idade;

    $relatorio       = trim($data['relatorio'] ?? '');
    $observacao      = trim($data['observacao'] ?? '');
    $tipo_contrato   = trim($data['tipo_contrato'] ?? '');

    // recebeu_bolsa: 1, 0 ou null
    $recebeu_bolsa   = $data['recebeu_bolsa'] ?? null;
    if ($recebeu_bolsa === '' || $recebeu_bolsa === null) $recebeu_bolsa = null;
    else $recebeu_bolsa = (int)$recebeu_bolsa;

    if ($id <= 0) {
        echo json_encode(['success'=>false,'message'=>'ID inválido']);
        exit;
    }

    // Monta UPDATE
    // IMPORTANTE: RA usa NULLIF(:ra,'') para gravar NULL quando vier vazio,
    // permitindo múltiplos alunos sem RA sem violar UNIQUE.
    $sql = "
        UPDATE alunos
           SET ra              = NULLIF(:ra,''),
               curso           = :curso,
               turno           = :turno,
               serie           = :serie,
               status          = :status,
               cargaSemanal    = :cargaSemanal,
               empresa_id      = :empresa_id,
               inicio_trabalho = :inicio_trabalho,
               fim_trabalho    = :fim_trabalho,
               renovou_contrato= :renovou_contrato,
               contato_aluno   = :contato_aluno,
               idade           = :idade,
               relatorio       = :relatorio,
               observacao      = :observacao,
               tipo_contrato   = :tipo_contrato,
               recebeu_bolsa   = :recebeu_bolsa
         WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
        ':ra'               => $ra,                 // vazio vira NULL pelo NULLIF
        ':curso'            => $curso ?: null,
        ':turno'            => $turno ?: null,
        ':serie'            => $serie ?: null,
        ':status'           => $status ?: null,
        ':cargaSemanal'     => $cargaSemanal,
        ':empresa_id'       => $empresa_id,
        ':inicio_trabalho'  => $inicio_trabalho,
        ':fim_trabalho'     => $fim_trabalho,
        ':renovou_contrato' => $renovou_contrato,
        ':contato_aluno'    => $contato_aluno ?: null,
        ':idade'            => $idade,
        ':relatorio'        => $relatorio ?: null,
        ':observacao'       => $observacao ?: null,
        ':tipo_contrato'    => $tipo_contrato ?: null,
        ':recebeu_bolsa'    => $recebeu_bolsa,
        ':id'               => $id
    ]);

    if ($ok) {
        echo json_encode(['success'=>true,'message'=>'Atualizado com sucesso']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Falha ao atualizar']);
    }
} catch (PDOException $e) {
    // Trata RA duplicado (1062)
    if ($e->getCode() === '23000') {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate entry') !== false && stripos($msg, "'ra'") !== false) {
            echo json_encode(['success'=>false,'message'=>'RA já cadastrado para outro aluno.']);
            exit;
        }
    }
    echo json_encode(['success'=>false,'message'=>'Erro no servidor','error'=>$e->getMessage()]);
}
?>