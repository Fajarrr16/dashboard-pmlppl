@echo off
title Cloudflare Tunnel PMLPPL
echo ========================================================
echo   Menghubungkan Dashboard PML/PPL ke Internet
echo   Via Cloudflare Tunnel (UNLIMITED KUOTA & GRATIS)
echo ========================================================
echo.
echo Cari baris URL HTTPS yang berakhiran .trycloudflare.com
echo Contoh: https://nama-acak.trycloudflare.com
echo.
.\cloudflared.exe tunnel --url http://127.0.0.1:8000
pause
