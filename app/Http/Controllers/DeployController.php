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
        $log[] = "";

        try {
            // Шаг 1: Обновление из git
            $log[] = "Шаг 1: Обновление кода из git...";
            
            // Проверяем, является ли директория git репозиторием
            if (!is_dir('.git')) {
                $log[] = "Предупреждение: Директория не является git репозиторием";
                $log[] = "Пропуск обновления из git";
            } else {
                $gitPull = $this->executeCommand('git pull origin master 2>&1', $log);
                if ($gitPull['code'] !== 0) {
                    // Попробуем ветку main
                    $gitPull = $this->executeCommand('git pull origin main 2>&1', $log);
                    if ($gitPull['code'] !== 0) {
                        $log[] = "Предупреждение: Не удалось обновить код из git (возможно, нет изменений или проблемы с подключением)";
                    }
                }
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
            
            // Используем абсолютные пути
            $basePath = base_path();
            $storagePath = $basePath . '/storage';
            $cachePath = $basePath . '/bootstrap/cache';
            
            if (is_dir($storagePath)) {
                $this->executeCommand("chmod -R 755 {$storagePath} 2>&1", $log);
                $log[] = "Права для storage установлены";
            } else {
                $log[] = "Предупреждение: директория storage не найдена";
            }
            
            if (is_dir($cachePath)) {
                $this->executeCommand("chmod -R 755 {$cachePath} 2>&1", $log);
                $log[] = "Права для bootstrap/cache установлены";
            } else {
                $log[] = "Предупреждение: директория bootstrap/cache не найдена";
            }
            
            $log[] = "";

            $log[] = "=== Развертывание завершено успешно! ===";

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
        // Получаем домашнюю директорию пользователя
        $homeDir = getenv('HOME') ?: (getenv('HOMEDRIVE') . getenv('HOMEPATH'));
        $user = get_current_user();
        
        // Проверяем различные возможные пути
        $paths = [
            'composer', // Глобальный composer
        ];
        
        // Добавляем пути с домашней директорией
        if ($homeDir) {
            $paths[] = 'php ' . $homeDir . '/composer.phar';
        }
        
        // Добавляем стандартные пути
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
                    $filePath = str_replace(['php ', '~/'], [$homeDir . '/', $homeDir . '/'], $path);
                    $filePath = preg_replace('/^php\s+/', '', $filePath);
                    $filePath = str_replace('~/', $homeDir . '/', $filePath);
                    
                    if (file_exists($filePath) || file_exists(str_replace($homeDir . '/', '', $filePath))) {
                        return $path;
                    }
                } else {
                    return $path;
                }
            }
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
