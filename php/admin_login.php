<?php
// php/admin_login.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php'; // expõe $pdo

try {
    // compat: aceita cpf/senha OU username/password
    $cpf    = trim($_POST['cpf'] ?? $_POST['username'] ?? '');
    $senha  = trim($_POST['senha'] ?? $_POST['password'] ?? '');

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

    $_SESSION['admin_id']  = $adm['id'];
    $_SESSION['admin_cpf'] = $adm['cpf'];
    $_SESSION['admin_nome']= $adm['nome'];

    echo json_encode(['success'=>true,'message'=>'Login realizado.']);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Erro no servidor','error'=>$e->getMessage()]);
}
?>