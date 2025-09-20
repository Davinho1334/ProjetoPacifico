<?php
// php/export_pdf.php
declare(strict_types=1);

// (opcional) proteger a rota se existir esse arquivo
@require_once __DIR__ . '/auth_admin.php';

// conexão (exige que db.php defina $pdo OU $mysqli)
require_once __DIR__ . '/db.php';

/* ----------------- helpers ----------------- */
function abort_with(string $msg, int $code = 500) {
  http_response_code($code);
  echo "<meta charset='utf-8'><pre style='font-family:ui-monospace,monospace'>{$msg}</pre>";
  exit;
}
function br_date(?string $iso): string {
  if (!$iso) return '-';
  $iso = trim($iso);
  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $iso)) {
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : $iso;
  }
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $iso)) return $iso;
  return $iso;
}
function safe($s): string {
  return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function vv($v): string {
  return ($v !== null && $v !== '') ? safe($v) : '-';
}

/* ----------------- entrada ----------------- */
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$cpf = isset($_GET['cpf']) ? trim((string)$_GET['cpf']) : null;

if (!$id && !$cpf) abort_with("Parâmetro ausente. Informe ?id=123 ou ?cpf=000.000.000-00", 400);

/* ----------------- conexões ----------------- */
$isPDO    = isset($pdo) && $pdo instanceof PDO;
$isMySQLi = isset($mysqli) && $mysqli instanceof mysqli;
if (!$isPDO && !$isMySQLi) abort_with("Nenhuma conexão disponível. Verifique se db.php define \$pdo ou \$mysqli.");

/* ----------------- query ----------------- */
$sql = "
SELECT
  a.id, a.nome, a.cpf, a.ra, a.contato_aluno, a.data_nascimento,
  a.inicio_trabalho, a.fim_trabalho, a.status, a.empresa,
  a.relatorio, a.serie, a.curso, a.turno, a.tipo_contrato,
  a.recebeu_bolsa, a.observacao
FROM alunos a
WHERE " . ($id ? "a.id = ?" : "a.cpf = ?") . "
LIMIT 1
";

$row = null;
try {
  if ($isPDO) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id ?: $cpf]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  } else {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) abort_with("Falha ao preparar a consulta (MySQLi): " . $mysqli->error);
    if ($id) { $stmt->bind_param("i", $id); } else { $stmt->bind_param("s", $cpf); }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }
} catch (Throwable $e) {
  abort_with("Erro ao consultar o aluno: " . $e->getMessage());
}

if (!$row) abort_with("Aluno não encontrado para " . ($id ? "id={$id}" : "cpf=" . safe($cpf)), 404);

/* ----------------- prepara variáveis p/ o HTML ----------------- */
$genDate      = date('d/m/Y H:i');

$alunoId      = (string)$row['id'];
$nome         = vv($row['nome']);
$cpfOut       = vv($row['cpf']);
$ra           = vv($row['ra']);
$contato      = vv($row['contato_aluno'] ?? $row['contato']);
$anoNasc      = vv($row['data_nascimento']);
$curso        = vv($row['curso']);
$serie        = vv($row['serie']);
$turno        = vv($row['turno']);
$tipoContrato = vv($row['tipo_contrato']);
$bolsa        = vv($row['recebeu_bolsa']);
$empresa      = vv($row['empresa']);
$status       = vv($row['status']);

$inicio       = safe(br_date($row['inicio_trabalho'] ?? null));
$fim          = safe(br_date($row['fim_trabalho'] ?? null));

$relatorio    = ($row['relatorio'] ?? '') !== '' ? nl2br(safe($row['relatorio'])) : '-';
$observacao   = ($row['observacao'] ?? '') !== '' ? nl2br(safe($row['observacao'])) : '-';

$arquivoSaida = 'Aluno_' . preg_replace('/\s+/', '_', trim(html_entity_decode($nome))) . '.pdf';

/* ----------------- HTML ----------------- */
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
    .w100 { width:100%; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>Ficha do Aluno</h1>
      <div class="muted">Gerado em: <span class="mono">{$genDate}</span></div>
    </div>
    <div>
      <span class="badge">{$status}</span>
    </div>
  </div>

  <div class="card">
    <table class="meta">
      <tr><td class="label">Nome</td><td class="value w100">{$nome}</td></tr>
      <tr><td class="label">CPF</td><td class="value">{$cpfOut}</td></tr>
      <tr><td class="label">RA</td><td class="value">{$ra}</td></tr>
      <tr><td class="label">Contato</td><td class="value">{$contato}</td></tr>
      <tr><td class="label">Ano de Nascimento</td><td class="value">{$anoNasc}</td></tr>
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
      <div class="label" style="margin-top:8px;">Bolsa</div>
      <div class="value">{$bolsa}</div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="label">Empresa</div>
      <div class="value">{$empresa}</div>
    </div>
    <div class="card">
      <div class="label">Período do Contrato</div>
      <div class="value">Início: {$inicio} — Término: {$fim}</div>
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
    ID interno: <span class="mono">{$alunoId}</span>
  </div>
</body>
</html>
HTML;

/* ----------------- PDF (Dompdf) ----------------- */
$haveDompdf = class_exists(\Dompdf\Dompdf::class);
if (!$haveDompdf) {
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
    abort_with("Falha ao gerar PDF (Dompdf): " . $e->getMessage());
  }
}

abort_with(
  "Biblioteca Dompdf não encontrada.\n\n".
  "Instale com Composer na raiz do projeto:\n".
  "  composer require dompdf/dompdf\n\n".
  "Depois tente novamente este export."
);
?>