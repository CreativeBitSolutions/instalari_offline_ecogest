@echo off
setlocal

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0CreeazaResurse.ps1" -OutputPath "%~dp0Resurse"
set "resurseExitCode=%ERRORLEVEL%"

echo.
if not "%resurseExitCode%"=="0" echo Generatorul s-a oprit cu codul %resurseExitCode%.
pause
exit /b %resurseExitCode%
