# Установка Composer на сервере Beget

## Проблема

При развертывании возникает ошибка:
```
Could not open input file: /root/composer.phar
```

## Решение

### Вариант 1: Установка Composer локально (рекомендуется для Beget)

```bash
cd ~
curl -sS https://getcomposer.org/installer | php

# Проверьте установку
php composer.phar --version
```

После установки composer будет доступен по пути: `php ~/composer.phar`

### Вариант 2: Установка Composer глобально

```bash
# Скачайте установщик
cd ~
curl -sS https://getcomposer.org/installer | php

# Переместите в глобальную директорию
sudo mv composer.phar /usr/local/bin/composer
# Или без sudo (если нет прав):
mkdir -p ~/bin
mv composer.phar ~/bin/composer
export PATH="$HOME/bin:$PATH"

# Сделайте исполняемым
chmod +x /usr/local/bin/composer
# Или:
chmod +x ~/bin/composer

# Проверьте
composer --version
```

### Вариант 3: Использование системного Composer (если доступен)

```bash
# Проверьте, может быть composer уже установлен
which composer
composer --version

# Если доступен, используйте его
```

## Проверка работы

После установки проверьте:

```bash
# Локальная установка
php ~/composer.phar --version

# Глобальная установка
composer --version
```

## Настройка для проекта

После установки composer, выполните в директории проекта:

```bash
cd ~/avito.siteaccess.ru/public_html

# Используйте установленный composer
php ~/composer.phar install --no-dev --optimize-autoloader --no-interaction
```

## Автоматическое определение

Контроллер развертывания теперь автоматически ищет composer в следующих местах:
1. `composer` (глобальный)
2. `php ~/composer.phar` (в домашней директории)
3. `php composer.phar` (в текущей директории)
4. `/usr/local/bin/composer`
5. `/usr/bin/composer`
6. `/opt/cpanel/composer/bin/composer` (для cPanel)

Если composer не найден, контроллер выведет предупреждение, но продолжит работу.

## Ручная установка зависимостей

Если автоматическая установка не работает, выполните вручную:

```bash
cd ~/avito.siteaccess.ru/public_html

# Найдите composer
which composer || echo "php ~/composer.phar"

# Установите зависимости
composer install --no-dev --optimize-autoloader --no-interaction
# Или:
php ~/composer.phar install --no-dev --optimize-autoloader --no-interaction
```
