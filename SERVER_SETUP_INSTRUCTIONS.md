# Инструкции для настройки сервера

## Выполните следующие команды на сервере

### 1. Установка переменной окружения HOME для веб-сервера

Проблема: Composer не находится, потому что переменная `HOME` не установлена для веб-сервера.

**Решение A: Добавить в .env (рекомендуется)**

```bash
cd ~/avito.siteaccess.ru/public_html
nano .env
```

Добавьте в конец файла:
```env
HOME=/home/d/dsc23ytp
```

**Решение B: Создать симлинк для composer (альтернатива)**

```bash
# Создайте симлинк в системной директории
sudo ln -s /home/d/dsc23ytp/composer.phar /usr/local/bin/composer-dsc23ytp
chmod +x /usr/local/bin/composer-dsc23ytp
```

### 2. Проверка git репозитория

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте, что git репозиторий инициализирован
git status

# Если ошибка, инициализируйте:
git init
git remote add origin https://github.com/letoceiling-coder/avito.git
git fetch origin
```

### 3. Проверка структуры директорий

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте наличие директорий
ls -la storage
ls -la bootstrap/cache

# Если их нет, создайте:
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Установите права
chmod -R 755 storage bootstrap/cache
```

### 4. Установка зависимостей вручную (если автоматическая не работает)

```bash
cd ~/avito.siteaccess.ru/public_html

# Установите PHP зависимости
php ~/composer.phar install --no-dev --optimize-autoloader --no-interaction

# Если npm доступен, установите Node зависимости
npm install
npm run build
```

### 5. Очистка кешей после изменений

```bash
cd ~/avito.siteaccess.ru/public_html

php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# Пересоздайте кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
```

### 6. Настройка прав доступа (если нужно)

```bash
cd ~/avito.siteaccess.ru/public_html

# Установите правильные права
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true

# Проверьте владельца файлов
ls -la storage
# Если нужно изменить владельца:
# chown -R ваш_пользователь:ваша_группа storage bootstrap/cache
```

### 7. Проверка работы роутов

```bash
# Проверьте, что роуты зарегистрированы
cd ~/avito.siteaccess.ru/public_html
php8.2 artisan route:list | grep -E "(deploy|logs)"
```

### 8. Тестирование API логов

```bash
# Проверьте доступность API логов
curl "http://avito.siteaccess.ru/logs?lines=10"

# Или с токеном (если установлен DEPLOY_TOKEN)
curl "http://avito.siteaccess.ru/logs?token=ваш_токен&lines=10"
```

## После выполнения всех команд

1. Очистите кеши Laravel (команда выше)
2. Попробуйте выполнить развертывание локально:
   ```bash
   php artisan set-deploy --message="Тест после настройки сервера"
   ```

## Проверка работы

После настройки проверьте:

```bash
# На сервере
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте git
git status

# 2. Проверьте composer
php ~/composer.phar --version

# 3. Проверьте директории
ls -la storage bootstrap/cache

# 4. Проверьте логи
tail -n 20 storage/logs/laravel.log
```

## Если проблемы остаются

Проверьте логи на сервере:
```bash
tail -f ~/avito.siteaccess.ru/public_html/storage/logs/laravel.log
```

Или используйте API:
```
http://avito.siteaccess.ru/logs?lines=50
```
