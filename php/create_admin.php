<?php
// php/create_admin.php
require_once __DIR__ . '/db.php'; // precisa expor $pdo

try {
    // (Opcional) Garante a tabela com as colunas que você tem
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
          id INT AUTO_INCREMENT PRIMARY KEY,
          cpf VARCHAR(14) NOT NULL UNIQUE,
          senha_hash VARCHAR(255) NOT NULL,
          nome VARCHAR(120) NOT NULL,
          criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // === DADOS PRÉ-DEFINIDOS (troque se quiser) ===
    $cpf  = '123.456.789-00';           // login será por CPF
    $nome = 'Administrador';
    $senha_plana = '123456';         // troque depois por algo mais forte

    // já existe?
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE cpf = :cpf LIMIT 1");
    $stmt->execute([':cpf' => $cpf]);
    if ($stmt->fetch()) {
        echo "Já existe um administrador com o CPF <b>{$cpf}</b>.";
        exit;
    }

    // cria hash e insere
    $hash = password_hash($senha_plana, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO admins (cpf, senha_hash, nome) VALUES (:cpf, :hash, :nome)");
    $ins->execute([':cpf'=>$cpf, ':hash'=>$hash, ':nome'=>$nome]);

    echo "Administrador criado com sucesso!<br>CPF: <b>{$cpf}</b><br>Senha: <b>{$senha_plana}</b>";
} catch (PDOException $e) {
    echo "Erro ao criar administrador: " . $e->getMessage();
}
?>