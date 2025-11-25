# Инструкция по развертыванию на сервере Beget

## Быстрый старт

### Вариант 1: Полный скрипт развертывания (рекомендуется)

```bash
chmod +x deploy.sh
./deploy.sh
```

### Вариант 2: Упрощенный скрипт для Beget

```bash
chmod +x deploy-beget.sh
./deploy-beget.sh
```

## Что делает скрипт развертывания

1. **Обновление кода** - `git pull` из репозитория
2. **Установка PHP зависимостей** - `composer install` (без dev-зависимостей)
3. **Установка Node.js зависимостей** - `npm install`
4. **Сборка фронтенда** - `npm run build`
5. **Проверка конфигурации** - проверка и создание `.env` файла
6. **Генерация ключа** - создание `APP_KEY` если нужно
7. **Миграции базы данных** - `php artisan migrate --force`
8. **Очистка кешей** - очистка всех кешей Laravel
9. **Оптимизация** - кеширование конфигурации, маршрутов, представлений
10. **Очистка логов** - удаление старых логов
11. **Установка прав** - настройка прав доступа для storage и cache

## Настройка на сервере Beget

### 1. Загрузка проекта на сервер

```bash
# Через SSH подключитесь к серверу
ssh ваш_логин@ваш_сервер.beget.tech

# Перейдите в директорию проекта
cd ~/ваш_домен/public_html

# Клонируйте репозиторий (если еще не клонирован)
git clone https://github.com/letoceiling-coder/avito.git .
```

### 2. Настройка .env файла

```bash
# Скопируйте пример конфигурации
cp .env.example .env

# Отредактируйте .env файл
nano .env
```

Обязательно настройте:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=mysql`
- `DB_HOST=localhost` (или ваш хост БД от Beget)
- `DB_DATABASE=ваша_база`
- `DB_USERNAME=ваш_пользователь`
- `DB_PASSWORD=ваш_пароль`

### 3. Установка Composer на Beget

Beget обычно имеет Composer, но если нет:

```bash
# Скачайте Composer локально
cd ~
curl -sS https://getcomposer.org/installer | php

# Используйте как: php ~/composer.phar
```

### 4. Настройка Node.js на Beget

Если Node.js не установлен, используйте nvm:

```bash
# Установка nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc

# Установка Node.js
nvm install 18
nvm use 18
```

### 5. Первый запуск развертывания

```bash
# Сделайте скрипт исполняемым
chmod +x deploy.sh

# Запустите развертывание
./deploy.sh
```

## Автоматическое развертывание через Cron

Для автоматического обновления можно настроить cron:

```bash
# Откройте crontab
crontab -e

# Добавьте строку для ежедневного обновления в 3:00 ночи
0 3 * * * cd ~/ваш_домен/public_html && ./deploy.sh >> deploy.log 2>&1
```

## Ручное развертывание через SSH

```bash
# Подключитесь к серверу
ssh ваш_логин@ваш_сервер.beget.tech

# Перейдите в директорию проекта
cd ~/ваш_домен/public_html

# Запустите скрипт
./deploy.sh
```

## Устранение проблем

### Ошибка: "Composer не найден"
```bash
# Используйте полный путь или установите глобально
php ~/composer.phar install --no-dev --optimize-autoloader
```

### Ошибка: "npm не найден"
```bash
# Установите Node.js через nvm или используйте версию без npm сборки
# В этом случае фронтенд нужно собирать локально и загружать на сервер
```

### Ошибка прав доступа
```bash
# Установите права вручную
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log
```

### Ошибка миграций
```bash
# Проверьте подключение к БД в .env
# Выполните миграции вручную
php artisan migrate --force
```

## Полезные команды Laravel для продакшена

```bash
# Очистка всех кешей
php artisan optimize:clear

# Оптимизация для продакшена
php artisan optimize

# Просмотр логов
tail -f storage/logs/laravel.log

# Проверка конфигурации
php artisan config:show
```

## Безопасность

⚠️ **Важно для продакшена:**

1. Убедитесь, что `APP_DEBUG=false` в `.env`
2. Не коммитьте `.env` файл в git
3. Используйте сильные пароли для БД
4. Регулярно обновляйте зависимости
5. Делайте резервные копии БД перед обновлением

## Контакты и поддержка

При возникновении проблем проверьте:
- Логи: `storage/logs/laravel.log`
- Логи развертывания: `deploy.log` (если настроен cron)
- Документацию Beget: https://beget.com/ru/kb
