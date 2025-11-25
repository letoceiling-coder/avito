# Исправление ошибки "remote origin уже существует"

## Проблема

```
error: внешний репозиторий origin уже существует
```

## Решение

### Вариант 1: Удалить и пересоздать remote (рекомендуется)

```bash
cd ~/avito.siteaccess.ru/public_html

# Удалите существующий remote
git remote remove origin

# Добавьте remote заново
git remote add origin https://github.com/letoceiling-coder/avito.git

# Проверьте
git remote -v
```

### Вариант 2: Обновить URL существующего remote

```bash
cd ~/avito.siteaccess.ru/public_html

# Обновите URL существующего remote
git remote set-url origin https://github.com/letoceiling-coder/avito.git

# Проверьте
git remote -v
```

### Вариант 3: Проверить текущий remote

```bash
cd ~/avito.siteaccess.ru/public_html

# Посмотрите текущие remotes
git remote -v

# Если URL правильный, можно просто продолжить
# Если нет - используйте вариант 1 или 2
```

## После настройки remote

```bash
# Проверьте подключение к репозиторию
git fetch origin

# Если все хорошо, можно сделать pull
git pull origin master --allow-unrelated-histories
```

## Полная последовательность команд

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Удалите старый remote (если есть)
git remote remove origin 2>/dev/null || true

# 2. Добавьте новый remote
git remote add origin https://github.com/letoceiling-coder/avito.git

# 3. Проверьте
git remote -v

# 4. Получите код из репозитория
git fetch origin

# 5. Добавьте существующие файлы
git add .

# 6. Сделайте коммит
git commit -m "Server files before merge" || true

# 7. Объедините с репозиторием
git pull origin master --allow-unrelated-histories --no-edit
```
