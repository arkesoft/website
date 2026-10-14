@echo off
setlocal
cd /d "%~dp0"
set "ARKESOFT_PHP=php"
where php >nul 2>&1
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set "ARKESOFT_PHP=C:\xampp\php\php.exe"
  ) else (
    echo PHP bulunamadi. XAMPP kurun veya PHP'yi PATH'e ekleyin.
    pause
    exit /b 1
  )
)
echo.
echo ARKESOFT yerel onizleme
echo Adres: http://127.0.0.1:8127/
echo Durdurmak icin Ctrl+C tuslarina basin.
echo Bu pencereyi siteyi kullanirken acik tutun.
echo.
if exist "%~dp0storage\local-config.php" (
  set "ARKESOFT_CONFIG=storage/local-config.php"
  "%ARKESOFT_PHP%" -d extension=pdo_sqlite -S 127.0.0.1:8127 router.php
) else (
  "%ARKESOFT_PHP%" -S 127.0.0.1:8127 router.php
)
echo.
echo Sunucu durdu. Port kullanimdaysa site zaten acik olabilir.
pause
