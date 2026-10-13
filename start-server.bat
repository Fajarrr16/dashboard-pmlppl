@echo off
title Laravel Server Dashboard PMLPPL
echo ========================================================
echo   Menjalankan Server Laravel Dashboard PML/PPL
echo ========================================================
echo.
set PHP_EXE=C:\Users\olah04\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
if exist "%PHP_EXE%" (
    "%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000
) else (
    php artisan serve --host=127.0.0.1 --port=8000
)
pause
