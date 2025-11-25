# Устранение ошибки 500 при развертывании

## Проблема

При выполнении `php artisan set-deploy` возникает ошибка 500 на сервере.

## Возможные причины и решения

### 1. Код не обновлен на сервере

**Симптомы:** Контроллер `DeployController` не найден или старая версия кода.

**Решение:**
```bash
# На сервере
cd ~/avito.siteaccess.ru/public_html
git pull origin master
php artisan config:clear
php artisan cache:clear
```

### 2. Ошибка в выполнении команд

**Симптомы:** Ошибка при выполнении git, composer или npm команд.

**Решение:**
- Проверьте права доступа к директории проекта
- Убедитесь, что git, composer и npm доступны
- Проверьте логи: `tail -f storage/logs/laravel.log`

### 3. Проблемы с правами доступа

**Решение:**
```bash
# Установите правильные права
chmod -R 755 storage bootstrap/cache
chown -R ваш_пользователь:ваша_группа storage bootstrap/cache
```

### 4. Ошибка в PHP коде

**Решение:**
- Включите режим отладки в `.env` на сервере (временно):
  ```env
  APP_DEBUG=true
  ```
- Проверьте логи: `storage/logs/laravel.log`
- После исправления верните `APP_DEBUG=false`

### 5. Проблемы с зависимостями

**Решение:**
```bash
# Переустановите зависимости
composer install --no-dev --optimize-autoloader
npm install
```

## Диагностика

### Проверка логов на сервере

```bash
# Просмотр последних ошибок
tail -n 100 storage/logs/laravel.log | grep -A 20 "Deploy failed"

# Или все ошибки
tail -f storage/logs/laravel.log
```

### Ручное тестирование роута

```bash
# На сервере проверьте, что роут доступен
curl -X POST http://avito.siteaccess.ru/deploy \
  -H "Content-Type: application/json" \
  -d '{"timestamp":"test","commit_message":"test"}'
```

### Проверка конфигурации

```bash
# Проверьте, что роут зарегистрирован
php artisan route:list | grep deploy

# Проверьте конфигурацию
php artisan config:show app
```

## Улучшенная обработка ошибок

Контроллер теперь возвращает более детальную информацию об ошибках:
- В режиме отладки (`APP_DEBUG=true`) - полная информация
- В продакшене - общее сообщение без деталей

## Следующие шаги

1. Обновите код на сервере через `git pull`
2. Очистите кеши: `php artisan config:clear && php artisan cache:clear`
3. Проверьте логи на сервере
4. Попробуйте команду `set-deploy` снова

Если проблема сохраняется, проверьте логи на сервере для получения детальной информации об ошибке.
