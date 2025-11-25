# ⚠️ Требуется исправление на сервере

## Проблемы из последнего развертывания

### 1. ❌ Git репозиторий не определяется
### 2. ❌ Composer не найден (ищет в /root/composer.phar)
### 3. ❌ Директории storage и bootstrap/cache не найдены

## 🔧 Выполните на сервере (ОБЯЗАТЕЛЬНО)

Скопируйте и выполните все команды:

```bash
cd ~/avito.siteaccess.ru/public_html

# ============================================
# 1. ИСПРАВЛЕНИЕ GIT РЕПОЗИТОРИЯ
# ============================================
echo "=== Исправление Git ==="
if [ ! -d .git ]; then
    echo "Инициализация git репозитория..."
    git init
    git remote add origin https://github.com/letoceiling-coder/avito.git
    git fetch origin
    echo "✓ Git репозиторий инициализирован"
else
    echo "✓ Git репозиторий уже существует"
    git remote -v
fi
echo ""

# ============================================
# 2. ИСПРАВЛЕНИЕ HOME В .ENV
# ============================================
echo "=== Исправление HOME в .env ==="
if [ -f .env ]; then
    if grep -q "^HOME=" .env; then
        echo "HOME уже есть в .env:"
        grep "^HOME=" .env
    else
        echo "Добавление HOME в .env..."
        echo "HOME=/home/d/dsc23ytp" >> .env
        echo "✓ HOME добавлен в .env"
    fi
else
    echo "✗ .env не найден! Создайте его из .env.example"
fi
echo ""

# ============================================
# 3. ПРОВЕРКА COMPOSER
# ============================================
echo "=== Проверка Composer ==="
if [ -f ~/composer.phar ]; then
    echo "✓ composer.phar найден: ~/composer.phar"
    php ~/composer.phar --version
else
    echo "✗ composer.phar не найден!"
    echo "Установка composer..."
    cd ~
    curl -sS https://getcomposer.org/installer | php
    cd ~/avito.siteaccess.ru/public_html
    echo "✓ Composer установлен"
fi
echo ""

# ============================================
# 4. СОЗДАНИЕ ДИРЕКТОРИЙ
# ============================================
echo "=== Создание директорий ==="
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache
echo "✓ Директории созданы"
echo ""

# ============================================
# 5. УСТАНОВКА ПРАВ
# ============================================
echo "=== Установка прав ==="
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true
echo "✓ Права установлены"
echo ""

# ============================================
# 6. ОЧИСТКА КЕШЕЙ
# ============================================
echo "=== Очистка кешей ==="
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear
echo "✓ Кеши очищены"
echo ""

# ============================================
# 7. ПРОВЕРКА
# ============================================
echo "=== Финальная проверка ==="
echo "Текущая директория: $(pwd)"
echo "Git репозиторий: $([ -d .git ] && echo '✓ существует' || echo '✗ не найден')"
echo "HOME в .env: $(grep '^HOME=' .env 2>/dev/null || echo 'не найден')"
echo "composer.phar: $([ -f ~/composer.phar ] && echo '✓ существует' || echo '✗ не найден')"
echo "storage: $([ -d storage ] && echo '✓ существует' || echo '✗ не найден')"
echo "bootstrap/cache: $([ -d bootstrap/cache ] && echo '✓ существует' || echo '✗ не найден')"
echo ""
echo "=== ГОТОВО! ==="
```

## ✅ После выполнения

После выполнения всех команд на сервере:

1. **Выполните развертывание локально:**
```bash
php artisan set-deploy --message="Исправление проблем на сервере"
```

2. **Проверьте результат** - теперь в выводе должно быть:
   - ✓ Git репозиторий найден
   - ✓ HOME из .env: /home/d/dsc23ytp
   - ✓ Используется composer: php /home/d/dsc23ytp/composer.phar
   - ✓ PHP зависимости установлены успешно
   - ✓ Права для storage установлены
   - ✓ Права для bootstrap/cache установлены

## 📊 Проверка логов

После развертывания проверьте логи:

```bash
# Локально
php artisan deploy:logs --lines=200

# Или в браузере
http://avito.siteaccess.ru/logs?lines=200
```

## ⚠️ Важно

Все проблемы связаны с настройкой сервера, а не с кодом. После выполнения команд выше все должно работать.
