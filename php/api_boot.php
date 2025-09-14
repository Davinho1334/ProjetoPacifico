<?php
// php/api_boot.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Funções utilitárias
function api_out(bool $ok, $data = null, ?string $msg = null): void {
  echo json_encode(['success'=>$ok,'data'=>$data,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// Tratamento global de erros/exceções -> sempre JSON
set_exception_handler(function(Throwable $e){
  http_response_code(500);
  api_out(false, null, 'Erro: '.$e->getMessage());
});
set_error_handler(function($severity, $message, $file, $line){
  // Converte warnings/notices em exceção para cair no handler acima
  throw new ErrorException($message, 0, $severity, $file, $line);
});
?>