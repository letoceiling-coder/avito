# Команда развертывания `set-deploy`

## Описание

Команда `php artisan set-deploy` автоматически:
1. Отправляет текущий проект в git (add, commit, push)
2. Отправляет POST запрос на сервер для автоматического развертывания

## Использование

### Базовое использование

```bash
php artisan set-deploy
```

Команда автоматически:
- Создаст коммит с сообщением "Deploy: YYYY-MM-DD HH:MM:SS"
- Отправит изменения в git
- Выполнит POST запрос на `http://avito.siteaccess.ru/deploy`

### С указанием сообщения коммита

```bash
php artisan set-deploy --message="Исправлена ошибка авторизации"
```

### С указанием URL для развертывания

```bash
php artisan set-deploy --url="http://avito.siteaccess.ru/deploy"
```

### Комбинированное использование

```bash
php artisan set-deploy --message="Обновление интерфейса" --url="http://avito.siteaccess.ru/deploy"
```

## Что происходит на сервере

При получении POST запроса на `/deploy`, сервер автоматически выполняет:

1. **Обновление кода** - `git pull origin master/main`
2. **Установка PHP зависимостей** - `composer install --no-dev --optimize-autoloader`
3. **Установка Node.js зависимостей** - `npm install` (если доступен)
4. **Сборка фронтенда** - `npm run build` (если доступен)
5. **Миграции базы данных** - `php artisan migrate --force`
6. **Очистка кешей** - очистка всех кешей Laravel
7. **Кеширование для продакшена** - кеширование конфигурации, маршрутов, представлений
8. **Очистка логов** - очистка `storage/logs/laravel.log`
9. **Установка прав доступа** - настройка прав для storage и cache

## Безопасность

### Защита роута /deploy

Для защиты роута `/deploy` от несанкционированного доступа, добавьте в `.env` файл на сервере:

```env
DEPLOY_TOKEN=ваш_секретный_токен_здесь
```

Затем при отправке POST запроса добавьте параметр `token`:

```bash
# В команде SetDeploy можно добавить отправку токена
# Или использовать middleware для проверки IP адреса
```

### Альтернативная защита через IP

Вы можете добавить middleware для проверки IP адреса в `DeployController`:

```php
// В конструкторе DeployController
public function __construct()
{
    $this->middleware(function ($request, $next) {
        $allowedIPs = explode(',', env('DEPLOY_ALLOWED_IPS', ''));
        if (!empty($allowedIPs) && !in_array($request->ip(), $allowedIPs)) {
            abort(403, 'Доступ запрещен');
        }
        return $next($request);
    });
}
```

И в `.env`:
```env
DEPLOY_ALLOWED_IPS=127.0.0.1,192.168.1.100
```

## Примеры использования

### Локальная разработка

```bash
# После внесения изменений
php artisan set-deploy --message="Добавлена новая функция"
```

### CI/CD интеграция

Можно использовать в GitHub Actions, GitLab CI и других системах:

```yaml
# .github/workflows/deploy.yml
- name: Deploy to server
  run: |
    php artisan set-deploy --message="Deploy from CI/CD"
```

## Ответ сервера

При успешном развертывании сервер вернет JSON:

```json
{
  "success": true,
  "message": "Развертывание выполнено успешно",
  "output": "=== Начало развертывания ===\n...",
  "timestamp": "2025-01-25 12:00:00"
}
```

При ошибке:

```json
{
  "success": false,
  "message": "Ошибка при развертывании: ...",
  "output": "...",
  "error": "Описание ошибки"
}
```

## Логирование

Все операции логируются в:
- `storage/logs/laravel.log` - стандартный лог Laravel
- Вывод команды `set-deploy` в консоли
- Ответ сервера в JSON формате

## Устранение проблем

### Ошибка: "Не найден git репозиторий"
Убедитесь, что вы находитесь в корне проекта с инициализированным git репозиторием.

### Ошибка: "Ошибка при отправке в git"
Проверьте:
- Настроен ли remote origin: `git remote -v`
- Есть ли права на push: `git push origin master`

### Ошибка: "Ошибка при отправке запроса на развертывание"
Проверьте:
- Доступность URL сервера
- Настройки firewall
- Логи на сервере: `storage/logs/laravel.log`

### Таймаут запроса
По умолчанию таймаут установлен на 5 минут (300 секунд). Если развертывание занимает больше времени, увеличьте таймаут в `SetDeploy.php`:

```php
$response = Http::timeout(600) // 10 минут
```

## Интеграция с другими системами

### Webhook от GitHub

Можно настроить webhook в GitHub, который будет вызывать `/deploy` при push в репозиторий.

### Cron задача

Для автоматического развертывания можно настроить cron:

```bash
# Каждый день в 3:00
0 3 * * * cd /path/to/project && php artisan set-deploy --message="Daily deploy"
```

## Дополнительные возможности

### Добавление уведомлений

Можно добавить отправку уведомлений (email, Telegram, Slack) после развертывания в `DeployController`.

### Откат изменений

Можно добавить функционал отката к предыдущей версии через git tags.

## Контакты

При возникновении проблем проверьте:
- Логи: `storage/logs/laravel.log`
- Статус git: `git status`
- Доступность сервера: `curl http://avito.siteaccess.ru/deploy`
