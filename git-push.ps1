# Скрипт для автоматической отправки изменений в git
# Использование: .\git-push.ps1 "Сообщение коммита"

param(
    [Parameter(Mandatory=$false)]
    [string]$Message = "Update: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
)

Write-Host "=== Отправка изменений в Git ===" -ForegroundColor Cyan

# Проверка наличия изменений
$status = git status --porcelain
if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "Нет изменений для отправки." -ForegroundColor Yellow
    exit 0
}

Write-Host "`nДобавление файлов..." -ForegroundColor Green
git add .

if ($LASTEXITCODE -ne 0) {
    Write-Host "Ошибка при добавлении файлов!" -ForegroundColor Red
    exit 1
}

Write-Host "Создание коммита с сообщением: $Message" -ForegroundColor Green
git commit -m $Message

if ($LASTEXITCODE -ne 0) {
    Write-Host "Ошибка при создании коммита!" -ForegroundColor Red
    exit 1
}

Write-Host "Отправка в репозиторий..." -ForegroundColor Green
git push

if ($LASTEXITCODE -ne 0) {
    Write-Host "Ошибка при отправке в репозиторий!" -ForegroundColor Red
    exit 1
}

Write-Host "`n=== Изменения успешно отправлены! ===" -ForegroundColor Green
