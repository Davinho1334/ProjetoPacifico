<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

echo json_encode([
  'ok'   => true,
  'php'  => PHP_VERSION,
  'time' => date('c')
]);
?>