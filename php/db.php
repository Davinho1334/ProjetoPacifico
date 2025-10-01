<?php
require_once __DIR__.'/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function pdo(){
  static $pdo=null;
  if(!$pdo){
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function require_admin(){
  if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
  }
}

function client_meta(){
  return [$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null];
}

function audit($action,$entity=null,$entity_id=null,$payload=null){
  try{
    [$ip,$ua] = client_meta();
    $stmt = pdo()->prepare(
      "INSERT INTO audit_log (admin_id,action,entity,entity_id,payload_json,ip,user_agent)
       VALUES (:admin_id,:action,:entity,:entity_id,:payload,:ip,:ua)"
    );
    $stmt->execute([
      ':admin_id'=>$_SESSION['admin_id']??null,
      ':action'=>$action,
      ':entity'=>$entity,
      ':entity_id'=>$entity_id,
      ':payload'=>$payload?json_encode($payload,JSON_UNESCAPED_UNICODE):null,
      ':ip'=>$ip, ':ua'=>$ua
    ]);
  }catch(Exception $e){}
}
// 🔥 Adicione isto:
$pdo = pdo();
?>