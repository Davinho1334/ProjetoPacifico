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

$aluno   = getAluno($pdo, $alunoId);
if (!$aluno) die('Aluno não encontrado');

$empresa = getEmpresa($pdo, $aluno['empresa_id'] ?? ($aluno['empresa'] ?? '')) ?: [];
$agenda  = carregarAgenda($pdo, $alunoId);
$cargas  = calcularCargas($aluno, $agenda);

// ===== Não preencher Responsável Legal (fica em branco) =====
// ===== Não substituir Entidade Formadora (fica como no modelo) =====

// Empregador: endereço completo (novo pedido)
$empNome = $empresa['razao_social'] ?? ($empresa['nome'] ?? '');
$empCnpj = $empresa['cnpj'] ?? '';
$empEndCompleto = formatEmpresaEnderecoCompleto($empresa); // logradouro, Nº, comp, bairro, cidade, UF, CEP

// Curso/CBO/datas/cargas
$curso = (string)($aluno['curso'] ?? '');
$cbo   = cboForCourse($curso, $aluno['cbo'] ?? '');
$inicioBR = formatDateBR($aluno['inicio_trabalho'] ?? '');
$fimBR    = formatDateBR($aluno['fim_trabalho'] ?? '');

$cargaSemanal = $cargas['sem_total'];
$cargaTotal   = $cargas['total_programa'];
$semTeo       = $cargas['sem_teo'];
$semPra       = $cargas['sem_pra'];

$txtTeorica = linhasHorario($agenda['teorica'] ?? []);
$txtPratica = linhasHorario($agenda['pratica'] ?? []);

// Salário (se existir no aluno). Para Estágio, considere recebe_salario; para Aprendiz, pode registrar só valor.
$salario = $aluno['salario'] ?? null;
$recebe  = $aluno['recebe_salario'] ?? null;

// Template
$templatePath = __DIR__ . '/../templates/contrato_aprendiz.docx';
if (!is_file($templatePath)) die('Modelo não encontrado');

$tp = new TemplateProcessor($templatePath);

// Empregador
$tp->setValue('EMPREGADOR_NOME', htmlspecialchars($empNome));
$tp->setValue('EMPREGADOR_CNPJ', htmlspecialchars($empCnpj));
$tp->setValue('EMPREGADOR_END',  htmlspecialchars($empEndCompleto));

// Aprendiz
$tp->setValue('APRENDIZ_NOME', htmlspecialchars($aluno['nome'] ?? ''));
$tp->setValue('APRENDIZ_END',  htmlspecialchars($aluno['endereco'] ?? ''));
$tp->setValue('APRENDIZ_CPF',  htmlspecialchars($aluno['cpf'] ?? ''));

// Responsável Legal => não setar (deixa em branco)
// Entidade Formadora => não setar (fica o texto original do modelo)

// Curso/CBO/Datas/Cargas
$tp->setValue('CURSO', htmlspecialchars($curso));
$tp->setValue('CBO',   htmlspecialchars($cbo));
$tp->setValue('CNAP',  htmlspecialchars($aluno['cnap'] ?? ''));
$tp->setValue('DATA_INICIO', $inicioBR);
$tp->setValue('DATA_FIM',    $fimBR);
$tp->setValue('CARGA_SEMANAL', (string)$cargaSemanal);
$tp->setValue('CARGA_TOTAL',   (string)$cargaTotal);

// Agenda teórica/prática — por placeholders (sem bagunçar o texto do modelo)
$tp->setValue('TEORICA_TABELA', $txtTeorica);
$tp->setValue('PRATICA_TABELA', $txtPratica);
$tp->setValue('TEORICA_SEMANAL_TOTAL', (string)$semTeo);
$tp->setValue('PRATICA_SEMANAL_TOTAL', (string)$semPra);

// Salário (se você colocou placeholders no docx)
$tp->setValue('SALARIO_VALOR', ($salario!==null && $salario!=='') ? number_format((float)$salario,2,',','.') : '');
$tp->setValue('SALARIO_TEM', ($recebe===null) ? '' : ((int)$recebe===1 ? 'SIM' : 'NÃO'));

// Aviso para responsabilidades (sem campo no sistema)
$tp->setValue('AVISO_RESPONSABILIDADES', 'ATENÇÃO: as responsabilidades do aprendiz/estagiário devem ser ajustadas manualmente neste documento conforme orientação da escola/empresa.');

$outDir = __DIR__ . '/../tmp';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/CONTRATO_APRENDIZ_' . $alunoId . '.docx';
$tp->saveAs($outFile);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($outFile).'"');
readfile($outFile);
?>