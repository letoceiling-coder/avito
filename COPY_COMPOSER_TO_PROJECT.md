# Копирование composer.phar в проект

## Проблема

PHP через веб-сервер не может прочитать файл из домашней директории пользователя из-за ограничений доступа.

## Решение: Скопировать composer.phar в проект

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Скопируйте composer.phar в проект
cp ~/composer.phar ./composer.phar

# 2. Установите права
chmod 755 ./composer.phar

# 3. Проверьте
ls -la ./composer.phar
php8.2 ./composer.phar --version

# 4. Проверьте через PHP, что файл читаемый
php8.2 -r "echo is_readable('./composer.phar') ? 'читаемый' : 'не читаемый' . PHP_EOL;"
```

## Альтернатива: Использовать глобальный composer

Если composer установлен глобально:

```bash
# Проверьте
which composer
composer --version

# Если работает, код автоматически найдет его
```

## После копирования

После копирования composer.phar в проект выполните локально:

```bash
php artisan set-deploy --message="Тест после копирования composer в проект"
```

Код автоматически найдет composer.phar в корне проекта и использует его.
