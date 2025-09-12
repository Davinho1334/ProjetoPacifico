<?php
// php/generate_declaracao.php
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

$agenda = carregarAgenda($pdo, $alunoId);

// totais semanais
$semTeo = somaSemanal($agenda['teorica']);
$semPra = somaSemanal($agenda['pratica']);
$cargaSemanal = $semTeo + $semPra;

// totais do programa (se você tiver em campos; se não, pode calcular a partir de datas × semanal, mas aqui deixo como opcional)
$totalTeoria  = (float)($aluno['carga_teorica'] ?? 400); // exemplo padrão do seu modelo
$totalPratica = (float)($aluno['carga_pratica'] ?? 800);
$cargaTotal   = $totalTeoria + $totalPratica;

$curso = $aluno['curso'] ?? '';
$cnap  = $aluno['cnap']  ?? '(CNAP)';
$cbo   = $aluno['cbo']   ?? '351605';
$inicio = $aluno['inicio_trabalho'] ?? '';
$fim    = $aluno['fim_trabalho'] ?? '';
$matricula = $aluno['cpf'] ?? ($aluno['ra'] ?? '');

$templatePath = __DIR__ . '/../templates/declaracao_matricula.docx';
if (!is_file($templatePath)) die('Modelo não encontrado');

$tp = new TemplateProcessor($templatePath);

// Placeholders recomendados no .docx
$tp->setValue('ALUNO_NOME', htmlspecialchars($aluno['nome'] ?? ''));
$tp->setValue('MATRICULA', htmlspecialchars($matricula));
$tp->setValue('CURSO', htmlspecialchars($curso));
$tp->setValue('CNAP', htmlspecialchars($cnap));
$tp->setValue('CBO', htmlspecialchars($cbo));

$tp->setValue('CARGA_TOTAL', (string)$cargaTotal);
$tp->setValue('CARGA_TEORIA_TOTAL', (string)$totalTeoria);
$tp->setValue('CARGA_PRATICA_TOTAL', (string)$totalPratica);

$tp->setValue('DATA_INICIO', $inicio);
$tp->setValue('DATA_FIM', $fim);

$tp->setValue('CARGA_SEMANAL', (string)$cargaSemanal);
$tp->setValue('CARGA_SEMANAL_TEO', (string)$semTeo);
$tp->setValue('CARGA_SEMANAL_PRA', (string)$semPra);

// Texto descritivo igual ao seu modelo (ex.: “08 horas teóricas semanais ... 20 horas práticas ...”)
$tp->setValue('LINHA_TEO', sprintf("%02d (Horas) teóricas semanais, desenvolvidas nessa instituição de ensino;", (int)$semTeo));
$tp->setValue('LINHA_PRA', sprintf("%02d (Horas) práticas semanais, desenvolvidas na empresa.", (int)$semPra));

$outDir = __DIR__ . '/../tmp';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/DECLARACAO_MATRICULA_' . $alunoId . '.docx';
$tp->saveAs($outFile);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($outFile).'"');
readfile($outFile);
?>