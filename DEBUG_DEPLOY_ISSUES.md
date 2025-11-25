# Диагностика проблем развертывания

## Проблемы из последнего развертывания

### 1. Git репозиторий не определяется
**Симптом:** "Предупреждение: Директория не является git репозиторием"

**Решение на сервере:**
```bash
cd ~/avito.siteaccess.ru/public_html
git status
# Если ошибка, инициализируйте:
git init
git remote add origin https://github.com/letoceiling-coder/avito.git
git fetch origin
```

### 2. Composer не найден
**Симптом:** "Could not open input file: /root/composer.phar"

**Проблема:** HOME не читается из .env или composer.phar не найден

**Решение на сервере:**
```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте, что HOME есть в .env
grep HOME .env

# 2. Если нет, добавьте:
echo "HOME=/home/d/dsc23ytp" >> .env

# 3. Проверьте, что composer.phar существует
ls -la ~/composer.phar

# 4. Если нет, установите:
cd ~
curl -sS https://getcomposer.org/installer | php
```

### 3. npm не найден
**Симптом:** "npm не найден, пропуск установки Node.js зависимостей"

**Решение (опционально):**
```bash
# Установите Node.js через nvm или используйте системный npm
# Или просто пропустите этот шаг, если фронтенд собирается локально
```

### 4. Директории не найдены
**Симптом:** "chmod: cannot access 'storage': No such file or directory"

**Решение на сервере:**
```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте структуру
ls -la
ls -la storage
ls -la bootstrap/cache

# Если директорий нет, создайте:
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 755 storage bootstrap/cache
```

## Проверка логов на сервере

### Через новую команду (локально):

```bash
# Просмотр последних 100 строк
php artisan deploy:logs

# Просмотр последних 200 строк
php artisan deploy:logs --lines=200

# С указанием URL и токена
php artisan deploy:logs --url=http://avito.siteaccess.ru --token=ваш_токен
```

### Через API (в браузере или curl):

```bash
# Без токена
curl "http://avito.siteaccess.ru/logs?lines=100"

# С токеном
curl "http://avito.siteaccess.ru/logs?token=ваш_токен&lines=100"
```

### Напрямую на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html
tail -n 100 storage/logs/laravel.log
```

## Полная диагностика на сервере

Выполните на сервере для полной проверки:

```bash
cd ~/avito.siteaccess.ru/public_html

echo "=== Проверка окружения ==="
echo "Текущая директория: $(pwd)"
echo "Пользователь: $(whoami)"
echo "HOME: $HOME"
echo ""

echo "=== Проверка .env ==="
if [ -f .env ]; then
    echo ".env существует"
    grep HOME .env || echo "HOME не найден в .env"
else
    echo ".env не найден!"
fi
echo ""

echo "=== Проверка git ==="
if [ -d .git ]; then
    echo "Git репозиторий найден"
    git remote -v
else
    echo "Git репозиторий не найден"
fi
echo ""

echo "=== Проверка composer ==="
if [ -f ~/composer.phar ]; then
    echo "composer.phar найден в: ~/composer.phar"
    php ~/composer.phar --version
else
    echo "composer.phar не найден в домашней директории"
fi
echo ""

echo "=== Проверка директорий ==="
[ -d storage ] && echo "storage существует" || echo "storage НЕ существует"
[ -d bootstrap/cache ] && echo "bootstrap/cache существует" || echo "bootstrap/cache НЕ существует"
echo ""

echo "=== Проверка npm ==="
which npm && npm --version || echo "npm не найден"
```

## После исправления

После выполнения всех исправлений на сервере, выполните локально:

```bash
php artisan set-deploy --message="Исправление проблем развертывания"
```

И проверьте логи:

```bash
php artisan deploy:logs --lines=200
```
