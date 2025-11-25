<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process as SymfonyProcess;

class DeployController extends Controller
{
    /**
     * Обработка запроса на обновление проекта из Git
     */
    public function deploy(Request $request)
    {
        // Проверка секретного ключа
        $secret = $request->input('secret');
        $expectedSecret = config('app.deploy_secret', env('DEPLOY_SECRET'));

        if (!$expectedSecret) {
            return response()->json([
                'message' => 'Секретный ключ не настроен на сервере',
                'error' => 'Установите DEPLOY_SECRET в .env файле',
            ], 500);
        }

        if ($secret !== $expectedSecret) {
            Log::warning('Попытка обновления с неверным секретным ключом', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Неверный секретный ключ',
                'error' => 'Доступ запрещен',
            ], 403);
        }

        $branch = $request->input('branch', 'master');

        try {
            Log::info('Начало обновления проекта', [
                'branch' => $branch,
                'ip' => $request->ip(),
                'timestamp' => $request->input('timestamp'),
            ]);

            // Запускаем команду deploy
            try {
                // Увеличиваем лимиты для выполнения команды
                set_time_limit(600); // 10 минут
                ini_set('max_execution_time', '600');
                
                Log::info('Запуск команды deploy синхронно');
                
                Artisan::call('deploy', ['--force' => true]);
                $output = Artisan::output();

                Log::info('Команда deploy выполнена успешно', [
                    'branch' => $branch,
                    'output_length' => strlen($output),
                ]);

                return response()->json([
                    'message' => 'Обновление выполнено успешно',
                    'status' => 'completed',
                    'branch' => $branch,
                ], 200);

            } catch (\Exception $syncException) {
                // Если синхронное выполнение не удалось, пробуем асинхронно
                Log::warning('Синхронное выполнение не удалось, пробуем асинхронно', [
                    'error' => $syncException->getMessage(),
                ]);

                // Для Linux используем nohup
                if (PHP_OS_FAMILY !== 'Windows') {
                    $logFile = storage_path('logs/deploy_' . date('Y-m-d_H-i-s') . '.log');
                    $command = sprintf(
                        'cd %s && nohup php artisan deploy --force > %s 2>&1 & echo $!',
                        escapeshellarg(base_path()),
                        escapeshellarg($logFile)
                    );
                    
                    $process = new SymfonyProcess(['sh', '-c', $command]);
                    $process->setWorkingDirectory(base_path());
                    $process->setTimeout(5);
                    $process->run();
                    
                    $pid = trim($process->getOutput());
                    
                    if (!empty($pid) && is_numeric($pid)) {
                        Log::info('Команда deploy запущена в фоновом режиме', [
                            'pid' => $pid,
                            'log_file' => $logFile,
                        ]);

                        return response()->json([
                            'message' => 'Обновление запущено в фоновом режиме',
                            'status' => 'running',
                            'branch' => $branch,
                            'pid' => $pid,
                            'log_file' => $logFile,
                        ], 202);
                    }
                }
                
                // Если асинхронный запуск не удался, пробрасываем исходную ошибку
                throw $syncException;
            }

        } catch (\Exception $e) {
            Log::error('Ошибка при обновлении проекта', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка при обновлении проекта',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Проверка статуса обновления
     */
    public function status()
    {
        // Можно добавить логику проверки статуса обновления
        // Например, проверка логов или файла статуса
        
        return response()->json([
            'message' => 'Статус обновления',
            'status' => 'unknown',
        ]);
    }
}
