# Быстрая проверка на сервере

## Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверка git remote (если origin уже существует, это нормально)
git remote set-url origin https://github.com/letoceiling-coder/avito.git
git remote -v

# 2. Проверка HOME в .env
grep "^HOME=" .env

# 3. Финальная очистка кешей
php8.2 artisan config:clear && php8.2 artisan cache:clear && php8.2 artisan route:clear && php8.2 artisan view:clear
php8.2 artisan config:cache && php8.2 artisan route:cache && php8.2 artisan view:cache

echo "✓ Готово к тестированию!"
```

## 🚀 Тестирование

После этого выполните локально:

```bash
php artisan set-deploy --message="Тест после всех исправлений"
```

## ✅ Ожидаемый результат

Теперь должно работать:
- ✅ Git репозиторий найден и обновляется
- ✅ Composer найден и устанавливает зависимости
- ✅ Директории найдены и права установлены
