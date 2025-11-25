# Срочные действия на сервере

## ⚠️ Проблема: HTTP 405

На сервере еще старый код, поэтому новый роут `/api/deploy` не работает.

## 🔧 Быстрое решение

Выполните на сервере **прямо сейчас**:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Обновить код
git pull origin master

# 2. Добавить секретный ключ
echo "DEPLOY_SECRET=123123123" >> .env

# 3. Очистить кеши
php8.2 artisan config:clear && php8.2 artisan cache:clear && php8.2 artisan route:clear

# 4. Пересоздать кеши
php8.2 artisan config:cache && php8.2 artisan route:cache

# 5. Проверить
php8.2 artisan route:list | grep deploy
```

## ✅ После выполнения

После выполнения команд на сервере выполните локально:

```bash
php artisan set-deploy --message="Тест" --secret=123123123
```

## 📝 Примечание

Команда автоматически пробует оба endpoint:
- `/api/deploy` (новый)
- `/deploy` (старый, для обратной совместимости)

Но для полной функциональности нужно обновить код на сервере!
