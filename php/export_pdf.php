<?php
// php/export_pdf.php
declare(strict_types=1);

// Produção: não exibir avisos em tela do usuário
error_reporting(E_ALL);
ini_set('display_errors', '0');

@require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/db.php';

/* =======================================================================
   Compat com qualquer conexão vinda do db.php
   ======================================================================= */
$mysqli = $mysqli ?? (isset($conn) && $conn instanceof mysqli ? $conn : null);
$isPDO    = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = $mysqli instanceof mysqli;

function abort_with(string $msg, int $code = 500) {
  http_response_code($code);
  echo "<meta charset='utf-8'><pre style='font-family:ui-monospace,monospace'>{$msg}</pre>";
  exit;
}
function safe($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function vv($v): string { return ($v !== null && $v !== '') ? safe($v) : '-'; }
function br_date(?string $iso): string {
  if (!$iso) return '-';
  $iso = trim($iso);
  if (preg_match('/^\d{4}-\d{2}-\d{2}/',$iso)) { $ts=strtotime($iso); return $ts?date('d/m/Y',$ts):$iso; }
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/',$iso)) return $iso;
  $ts=strtotime($iso); return $ts?date('d/m/Y',$ts):$iso;
}
function yn($v): string {
  if ($v === null || $v === '') return '-';
  $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$v),'UTF-8') : strtolower(trim((string)$v));
  if (in_array($s, ['1','true','sim','s'], true))  return 'Sim';
  if (in_array($s, ['0','false','nao','não','n'], true)) return 'Não';
  if (is_numeric($s)) return ((int)$s)!==0 ? 'Sim' : 'Não';
  return ucfirst($s);
}

/* =======================================================================
   Entrada
   ======================================================================= */
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$cpf = isset($_GET['cpf']) ? trim((string)$_GET['cpf']) : null;
if (!$id && !$cpf) abort_with("Parâmetro ausente. Use ?id=123 ou ?cpf=000.000.000-00", 400);

if (!$isPDO && !$isMySQLi) abort_with("Nenhuma conexão disponível. Verifique se db.php define \$pdo ou \$mysqli.");

/* =======================================================================
   Descobrir nome do DB (para information_schema)
   ======================================================================= */
try {
  if ($isPDO) {
    $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
  } else {
    $res = $mysqli->query("SELECT DATABASE()");
    $dbName = $res ? (string)($res->fetch_row()[0] ?? '') : '';
  }
} catch (Throwable $e) { $dbName = ''; }

/* =======================================================================
   Util: checar coluna existente
   ======================================================================= */
function findExistingColumn($isPDO, $pdo, $mysqli, string $db, string $table, array $candidates): ?string {
  if (!$db) return null;
  $in = implode("','", array_map(fn($c)=>str_replace("'", "''", $c), $candidates));
  $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ('{$in}')
          ORDER BY FIELD(COLUMN_NAME, '{$in}') LIMIT 1";
  try {
    if ($isPDO) {
      $st = $pdo->prepare($sql);
      $st->execute([$db, $table]);
      $col = $st->fetchColumn();
      return $col ? (string)$col : null;
    } elseif ($mysqli instanceof mysqli) {
      $st = $mysqli->prepare($sql);
      $st->bind_param("ss", $db, $table);
      $st->execute();
      $rs = $st->get_result();
      $row = $rs ? $rs->fetch_row() : null;
      $st->close();
      return $row[0] ?? null;
    }
  } catch (Throwable $e) {
    // ignora, volta null
  }
  return null;
}

/* =======================================================================
   Colunas de endereço da empresa (dinâmico, sem quebrar)
   ======================================================================= */
$empCols = [];
$empCols['logradouro'] = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['logradouro','rua','endereco']);
$empCols['numero']     = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['numero','nro','num']);
$empCols['bairro']     = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['bairro']);
$empCols['cidade']     = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['cidade','municipio','município']);
$empCols['uf']         = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['uf','estado','sigla_uf']);
$empCols['cep']        = findExistingColumn($isPDO, $pdo ?? null, $mysqli, $dbName, 'empresas', ['cep']);

$empSelectParts = [];
foreach (['logradouro','numero','bairro','cidade','uf','cep'] as $alias) {
  $col = $empCols[$alias] ?? null;
  $empSelectParts[] = $col ? "e.`{$col}` AS empresa_{$alias}" : "NULL AS empresa_{$alias}";
}
$empSelectExtra = implode(",\n  ", $empSelectParts);

/* =======================================================================
   SELECT principal (ajustado ao seu esquema)
   ======================================================================= */
$sql = "
SELECT
  a.id,
  a.nome,
  a.cpf,
  a.ra,
  a.data_nascimento,
  a.curso,
  a.turno,
  a.serie,
  a.status,
  a.contato_aluno,
  a.inicio_trabalho,
  a.fim_trabalho,
  a.tipo_contrato,
  a.recebeu_bolsa,
  a.renovou_contrato,
  a.relatorio,
  a.observacao,
  COALESCE(e.nome, e.razao_social, a.empresa) AS empresa_nome,
  e.cnpj        AS empresa_cnpj,
  e.telefone    AS empresa_telefone,
  {$empSelectExtra}
FROM alunos a
LEFT JOIN empresas e ON e.id = a.empresa_id
WHERE " . ($id ? "a.id = ?" : "a.cpf = ?") . "
LIMIT 1
";

/* =======================================================================
   Executar
   ======================================================================= */
$row = null;
try {
  if ($isPDO) {
    $st = $pdo->prepare($sql);
    $st->execute([$id ?: $cpf]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  } else {
    $st = $mysqli->prepare($sql);
    if (!$st) abort_with("Falha ao preparar consulta (MySQLi): " . $mysqli->error);
    if ($id) { $st->bind_param("i", $id); } else { $st->bind_param("s", $cpf); }
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $st->close();
  }
} catch (Throwable $e) {
  abort_with("Erro ao consultar o aluno: " . $e->getMessage());
}
if (!$row) abort_with("Aluno não encontrado.", 404);

/* =======================================================================
   Variáveis para o template
   ======================================================================= */
$genDate      = date('d/m/Y H:i');

$alunoId      = (string)($row['id'] ?? '');
$nome         = vv($row['nome'] ?? '');
$cpfOut       = vv($row['cpf'] ?? '');
$ra           = vv($row['ra'] ?? '');
$contato      = vv($row['contato_aluno'] ?? '');
$nascimento   = vv(br_date($row['data_nascimento'] ?? null));

$curso        = vv($row['curso'] ?? '');
$serie        = vv($row['serie'] ?? '');
$turno        = vv($row['turno'] ?? '');
$status       = vv($row['status'] ?? '');
$tipoContrato = vv($row['tipo_contrato'] ?? '');

$recebeuBolsa = yn($row['recebeu_bolsa'] ?? null);
$renovou      = yn($row['renovou_contrato'] ?? null);

$empresaNome  = vv($row['empresa_nome'] ?? '');
$empresaCnpj  = vv($row['empresa_cnpj'] ?? '');
$empresaTel   = vv($row['empresa_telefone'] ?? '');

$empLog  = vv($row['empresa_logradouro'] ?? null);
$empNum  = vv($row['empresa_numero'] ?? null);
$empBai  = vv($row['empresa_bairro'] ?? null);
$empCid  = vv($row['empresa_cidade'] ?? null);
$empUF   = vv($row['empresa_uf'] ?? null);
$empCEP  = vv($row['empresa_cep'] ?? null);

$inicio       = vv(br_date($row['inicio_trabalho'] ?? null));
$fim          = vv(br_date($row['fim_trabalho'] ?? null));

$relatorio    = ($row['relatorio'] ?? '') !== '' ? nl2br(safe($row['relatorio'])) : '-';
$observacao   = ($row['observacao'] ?? '') !== '' ? nl2br(safe($row['observacao'])) : '-';

$linha1Parts  = array_filter([$empLog !== '-' ? $empLog : null, $empNum !== '-' ? $empNum : null]);
$linha2Parts  = array_filter([$empBai !== '-' ? $empBai : null, $empCid !== '-' ? $empCid : null, $empUF !== '-' ? $empUF : null, $empCEP !== '-' ? $empCEP : null]);
$empEndereco1 = $linha1Parts ? implode(', ', $linha1Parts) : '-';
$empEndereco2 = $linha2Parts ? implode(' • ', $linha2Parts) : '-';

$arquivoSaida = 'Aluno_' . preg_replace('/[^A-Za-z0-9_\-]+/','_', trim(html_entity_decode($nome))) . '.pdf';

/* =======================================================================
   HTML
   ======================================================================= */
$html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Ficha do Aluno - {$nome}</title>
  <style>
    @page { margin: 24mm 18mm; }
    body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, Helvetica, sans-serif; font-size: 12px; color: #111; }
    h1 { font-size: 20px; margin: 0 0 8px; }
    .muted { color:#666; }
    .header { display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; }
    .badge { font-size: 11px; padding: 2px 8px; border:1px solid #333; border-radius: 999px; display:inline-block; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin-top: 12px; }
    .card { border:1px solid #ddd; border-radius: 8px; padding: 12px; }
    .label { font-size: 10px; letter-spacing:.02em; color:#555; }
    .value { font-size: 13px; font-weight:600; }
    .section-title { font-size: 14px; margin: 18px 0 8px; font-weight:700; }
    .footer { margin-top: 24px; font-size: 10px; color:#666; text-align:right; }
    table.meta { width:100%; border-collapse:collapse; }
    table.meta td { padding:6px 0; vertical-align:top; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>Ficha do Aluno</h1>
      <div class="muted">Gerado em: <span>{$genDate}</span></div>
    </div>
    <div>
      <span class="badge">{$status}</span>
    </div>
  </div>

  <div class="card">
    <table class="meta">
      <tr><td class="label">Nome</td><td class="value">{$nome}</td></tr>
      <tr><td class="label">CPF</td><td class="value">{$cpfOut}</td></tr>
      <tr><td class="label">RA</td><td class="value">{$ra}</td></tr>
      <tr><td class="label">Contato</td><td class="value">{$contato}</td></tr>
      <tr><td class="label">Nascimento</td><td class="value">{$nascimento}</td></tr>
    </table>
  </div>

  <div class="grid">
    <div class="card">
      <div class="label">Curso</div>
      <div class="value">{$curso}</div>
      <div class="label" style="margin-top:8px;">Série</div>
      <div class="value">{$serie}</div>
      <div class="label" style="margin-top:8px;">Turno</div>
      <div class="value">{$turno}</div>
    </div>
    <div class="card">
      <div class="label">Tipo de Contrato</div>
      <div class="value">{$tipoContrato}</div>
      <div class="label" style="margin-top:8px;">Recebeu Bolsa</div>
      <div class="value">{$recebeuBolsa}</div>
      <div class="label" style="margin-top:8px;">Renovou Contrato</div>
      <div class="value">{$renovou}</div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="section-title">Empresa</div>
      <div class="label">Nome</div>
      <div class="value">{$empresaNome}</div>
      <div class="label" style="margin-top:8px;">CNPJ</div>
      <div class="value">{$empresaCnpj}</div>
      <div class="label" style="margin-top:8px;">Telefone</div>
      <div class="value">{$empresaTel}</div>
      <div class="label" style="margin-top:8px;">Endereço</div>
      <div class="value">{$empEndereco1}</div>
      <div class="value" style="margin-top:4px;">{$empEndereco2}</div>
    </div>
    <div class="card">
      <div class="section-title">Período do Trabalho</div>
      <div class="label">Início</div>
      <div class="value">{$inicio}</div>
      <div class="label" style="margin-top:8px;">Fim</div>
      <div class="value">{$fim}</div>
    </div>
  </div>

  <div class="card">
    <div class="section-title">Relatório / Observações</div>
    <div class="label">Relatório</div>
    <div class="value">{$relatorio}</div>
    <div class="label" style="margin-top:10px;">Observação</div>
    <div class="value">{$observacao}</div>
  </div>

  <div class="footer">
    ID interno: <span>{$alunoId}</span>
  </div>
</body>
</html>
HTML;

/* =======================================================================
   PDF com Dompdf (se instalado) — senão, HTML pronto para imprimir
   ======================================================================= */
$haveDompdf = class_exists(\Dompdf\Dompdf::class);
if (!$haveDompdf) {
  // tenta carregar o autoload do Composer (../vendor para a pasta php/)
  $autoload = dirname(__DIR__) . '/vendor/autoload.php';
  if (is_file($autoload)) {
    require_once $autoload;
    $haveDompdf = class_exists(\Dompdf\Dompdf::class);
  }
}

if ($haveDompdf) {
  try {
    if (ob_get_length()) { ob_end_clean(); }
    $dompdf = new \Dompdf\Dompdf([
      'isRemoteEnabled' => true,
      'isHtml5ParserEnabled' => true,
      'defaultFont' => 'DejaVu Sans'
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $nomeArquivo = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $arquivoSaida);
    $dompdf->stream($nomeArquivo, ['Attachment' => true]);
    exit;
  } catch (Throwable $e) {
    // se der qualquer falha, cai no fallback HTML
  }
}

/* --------- Fallback: entregar o HTML e abrir a caixa de impressão --------- */
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: text/html; charset=UTF-8');
echo $html . "<script>try{window.print();}catch(e){}</script>";
exit;
?>