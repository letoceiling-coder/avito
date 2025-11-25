# Быстрая настройка сервера (новое развертывание)

## ⚡ Быстрая настройка (скопируйте и выполните)

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Обновить код
git pull origin master

# 2. Добавить секретный ключ в .env (ВАЖНО!)
echo "DEPLOY_SECRET=ваш_секретный_ключ" >> .env
# Или отредактируйте вручную: nano .env

# 3. Убедиться, что composer доступен
if [ ! -f ./composer.phar ]; then
    cp ~/composer.phar ./composer.phar 2>/dev/null || curl -sS https://getcomposer.org/installer | php && mv composer.phar ./composer.phar
    chmod +x ./composer.phar
fi

# 4. Создать директории
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 755 storage bootstrap/cache

# 5. Очистить и пересоздать кеши
php8.2 artisan config:clear && php8.2 artisan cache:clear && php8.2 artisan route:clear && php8.2 artisan view:clear
php8.2 artisan config:cache && php8.2 artisan route:cache && php8.2 artisan view:cache

# 6. Проверить
php8.2 artisan list | grep deploy
php8.2 artisan route:list | grep deploy

echo "✅ Готово!"
```

## 🔑 Секретный ключ

**ОБЯЗАТЕЛЬНО** установите `DEPLOY_SECRET` в `.env` на сервере!

Используйте тот же ключ, что и в локальном `.env`:

```bash
# Локально в .env
DEPLOY_SECRET=мой_секретный_ключ_12345

# На сервере в .env (тот же ключ!)
DEPLOY_SECRET=мой_секретный_ключ_12345
```

## ✅ Проверка

После настройки выполните локально:

```bash
php artisan set-deploy --message="Тест" --secret=ваш_секретный_ключ
```

## 📝 Что изменилось

- ✅ Новая реализация на основе UR проекта
- ✅ Роут изменился: `/api/deploy` вместо `/deploy`
- ✅ Секретный ключ обязателен
- ✅ Улучшенная обработка Git операций
- ✅ Автоматический поиск Composer и PHP версии
