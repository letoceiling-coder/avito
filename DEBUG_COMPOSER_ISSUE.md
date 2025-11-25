# Диагностика проблемы с Composer

## Проблема

Composer установлен в `/home/d/dsc23ytp/composer.phar`, но при выполнении команды выдает ошибку:
```
Could not open input file: /home/d/dsc23ytp/composer.phar
```

## Возможные причины

1. PHP запускается от другого пользователя (возможно root)
2. Проблема с правами доступа
3. Путь неправильный

## Проверка на сервере

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте, что файл существует
ls -la ~/composer.phar
ls -la /home/d/dsc23ytp/composer.phar

# 2. Проверьте права доступа
stat ~/composer.phar

# 3. Проверьте, от какого пользователя запускается PHP
php8.2 -r "echo get_current_user() . PHP_EOL;"
php8.2 -r "echo posix_geteuid() . PHP_EOL;"

# 4. Попробуйте выполнить composer напрямую
php8.2 ~/composer.phar --version

# 5. Проверьте, может ли PHP прочитать файл
php8.2 -r "echo file_exists('/home/d/dsc23ytp/composer.phar') ? 'существует' : 'не существует' . PHP_EOL;"
php8.2 -r "echo is_readable('/home/d/dsc23ytp/composer.phar') ? 'читаемый' : 'не читаемый' . PHP_EOL;"
```

## Решение

Если PHP запускается от root, а composer.phar принадлежит dsc23ytp:

```bash
# Вариант 1: Установите composer для root
sudo curl -sS https://getcomposer.org/installer | sudo php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Вариант 2: Сделайте composer.phar доступным для всех
chmod 644 ~/composer.phar
chmod 755 ~
```

## Альтернатива: Использовать глобальный composer

Если composer установлен глобально:

```bash
# Проверьте, есть ли глобальный composer
which composer
composer --version

# Если есть, обновите .env, чтобы использовать его
# Но лучше оставить HOME=/home/d/dsc23ytp и установить composer правильно
```
