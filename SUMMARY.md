# Итоговая сводка: Система развертывания и просмотра логов

## ✅ Что создано

### 1. Команда развертывания `set-deploy`
- **Файл:** `app/Console/Commands/SetDeploy.php`
- **Использование:** `php artisan set-deploy --message="Ваше сообщение"`
- **Функционал:**
  - Отправляет изменения в git
  - Отправляет POST запрос на сервер для автоматического развертывания
  - Показывает детальный вывод развертывания

### 2. API для просмотра логов
- **Контроллер:** `app/Http/Controllers/LogsController.php`
- **Роуты:**
  - `GET /logs` - просмотр последних строк лога
  - `GET /logs/list` - список всех логов
  - `POST /logs/clear` - очистка логов

### 3. Контроллер развертывания
- **Файл:** `app/Http/Controllers/DeployController.php`
- **Роут:** `POST /deploy`
- **Функционал:**
  - Обновление кода из git
  - Установка зависимостей (composer, npm)
  - Выполнение миграций
  - Очистка и пересоздание кешей
  - Установка прав доступа

## 📋 Инструкции для сервера

### Быстрая настройка (скопируйте и выполните):

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Добавьте HOME в .env
echo "HOME=/home/d/dsc23ytp" >> .env

# 2. Создайте недостающие директории
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# 3. Установите права
chmod -R 755 storage bootstrap/cache

# 4. Очистите и пересоздайте кеши
php8.2 artisan config:clear && php8.2 artisan cache:clear && php8.2 artisan route:clear && php8.2 artisan view:clear
php8.2 artisan config:cache && php8.2 artisan route:cache && php8.2 artisan view:cache
```

## 🔧 Использование

### Локально:

```bash
# Развертывание
php artisan set-deploy --message="Описание изменений"

# С указанием URL
php artisan set-deploy --message="Изменения" --url="http://avito.siteaccess.ru/deploy"
```

### Просмотр логов:

```bash
# В браузере
http://avito.siteaccess.ru/logs?lines=100

# Через curl
curl "http://avito.siteaccess.ru/logs?lines=50"

# С токеном (если установлен DEPLOY_TOKEN)
curl "http://avito.siteaccess.ru/logs?token=ваш_токен&lines=50"
```

## 🔒 Безопасность

Для защиты API добавьте в `.env` на сервере:

```env
DEPLOY_TOKEN=ваш_секретный_токен
```

Токен будет проверяться при запросах к `/deploy` и `/logs`.

## 📚 Документация

- `LOGS_API.md` - документация по API логов
- `SERVER_SETUP_INSTRUCTIONS.md` - подробные инструкции для сервера
- `QUICK_SERVER_SETUP.md` - быстрая настройка сервера
- `DEPLOY_COMMAND_README.md` - документация по команде развертывания

## ✅ После настройки сервера

1. Выполните команды из `QUICK_SERVER_SETUP.md`
2. Протестируйте развертывание:
   ```bash
   php artisan set-deploy --message="Тест после настройки"
   ```
3. Проверьте логи:
   ```
   http://avito.siteaccess.ru/logs?lines=50
   ```

## 🎯 Готово к использованию!

После выполнения инструкций на сервере система будет полностью работоспособна.
