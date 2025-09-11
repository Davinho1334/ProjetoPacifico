<?php
// php/register_company.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // precisa expor $pdo (PDO conectado)

try {
    // Cria a tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empresas (
          id INT AUTO_INCREMENT PRIMARY KEY,
          razao_social VARCHAR(200) NOT NULL,
          cnpj VARCHAR(20) NOT NULL,
          endereco VARCHAR(255) NULL,
          cep VARCHAR(20) NULL,
          telefone VARCHAR(30) NULL,
          tipo_contrato ENUM('Menor Aprendiz','Jovem Aprendiz','Estágio') NOT NULL,
          criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_cnpj (cnpj)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Campos (empresa.html envia 'razao_social', mas aceitamos 'nome' também)
    $razao_social  = trim($_POST['razao_social'] ?? $_POST['nome'] ?? '');
    $cnpj          = trim($_POST['cnpj'] ?? '');
    $endereco      = trim($_POST['endereco'] ?? '');
    $cep           = trim($_POST['cep'] ?? '');
    $telefone      = trim($_POST['telefone'] ?? '');
    $tipo_contrato = trim($_POST['tipo_contrato'] ?? '');

    if ($razao_social === '' || $cnpj === '' || $tipo_contrato === '') {
        echo json_encode(['success'=>false,'message'=>'Preencha Nome (Razão Social), CNPJ e Tipo de Contrato.']);
        exit;
    }

    // Normaliza CNPJ
    $cnpj_digits = preg_replace('/\D+/', '', $cnpj);
    if (strlen($cnpj_digits) < 8) {
        echo json_encode(['success'=>false,'message'=>'CNPJ inválido.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO empresas (razao_social, cnpj, endereco, cep, telefone, tipo_contrato)
        VALUES (:razao_social, :cnpj, :endereco, :cep, :telefone, :tipo_contrato)
    ");
    $stmt->execute([
        ':razao_social'  => $razao_social,
        ':cnpj'          => $cnpj_digits,
        ':endereco'      => ($endereco !== '' ? $endereco : null),
        ':cep'           => ($cep !== '' ? $cep : null),
        ':telefone'      => ($telefone !== '' ? $telefone : null),
        ':tipo_contrato' => $tipo_contrato,
    ]);

    echo json_encode(['success'=>true,'message'=>'Empresa cadastrada com sucesso!']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success'=>false,'message'=>'Já existe uma empresa com esse CNPJ.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Erro ao salvar empresa','error'=>$e->getMessage()]);
    }
}
?>