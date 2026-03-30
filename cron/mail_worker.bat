@echo off
setlocal enabledelayedexpansion

:: 1. AYARLAR
set "TASK_NAME=ProjectOil_MailWorker"
set "XAMPP_PATH=E:\xampp"
set "PHP_BIN=%XAMPP_PATH%\php\php.exe"
set "SCRIPT_PATH=%~dp0mail_worker.php"
set "LOG_PATH=%~dp0mail_worker.log"

:: Yönetici Yetkisi Kontrolü
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [HATA] Lutfen Yonetici Olarak Calistirin.
    pause
    exit /b
)

if "%1"=="install" goto install
if "%1"=="uninstall" goto uninstall
if "%1"=="run" goto run
goto menu

:menu
cls
echo =====================================
echo       PROJECT OIL - MAIL WORKER 
echo =====================================
echo.
echo [1] Yukle - Mail Worker Install
echo [2] Kaldir - Remove Mail Worker
echo [3] Manuel Test
echo [4] Cikis - Exit
echo.
set /p "choice=Seciniz [1-4]: "

if "%choice%"=="1" goto install
if "%choice%"=="2" goto uninstall
if "%choice%"=="3" goto manuel
if "%choice%"=="4" exit /b
goto menu

:install
    schtasks /create /tn "%TASK_NAME%" /tr "\"%~f0\" run" /sc onstart /rl highest /ru "SYSTEM" /f > nul
    schtasks /run /tn "%TASK_NAME%" > nul
    echo Mail Worker Basariyla Yuklendi.
    pause
    goto menu

:uninstall
    schtasks /end /tn "%TASK_NAME%" > nul
    echo Mail Worker Durduruldu.
    timeout /t 2 /nobreak > nul
    schtasks /delete /tn "%TASK_NAME%" /f > nul
    echo Mail Worker Basariyla Kaldirildi.
    pause
    goto menu

:run
    :loop
        if not exist "%PHP_BIN%" (
            echo [%date% %time%] HATA: PHP Bulunamadi! >> "%LOG_PATH%"
            timeout /t 60 /nobreak > nul
            goto loop
        )
        "%PHP_BIN%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1
        timeout /t 60 /nobreak > nul
    goto loop

:manuel
    cls
    echo =====================================================
    echo    MANUEL TEST MODU - CIKIS ICIN PENCEREYI KAPATIN
    echo =====================================================
    echo.
    echo [*] PHP Yolu: %PHP_BIN%
    echo [*] Script: %SCRIPT_PATH%
    echo [*] Log Dosyasi: %LOG_PATH%
    echo.
    
    :manuel_loop
        if not exist "%PHP_BIN%" (
            echo [%time%] HATA: PHP bulunamadi!
            timeout /t 60 /nobreak > nul
            goto manuel_loop
        )

        "%PHP_BIN%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1

        for /f "delims=" %%a in ('powershell -command "Get-Content '%LOG_PATH%' -Tail 1 -Encoding UTF8"') do (
            set "lastline=%%a"
        )
        echo %lastline%
        timeout /t 60 /nobreak > nul
    goto manuel_loop