<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SetDeploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-deploy 
                            {--message= : Сообщение для коммита}
                            {--url= : URL для отправки POST запроса}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправить проект в git и выполнить развертывание на сервере';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Начало развертывания ===');
        
        // Получаем параметры
        $commitMessage = $this->option('message') ?: 'Deploy: ' . date('Y-m-d H:i:s');
        $deployUrl = $this->option('url') ?: 'http://avito.siteaccess.ru/deploy';

        // Шаг 1: Проверка git репозитория
        $this->info('Шаг 1: Проверка git репозитория...');
        if (!is_dir('.git')) {
            $this->error('Не найден git репозиторий!');
            return 1;
        }

        // Шаг 2: Добавление файлов
        $this->info('Шаг 2: Добавление файлов в git...');
        exec('git add .', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->error('Ошибка при добавлении файлов в git!');
            $this->line(implode("\n", $output));
            return 1;
        }
        $this->info('Файлы добавлены');

        // Шаг 3: Проверка наличия изменений
        exec('git status --porcelain', $statusOutput, $statusCode);
        if (empty($statusOutput)) {
            $this->warn('Нет изменений для коммита');
        } else {
            // Шаг 4: Создание коммита
            $this->info('Шаг 3: Создание коммита...');
            
            // Используем Process для надежного выполнения команды
            $process = new Process(['git', 'commit', '-m', $commitMessage]);
            $process->setWorkingDirectory(getcwd());
            $process->run();
            
            if (!$process->isSuccessful()) {
                $this->error('Ошибка при создании коммита!');
                $this->line($process->getErrorOutput());
                return 1;
            }
            $this->info('Коммит создан: ' . $commitMessage);

            // Шаг 5: Отправка в git
            $this->info('Шаг 4: Отправка в git репозиторий...');
            exec('git push origin master 2>&1', $pushOutput, $pushReturnCode);
            
            if ($pushReturnCode !== 0) {
                // Попробуем ветку main
                exec('git push origin main 2>&1', $pushOutput, $pushReturnCode);
                if ($pushReturnCode !== 0) {
                    $this->error('Ошибка при отправке в git репозиторий!');
                    $this->line(implode("\n", $pushOutput));
                    return 1;
                }
            }
            $this->info('Изменения отправлены в git');
        }

        // Шаг 6: Отправка POST запроса на сервер
        $this->info('Шаг 5: Отправка запроса на развертывание...');
        $this->line("URL: {$deployUrl}");

        try {
            // Подготовка данных для отправки
            $postData = [
                'timestamp' => now()->toDateTimeString(),
                'commit_message' => $commitMessage,
                'source' => 'artisan-command'
            ];

            // Добавляем токен, если он указан в .env
            $deployToken = env('DEPLOY_TOKEN');
            if ($deployToken) {
                $postData['token'] = $deployToken;
            }

            $response = Http::timeout(300) // 5 минут таймаут
                ->post($deployUrl, $postData);

            if ($response->successful()) {
                $this->info('Запрос на развертывание отправлен успешно!');
                $responseData = $response->json();
                
                if (isset($responseData['message'])) {
                    $this->line('Ответ сервера: ' . $responseData['message']);
                }
                
                if (isset($responseData['output'])) {
                    $this->line("\nВывод развертывания:");
                    $this->line($responseData['output']);
                }

                return 0;
            } else {
                $this->error('Ошибка при отправке запроса на развертывание!');
                $this->line('Статус: ' . $response->status());
                $this->line('Ответ: ' . $response->body());
                
                Log::error('Deploy request failed', [
                    'url' => $deployUrl,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Исключение при отправке запроса: ' . $e->getMessage());
            Log::error('Deploy request exception', [
                'url' => $deployUrl,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }
}
