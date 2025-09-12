<?php
// php/auth_admin.php  (SUBSTITUIR COMPLETO)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/*
 * ✅ Modo DEV opcional (libera SOMENTE em localhost)
 *   - Para ativar, crie um arquivo vazio php/.dev_allow  OU
 *   - defina a variável de ambiente DEV_EXPORT_ALLOW=1
 *   - NUNCA use isso em produção.
 */
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$devAllow = $isLocalhost && (
  !empty($_ENV['DEV_EXPORT_ALLOW']) ||
  file_exists(__DIR__ . '/.dev_allow')
);

/*
 * ✅ Heurística de autenticação de admin (aceita várias convenções).
 * Ajuste conforme seu projeto. A ideia é NÃO travar você enquanto
 * descobrimos qual flag sua app usa.
 */
$role = strtolower((string)(
  $_SESSION['user_role'] ??
  $_SESSION['role'] ??
  $_SESSION['perfil'] ??
  ''
));

$anyAdminKey = false;
foreach ($_SESSION as $k => $v) {
  if (stripos((string)$k, 'admin') !== false && !empty($v)) {
    $anyAdminKey = true;
    break;
  }
}

$isAdmin =
  // flags mais comuns
  !empty($_SESSION['admin_logged_in']) ||
  !empty($_SESSION['admin']) ||
  !empty($_SESSION['is_admin']) ||
  !empty($_SESSION['admin_login']) ||
  // objetos/arrays típicos
  (!empty($_SESSION['usuario']['is_admin'] ?? null)) ||
  ($role === 'admin') ||
  $anyAdminKey;

if (!$isAdmin && !$devAllow) {
  http_response_code(401);
  echo 'Não autorizado';
  exit;
}
?>