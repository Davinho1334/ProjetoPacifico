<?php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = ''; // no XAMPP geralmente é vazio
$DB_NAME = 'escola_portal';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if($mysqli->connect_error){
  http_response_code(500);
  die(json_encode(['success'=>false,'message'=>'Erro na conexão com o banco: '.$mysqli->connect_error]));
}
$mysqli->set_charset("utf8mb4");
?>