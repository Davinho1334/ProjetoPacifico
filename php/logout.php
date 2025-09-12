<?php
// php/logout.php
declare(strict_types=1);

// Sempre responda JSON e nunca deixe o browser cachear essa resposta
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Aceita POST (recomendado) e GET (fallback)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST','GET'], true)) {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método não permitido']);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Limpa variáveis da sessão
$_SESSION = [];

// Invalida o cookie da sessão, se existir
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    [
      'expires'  => time() - 42000,
      'path'     => $params['path'],
      'domain'   => $params['domain'],
      'secure'   => $params['secure'],
      'httponly' => $params['httponly'],
      'samesite' => $params['samesite'] ?? 'Lax',
    ]
  );
}

// Destroi a sessão no servidor
session_destroy();

// (Opcional) Se você usa cookies extras de autenticação, limpe-os aqui
// setcookie('remember_me', '', time() - 3600, '/');

echo json_encode(['success' => true, 'message' => 'Logout efetuado']);
?>