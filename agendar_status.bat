@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Agendar atualizacao de status (SchoolFlow)

:: ================== CONFIGURACAO ==================
:: Ajuste se o seu caminho for diferente:
set "PHP_EXE=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\htdocs\ProjetoPacifico\php\cron_update_status.php"

:: Nome da tarefa e horario (HH:MM no formato 24h)
set "TASK_NAME=SchoolFlowUpdateStatus"
set "START_TIME=00:05"
:: ==================================================

:: --- Checa se esta em modo admin; se nao, eleva com UAC ---
net session >nul 2>&1
if %errorlevel% neq 0 (
  echo Solicitando permissao de administrador...
  powershell -NoProfile -ExecutionPolicy Bypass -Command ^
   "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

echo.
echo ================== Verificacoes ==================
if not exist "%PHP_EXE%" (
  echo [ERRO] Nao encontrei o PHP em: "%PHP_EXE%"
  echo        Ajuste a variavel PHP_EXE no cabecalho do script.
  exit /b 1
)

if not exist "%SCRIPT%" (
  echo [ERRO] Nao encontrei o script em: "%SCRIPT%"
  echo        Ajuste a variavel SCRIPT no cabecalho do script.
  exit /b 1
)

echo PHP encontrado:    "%PHP_EXE%"
echo Script encontrado: "%SCRIPT%"
echo Horario agendado:  %START_TIME%
echo Nome da tarefa:    %TASK_NAME%
echo ==================================================
echo.

:: --- (Re)cria a tarefa agendada ---
echo Criando/atualizando a tarefa agendada...
schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1

schtasks /Create ^
  /SC DAILY ^
  /TN "%TASK_NAME%" ^
  /TR "\"%PHP_EXE%\" \"%SCRIPT%\"" ^
  /ST %START_TIME% ^
  /RL HIGHEST ^
  /F

if errorlevel 1 (
  echo [ERRO] Falha ao criar a tarefa. Verifique os caminhos e tente novamente.
  exit /b 1
)

echo.
echo Tarefa criada com sucesso!
echo.

:: --- Executa um teste imediato ---
echo Executando teste agora...
schtasks /Run /TN "%TASK_NAME%" >nul 2>&1
if errorlevel 1 (
  echo [ATENCAO] Nao consegui iniciar a tarefa agora. Isto pode ocorrer se:
  echo  - O servico de Agendador de Tarefas estiver parado
  echo  - Houve algum bloqueio de permissao
) else (
  echo Tarefa iniciada para teste. Acompanhe o resultado no navegador ou no banco.
)

echo.
echo Conferindo tarefa:
schtasks /Query /TN "%TASK_NAME%"
echo.
echo Pronto! A execucao diaria esta configurada. Para remover, use o script "remover_status.bat".
pause
