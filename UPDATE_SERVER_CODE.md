# Обновление кода на сервере

## ⚠️ ВАЖНО: Код на сервере нужно обновить из git!

Проблема: На сервере еще старая версия кода, поэтому новые исправления не работают.

## 🔧 Решение: Обновите код на сервере вручную

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте текущее состояние git
git status

# 2. Если есть изменения, сохраните их или откатите
git reset --hard HEAD

# 3. Получите последний код из репозитория
git fetch origin
git pull origin master

# Если будет конфликт, используйте:
git pull origin master --allow-unrelated-histories

# 4. Очистите кеши после обновления
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# 5. Пересоздайте кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
```

## 🔄 Альтернатива: Клонирование заново

Если git pull не работает, можно клонировать заново:

```bash
cd ~
# Создайте резервную копию .env
cp avito.siteaccess.ru/public_html/.env avito.siteaccess.ru/.env.backup

# Удалите старую директорию
rm -rf avito.siteaccess.ru/public_html

# Клонируйте заново
git clone https://github.com/letoceiling-coder/avito.git avito.siteaccess.ru/public_html

# Восстановите .env
cp avito.siteaccess.ru/.env.backup avito.siteaccess.ru/public_html/.env

# Установите зависимости
cd avito.siteaccess.ru/public_html
php ~/composer.phar install --no-interaction --prefer-dist --optimize-autoloader
```

## ✅ После обновления кода

После обновления кода на сервере выполните локально:

```bash
php artisan set-deploy --message="Тест после обновления кода на сервере"
```

Теперь должны работать:
- ✅ Детальная диагностика git
- ✅ Правильный поиск composer (с HOME из .env)
- ✅ Правильная проверка директорий
