# Настройка сервера для развертывания

## Проблемы и решения

### 1. Git репозиторий не найден

**Проблема:**
```
fatal: не найден git репозиторий
```

**Решение:**

#### Вариант A: Инициализация git и подключение к удаленному репозиторию

```bash
cd ~/avito.siteaccess.ru/public_html

# Инициализируйте git репозиторий
git init

# Добавьте удаленный репозиторий
git remote add origin https://github.com/letoceiling-coder/avito.git

# Получите код из репозитория
git pull origin master --allow-unrelated-histories

# Или если ветка называется main
git pull origin main --allow-unrelated-histories
```

#### Вариант B: Клонирование репозитория заново

```bash
# Создайте резервную копию текущего проекта
cd ~
cp -r avito.siteaccess.ru/public_html avito.siteaccess.ru/public_html.backup

# Удалите старую директорию
rm -rf avito.siteaccess.ru/public_html

# Клонируйте репозиторий
git clone https://github.com/letoceiling-coder/avito.git avito.siteaccess.ru/public_html

# Скопируйте .env файл из резервной копии
cp avito.siteaccess.ru/public_html.backup/.env avito.siteaccess.ru/public_html/.env
```

### 2. Ошибка подключения к базе данных

**Проблема:**
```
Access denied for user 'root'@'localhost' (using password: NO)
```

**Решение:**

1. Проверьте и исправьте настройки БД в `.env`:

```bash
cd ~/avito.siteaccess.ru/public_html
nano .env
```

2. Убедитесь, что указаны правильные данные:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=имя_вашей_базы
DB_USERNAME=ваш_пользователь
DB_PASSWORD=ваш_пароль
```

3. Для Beget обычно используются данные из панели управления:
   - Логин БД: обычно начинается с префикса вашего аккаунта
   - Пароль: пароль от БД (не от аккаунта)
   - Хост: обычно `localhost` или `127.0.0.1`
   - База данных: имя базы из панели Beget

4. После изменения `.env` очистите кеш:

```bash
php8.2 artisan config:clear
php8.2 artisan cache:clear
```

### 3. Проверка подключения к БД

```bash
# Проверьте подключение
php8.2 artisan tinker
# В tinker выполните:
DB::connection()->getPdo();
# Если ошибки нет - подключение работает
```

## Полная настройка сервера

### Шаг 1: Настройка git

```bash
cd ~/avito.siteaccess.ru/public_html

# Если git не инициализирован
if [ ! -d ".git" ]; then
    git init
    git remote add origin https://github.com/letoceiling-coder/avito.git
    git pull origin master --allow-unrelated-histories
fi
```

### Шаг 2: Настройка .env

```bash
# Скопируйте пример, если .env не существует
if [ ! -f ".env" ]; then
    cp .env.example .env
    php8.2 artisan key:generate
fi

# Отредактируйте .env
nano .env
```

### Шаг 3: Установка зависимостей

```bash
# PHP зависимости
composer install --no-dev --optimize-autoloader --no-interaction

# Node.js зависимости (если доступен npm)
npm install
npm run build
```

### Шаг 4: Настройка базы данных

```bash
# Выполните миграции
php8.2 artisan migrate --force

# Заполните начальные данные
php8.2 artisan db:seed
```

### Шаг 5: Настройка прав доступа

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true
```

### Шаг 6: Очистка и кеширование

```bash
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan optimize
```

## Проверка работы

После настройки проверьте:

```bash
# Проверка git
git status

# Проверка подключения к БД
php8.2 artisan migrate:status

# Проверка роутов
php8.2 artisan route:list | grep deploy
```

## Автоматическое развертывание

После настройки команда `set-deploy` должна работать:

```bash
# Локально
php artisan set-deploy --message="Тестовое развертывание"
```

Контроллер теперь корректно обрабатывает ситуацию, когда git репозиторий не инициализирован - он просто пропустит обновление из git и продолжит с остальными шагами.
