# ⚠️ Требуется обновление кода на сервере

## Проблема

Ошибка **HTTP 405 (Method Not Allowed)** означает, что на сервере еще старый код, который не поддерживает новый роут `/api/deploy`.

## 🔧 Решение: Обновить код на сервере

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Обновить код из Git
git pull origin master

# 2. Установить секретный ключ (ОБЯЗАТЕЛЬНО!)
echo "DEPLOY_SECRET=123123123" >> .env
# Или отредактируйте: nano .env

# 3. Очистить кеши
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# 4. Пересоздать кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache

# 5. Проверить роуты
php8.2 artisan route:list | grep deploy

# Должны увидеть:
# POST   api/deploy
# GET    api/deploy/status
```

## ✅ После обновления

После обновления кода на сервере выполните локально:

```bash
php artisan set-deploy --message="Тест после обновления" --secret=123123123
```

## 📝 Временное решение

Пока код не обновлен на сервере, команда автоматически попробует оба endpoint:
- `/api/deploy` (новый)
- `/deploy` (старый, для обратной совместимости)

Но для полной функциональности нужно обновить код на сервере!
