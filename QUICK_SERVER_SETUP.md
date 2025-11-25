# Быстрая настройка сервера

## Выполните на сервере (скопируйте и вставьте):

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Добавьте HOME в .env
echo "HOME=/home/d/dsc23ytp" >> .env

# 2. Создайте недостающие директории
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# 3. Установите права
chmod -R 755 storage bootstrap/cache

# 4. Очистите кеши
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# 5. Пересоздайте кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache

# 6. Проверьте git
git status

# 7. Проверьте composer
php ~/composer.phar --version
```

## Готово!

После этого выполните локально:
```bash
php artisan set-deploy --message="Тест после настройки"
```

## Просмотр логов

После развертывания можно посмотреть логи:
```
http://avito.siteaccess.ru/logs?lines=100
```

Или через curl:
```bash
curl "http://avito.siteaccess.ru/logs?lines=50"
```
