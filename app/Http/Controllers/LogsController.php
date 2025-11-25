<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogsController extends Controller
{
    /**
     * Просмотр логов Laravel
     */
    public function index(Request $request)
    {
        // Опциональная проверка токена для безопасности
        $deployToken = env('DEPLOY_TOKEN');
        if ($deployToken) {
            if ($request->get('token') !== $deployToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный токен доступа'
                ], 401);
            }
        }

        $logFile = storage_path('logs/laravel.log');
        $lines = $request->get('lines', 100); // По умолчанию последние 100 строк
        $lines = min($lines, 1000); // Максимум 1000 строк

        if (!file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Файл логов не найден',
                'log_file' => $logFile
            ], 404);
        }

        try {
            // Читаем последние N строк из файла
            $content = File::get($logFile);
            $allLines = explode("\n", $content);
            $totalLines = count($allLines);
            
            // Берем последние N строк
            $logLines = array_slice($allLines, -$lines);
            $logContent = implode("\n", $logLines);

            return response()->json([
                'success' => true,
                'total_lines' => $totalLines,
                'showing_lines' => count($logLines),
                'log_file' => $logFile,
                'file_size' => filesize($logFile),
                'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
                'content' => $logContent
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при чтении логов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Очистка логов
     */
    public function clear(Request $request)
    {
        // Опциональная проверка токена для безопасности
        $deployToken = env('DEPLOY_TOKEN');
        if ($deployToken) {
            if ($request->get('token') !== $deployToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный токен доступа'
                ], 401);
            }
        }

        $logFile = storage_path('logs/laravel.log');

        try {
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                return response()->json([
                    'success' => true,
                    'message' => 'Логи успешно очищены'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Файл логов не найден'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при очистке логов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Список всех логов
     */
    public function list(Request $request)
    {
        // Опциональная проверка токена для безопасности
        $deployToken = env('DEPLOY_TOKEN');
        if ($deployToken) {
            if ($request->get('token') !== $deployToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный токен доступа'
                ], 401);
            }
        }

        $logsDir = storage_path('logs');
        $files = [];

        try {
            if (is_dir($logsDir)) {
                $allFiles = File::files($logsDir);
                foreach ($allFiles as $file) {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'path' => $file->getPathname()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'logs_dir' => $logsDir,
                'files' => $files
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка логов: ' . $e->getMessage()
            ], 500);
        }
    }
}
