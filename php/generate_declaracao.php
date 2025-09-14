<?php
declare(strict_types=1);
require_once __DIR__.'/auth_admin.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/doc_utils.php';
require_once __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$alunoId = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : 0;
if ($alunoId <= 0) die('aluno_id inválido');

$pdo = $pdo ?? null;
if (!($pdo instanceof PDO)) die('PDO não disponível');

$aluno  = getAluno($pdo, $alunoId);
if (!$aluno) die('Aluno não encontrado');

$agenda = carregarAgenda($pdo, $alunoId);
$cargas = calcularCargas($aluno, $agenda);

// Matrícula = RA (aviso se vazio)
$ra = trim((string)($aluno['ra'] ?? ''));
$matricula = ($ra !== '') ? $ra : '(Preencher corretamente o R.A. no cadastro do aluno)';

// CBO por curso (ou usa aluno.cbo se já salvo)
$curso = (string)($aluno['curso'] ?? '');
$cbo   = cboForCourse($curso, $aluno['cbo'] ?? '');

// Datas BR
$inicioBR = formatDateBR($aluno['inicio_trabalho'] ?? '');
$fimBR    = formatDateBR($aluno['fim_trabalho'] ?? '');

// Cargas
$cargaTotal   = $cargas['total_programa'];
$cargaSemanal = $cargas['sem_total'];
$semTeo       = $cargas['sem_teo'];
$semPra       = $cargas['sem_pra'];
$totalTeoria  = ($cargas['semanas'] > 0) ? round($semTeo * $cargas['semanas'], 2) : $semTeo;
$totalPratica = ($cargas['semanas'] > 0) ? round($semPra * $cargas['semanas'], 2) : $semPra;

// Linhas descritivas semanais
$linhaTeo = sprintf("%02d (Horas) teóricas semanais, desenvolvidas nessa instituição de ensino;", (int)round($semTeo));
$linhaPra = sprintf("%02d (Horas) práticas semanais, desenvolvidas na empresa.", (int)round($semPra));

// Template
$templatePath = __DIR__ . '/../templates/declaracao_matricula.docx';
if (!is_file($templatePath)) die('Modelo não encontrado');

$tp = new TemplateProcessor($templatePath);

// Placeholders do modelo
$tp->setValue('ALUNO_NOME', htmlspecialchars($aluno['nome'] ?? ''));
$tp->setValue('MATRICULA', htmlspecialchars($matricula));
$tp->setValue('CURSO', htmlspecialchars($curso));
$tp->setValue('CNAP', htmlspecialchars($aluno['cnap'] ?? ''));
$tp->setValue('CBO', htmlspecialchars($cbo));

$tp->setValue('CARGA_TOTAL', (string)$cargaTotal);
$tp->setValue('CARGA_TEORIA_TOTAL', (string)$totalTeoria);
$tp->setValue('CARGA_PRATICA_TOTAL', (string)$totalPratica);

$tp->setValue('DATA_INICIO', $inicioBR);
$tp->setValue('DATA_FIM', $fimBR);
$tp->setValue('CARGA_SEMANAL', (string)$cargaSemanal);

$tp->setValue('CARGA_SEMANAL_TEO', (string)$semTeo);
$tp->setValue('CARGA_SEMANAL_PRA', (string)$semPra);
$tp->setValue('LINHA_TEO', $linhaTeo);
$tp->setValue('LINHA_PRA', $linhaPra);

// Saída
$outDir = __DIR__ . '/../tmp';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/DECLARACAO_MATRICULA_' . $alunoId . '.docx';
$tp->saveAs($outFile);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($outFile).'"');
readfile($outFile);
?>