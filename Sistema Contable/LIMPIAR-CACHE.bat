@echo off
setlocal
title Sistema Contable - Limpiar cache

set "XAMPP=C:\xampp"
set "URL=http://localhost/SistemaContable/"
set "RUTA=%~dp0"
set "SILENCIOSO=%~1"

set "PHP=%XAMPP%\php\php.exe"
if not exist "%PHP%" set "PHP=php"

REM Con "silencioso" solo deja el servidor listo, sin imprimir ni abrir el navegador.
if /i not "%SILENCIOSO%"=="silencioso" (
    echo ============================================
    echo    SISTEMA CONTABLE - Limpiando cache
    echo ============================================
    echo.
)

REM --- 1. Detener Apache ---
REM Reiniciarlo garantiza que PHP ejecute el codigo recien actualizado.
taskkill /IM httpd.exe /F >nul 2>&1
set /a ESPERA=0

:esperar_apache
tasklist /fi "imagename eq httpd.exe" 2>NUL | find "httpd.exe" >nul
if errorlevel 1 goto apache_listo
set /a ESPERA+=1
if %ESPERA% lss 15 goto seguir_espera
goto apache_listo
:seguir_espera
timeout /t 1 /nobreak >nul
goto esperar_apache

:apache_listo
if not "%SILENCIOSO%"=="silencioso" echo [1/3] Apache detenido.

REM --- 2. Invalidar los estilos y scripts guardados en el navegador ---
REM Cada .css y .js se pide con ?v=<fecha del archivo>. Al cambiar la fecha del
REM archivo, esa direccion cambia y el navegador esta obligado a descargarlo otra vez.
set "SERVIDO=%XAMPP%\htdocs\SistemaContable"
if not exist "%SERVIDO%\assets" set "SERVIDO=%RUTA%"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference='SilentlyContinue';" ^
  "$dirs = @('%SERVIDO%','%RUTA%') | Select-Object -Unique;" ^
  "foreach ($d in $dirs) {" ^
  "  $raiz = Join-Path $d 'assets';" ^
  "  if (Test-Path $raiz) {" ^
  "    Get-ChildItem -Path $raiz -Recurse -File |" ^
  "      Where-Object { $_.Extension -eq '.css' -or $_.Extension -eq '.js' } |" ^
  "      ForEach-Object { $_.LastWriteTime = Get-Date }" ^
  "  }" ^
  "}"

if not "%SILENCIOSO%"=="silencioso" echo [2/3] Estilos y scripts marcados como nuevos.

REM --- 3. Iniciar Apache de nuevo ---
start "" wscript.exe //nologo "%RUTA%iniciar_apache.vbs"
timeout /t 5 /nobreak >nul
tasklist /fi "imagename eq httpd.exe" 2>NUL | find "httpd.exe" >nul
if errorlevel 1 goto apache_fallo

REM --- 4. Comprobacion ---
timeout /t 2 /nobreak >nul
"%PHP%" "%RUTA%verificar_web.php" "%URL%"
if errorlevel 1 goto pagina_fallo

if /i not "%SILENCIOSO%"=="silencioso" goto mostrar_ok
endlocal & exit /b 0

:apache_fallo
if not "%SILENCIOSO%"=="silencioso" echo [3/3] Apache no arranco.
if /i not "%SILENCIOSO%"=="silencioso" (
    echo.
    echo Abre XAMPP y presiona Start en Apache, luego ejecuta INICIAR.bat.
    timeout /t 8 /nobreak >nul
)
endlocal & exit /b 1

:pagina_fallo
if not "%SILENCIOSO%"=="silencioso" echo [3/3] Apache quedo arriba pero la pagina no respondio.
if /i not "%SILENCIOSO%"=="silencioso" (
    echo.
    echo Revisa que PostgreSQL este corriendo en el Administrador de servicios.
    timeout /t 8 /nobreak >nul
)
endlocal & exit /b 1

:mostrar_ok
echo [3/3] Cache limpiado. Todo listo.
echo.
start "" "%URL%"
echo Se abrio el navegador con la version mas reciente.
echo.
echo Si aun ve algo viejo, presiona Ctrl+F5 una sola vez.
echo.
timeout /t 5 /nobreak >nul
endlocal & exit /b 0
