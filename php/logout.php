<?php
// php/logout.php
session_start();

// limpa variáveis da sessão
$_SESSION = [];
session_unset();

// apaga cookie da sessão
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'] ?: '/', $p['domain'] ?: '', $p['secure'] ?: false, $p['httponly'] ?: true);
}

// destrói a sessão
session_destroy();

// evita cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// pode responder 204 (sem corpo) ou JSON
http_response_code(204);
// se preferir JSON, troque a linha acima por:
// header('Content-Type: application/json'); echo json_encode(['success'=>true]);
exit;
?>