#!/bin/bash

# Скрипт развертывания проекта на сервере Beget
# Использование: ./deploy.sh

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
log_info() {
    echo -e "${CYAN}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BLUE}=== $1 ===${NC}\n"
}

# Проверка, что скрипт запущен из корня проекта
if [ ! -f "artisan" ]; then
    log_error "Скрипт должен быть запущен из корня Laravel проекта!"
    exit 1
fi

# Начало развертывания
log_step "Начало развертывания проекта"
START_TIME=$(date +%s)

# Шаг 1: Обновление кода из Git
log_step "Шаг 1: Обновление кода из Git"
log_info "Выполняется git pull..."
if git pull origin master 2>/dev/null || git pull origin main 2>/dev/null; then
    log_success "Код успешно обновлен из Git"
else
    log_warning "Не удалось обновить код из Git (возможно, не git репозиторий или нет изменений)"
fi

# Шаг 2: Установка PHP зависимостей
log_step "Шаг 2: Установка PHP зависимостей (Composer)"
log_info "Выполняется composer install --no-dev --optimize-autoloader..."

# Проверка наличия composer
if ! command -v composer &> /dev/null; then
    log_error "Composer не найден! Установите Composer или используйте php composer.phar"
    exit 1
fi

if composer install --no-dev --optimize-autoloader --no-interaction; then
    log_success "PHP зависимости установлены"
else
    log_error "Ошибка при установке PHP зависимостей"
    exit 1
fi

# Шаг 3: Установка Node.js зависимостей
log_step "Шаг 3: Установка Node.js зависимостей"
log_info "Выполняется npm install..."

# Проверка наличия npm
if ! command -v npm &> /dev/null; then
    log_warning "npm не найден! Пропускаем установку Node.js зависимостей"
else
    if npm install --production=false; then
        log_success "Node.js зависимости установлены"
    else
        log_error "Ошибка при установке Node.js зависимостей"
        exit 1
    fi
fi

# Шаг 4: Сборка фронтенда
log_step "Шаг 4: Сборка фронтенда"
log_info "Выполняется npm run build..."

if command -v npm &> /dev/null; then
    if npm run build; then
        log_success "Фронтенд успешно собран"
    else
        log_error "Ошибка при сборке фронтенда"
        exit 1
    fi
else
    log_warning "npm не найден, пропускаем сборку фронтенда"
fi

# Шаг 5: Проверка .env файла
log_step "Шаг 5: Проверка конфигурации"
if [ ! -f ".env" ]; then
    log_warning ".env файл не найден, копирую из .env.example..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        log_warning "Создан .env файл. НЕОБХОДИМО НАСТРОИТЬ ЕГО ВРУЧНУЮ!"
    else
        log_error ".env.example не найден!"
    fi
else
    log_success ".env файл существует"
fi

# Шаг 6: Генерация ключа приложения (если нужно)
log_step "Шаг 6: Проверка ключа приложения"
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    log_info "Генерация нового ключа приложения..."
    php artisan key:generate --force
    log_success "Ключ приложения сгенерирован"
else
    log_success "Ключ приложения уже установлен"
fi

# Шаг 7: Выполнение миграций
log_step "Шаг 7: Выполнение миграций базы данных"
log_info "Выполняется php artisan migrate --force..."

if php artisan migrate --force; then
    log_success "Миграции выполнены успешно"
else
    log_error "Ошибка при выполнении миграций"
    exit 1
fi

# Шаг 8: Очистка кешей
log_step "Шаг 8: Очистка всех кешей"
log_info "Очистка кеша приложения..."
php artisan cache:clear
log_success "Кеш приложения очищен"

log_info "Очистка кеша конфигурации..."
php artisan config:clear
log_success "Кеш конфигурации очищен"

log_info "Очистка кеша маршрутов..."
php artisan route:clear
log_success "Кеш маршрутов очищен"

log_info "Очистка кеша представлений..."
php artisan view:clear
log_success "Кеш представлений очищен"

log_info "Очистка кеша событий..."
php artisan event:clear 2>/dev/null || true
log_success "Кеш событий очищен"

# Шаг 9: Пересоздание кешей (оптимизация)
log_step "Шаг 9: Оптимизация и кеширование"
log_info "Кеширование конфигурации..."
php artisan config:cache
log_success "Конфигурация закеширована"

log_info "Кеширование маршрутов..."
php artisan route:cache
log_success "Маршруты закешированы"

log_info "Кеширование представлений..."
php artisan view:cache
log_success "Представления закешированы"

log_info "Оптимизация автозагрузчика..."
composer dump-autoload --optimize --classmap-authoritative
log_success "Автозагрузчик оптимизирован"

# Шаг 10: Очистка логов
log_step "Шаг 10: Очистка логов"
log_info "Очистка логов Laravel..."

# Очистка основного лога
if [ -f "storage/logs/laravel.log" ]; then
    > storage/logs/laravel.log
    log_success "Лог laravel.log очищен"
fi

# Очистка всех логов в директории
find storage/logs -name "*.log" -type f -exec truncate -s 0 {} \; 2>/dev/null || true
log_success "Все логи очищены"

# Шаг 11: Установка прав доступа
log_step "Шаг 11: Установка прав доступа"
log_info "Установка прав для storage и bootstrap/cache..."

# Для Beget обычно используются права 755 для директорий и 644 для файлов
chmod -R 755 storage bootstrap/cache 2>/dev/null || true
chmod -R 644 storage/logs/*.log 2>/dev/null || true

log_success "Права доступа установлены"

# Шаг 12: Оптимизация для продакшена
log_step "Шаг 12: Финальная оптимизация"
log_info "Оптимизация для продакшена..."

php artisan optimize
log_success "Оптимизация завершена"

# Завершение
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

log_step "Развертывание завершено успешно!"
log_success "Время выполнения: ${DURATION} секунд"
log_info "Проект готов к работе!"

echo -e "\n${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Развертывание завершено успешно!   ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}\n"
