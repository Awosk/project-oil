@echo off
:: =====================================================
:: Project Oil — Mail Worker (Windows Servisi)
:: Sistem başlangıcında Task Scheduler ile çalıştırın.
::
:: Task Scheduler Ayarları:
::   Tetikleyici : Bilgisayar başlangıcında
::   Program     : C:\xampp\htdocs\project-oil\cron\mail_worker.bat
::   Çalıştır    : En yüksek ayrıcalıklarla
:: =====================================================

set PHP_PATH=E:\xampp\php\php.exe
set WORKER_PATH=E:\xampp\htdocs\project-oil\cron\mail_worker.php
set LOG_PATH=E:\xampp\htdocs\project-oil\cron\mail_worker.log

:loop
    %PHP_PATH% %WORKER_PATH% >> %LOG_PATH% 2>&1
    timeout /t 60 /nobreak > nul
goto loop