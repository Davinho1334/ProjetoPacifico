<?php
// php/auth_admin.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Impede cache do navegador (evita mostrar dashboard "fantasma" após logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Heurística para detectar chamadas AJAX/fetch
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$xrw    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$secMode= $_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''; // "navigate" quando é navegação normal

$isAjax = (
    stripos($accept, 'application/json') !== false
    || $xrw === 'xmlhttprequest'
    || ($secMode && strtolower($secMode) !== 'navigate')
);

// Verifica sessão de admin
$logged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$logged) {
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Não autorizado']);
        exit;
    }
    // Navegação normal → redireciona para o login
    header('Location: ../admin_login.html', true, 302);
    exit;
}

// Se chegou aqui, está autenticado; páginas/endereços que incluem este arquivo podem seguir normalmente.
?>