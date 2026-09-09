@echo off
setlocal

set "SOURCE_DIR=\\BAR\ecosoft\ServerLocal"
set "TARGET_DIR=%~dp0baza_date_copiata"

set "LOG_FILE=%~dp0app\storage\foxpro_sync_output.txt"
set "RESULT_FILE=%~dp0app\storage\foxpro_sync_result.txt"

if not exist "%SOURCE_DIR%\" (
    >"%LOG_FILE%" echo Sursa FoxPro nu este accesibila: "%SOURCE_DIR%"
    >>"%LOG_FILE%" echo Nu s-au modificat copiile locale existente.
    set "ROBOCOPY_CODE=2"
    goto finish
)

if not exist "%TARGET_DIR%\" mkdir "%TARGET_DIR%"
if not exist "%TARGET_DIR%\" (
    >"%LOG_FILE%" echo Nu pot crea folderul local: "%TARGET_DIR%"
    set "ROBOCOPY_CODE=3"
    goto finish
)

> "%LOG_FILE%" echo Sincronizare FoxPro din "%SOURCE_DIR%"...
robocopy "%SOURCE_DIR%" "%TARGET_DIR%" note.dbf COMPNOTE.DBF temp_bonuri.dbf bon_comanda.dbf totaluri.dbf comp_total.dbf comptotal.dbf disponibilitati.dbf prod_mat.dbf /R:3 /W:2 /COPY:DAT /DCOPY:T /NFL /NDL >>"%LOG_FILE%" 2>&1
set "ROBOCOPY_CODE=%ERRORLEVEL%"

if %ROBOCOPY_CODE% GEQ 8 (
    >>"%LOG_FILE%" echo Sincronizarea a esuat, se pastreaza copiile locale existente.
    goto finish
)

>>"%LOG_FILE%" echo Copiile locale sunt actualizate sau erau deja la zi.

:finish
>"%RESULT_FILE%" echo CODE=%ROBOCOPY_CODE%
>>"%RESULT_FILE%" echo TIME=%DATE% %TIME%
echo.
echo Cod robocopy: %ROBOCOPY_CODE%
echo Verifica folderul: "%TARGET_DIR%"
pause
exit /b %ROBOCOPY_CODE%
