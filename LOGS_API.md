# API для просмотра логов

## Роуты

### 1. Просмотр последних строк лога

```
GET /logs?token=ВАШ_ТОКЕН&lines=100
```

**Параметры:**
- `token` (опционально) - токен доступа, если установлен `DEPLOY_TOKEN` в `.env`
- `lines` (опционально) - количество последних строк (по умолчанию 100, максимум 1000)

**Пример:**
```bash
curl "http://avito.siteaccess.ru/logs?token=ваш_токен&lines=50"
```

**Ответ:**
```json
{
  "success": true,
  "total_lines": 1500,
  "showing_lines": 50,
  "log_file": "/path/to/storage/logs/laravel.log",
  "file_size": 1024000,
  "last_modified": "2025-11-25 13:15:26",
  "content": "..."
}
```

### 2. Список всех логов

```
GET /logs/list?token=ВАШ_ТОКЕН
```

**Пример:**
```bash
curl "http://avito.siteaccess.ru/logs/list?token=ваш_токен"
```

**Ответ:**
```json
{
  "success": true,
  "logs_dir": "/path/to/storage/logs",
  "files": [
    {
      "name": "laravel.log",
      "size": 1024000,
      "modified": "2025-11-25 13:15:26",
      "path": "/path/to/storage/logs/laravel.log"
    }
  ]
}
```

### 3. Очистка логов

```
POST /logs/clear?token=ВАШ_ТОКЕН
```

**Пример:**
```bash
curl -X POST "http://avito.siteaccess.ru/logs/clear?token=ваш_токен"
```

**Ответ:**
```json
{
  "success": true,
  "message": "Логи успешно очищены"
}
```

## Безопасность

Для защиты роутов добавьте в `.env` на сервере:

```env
DEPLOY_TOKEN=ваш_секретный_токен
```

Токен будет проверяться при каждом запросе.

## Использование в браузере

Можно открыть в браузере:
```
http://avito.siteaccess.ru/logs?token=ваш_токен&lines=100
```

Или без токена, если `DEPLOY_TOKEN` не установлен:
```
http://avito.siteaccess.ru/logs?lines=100
```
