# Руководство по миграции на новую систему развертывания

## ✅ Что было сделано

### Переписаны команды и контроллеры:

1. **`app/Console/Commands/SetDeploy.php`** - полностью переписан на основе `PushToServer.php` из UR проекта
   - Использует `SymfonyProcess` для всех Git операций
   - Обработка конфликтов (stash, rebase, fallback)
   - Детальная обработка ошибок
   - Множество опций (`--skip-commit`, `--skip-push`, `--skip-pull`, `--force`, `--no-ssl-verify`)
   - Отправка на `/api/deploy` вместо `/deploy`

2. **`app/Console/Commands/Deploy.php`** - новая команда для сервера (на основе `Deploy.php` из UR проекта)
   - Progress bar
   - Автоматический stash перед pull
   - Rebase с fallback
   - Детектирование PHP версии (php8.2)
   - Улучшенный поиск Composer
   - Поддержка NVM для NPM
   - Опции для пропуска шагов

3. **`app/Http/Controllers/DeployController.php`** - переписан на основе UR проекта
   - Проверка секретного ключа (обязательно)
   - Запуск команды `deploy` через Artisan
   - Поддержка фонового выполнения
   - Возврат статуса обновления

4. **`routes/api.php`** - добавлен роут `/api/deploy`
   - Защита через throttle middleware
   - Роут `/api/deploy/status` для проверки статуса

## 🔧 Что нужно выполнить на сервере

### 1. Обновить код из Git

```bash
cd ~/avito.siteaccess.ru/public_html
git pull origin master
```

### 2. Установить секретный ключ в .env

```bash
cd ~/avito.siteaccess.ru/public_html

# Откройте .env
nano .env

# Добавьте строку (используйте тот же ключ, что и локально):
DEPLOY_SECRET=ваш_секретный_ключ_здесь

# Сохраните (Ctrl+O, Enter, Ctrl+X)
```

**⚠️ ВАЖНО:** Секретный ключ обязателен! Без него развертывание не будет работать.

### 3. Убедиться, что Git настроен

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте remote
git remote -v

# Если не настроен:
git remote add origin https://github.com/letoceiling-coder/avito.git
```

### 4. Убедиться, что Composer доступен

```bash
# Вариант 1: В домашней директории
ls -la ~/composer.phar
# Если нет:
cd ~ && curl -sS https://getcomposer.org/installer | php && chmod +x ~/composer.phar

# Вариант 2: В проекте (рекомендуется)
cd ~/avito.siteaccess.ru/public_html
cp ~/composer.phar ./composer.phar
chmod +x ./composer.phar
```

### 5. Создать директории (если их нет)

```bash
cd ~/avito.siteaccess.ru/public_html
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 755 storage bootstrap/cache
```

### 6. Очистить и пересоздать кеши

```bash
cd ~/avito.siteaccess.ru/public_html
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
```

### 7. Проверить команду deploy

```bash
cd ~/avito.siteaccess.ru/public_html
php8.2 artisan list | grep deploy
# Должно показать: deploy
```

## 🚀 Использование

### Локально:

```bash
# Базовое использование
php artisan set-deploy --message="Описание изменений"

# С секретным ключом
php artisan set-deploy --message="Изменения" --secret=ваш_ключ

# С кастомным сервером
php artisan set-deploy --server=https://example.com --secret=ключ

# Пропустить pull
php artisan set-deploy --skip-pull

# Принудительная отправка (опасно!)
php artisan set-deploy --force

# Отключить проверку SSL (только для разработки!)
php artisan set-deploy --no-ssl-verify
```

### На сервере (вручную):

```bash
cd ~/avito.siteaccess.ru/public_html
php8.2 artisan deploy
```

## 📊 Основные изменения

### Преимущества новой реализации:

1. ✅ **Надежность** - использование `SymfonyProcess` вместо `exec()`
2. ✅ **Обработка конфликтов** - автоматический stash, rebase с fallback
3. ✅ **Детектирование зависимостей** - автоматический поиск PHP версии и Composer
4. ✅ **Поддержка NVM** - для работы с Node.js через NVM
5. ✅ **Безопасность** - обязательный секретный ключ
6. ✅ **Гибкость** - множество опций для настройки
7. ✅ **Удобство** - progress bar, детальные сообщения, подсказки
8. ✅ **Обработка ошибок** - детальные сообщения с подсказками по исправлению

### Изменения в API:

- **Старый роут:** `POST /deploy`
- **Новый роут:** `POST /api/deploy`
- **Защита:** Обязательный секретный ключ (DEPLOY_SECRET)
- **Throttle:** 10 запросов в минуту

## ⚠️ Важные замечания

1. **Секретный ключ обязателен** - без него развертывание не будет работать
2. **Роут изменился** - теперь `/api/deploy` вместо `/deploy`
3. **Новая команда** - на сервере используется команда `deploy` вместо выполнения через контроллер
4. **Улучшенная обработка** - все операции выполняются через `SymfonyProcess`

## 🔍 Проверка после настройки

```bash
# На сервере
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте команду
php8.2 artisan deploy --help

# 2. Проверьте роут
php8.2 artisan route:list | grep deploy

# 3. Проверьте секретный ключ
grep DEPLOY_SECRET .env

# 4. Проверьте Composer
php8.2 ./composer.phar --version
```

## ✅ Готово!

После выполнения всех шагов на сервере система будет готова к использованию!
