<?php
// php/get_me.php
declare(strict_types=1);

// Evita saída antes do JSON
while (ob_get_level() > 0) { ob_end_clean(); }

session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

if (empty($_SESSION['cpf'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Não autenticado.']); exit;
}

try {
  require_once __DIR__ . '/_db_bridge.php'; // expõe $DB sem mexer no seu db.php
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Erro de conexão: '.$e->getMessage()]);
  exit;
}

function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s);
}

// pega o primeiro valor encontrado no array $cands que exista no $row
function pick(array $row, array $cands) {
  foreach ($cands as $k) {
    if (array_key_exists($k, $row) && $row[$k] !== null) {
      return $row[$k];
    }
  }
  return null;
}

try {
  $cpfSessao = only_digits((string)$_SESSION['cpf']);

  // 1) Busca o registro inteiro (SELECT *)
  $row = null;
  if ($DB['type'] === 'pdo') {
    /** @var PDO $pdo */
    $pdo = $DB['pdo'];
    $st = $pdo->prepare("
      SELECT * FROM alunos
      WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
      LIMIT 1
    ");
    $st->execute([':cpf'=>$cpfSessao]);
    $row = $st->fetch();

  } elseif ($DB['type'] === 'mysqli') {
    /** @var mysqli $conn */
    $conn = $DB['mysqli'];
    $st = $conn->prepare("
      SELECT * FROM alunos
      WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = ?
      LIMIT 1
    ");
    if (!$st) { throw new Exception('Falha prepare (mysqli): '.$conn->error); }
    $st->bind_param('s', $cpfSessao);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
  } else {
    throw new Exception('Tipo de conexão desconhecido.');
  }

  if (!$row) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Aluno não encontrado.']); exit;
  }

  // 2) Mapas de possíveis nomes por campo
  $campos = [
    'ra'               => ['ra','RA','registro_academico'],
    'nome'             => ['nome','Nome','nome_completo'],
    'nome_nascimento'  => ['nome_nascimento','nome_nasc','nome_registro','nome_de_nascimento','nome_ao_nascer'],
    'cpf'              => ['cpf','CPF'],
    'curso'            => ['curso','Curso'],
    'turno'            => ['turno','Turno'],
    'serie'            => ['serie','série','Serie','Série','ano','Ano'],
    'status'           => ['status','situacao','situação','Status'],
    'empresa'          => ['empresa','empresa_atual','empresa_trabalho'],
    'inicio_trabalho'  => ['inicio_trabalho','inicio_contrato','data_inicio','data_inicio_contrato','inicio'],
    'fim_trabalho'     => ['fim_trabalho','fim_contrato','data_fim','data_fim_contrato','termino','término'],
    'renovou_contrato' => ['renovou_contrato','renovou','renovacao','renovação','renovacao_contrato'],
    'contato'          => ['contato','telefone','celular','email','e_mail'],
    'tipo_contrato'    => ['tipo_contrato','tipo','modalidade','vinculo','vínculo'],
    'data_nascimento'  => ['data_nascimento','nascimento','dt_nascimento','dataDeNascimento','dn'],
  ];

  // 3) Monta payload com base no que existir
  $out = [];
  foreach ($campos as $padrao => $cands) {
    $out[$padrao] = pick($row, $cands);
  }

  // 4) Pós-processamento
  // boolean para renovou_contrato
  if ($out['renovou_contrato'] !== null) {
    $val = $out['renovou_contrato'];
    // aceita 1/0, '1'/'0', 'sim'/'não', 'true'/'false'
    $out['renovou_contrato'] = in_array(strtolower((string)$val), ['1','sim','true','yes','y'], true) || $val === 1;
  }

  // idade a partir de data_nascimento
  $idade = null;
  if (!empty($out['data_nascimento'])) {
    try {
      $dn   = new DateTime((string)$out['data_nascimento']);
      $idade = $dn->diff(new DateTime('today'))->y;
    } catch (Throwable $e) { $idade = null; }
  }

  echo json_encode([
    'success' => true,
    'data' => [
      'ra'               => $out['ra'],
      'nome'             => $out['nome'],
      'nome_nascimento'  => $out['nome_nascimento'],
      'cpf'              => $out['cpf'],
      'curso'            => $out['curso'],
      'turno'            => $out['turno'],
      'serie'            => $out['serie'],
      'status'           => $out['status'],
      'empresa'          => $out['empresa'],
      'inicio_trabalho'  => $out['inicio_trabalho'],
      'fim_trabalho'     => $out['fim_trabalho'],
      'renovou_contrato' => $out['renovou_contrato'],
      'contato'          => $out['contato'],
      'tipo_contrato'    => $out['tipo_contrato'],
      'data_nascimento'  => $out['data_nascimento'],
      'idade'            => $idade
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode(['success'=>false,'message'=>'Erro no servidor: '.$e->getMessage()]);
}
?>