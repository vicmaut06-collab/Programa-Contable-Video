@echo off
setlocal
title Sistema Contable - Iniciar

set "XAMPP=C:\xampp"
set "URL=http://localhost/SistemaContable/"
set "RUTA=%~dp0"

echo ============================================
echo    SISTEMA CONTABLE - Iniciando
echo ============================================
echo.

REM --- 1. Base de datos (PostgreSQL) ---
sc query postgresql-x64-18 | find "RUNNING" >nul
if errorlevel 1 (
    echo [1/3] Iniciando PostgreSQL...
    net start postgresql-x64-18 >nul 2>&1
    timeout /t 3 /nobreak >nul
) else (
    echo [1/3] PostgreSQL ya esta corriendo.
)

REM --- 2. Apache ---
tasklist /fi "imagename eq httpd.exe" | find "httpd.exe" >nul
if errorlevel 1 (
    echo [2/3] Iniciando Apache...
    start "" wscript.exe //nologo "%RUTA%iniciar_apache.vbs"
    timeout /t 5 /nobreak >nul
) else (
    echo [2/3] Apache ya esta corriendo.
)

REM --- 3. Comprobacion ---
timeout /t 2 /nobreak >nul
curl -s -o nul -w "" "%URL%"
if errorlevel 1 (
    echo.
    echo [3/3] NO se pudo abrir la pagina. Espera unos segundos e intenta de nuevo.
    echo.
    pause
    exit /b 1
)

echo [3/3] Todo listo.
echo.
start "" "%URL%"
echo Se abrio el navegador en %URL%
echo.
echo Para detenerlo: abre la carpeta C:\xampp y ejecuta STOP de Apache.
echo.
timeout /t 4 /nobreak >nul
endlocal
