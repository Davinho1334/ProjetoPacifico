<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// Proteção
if(!isset($_SESSION['admin_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
if(!$id){
    echo json_encode(['success'=>false,'error'=>'ID inválido']);
    exit;
}

$allowed = [
    'ra','curso','turno','serie','status','cargaSemanal','bolsa',
    'contato_aluno','idade','relatorio','observacao','empresa_id',
    'inicio_trabalho','fim_trabalho','renovou_contrato'
];

$fields = [];
$params = [];
$types = '';

foreach($allowed as $f){
    if(array_key_exists($f, $data)){
        // não atualiza se empresa_id for vazio
        if($f === 'empresa_id' && ($data[$f] === '' || $data[$f] === null)) continue;

        $fields[] = "$f = ?";
        $params[] = $data[$f];

        if($f==='cargaSemanal' || $f==='idade' || $f==='empresa_id' || $f==='renovou_contrato') $types .= 'i';
        else if($f==='bolsa') $types .= 'd';
        else $types .= 's';
    }
}

if(empty($fields)){
    echo json_encode(['success'=>false,'error'=>'Nada para atualizar']);
    exit;
}

$sql = "UPDATE alunos SET ".implode(', ', $fields)." WHERE id = ?";
$params[] = $id;
$types .= 'i';

$stmt = $mysqli->prepare($sql);
if(!$stmt){
    echo json_encode(['success'=>false,'error'=>'Erro no prepare: '.$mysqli->error, 'sql'=>$sql]);
    exit;
}

// bind_param dinâmico
$bind_names[] = $types;
for($i=0; $i<count($params); $i++){
    $bind_name = 'bind'.$i;
    $$bind_name = $params[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array([$stmt,'bind_param'],$bind_names);

$ok = $stmt->execute();
if($ok){
    if($stmt->affected_rows>0){
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>'Nenhuma linha alterada']);
    }
}else{
    echo json_encode(['success'=>false,'error'=>$stmt->error, 'sql'=>$sql]);
}

$stmt->close();
$mysqli->close();
?>
