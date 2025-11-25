#!/bin/bash

# Упрощенная версия для Beget (с учетом особенностей хостинга)
# Использование: ./deploy-beget.sh

set -e

# Цвета
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

echo_info() { echo -e "${CYAN}[INFO]${NC} $1"; }
echo_success() { echo -e "${GREEN}[OK]${NC} $1"; }
echo_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
echo_error() { echo -e "${RED}[ERROR]${NC} $1"; }
echo_step() { echo -e "\n${BLUE}>>> $1${NC}"; }

# Проверка
if [ ! -f "artisan" ]; then
    echo_error "Запустите скрипт из корня Laravel проекта!"
    exit 1
fi

START=$(date +%s)

# 1. Git pull
echo_step "Обновление кода"
git pull origin master 2>/dev/null || git pull origin main 2>/dev/null || echo_warning "Git pull пропущен"

# 2. Composer
echo_step "Установка PHP зависимостей"
php ~/composer.phar install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || \
composer install --no-dev --optimize-autoloader --no-interaction || \
echo_warning "Composer не найден"

# 3. NPM (если доступен)
echo_step "Установка Node зависимостей"
if command -v npm &> /dev/null; then
    npm install && npm run build || echo_warning "NPM ошибка"
else
    echo_warning "NPM недоступен"
fi

# 4. Миграции
echo_step "Миграции базы данных"
php artisan migrate --force || echo_error "Ошибка миграций"

# 5. Очистка
echo_step "Очистка кешей и логов"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
> storage/logs/laravel.log 2>/dev/null || true

# 6. Кеширование
echo_step "Кеширование для продакшена"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 7. Права
chmod -R 755 storage bootstrap/cache 2>/dev/null || true

END=$(date +%s)
echo_success "Готово! Время: $((END - START)) сек"
