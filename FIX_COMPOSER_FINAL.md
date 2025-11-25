# Финальное исправление: Composer

## ✅ Что уже работает

1. ✅ Git репозиторий найден и обновляется
2. ✅ HOME из .env читается правильно: `/home/d/dsc23ytp`
3. ✅ Директории storage и bootstrap/cache найдены
4. ✅ Права установлены
5. ✅ Queue workers перезапускаются

## ❌ Осталась одна проблема

**Composer не найден:** `Could not open input file: /home/d/dsc23ytp/composer.phar`

## 🔧 Решение

Выполните на сервере:

```bash
cd ~/avito.siteaccess.ru/public_html

# 1. Проверьте, где находится composer.phar
ls -la ~/composer.phar
ls -la /home/d/dsc23ytp/composer.phar

# 2. Если не найден, установите:
cd ~
curl -sS https://getcomposer.org/installer | php

# 3. Проверьте установку
php ~/composer.phar --version
ls -la ~/composer.phar

# 4. Если composer.phar находится в другом месте, обновите HOME в .env
# Например, если он в /home/d/dsc23ytp/composer.phar, то HOME должен быть /home/d/dsc23ytp
# Это уже правильно установлено!

# 5. Проверьте права на файл
chmod +x ~/composer.phar
```

## 🔍 Диагностика

Если composer.phar не найден, выполните:

```bash
# Проверьте домашнюю директорию
echo $HOME
pwd
whoami

# Проверьте, где может быть composer
find ~ -name "composer.phar" 2>/dev/null
which composer
```

## ✅ После исправления

После установки composer.phar выполните локально:

```bash
php artisan set-deploy --message="Финальный тест после установки composer"
```

Теперь должно работать полностью:
- ✅ Git обновляется
- ✅ Composer найден и устанавливает зависимости
- ✅ Все директории найдены
- ✅ Права установлены
