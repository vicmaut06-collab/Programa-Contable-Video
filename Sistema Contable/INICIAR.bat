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

REM --- 2. Apache con cache limpio ---
REM Reinicia Apache y fuerza a recargar estilos y scripts, para no quedar
REM viendo una version vieja guardada en el navegador.
REM LIMPIAR-CACHE.bat ya comprueba que la pagina responde.
echo [2/3] Limpiando cache y reiniciando Apache...
call "%RUTA%LIMPIAR-CACHE.bat" silencioso
if errorlevel 1 (
    echo.
    echo No se pudo dejar Apache listo.
    echo Abre XAMPP y presiona Start en Apache, luego ejecuta INICIAR.bat otra vez.
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
