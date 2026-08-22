@echo off
REM ============================================================
REM  backup_db.bat - Sao lưu database nali_dental (chuẩn nhất)
REM  Chạy khi MySQL trong XAMPP đang BẬT. Nhấp đúp file này.
REM  Kết quả: file nali_dental_backup.sql khớp 100%% DB thật.
REM ============================================================
setlocal
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DBNAME=nali_dental
set OUTFILE=nali_dental_backup.sql

if not exist "%MYSQLDUMP%" (
    echo [LOI] Khong tim thay mysqldump tai: %MYSQLDUMP%
    echo Kiem tra lai duong dan cai dat XAMPP.
    pause
    exit /b 1
)

echo Dang sao luu database "%DBNAME%" ...
"%MYSQLDUMP%" -u root --port=3306 --databases %DBNAME% --add-drop-table --result-file="%OUTFILE%"

if %errorlevel%==0 (
    echo [OK] Da tao file: %OUTFILE%
    echo Day la ban backup CHUAN, khop voi du lieu that.
) else (
    echo [LOI] Sao luu that bai. Hay chac chan MySQL dang chay va DB ton tai.
)
pause
endlocal
