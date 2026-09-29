@echo off
setlocal
title Sistema Contable - Instalar en esta computadora

echo ============================================================
echo     SISTEMA CONTABLE - Instalacion automatica
echo ============================================================
echo.
echo  Esto se hace una sola vez, en la computadora nueva.
echo  Necesitas: XAMPP instalado y PostgreSQL instalado.
echo.

set "XAMPP=C:\xampp"

if not exist "%XAMPP%\php\php.exe" (
    echo [FALLA] No se encontro PHP en %XAMPP%
    echo.
    echo   Instala XAMPP desde https://www.apachefriends.org/es/index.html
    echo   y vuelve a ejecutar este instalador.
    echo.
    pause
    exit /b 1
)

"%XAMPP%\php\php.exe" -c "%XAMPP%\php\php.ini" "%~dp0instalar_pc.php"
set "CODIGO=%ERRORLEVEL%"

echo.
if "%CODIGO%"=="0" (
    echo ============================================================
    echo   INSTALACION COMPLETADA
    echo ============================================================
) else (
    echo ============================================================
    echo   LA INSTALACION NO SE COMPLETO - revisa los mensajes de arriba
    echo ============================================================
)
echo.
pause
endlocal & exit /b %CODIGO%
