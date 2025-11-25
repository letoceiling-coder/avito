<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeployController extends Controller
{
    /**
     * Обработка POST запроса на развертывание
     */
    public function deploy(Request $request)
    {
        // Опциональная проверка токена для безопасности
        $deployToken = env('DEPLOY_TOKEN');
        if ($deployToken) {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|in:' . $deployToken,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный токен доступа',
                    'error' => $validator->errors()
                ], 401);
            }
        }

        $log = [];
        $log[] = "=== Начало развертывания ===";
        $log[] = "Время: " . now()->toDateTimeString();
        
        // Переходим в корень проекта для выполнения команд
        $basePath = base_path();
        $log[] = "Рабочая директория: {$basePath}";
        $log[] = "";

        try {
            // Сохраняем текущую директорию
            $originalDir = getcwd();
            
            // Переходим в корень проекта
            if (!chdir($basePath)) {
                throw new \Exception("Не удалось перейти в директорию проекта: {$basePath}");
            }
            // Шаг 1: Обновление из git
            $log[] = "Шаг 1: Обновление кода из git...";
            
            // Проверяем, является ли директория git репозиторием
            $currentDir = getcwd();
            $log[] = "Текущая директория: {$currentDir}";
            $log[] = "base_path(): " . base_path();
            $log[] = "Содержимое директории (первые 10 файлов): " . implode(', ', array_slice(scandir('.'), 0, 10));
            
            // Проверяем несколькими способами
            $isGitRepo = false;
            
            // Способ 1: Проверка директории .git
            $gitDir = '.git';
            $gitDirAbs = $currentDir . '/.git';
            $log[] = "Проверка .git: относительный '{$gitDir}' = " . (is_dir($gitDir) ? 'существует' : 'не существует');
            $log[] = "Проверка .git: абсолютный '{$gitDirAbs}' = " . (is_dir($gitDirAbs) ? 'существует' : 'не существует');
            
            if (is_dir('.git') || is_dir($gitDirAbs)) {
                $isGitRepo = true;
                $log[] = "✓ Git репозиторий найден через проверку директории .git";
            }
            
            // Способ 2: Проверка через git rev-parse
            if (!$isGitRepo) {
                $gitCheck = $this->executeCommand('git rev-parse --git-dir 2>&1', $gitCheckLog);
                $log[] = "git rev-parse --git-dir: код = {$gitCheck['code']}, вывод = " . implode(' | ', array_slice($gitCheck['output'], 0, 3));
                if ($gitCheck['code'] === 0 && !empty($gitCheck['output'])) {
                    $isGitRepo = true;
                    $log[] = "✓ Git репозиторий найден через git rev-parse";
                }
            }
            
            // Способ 3: Проверка через git status
            if (!$isGitRepo) {
                $gitStatus = $this->executeCommand('git status 2>&1', $gitStatusLog);
                $log[] = "git status: код = {$gitStatus['code']}";
                if ($gitStatus['code'] === 0) {
                    $isGitRepo = true;
                    $log[] = "✓ Git репозиторий найден через git status";
                }
            }
            
            if ($isGitRepo) {
                $log[] = "Выполняется обновление из git...";
                $gitPull = $this->executeCommand('git pull origin master 2>&1', $log);
                if ($gitPull['code'] !== 0) {
                    // Попробуем ветку main
                    $log[] = "Попытка обновления из ветки main...";
                    $gitPull = $this->executeCommand('git pull origin main 2>&1', $log);
                    if ($gitPull['code'] !== 0) {
                        $log[] = "Предупреждение: Не удалось обновить код из git (возможно, нет изменений или проблемы с подключением)";
                    } else {
                        $log[] = "✓ Код успешно обновлен из ветки main";
                    }
                } else {
                    $log[] = "✓ Код успешно обновлен из ветки master";
                }
            } else {
                $log[] = "⚠ Предупреждение: Директория не является git репозиторием";
                $log[] = "Пропуск обновления из git";
            }
            $log[] = "";

            // Шаг 2: Установка PHP зависимостей
            $log[] = "Шаг 2: Установка PHP зависимостей...";
            $log[] = "Текущая директория: " . getcwd();
            $log[] = "Путь к .env: " . base_path('.env');
            $log[] = ".env существует: " . (file_exists(base_path('.env')) ? 'да' : 'нет');
            
            // Показываем информацию о HOME
            $homeFromEnv = $this->getHomeFromEnv();
            $homeFromGetenv = getenv('HOME');
            $log[] = "HOME из .env: " . ($homeFromEnv ?: 'не найден');
            $log[] = "HOME из getenv: " . ($homeFromGetenv ?: 'не установлен');
            $log[] = "Пользователь: " . get_current_user();
            
            $composerPath = $this->findComposer($log);
            $log[] = "Используется composer: {$composerPath}";
            
            // Выполняем команду composer (мы уже в корне проекта)
            // Используем параметры из требований: --no-interaction --prefer-dist --optimize-autoloader
            $composerResult = $this->executeCommand("{$composerPath} install --no-interaction --prefer-dist --optimize-autoloader 2>&1", $log);
            
            if ($composerResult['code'] !== 0) {
                $log[] = "Предупреждение: Ошибка при установке зависимостей через composer";
                $log[] = "Код возврата: " . $composerResult['code'];
                $log[] = "Попробуйте установить зависимости вручную: {$composerPath} install";
            } else {
                $log[] = "PHP зависимости установлены успешно";
            }
            $log[] = "";

            // Шаг 3: Установка Node.js зависимостей (если доступен npm)
            if ($this->commandExists('npm')) {
                $log[] = "Шаг 3: Установка Node.js зависимостей...";
                $this->executeCommand('npm install --production=false 2>&1', $log);
                $log[] = "";
            } else {
                $log[] = "Шаг 3: npm не найден, пропуск установки Node.js зависимостей";
                $log[] = "";
            }

            // Шаг 4: Сборка фронтенда (если доступен npm)
            // Требование: npm run build или npm run prod
            if ($this->commandExists('npm')) {
                $log[] = "Шаг 4: Сборка фронтенда...";
                
                // Установка прав на node_modules/.bin для выполнения скриптов
                $nodeBinPath = base_path('node_modules/.bin');
                if (is_dir($nodeBinPath)) {
                    $this->executeCommand("chmod -R +x {$nodeBinPath} 2>&1", $log);
                }
                
                // Сначала пробуем npm run prod (для продакшена)
                $buildResult = $this->executeCommand('npm run prod 2>&1', $log);
                
                // Если npm run prod не сработал, пробуем npm run build
                if ($buildResult['code'] !== 0) {
                    $log[] = "npm run prod не найден, пробуем npm run build...";
                    $buildResult = $this->executeCommand('npm run build 2>&1', $log);
                    
                    // Если и build не сработал, пробуем через npx vite build
                    if ($buildResult['code'] !== 0) {
                        $log[] = "Попытка через npx vite build...";
                        $this->executeCommand('npx vite build 2>&1', $log);
                    } else {
                        $log[] = "Фронтенд собран через npm run build";
                    }
                } else {
                    $log[] = "Фронтенд собран через npm run prod";
                }
                
                $log[] = "";
            } else {
                $log[] = "Шаг 4: npm не найден, пропуск сборки фронтенда";
                $log[] = "";
            }

            // Шаг 5: Выполнение миграций
            $log[] = "Шаг 5: Выполнение миграций базы данных...";
            Artisan::call('migrate', ['--force' => true]);
            $log[] = Artisan::output();
            $log[] = "";

            // Шаг 6: Очистка кешей
            $log[] = "Шаг 6: Очистка кешей...";
            Artisan::call('cache:clear');
            $log[] = "Кеш приложения очищен";
            Artisan::call('config:clear');
            $log[] = "Кеш конфигурации очищен";
            Artisan::call('route:clear');
            $log[] = "Кеш маршрутов очищен";
            Artisan::call('view:clear');
            $log[] = "Кеш представлений очищен";
            $log[] = "";

            // Шаг 7: Кеширование для продакшена
            $log[] = "Шаг 7: Кеширование для продакшена...";
            Artisan::call('config:cache');
            $log[] = "Конфигурация закеширована";
            Artisan::call('route:cache');
            $log[] = "Маршруты закешированы";
            Artisan::call('view:cache');
            $log[] = "Представления закешированы";
            Artisan::call('optimize');
            $log[] = "Оптимизация завершена";
            $log[] = "";

            // Шаг 7.1: Перезапуск сервисов (опционально)
            $log[] = "Шаг 7.1: Проверка и перезапуск сервисов...";
            
            // Перезапуск queue workers (если используется очередь)
            if (config('queue.default') !== 'sync') {
                $log[] = "Обнаружена очередь: " . config('queue.default');
                $log[] = "Рекомендуется перезапустить queue workers вручную: php artisan queue:restart";
                // Выполняем queue:restart для перезапуска workers
                Artisan::call('queue:restart');
                $log[] = "Команда queue:restart выполнена (workers перезапустятся автоматически)";
            } else {
                $log[] = "Очередь не используется (sync), перезапуск не требуется";
            }
            
            $log[] = "";

            // Шаг 8: Очистка логов
            $log[] = "Шаг 8: Очистка логов...";
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                $log[] = "Лог laravel.log очищен";
            }
            $log[] = "";

            // Шаг 9: Установка прав доступа
            $log[] = "Шаг 9: Установка прав доступа...";
            
            // Используем относительные пути, так как мы уже в корне проекта
            $currentDir = getcwd();
            $log[] = "Текущая директория: {$currentDir}";
            $log[] = "Содержимое директории: " . implode(', ', array_slice(scandir('.'), 0, 10));
            
            // Проверяем storage
            $storagePath = 'storage';
            $storageAbsPath = $currentDir . '/' . $storagePath;
            $log[] = "Проверка storage: относительный путь '{$storagePath}', абсолютный '{$storageAbsPath}'";
            $log[] = "is_dir('storage'): " . (is_dir('storage') ? 'true' : 'false');
            $log[] = "is_dir('{$storageAbsPath}'): " . (is_dir($storageAbsPath) ? 'true' : 'false');
            
            if (is_dir('storage') || is_dir($storageAbsPath)) {
                $this->executeCommand("chmod -R 755 storage 2>&1", $log);
                $log[] = "Права для storage установлены";
            } else {
                $log[] = "Предупреждение: директория storage не найдена";
                $log[] = "Попытка создать директорию storage...";
                @mkdir('storage', 0755, true);
                if (is_dir('storage')) {
                    $log[] = "Директория storage создана";
                }
            }
            
            // Проверяем bootstrap/cache
            $cachePath = 'bootstrap/cache';
            $cacheAbsPath = $currentDir . '/' . $cachePath;
            $log[] = "Проверка bootstrap/cache: относительный путь '{$cachePath}', абсолютный '{$cacheAbsPath}'";
            $log[] = "is_dir('bootstrap/cache'): " . (is_dir('bootstrap/cache') ? 'true' : 'false');
            $log[] = "is_dir('{$cacheAbsPath}'): " . (is_dir($cacheAbsPath) ? 'true' : 'false');
            
            if (is_dir('bootstrap/cache') || is_dir($cacheAbsPath)) {
                $this->executeCommand("chmod -R 755 bootstrap/cache 2>&1", $log);
                $log[] = "Права для bootstrap/cache установлены";
            } else {
                $log[] = "Предупреждение: директория bootstrap/cache не найдена";
                $log[] = "Попытка создать директорию bootstrap/cache...";
                @mkdir('bootstrap/cache', 0755, true);
                if (is_dir('bootstrap/cache')) {
                    $log[] = "Директория bootstrap/cache создана";
                }
            }
            
            $log[] = "";

            $log[] = "=== Развертывание завершено успешно! ===";

            // Возвращаемся в исходную директорию
            if (isset($originalDir)) {
                chdir($originalDir);
            }

            Log::info('Deploy completed successfully', [
                'timestamp' => now()->toDateTimeString(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Развертывание выполнено успешно',
                'output' => implode("\n", $log),
                'timestamp' => now()->toDateTimeString()
            ], 200);

        } catch (\Exception $e) {
            // Возвращаемся в исходную директорию в случае ошибки
            if (isset($originalDir)) {
                @chdir($originalDir);
            }
            
            $log[] = "";
            $log[] = "=== ОШИБКА ===";
            $log[] = "Сообщение: " . $e->getMessage();
            $log[] = "Файл: " . $e->getFile();
            $log[] = "Строка: " . $e->getLine();
            
            // Добавляем trace только в режиме отладки
            if (config('app.debug')) {
                $log[] = "Trace: " . $e->getTraceAsString();
            }

            Log::error('Deploy failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);

            $errorMessage = config('app.debug') 
                ? $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine()
                : 'Ошибка при развертывании. Проверьте логи на сервере.';

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'output' => implode("\n", $log),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Выполнение команды и добавление вывода в лог
     */
    private function executeCommand($command, &$log)
    {
        exec($command, $output, $returnCode);
        $log = array_merge($log, $output);
        return ['output' => $output, 'code' => $returnCode];
    }

    /**
     * Поиск пути к Composer
     */
    private function findComposer(&$log = null)
    {
        // Сначала пробуем получить HOME из .env файла
        $homeDir = $this->getHomeFromEnv();
        
        // Если не нашли в .env, пробуем другие способы
        if (!$homeDir) {
            $homeDir = getenv('HOME');
        }
        
        // Логируем для отладки (будет видно в выводе развертывания)
        $debugInfo = [];
        $debugInfo[] = "Поиск composer...";
        $debugInfo[] = "HOME из .env: " . ($homeDir ?: 'не найден');
        
        if (!$homeDir) {
            // Для Windows
            $homeDir = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        
        // Альтернативный способ получения домашней директории через posix
        if (!$homeDir && function_exists('posix_getpwuid')) {
            $userInfo = posix_getpwuid(posix_geteuid());
            if ($userInfo && isset($userInfo['dir'])) {
                $homeDir = $userInfo['dir'];
            }
        }
        
        // Еще один способ - через whoami и /etc/passwd
        if (!$homeDir) {
            $whoami = trim(shell_exec('whoami 2>/dev/null'));
            if ($whoami) {
                $passwdLine = shell_exec("grep ^{$whoami}: /etc/passwd 2>/dev/null");
                if ($passwdLine) {
                    $parts = explode(':', $passwdLine);
                    if (isset($parts[5])) {
                        $homeDir = $parts[5];
                    }
                }
            }
        }
        
        // Получаем пользователя, который запускает PHP
        $user = get_current_user();
        
        // Логируем для отладки
        if ($log !== null) {
            $log[] = "Поиск composer...";
            if ($homeDir) {
                $log[] = "HOME найден: {$homeDir}";
            } else {
                $log[] = "HOME не найден";
            }
            $log[] = "Пользователь: {$user}";
        }
        
        // Проверяем различные возможные пути
        $paths = [];
        
        // Сначала проверяем домашнюю директорию пользователя
        if ($homeDir) {
            $composerPhar = rtrim($homeDir, '/') . '/composer.phar';
            $composerPharExists = file_exists($composerPhar);
            if ($log !== null) {
                $log[] = "Проверка composer.phar: {$composerPhar}";
                $log[] = "Файл существует: " . ($composerPharExists ? 'да' : 'нет');
            }
            if ($composerPharExists) {
                $paths[] = 'php ' . $composerPhar;
                if ($log !== null) {
                    $log[] = "✓ Найден composer.phar в: {$composerPhar}";
                }
            } else {
                if ($log !== null) {
                    $log[] = "✗ composer.phar не найден в: {$composerPhar}";
                    // Попробуем найти composer.phar в других местах
                    $log[] = "Поиск composer.phar в других местах...";
                    $findResult = $this->executeCommand("find {$homeDir} -name composer.phar -type f 2>/dev/null | head -1", $findLog);
                    if (!empty($findResult['output']) && file_exists(trim($findResult['output'][0]))) {
                        $foundPath = trim($findResult['output'][0]);
                        $log[] = "✓ Найден composer.phar в: {$foundPath}";
                        $paths[] = 'php ' . $foundPath;
                    }
                }
            }
        } else {
            if ($log !== null) {
                $log[] = "HOME не установлен, пропуск проверки composer.phar в домашней директории";
            }
        }
        
        // Проверяем глобальный composer
        $paths[] = 'composer';
        
        // Проверяем стандартные пути
        $paths = array_merge($paths, [
            'php ~/composer.phar', // В домашней директории (через ~)
            'php composer.phar', // В текущей директории
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/cpanel/composer/bin/composer', // Для cPanel
        ]);
        
        // Проверяем каждый путь
        foreach ($paths as $path) {
            // Для путей с пробелами проверяем только первую часть
            $commandParts = explode(' ', $path);
            $checkCommand = $commandParts[0];
            
            if ($this->commandExists($checkCommand)) {
                // Если это путь с файлом, проверяем существование файла
                if (count($commandParts) > 1 && strpos($path, 'composer.phar') !== false) {
                    // Заменяем ~ на реальный путь
                    $filePath = str_replace('~/', ($homeDir ? $homeDir . '/' : ''), $path);
                    $filePath = preg_replace('/^php\s+/', '', $filePath);
                    
                    if (file_exists($filePath)) {
                        return $path;
                    }
                } else {
                    // Для простых команд проверяем, что они работают
                    $testResult = $this->executeCommand("{$path} --version 2>&1", $testLog);
                    if ($testResult['code'] === 0) {
                        return $path;
                    }
                }
            }
        }

        // Последняя попытка - проверим через exec напрямую
        if ($homeDir && file_exists($homeDir . '/composer.phar')) {
            return 'php ' . $homeDir . '/composer.phar';
        }

        return 'composer'; // По умолчанию (может не работать, но попробуем)
    }

    /**
     * Проверка существования команды
     */
    private function commandExists($command)
    {
        $parts = explode(' ', $command);
        $command = $parts[0];
        
        exec("which {$command} 2>&1", $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Получение HOME из .env файла
     */
    private function getHomeFromEnv()
    {
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            // Ищем HOME= в начале строки (может быть пробелы перед =)
            // Поддерживаем формат: HOME=value или HOME = value
            if (preg_match('/^HOME\s*=\s*(.+)$/m', $envContent, $matches)) {
                $home = trim($matches[1]);
                // Убираем кавычки, если есть
                $home = trim($home, '"\'');
                // Убираем комментарии после значения
                $home = preg_replace('/\s*#.*$/', '', $home);
                return trim($home);
            }
        }
        return null;
    }
}
