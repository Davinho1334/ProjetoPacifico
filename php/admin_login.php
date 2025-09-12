<?php
// php/admin_login.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
    require_once __DIR__ . '/db.php';

    $cpf   = trim($_POST['cpf'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($cpf === '' || $senha === '') {
        echo json_encode(['success'=>false,'message'=>'Informe CPF e senha.']);
        exit;
    }

    $st = $pdo->prepare("SELECT id, cpf, nome, senha_hash FROM admins WHERE cpf = :cpf LIMIT 1");
    $st->execute([':cpf'=>$cpf]);
    $adm = $st->fetch(PDO::FETCH_ASSOC);

    if (!$adm) {
        echo json_encode(['success'=>false,'message'=>'Administrador não encontrado.']);
        exit;
    }

    if (!password_verify($senha, $adm['senha_hash'])) {
        echo json_encode(['success'=>false,'message'=>'Senha incorreta.']);
        exit;
    }

    $_SESSION['admin_id']         = $adm['id'];
    $_SESSION['admin_cpf']        = $adm['cpf'];
    $_SESSION['admin_nome']       = $adm['nome'];
    $_SESSION['admin_logged_in']  = true; // flag usada pelo auth

    echo json_encode(['success'=>true,'message'=>'Login realizado.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro no servidor','error'=>$e->getMessage()]);
}
?>