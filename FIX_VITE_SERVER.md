# Решение проблемы "vite: Permission denied" на сервере

## Проблема

При выполнении `npm run build` на сервере возникает ошибка:
```
sh: 1: vite: Permission denied
```

## Решение

### Вариант 1: Использование npx (рекомендуется)

```bash
npx vite build
```

### Вариант 2: Установка прав на node_modules/.bin

```bash
# Перейдите в директорию проекта
cd ~/avito.siteaccess.ru/public_html

# Установите права на выполнение для всех скриптов в node_modules/.bin
chmod -R +x node_modules/.bin

# Теперь попробуйте снова
npm run build
```

### Вариант 3: Переустановка зависимостей

```bash
# Удалите node_modules и package-lock.json
rm -rf node_modules package-lock.json

# Переустановите зависимости
npm install

# Установите права
chmod -R +x node_modules/.bin

# Соберите проект
npm run build
```

### Вариант 4: Использование полного пути к vite

```bash
./node_modules/.bin/vite build
```

## Автоматическое исправление

Контроллер развертывания теперь автоматически:
1. Устанавливает права на `node_modules/.bin`
2. Использует `npx vite build` вместо прямого вызова vite

## Проверка

После исправления проверьте:

```bash
# Проверьте права
ls -la node_modules/.bin/vite

# Должно быть что-то вроде:
# -rwxr-xr-x 1 user user ... vite

# Если нет 'x' (права на выполнение), установите:
chmod +x node_modules/.bin/vite
```

## Если проблема сохраняется

1. Проверьте версию Node.js:
   ```bash
   node --version
   npm --version
   ```

2. Убедитесь, что vite установлен:
   ```bash
   npm list vite
   ```

3. Попробуйте установить vite глобально (не рекомендуется, но может помочь):
   ```bash
   npm install -g vite
   ```

4. Используйте альтернативный способ сборки через package.json:
   ```json
   "scripts": {
     "build": "node node_modules/vite/bin/vite.js build"
   }
   ```
