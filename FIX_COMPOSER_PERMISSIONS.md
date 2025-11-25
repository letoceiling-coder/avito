# Исправление прав доступа на composer.phar

## Проблема

В логах видно:
- `file_exists(): да` - файл существует
- `is_readable(): нет` - файл НЕ читаемый!
- `Права доступа: 0644`

PHP через веб-сервер не может прочитать файл из-за прав доступа.

## Решение

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте текущие права
ls -la ~/composer.phar

# 2. Установите правильные права (читаемый и исполняемый для всех)
chmod 755 ~/composer.phar

# 3. Проверьте права снова
ls -la ~/composer.phar

# 4. Проверьте, что файл теперь читаемый через PHP
php8.2 -r "echo is_readable('/home/d/dsc23ytp/composer.phar') ? 'читаемый' : 'не читаемый' . PHP_EOL;"

# 5. Попробуйте выполнить composer
php8.2 ~/composer.phar --version
```

## Альтернативное решение

Если права не помогают, можно скопировать composer.phar в проект:

```bash
cd ~/avito.siteaccess.ru/public_html
cp ~/composer.phar ./composer.phar
chmod 755 ./composer.phar
```

И обновить .env:
```
COMPOSER_PATH=/home/d/dsc23ytp/avito.siteaccess.ru/public_html/composer.phar
```

Но лучше просто исправить права на ~/composer.phar.
