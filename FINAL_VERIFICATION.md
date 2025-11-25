# Финальная проверка на сервере

## ✅ Что уже сделано

1. ✅ Git репозиторий инициализирован
2. ✅ HOME добавлен в .env
3. ✅ Директории созданы
4. ✅ Права установлены
5. ✅ Кеши очищаются

## 🔍 Финальная проверка

Выполните на сервере для проверки:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверка git
echo "=== Git ==="
git remote -v
git status --short | head -5
echo ""

# 2. Проверка HOME в .env
echo "=== HOME в .env ==="
grep "^HOME=" .env
echo ""

# 3. Проверка composer
echo "=== Composer ==="
ls -la ~/composer.phar
php ~/composer.phar --version
echo ""

# 4. Проверка директорий
echo "=== Директории ==="
ls -ld storage bootstrap/cache
echo ""

# 5. Очистка всех кешей
echo "=== Очистка кешей ==="
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
echo "✓ Кеши очищены и пересозданы"
```

## 🚀 Тестирование

После проверки выполните локально:

```bash
php artisan set-deploy --message="Тест после исправления проблем"
```

## 📊 Ожидаемый результат

В выводе развертывания должно быть:

```
Шаг 1: Обновление кода из git...
Текущая директория: /home/d/dsc23ytp/avito.siteaccess.ru/public_html
✓ Git репозиторий найден через проверку директории .git
✓ Код успешно обновлен из ветки master

Шаг 2: Установка PHP зависимостей...
HOME из .env: /home/d/dsc23ytp
Используется composer: php /home/d/dsc23ytp/composer.phar
PHP зависимости установлены успешно

Шаг 9: Установка прав доступа...
✓ Права для storage установлены
✓ Права для bootstrap/cache установлены
```

## ✅ Готово!

После выполнения всех проверок система должна работать полностью!
