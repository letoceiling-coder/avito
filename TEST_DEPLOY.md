# Тестирование развертывания

## Проверка настройки сервера

### 1. Git репозиторий ✅
```bash
cd ~/avito.siteaccess.ru/public_html
git remote -v
# Должно показать: origin  https://github.com/letoceiling-coder/avito.git
```

### 2. Composer ✅
```bash
php ~/composer.phar --version
# Должно показать версию Composer
```

### 3. Проверка git pull
```bash
cd ~/avito.siteaccess.ru/public_html
git fetch origin
git status
```

## Тестирование развертывания

### Локально выполните:

```bash
php artisan set-deploy --message="Тестовое развертывание после настройки"
```

### Что должно произойти:

1. ✅ Код отправится в git
2. ✅ POST запрос отправится на сервер
3. ✅ Сервер обновит код из git (теперь должен работать!)
4. ✅ Установит зависимости через composer (теперь должен найти!)
5. ✅ Выполнит миграции
6. ✅ Очистит и пересоздаст кеши
7. ✅ Установит права доступа

## Ожидаемый результат

В выводе развертывания вы должны увидеть:

```
Шаг 1: Обновление кода из git...
Git репозиторий найден, выполняется обновление...
Код успешно обновлен из ветки master

Шаг 2: Установка PHP зависимостей...
Используется composer: php /home/d/dsc23ytp/composer.phar
PHP зависимости установлены успешно

Шаг 9: Установка прав доступа...
Текущая директория: /home/d/dsc23ytp/avito.siteaccess.ru/public_html
Права для storage установлены
Права для bootstrap/cache установлены
```

## Если что-то не работает

### Проблема: Git не обновляется

```bash
# Проверьте подключение к репозиторию
cd ~/avito.siteaccess.ru/public_html
git fetch origin
git pull origin master
```

### Проблема: Composer не найден

```bash
# Проверьте, что composer установлен
ls -la ~/composer.phar

# Если нет, установите снова
cd ~
curl -sS https://getcomposer.org/installer | php
```

### Проблема: Директории не найдены

```bash
# Проверьте структуру проекта
cd ~/avito.siteaccess.ru/public_html
ls -la
ls -la storage
ls -la bootstrap/cache
```

## Успешное развертывание

После успешного развертывания вы увидите:

```
=== Развертывание завершено успешно! ===
```

И в логах сервера:
```
[INFO] Deploy completed successfully
```
