# Инструкция по настройке сервера для нового развертывания

## 📋 Что нужно выполнить на сервере

### 1. Обновить код из Git

```bash
cd ~/avito.siteaccess.ru/public_html

# Получите последний код
git pull origin master

# Если есть конфликты, разрешите их вручную
```

### 2. Установить секретный ключ в .env

```bash
cd ~/avito.siteaccess.ru/public_html

# Откройте .env файл
nano .env

# Добавьте или обновите строку:
DEPLOY_SECRET=ваш_секретный_ключ_здесь

# Сохраните файл (Ctrl+O, Enter, Ctrl+X)
```

**Важно:** Используйте тот же секретный ключ, что и в локальном `.env` файле!

### 3. Убедиться, что Git репозиторий настроен

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте remote
git remote -v

# Если remote не настроен:
git remote add origin https://github.com/letoceiling-coder/avito.git

# Или обновите URL:
git remote set-url origin https://github.com/letoceiling-coder/avito.git
```

### 4. Убедиться, что Composer доступен

```bash
# Проверьте, что composer.phar существует
ls -la ~/composer.phar

# Если нет, установите:
cd ~
curl -sS https://getcomposer.org/installer | php
chmod +x ~/composer.phar

# Или скопируйте в проект:
cp ~/composer.phar ~/avito.siteaccess.ru/public_html/composer.phar
chmod +x ~/avito.siteaccess.ru/public_html/composer.phar
```

### 5. Убедиться, что директории существуют

```bash
cd ~/avito.siteaccess.ru/public_html

# Создайте директории если их нет
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Установите права
chmod -R 755 storage bootstrap/cache
```

### 6. Очистить и пересоздать кеши

```bash
cd ~/avito.siteaccess.ru/public_html

# Очистите кеши
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan route:clear
php8.2 artisan view:clear

# Пересоздайте кеши
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
```

### 7. Проверить, что команда deploy доступна

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте, что команда зарегистрирована
php8.2 artisan list | grep deploy

# Должны увидеть:
# deploy              Обновить проект из Git репозитория на сервере
```

### 8. Проверить роуты

```bash
cd ~/avito.siteaccess.ru/public_html

# Проверьте, что роут /api/deploy зарегистрирован
php8.2 artisan route:list | grep deploy

# Должны увидеть:
# POST   api/deploy
```

## ✅ Проверка готовности

После выполнения всех шагов проверьте:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Git
git status
git remote -v

# 2. Composer
php8.2 ~/composer.phar --version
# Или если в проекте:
php8.2 ./composer.phar --version

# 3. Секретный ключ
grep DEPLOY_SECRET .env

# 4. Команда deploy
php8.2 artisan deploy --help

# 5. Роуты
php8.2 artisan route:list | grep deploy
```

## 🚀 Тестирование

После настройки выполните локально:

```bash
php artisan set-deploy --message="Тест нового развертывания" --secret=ваш_секретный_ключ
```

## 📝 Дополнительные настройки (опционально)

### Настройка NVM (если используется)

```bash
# Если используете NVM для Node.js
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm use default
```

### Настройка PHP версии

Команда автоматически найдет `php8.2`, но если нужно использовать другую версию:

```bash
# Проверьте доступные версии PHP
which php8.2
which php8.1
which php

# Команда автоматически найдет правильную версию
```

## ⚠️ Важно

1. **Секретный ключ** должен совпадать на локальной машине и на сервере
2. **Git репозиторий** должен быть правильно настроен
3. **Composer** должен быть доступен (в проекте или в домашней директории)
4. **Директории** storage и bootstrap/cache должны существовать
5. **Кеши** должны быть очищены после обновления кода

## 🔒 Безопасность

- Секретный ключ должен быть сложным и уникальным
- Не храните секретный ключ в публичных репозиториях
- Используйте HTTPS для запросов к серверу
- Роут защищен throttle middleware (10 запросов в минуту)

## 📊 Мониторинг

После развертывания проверьте логи:

```bash
# Логи Laravel
tail -f storage/logs/laravel.log

# Логи развертывания (если запускалось в фоне)
ls -la storage/logs/deploy_*.log
tail -f storage/logs/deploy_*.log
```

## ✅ Готово!

После выполнения всех шагов система развертывания будет готова к использованию!
