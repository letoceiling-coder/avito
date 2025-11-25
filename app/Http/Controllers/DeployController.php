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
            
            // Проверяем, является ли директория git репозиторием через git команду
            $currentDir = getcwd();
            $log[] = "Текущая директория: {$currentDir}";
            
            // Проверяем через git rev-parse
            $gitCheck = $this->executeCommand('git rev-parse --git-dir 2>&1', $log);
            
            if ($gitCheck['code'] === 0 && !empty($gitCheck['output'])) {
                $log[] = "Git репозиторий найден, выполняется обновление...";
                $gitPull = $this->executeCommand('git pull origin master 2>&1', $log);
                if ($gitPull['code'] !== 0) {
                    // Попробуем ветку main
                    $log[] = "Попытка обновления из ветки main...";
                    $gitPull = $this->executeCommand('git pull origin main 2>&1', $log);
                    if ($gitPull['code'] !== 0) {
                        $log[] = "Предупреждение: Не удалось обновить код из git (возможно, нет изменений или проблемы с подключением)";
                    } else {
                        $log[] = "Код успешно обновлен из ветки main";
                    }
                } else {
                    $log[] = "Код успешно обновлен из ветки master";
                }
            } else {
                $log[] = "Предупреждение: Директория не является git репозиторием";
                $log[] = "Проверка через is_dir('.git'): " . (is_dir('.git') ? 'true' : 'false');
                $log[] = "Пропуск обновления из git";
            }
            $log[] = "";

            // Шаг 2: Установка PHP зависимостей
            $log[] = "Шаг 2: Установка PHP зависимостей...";
            $composerPath = $this->findComposer();
            $this->executeCommand("{$composerPath} install --no-dev --optimize-autoloader --no-interaction 2>&1", $log);
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
            if ($this->commandExists('npm')) {
                $log[] = "Шаг 4: Сборка фронтенда...";
                
                // Установка прав на node_modules/.bin для выполнения скриптов
                $nodeBinPath = base_path('node_modules/.bin');
                if (is_dir($nodeBinPath)) {
                    $this->executeCommand("chmod -R +x {$nodeBinPath} 2>&1", $log);
                }
                
                // Используем npx для гарантированного выполнения vite
                $buildResult = $this->executeCommand('npx vite build 2>&1', $log);
                
                // Если npx не сработал, пробуем через npm run build
                if ($buildResult['code'] !== 0) {
                    $log[] = "Попытка через npm run build...";
                    $this->executeCommand('npm run build 2>&1', $log);
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
    private function findComposer()
    {
        // Получаем домашнюю директорию пользователя разными способами
        $homeDir = getenv('HOME');
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
        
        // Логируем для отладки (будет видно в логах)
        $dummy = [];
        if ($homeDir) {
            $dummy[] = "HOME найден: {$homeDir}";
        } else {
            $dummy[] = "HOME не найден";
        }
        $dummy[] = "Пользователь: {$user}";
        
        // Проверяем различные возможные пути
        $paths = [];
        
        // Сначала проверяем домашнюю директорию пользователя
        if ($homeDir) {
            $composerPhar = $homeDir . '/composer.phar';
            if (file_exists($composerPhar)) {
                $paths[] = 'php ' . $composerPhar;
                $dummy[] = "Найден composer.phar в: {$composerPhar}";
            } else {
                $dummy[] = "composer.phar не найден в: {$composerPhar}";
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
}
