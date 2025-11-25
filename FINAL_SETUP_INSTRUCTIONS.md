# Финальная инструкция по настройке

## ⚠️ Текущая ситуация

На сервере еще старый код, поэтому новый роут `/api/deploy` возвращает ошибку 405.

## 🔧 Что нужно сделать на сервере СЕЙЧАС

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Обновить код из Git
git pull origin master

# 2. Установить секретный ключ (ОБЯЗАТЕЛЬНО!)
echo "DEPLOY_SECRET=123123123" >> .env
# Или отредактируйте вручную: nano .env

# 3. Очистить кеши
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# 4. Пересоздать кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache

# 5. Проверить, что команда deploy доступна
php8.2 artisan list | grep deploy

# 6. Проверить роуты
php8.2 artisan route:list | grep deploy
# Должны увидеть:
# POST   api/deploy
# GET    api/deploy/status
```

## ✅ После обновления

После выполнения команд на сервере выполните локально:

```bash
php artisan set-deploy --message="Тест после обновления" --secret=123123123
```

## 📝 Временная обратная совместимость

Пока код не обновлен на сервере:
- Команда автоматически пробует `/api/deploy` (новый)
- Если не работает, пробует `/deploy` (старый)
- Но для полной функциональности нужно обновить код!

## 🔑 Секретный ключ

**Важно:** Используйте тот же секретный ключ локально и на сервере:

```bash
# Локально в .env
DEPLOY_SECRET=123123123

# На сервере в .env (тот же!)
DEPLOY_SECRET=123123123
```

## ✅ Готово!

После обновления кода на сервере все будет работать!
