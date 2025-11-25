@echo off
REM Скрипт для автоматической отправки изменений в git
REM Использование: git-push.bat "Сообщение коммита"

setlocal

if "%1"=="" (
    set "COMMIT_MSG=Update: %date% %time%"
) else (
    set "COMMIT_MSG=%1"
)

echo === Отправка изменений в Git ===

echo.
echo Добавление файлов...
git add .
if errorlevel 1 (
    echo Ошибка при добавлении файлов!
    exit /b 1
)

echo Создание коммита с сообщением: %COMMIT_MSG%
git commit -m "%COMMIT_MSG%"
if errorlevel 1 (
    echo Ошибка при создании коммита!
    exit /b 1
)

echo Отправка в репозиторий...
git push
if errorlevel 1 (
    echo Ошибка при отправке в репозиторий!
    exit /b 1
)

echo.
echo === Изменения успешно отправлены! ===
pause
