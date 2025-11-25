# Объединение существующих файлов с git репозиторием

## Проблема

Git предупреждает, что неотслеживаемые файлы будут перезаписаны при слиянии.

## Решение

### Вариант 1: Добавить существующие файлы и сделать merge (рекомендуется)

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Добавьте все существующие файлы в git
git add .

# 2. Сделайте коммит с существующими файлами
git commit -m "Initial server files"

# 3. Теперь сделайте pull с merge
git pull origin master --allow-unrelated-histories --no-edit

# Если будут конфликты, разрешите их:
# git add .
# git commit -m "Merge with remote repository"
```

### Вариант 2: Принудительное обновление (перезапишет локальные файлы)

**ВНИМАНИЕ:** Это перезапишет все локальные файлы файлами из репозитория!

```bash
cd ~/avito.siteaccess.ru/public_html

# Сохраните важные файлы (например, .env)
cp .env .env.backup

# Принудительно получите файлы из репозитория
git fetch origin master
git reset --hard origin/master

# Восстановите .env
cp .env.backup .env
```

### Вариант 3: Stash существующих файлов

```bash
cd ~/avito.siteaccess.ru/public_html

# Сохраните существующие файлы
git add .
git stash

# Получите файлы из репозитория
git pull origin master --allow-unrelated-histories

# Примените сохраненные изменения обратно
git stash pop

# Разрешите конфликты, если они есть
# Затем:
git add .
git commit -m "Merge server files with repository"
```

## Рекомендуемая последовательность

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Сохраните важные файлы
cp .env .env.backup 2>/dev/null || true

# 2. Добавьте все файлы
git add .

# 3. Сделайте коммит
git commit -m "Server files before merge"

# 4. Настройте merge стратегию (предпочитаем наши файлы при конфликтах)
git pull origin master --allow-unrelated-histories --strategy-option=ours --no-edit

# 5. Если были конфликты, разрешите их
# git add .
# git commit -m "Resolved conflicts"

# 6. Восстановите .env если нужно
if [ -f .env.backup ]; then
    cp .env.backup .env
fi
```

## После успешного merge

```bash
# Установите upstream для удобства
git branch --set-upstream-to=origin/master master

# Проверьте статус
git status

# Очистите кеши Laravel
php8.2 artisan config:clear
php8.2 artisan cache:clear
```

## Если что-то пошло не так

```bash
# Отменить последний merge
git merge --abort

# Или сбросить к состоянию до merge
git reset --hard HEAD~1
```
