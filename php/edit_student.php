<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// proteção (se você usa sessão de admin)
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
    'inicio_trabalho','fim_trabalho','renovou_contrato','tipo_contrato' // <-- adicionado
];

$fields = [];
$params = [];
$types = '';

foreach($allowed as $f){
    if(array_key_exists($f, $data)){
        if($f === 'empresa_id'){
            if($data[$f] === '' || $data[$f] === null){
                continue; // ignora se vazio
            }
            $empId = intval($data[$f]);
            $chk = $mysqli->prepare("SELECT id FROM empresas WHERE id = ?");
            if(!$chk){ echo json_encode(['success'=>false,'error'=>'Erro DB: '.$mysqli->error]); exit; }
            $chk->bind_param('i', $empId);
            $chk->execute();
            $chk->store_result();
            if($chk->num_rows === 0){
                echo json_encode(['success'=>false,'error'=>'Empresa inválida (id não encontrada)']); 
                $chk->close(); 
                exit;
            }
            $chk->close();

            $fields[] = "$f = ?";
            $params[] = $empId;
            $types .= 'i';
            continue;
        }

        $fields[] = "$f = ?";
        $params[] = $data[$f];

        if(in_array($f, ['cargaSemanal','idade','renovou_contrato'])) $types .= 'i';
        else if($f === 'bolsa') $types .= 'd';
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
    echo json_encode(['success'=>false,'error'=>'Erro prepare: '.$mysqli->error, 'sql'=>$sql]);
    exit;
}

// bind dinâmico
$bind_names = [];
$bind_names[] = $types;
for($i=0;$i<count($params);$i++){
    ${"bind".$i} = $params[$i];
    $bind_names[] = &${"bind".$i};
}
call_user_func_array([$stmt,'bind_param'],$bind_names);

$ok = $stmt->execute();
if($ok){
    if($stmt->affected_rows>0){
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>'Nenhuma linha alterada (os dados podem ser iguais)']);
    }
}else{
    echo json_encode(['success'=>false,'error'=>$stmt->error, 'sql'=>$sql]);
}

$stmt->close();
$mysqli->close();
?>
