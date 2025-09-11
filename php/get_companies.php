<?php
// php/get_companies.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // precisa expor $pdo (PDO conectado)

try {
    $stmt = $pdo->query("
        SELECT id, razao_social, cnpj, tipo_contrato
        FROM empresas
        ORDER BY razao_social ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // O dashboard aceita 'nome' OU 'razao_social'
    $data = array_map(function($r){
        return [
            'id'            => (int)$r['id'],
            'razao_social'  => $r['razao_social'],
            'nome'          => $r['razao_social'], // alias para compatibilidade
            'cnpj'          => $r['cnpj'],
            'tipo_contrato' => $r['tipo_contrato'],
        ];
    }, $rows);

    echo json_encode(['success'=>true,'data'=>$data]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Erro ao listar empresas','error'=>$e->getMessage()]);
}
?>