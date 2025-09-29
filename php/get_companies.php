<?php
// php/get_companies.php
// Detecta e suporta tanto PDO quanto mysqli (escolha automática).
// Só seleciona/expõe os campos usados pela UI e fornece aliases 'tel'/'telefone'.

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db.php'; // seu db.php existente (deve expor $pdo ou $conn)

    // Campos que queremos expor (ajuste aqui se precisar de mais)
    $fields = [
        'id',
        'razao_social',
        'cnpj',
        'cep',
        'endereco_rua',
        'endereco_numero',
        'endereco_bairro',
        'endereco_cidade',
        'endereco_estado',
        'telefone',
        'tipo_contrato'
    ];
    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM empresas ORDER BY razao_social ASC';

    $rows = [];

    if (isset($pdo) && $pdo instanceof PDO) {
        // PDO
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            throw new Exception('Falha na query PDO.');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && ($conn instanceof mysqli || (is_object($conn) && method_exists($conn, 'query')))) {
        // mysqli
        $res = $conn->query($sql);
        if ($res === false) {
            throw new Exception('Falha na query MySQLi: ' . $conn->error);
        }
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    } else {
        throw new Exception('Conexão ao banco não encontrada. Verifique seu db.php (deve expor $pdo ou $conn).');
    }

    // Compat: garanta 'tel' e 'telefone' presentes (mesma informação em ambos)
    foreach ($rows as &$r) {
        if (!array_key_exists('telefone', $r) && array_key_exists('tel', $r)) {
            $r['telefone'] = $r['tel'];
        }
        if (!array_key_exists('tel', $r) && array_key_exists('telefone', $r)) {
            $r['tel'] = $r['telefone'];
        }
        // Garante que chaves existam (mesmo vazias) para facilitar o front-end
        foreach ($fields as $f) {
            if (!array_key_exists($f, $r)) $r[$f] = '';
        }
        if (!array_key_exists('tel', $r)) $r['tel'] = '';
        if (!array_key_exists('telefone', $r)) $r['telefone'] = '';
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    // Não vaza detalhes sensíveis — mensagem curta para debug
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao obter empresas: ' . $e->getMessage()]);
    exit;
}
?>