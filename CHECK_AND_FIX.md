# Проверка и исправление проблем развертывания

## 🔍 Проверка логов

### Вариант 1: Через новую команду (рекомендуется)

```bash
# Просмотр последних 100 строк логов
php artisan deploy:logs

# Просмотр последних 200 строк
php artisan deploy:logs --lines=200

# С токеном (если установлен DEPLOY_TOKEN)
php artisan deploy:logs --token=ваш_токен
```

### Вариант 2: Через API в браузере

```
http://avito.siteaccess.ru/logs?lines=100
```

### Вариант 3: Напрямую на сервере

```bash
cd ~/avito.siteaccess.ru/public_html
tail -n 100 storage/logs/laravel.log
```

## 🔧 Исправление проблем на сервере

### Проблема 1: Git репозиторий не определяется

**Выполните на сервере:**

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте git
git status

# Если ошибка, инициализируйте:
git init
git remote add origin https://github.com/letoceiling-coder/avito.git
git fetch origin
```

### Проблема 2: Composer не найден

**Выполните на сервере:**

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте HOME в .env
grep HOME .env

# 2. Если нет или неправильно, добавьте/исправьте:
echo "HOME=/home/d/dsc23ytp" >> .env
# Или отредактируйте вручную:
nano .env
# Добавьте строку: HOME=/home/d/dsc23ytp

# 3. Проверьте composer
ls -la ~/composer.phar
php ~/composer.phar --version

# 4. Если composer не установлен:
cd ~
curl -sS https://getcomposer.org/installer | php
```

### Проблема 3: Директории не найдены

**Выполните на сервере:**

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте структуру
ls -la | grep -E "(storage|bootstrap)"

# Если директорий нет, создайте:
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Установите права
chmod -R 755 storage bootstrap/cache
```

## 📋 Полная диагностика на сервере

Выполните этот скрипт на сервере для полной проверки:

```bash
cd ~/avito.siteaccess.ru/public_html

echo "=== Проверка окружения ==="
echo "Текущая директория: $(pwd)"
echo "Пользователь: $(whoami)"
echo "HOME: $HOME"
echo ""

echo "=== Проверка .env ==="
if [ -f .env ]; then
    echo "✓ .env существует"
    if grep -q "^HOME=" .env; then
        echo "✓ HOME найден в .env:"
        grep "^HOME=" .env
    else
        echo "✗ HOME не найден в .env"
        echo "Добавьте: HOME=/home/d/dsc23ytp"
    fi
else
    echo "✗ .env не найден!"
fi
echo ""

echo "=== Проверка git ==="
if [ -d .git ]; then
    echo "✓ Git репозиторий найден"
    git remote -v
    git status --short | head -5
else
    echo "✗ Git репозиторий не найден"
    echo "Выполните: git init && git remote add origin https://github.com/letoceiling-coder/avito.git"
fi
echo ""

echo "=== Проверка composer ==="
if [ -f ~/composer.phar ]; then
    echo "✓ composer.phar найден в: ~/composer.phar"
    php ~/composer.phar --version
else
    echo "✗ composer.phar не найден в домашней директории"
    echo "Установите: cd ~ && curl -sS https://getcomposer.org/installer | php"
fi
echo ""

echo "=== Проверка директорий ==="
[ -d storage ] && echo "✓ storage существует" || echo "✗ storage НЕ существует"
[ -d bootstrap/cache ] && echo "✓ bootstrap/cache существует" || echo "✗ bootstrap/cache НЕ существует"
echo ""

echo "=== Проверка npm ==="
if which npm > /dev/null 2>&1; then
    echo "✓ npm найден: $(which npm)"
    npm --version
else
    echo "✗ npm не найден (опционально)"
fi
```

## ✅ После исправления

После выполнения всех исправлений:

1. **Очистите кеши на сервере:**
```bash
cd ~/avito.siteaccess.ru/public_html
php8.2 artisan config:clear
php8.2 artisan cache:clear
```

2. **Выполните развертывание локально:**
```bash
php artisan set-deploy --message="Исправление проблем развертывания"
```

3. **Проверьте логи:**
```bash
php artisan deploy:logs --lines=200
```

## 📊 Ожидаемый результат

После исправления в логах должно быть:

```
Шаг 1: Обновление кода из git...
Git репозиторий найден, выполняется обновление...
Код успешно обновлен из ветки master

Шаг 2: Установка PHP зависимостей...
HOME из .env: /home/d/dsc23ytp
Используется composer: php /home/d/dsc23ytp/composer.phar
PHP зависимости установлены успешно

Шаг 9: Установка прав доступа...
Права для storage установлены
Права для bootstrap/cache установлены
```
