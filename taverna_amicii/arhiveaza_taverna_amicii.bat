@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\arhiveaza_pachet_client.ps1" -ClientName "taverna_amicii" -ApplicationRelativePath "taverna_amicii" -SourceRelativePath "app_restaurant_v2"
set "RESULT=%ERRORLEVEL%"
echo.
pause
exit /b %RESULT%
