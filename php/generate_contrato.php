<?php
// php/generate_contrato.php
declare(strict_types=1);
require_once __DIR__.'/auth_admin.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/doc_utils.php';
require_once __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$alunoId = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;
if ($alunoId <= 0) { die('aluno_id inválido'); }

$pdo = $pdo ?? null;
if (!($pdo instanceof PDO)) { die('PDO não disponível'); }

$aluno = getAluno($pdo, $alunoId);
if (!$aluno) die('Aluno não encontrado');

$empresa = getEmpresa($pdo, $aluno['empresa_id'] ?? ($aluno['empresa'] ?? ''));
$agenda  = carregarAgenda($pdo, $alunoId);

$ini = $aluno['inicio_trabalho'] ?? '';
$fim = $aluno['fim_trabalho'] ?? '';
$curso = $aluno['curso'] ?? '';
$entidade = $aluno['escola'] ?? 'CETI - (preencher)';

$templatePath = __DIR__ . '/../templates/contrato_aprendiz.docx';
if (!is_file($templatePath)) die('Modelo não encontrado');

$tp = new TemplateProcessor($templatePath);

// --- Placeholders esperados (recomendado editar o .docx e inserir estes marcadores) ---
$map = [
  'EMPREGADOR_NOME'  => ($empresa['nome'] ?? $empresa['razao_social'] ?? ''),
  'EMPREGADOR_END'   => ($empresa['endereco'] ?? ''),
  'EMPREGADOR_CNPJ'  => ($empresa['cnpj'] ?? ''),
  'EMPREGADOR_BAIRROCEP' => '',

  'APRENDIZ_NOME'    => ($aluno['nome'] ?? ''),
  'APRENDIZ_END'     => ($aluno['endereco'] ?? ''),
  'APRENDIZ_CPF'     => ($aluno['cpf'] ?? ''),
  'RESPONSAVEL_NOME' => ($aluno['responsavel'] ?? ''),
  'RESPONSAVEL_CPF'  => ($aluno['responsavel_cpf'] ?? ''),

  'ENTIDADE_NOME'    => $entidade,
  'ENTIDADE_CNPJ'    => ($aluno['entidade_cnpj'] ?? ''),
  'ENTIDADE_END'     => ($aluno['entidade_endereco'] ?? ''),

  'CURSO'            => $curso,
  'CBO'              => ($aluno['cbo'] ?? '351605'),
  'DATA_INICIO'      => $ini,
  'DATA_FIM'         => $fim,

  // totais (opcional usar no docx)
  'CARGA_TOTAL'      => ($aluno['carga_total'] ?? ''), // se quiser: teorica_total+pratica_total do programa
  'CARGA_TEORICA'    => ($aluno['carga_teorica'] ?? ''),
  'CARGA_PRATICA'    => ($aluno['carga_pratica'] ?? ''),
  'CARGA_SEMANAL'    => ($aluno['cargaSemanal'] ?? ''),
];

// preenche placeholders (se existirem)
foreach($map as $key=>$val){ $tp->setValue($key, htmlspecialchars((string)$val)); }

// --- Monta os horários (substituição textual) ---
$sumT = somaSemanal($agenda['teorica']);
$sumP = somaSemanal($agenda['pratica']);

// Exemplo: trocar as linhas azuis do seu modelo por linhas com os horários do editor.
// Você pode ancorar por dia da semana; abaixo criamos um bloco textual simples:
function linhasHorario(array $blocos): string {
  // retorna string multiline com cada dia/ini/fim/total
  $dias = ['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira'];
  $out = [];
  foreach($blocos as $b){
    $h = diffHoras($b['ini'],$b['fim']);
    $out[] = sprintf("%s\t%s\t%s\t%.0f horas", $dias[$b['dia']], $b['ini'], $b['fim'], $h);
  }
  return implode("\n", $out);
}
$txtTeorica = linhasHorario($agenda['teorica']);
$txtPratica = linhasHorario($agenda['pratica']);

// Se você colocar placeholders no docx (ex.: ${TEORICA_TABELA}, ${PRATICA_TABELA}, ${TEORICA_SEMANAL_TOTAL}, ${PRATICA_SEMANAL_TOTAL}):
$tp->setValue('TEORICA_TABELA', $txtTeorica);
$tp->setValue('PRATICA_TABELA', $txtPratica);
$tp->setValue('TEORICA_SEMANAL_TOTAL', (string)$sumT);
$tp->setValue('PRATICA_SEMANAL_TOTAL', (string)$sumP);

// Saída
$outDir = __DIR__ . '/../tmp';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/CONTRATO_APRENDIZ_' . $alunoId . '.docx';
$tp->saveAs($outFile);

// baixar
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($outFile).'"');
readfile($outFile);
?>