@echo off
setlocal
title ESER Costing System - Lokal
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\jalankan-lokal.ps1" %*
if errorlevel 1 (
  echo.
  echo Sistem belum berhasil dijalankan. Periksa pesan di atas.
  pause
  exit /b 1
)
endlocal
