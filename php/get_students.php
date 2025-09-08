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

// Se for busca individual
if(isset($_GET['id']) && $_GET['id'] !== ''){
    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("
        SELECT a.id, a.ra, a.nome, a.cpf, a.ano_nascimento, a.curso, a.turno, a.serie, a.status,
               a.cargaSemanal, a.bolsa, a.escola,
               a.contato_aluno, a.idade, a.relatorio, a.observacao,
               a.empresa_id, c.razao_social AS empresa_nome,
               a.inicio_trabalho, a.fim_trabalho, a.renovou_contrato, a.criado_em
        FROM alunos a
        LEFT JOIN empresas c ON a.empresa_id = c.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    echo json_encode(['success'=>true,'data'=>$row]);
    $stmt->close();
    $mysqli->close();
    exit;
}

// Busca geral
$res = $mysqli->query("
    SELECT a.id, a.ra, a.nome, a.cpf, a.ano_nascimento, a.curso, a.turno, a.serie, a.status,
           a.cargaSemanal, a.bolsa, a.escola,
           a.contato_aluno, a.idade, a.relatorio, a.observacao,
           a.empresa_id, c.razao_social AS empresa_nome,
           a.inicio_trabalho, a.fim_trabalho, a.renovou_contrato, a.criado_em
    FROM alunos a
    LEFT JOIN empresas c ON a.empresa_id = c.id
    ORDER BY a.criado_em DESC
");
$data = [];
if($res){
  while($row = $res->fetch_assoc()){
    $data[] = $row;
  }
}
echo json_encode(['success'=>true,'data'=>$data]);
$mysqli->close();
?>
