<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckDeployLogs extends Command
{
    protected $signature = 'deploy:logs 
                            {--url= : URL сервера}
                            {--lines=100 : Количество строк лога}
                            {--token= : Токен доступа}';

    protected $description = 'Просмотр логов развертывания на сервере';

    public function handle()
    {
        $serverUrl = $this->option('url') ?: 'http://avito.siteaccess.ru';
        $lines = $this->option('lines') ?: 100;
        $token = $this->option('token') ?: env('DEPLOY_TOKEN');

        $this->info('=== Просмотр логов на сервере ===');
        $this->line("URL: {$serverUrl}");
        $this->line("Строк: {$lines}");
        $this->line("");

        try {
            $url = "{$serverUrl}/logs?lines={$lines}";
            if ($token) {
                $url .= "&token={$token}";
            }

            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data && $data['success']) {
                    $this->info('Логи успешно получены:');
                    $this->line("Всего строк в файле: {$data['total_lines']}");
                    $this->line("Показано строк: {$data['showing_lines']}");
                    $this->line("Размер файла: " . $this->formatBytes($data['file_size']));
                    $this->line("Последнее изменение: {$data['last_modified']}");
                    $this->line("");
                    $this->line("=== Содержимое лога ===");
                    $this->line($data['content']);
                } else {
                    $this->error('Ошибка: ' . ($data['message'] ?? 'Неизвестная ошибка'));
                    return 1;
                }
            } else {
                $this->error('Ошибка при получении логов!');
                $this->line('Статус: ' . $response->status());
                $this->line('Ответ: ' . $response->body());
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Исключение: ' . $e->getMessage());
            return 1;
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
