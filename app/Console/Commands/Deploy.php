<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class Deploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy 
                            {--skip-migrations : Пропустить выполнение миграций}
                            {--skip-build : Пропустить сборку фронтенда}
                            {--skip-optimize : Пропустить оптимизацию}
                            {--force : Принудительное выполнение без подтверждения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновить проект из Git репозитория на сервере';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Начало обновления проекта...');

        if (!$this->option('force')) {
            if (!$this->confirm('Вы уверены, что хотите обновить проект?', true)) {
                $this->warn('Обновление отменено.');
                return Command::FAILURE;
            }
        }

        $steps = [
            'git' => 'Получение обновлений из Git',
            'composer' => 'Обновление Composer зависимостей',
            'npm' => 'Обновление NPM зависимостей',
            'build' => 'Сборка фронтенда',
            'migrations' => 'Выполнение миграций',
            'cache' => 'Очистка кэша',
            'optimize' => 'Оптимизация приложения',
        ];

        $bar = $this->output->createProgressBar(count($steps));
        $bar->start();

        try {
            // Проверка наличия Git репозитория
            if (!is_dir(base_path('.git'))) {
                $this->newLine();
                $this->error('❌ Git репозиторий не найден!');
                $this->warn('');
                $this->warn('Для настройки Git репозитория выполните:');
                $this->line('1. Инициализируйте репозиторий:');
                $this->line('   git init');
                $this->line('');
                $this->line('2. Добавьте remote:');
                $this->line('   git remote add origin https://github.com/letoceiling-coder/avito.git');
                return Command::FAILURE;
            }

            // Проверка наличия remote
            try {
                $remoteCheck = Process::run('git remote get-url origin');
                if (!$remoteCheck->successful()) {
                    $this->newLine();
                    $this->error('❌ Remote origin не настроен!');
                    $this->warn('');
                    $this->warn('Добавьте remote:');
                    $this->line('   git remote add origin https://github.com/letoceiling-coder/avito.git');
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $process = new SymfonyProcess(['git', 'remote', 'get-url', 'origin']);
                $process->run();
                if (!$process->isSuccessful()) {
                    $this->newLine();
                    $this->error('❌ Remote origin не настроен!');
                    $this->warn('');
                    $this->warn('Добавьте remote:');
                    $this->line('   git remote add origin https://github.com/letoceiling-coder/avito.git');
                    return Command::FAILURE;
                }
            }

            // 1. Git pull
            $this->newLine();
            $this->info('📥 Получение обновлений из Git...');
            
            // Сначала проверяем статус
            $statusProcess = new SymfonyProcess(['git', 'status', '--porcelain']);
            $statusProcess->run();
            $statusOutput = trim($statusProcess->getOutput());
            
            // Проверяем наличие локальных изменений
            if (!empty($statusOutput)) {
                $this->warn('⚠️  Обнаружены локальные изменения в репозитории');
                
                // Разделяем на отслеживаемые и неотслеживаемые файлы
                $lines = explode("\n", $statusOutput);
                $modifiedFiles = [];
                $untrackedFiles = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, '??') === 0) {
                        $untrackedFiles[] = substr($line, 3);
                    } else {
                        $modifiedFiles[] = substr($line, 3);
                    }
                }
                
                if (!empty($modifiedFiles)) {
                    $this->warn('   Измененные файлы:');
                    foreach (array_slice($modifiedFiles, 0, 5) as $file) {
                        $this->line("     - {$file}");
                    }
                    if (count($modifiedFiles) > 5) {
                        $this->line("     ... и еще " . (count($modifiedFiles) - 5) . " файлов");
                    }
                }
                
                if (!empty($untrackedFiles)) {
                    $this->warn('   Неотслеживаемые файлы:');
                    foreach (array_slice($untrackedFiles, 0, 5) as $file) {
                        $this->line("     - {$file}");
                    }
                    if (count($untrackedFiles) > 5) {
                        $this->line("     ... и еще " . (count($untrackedFiles) - 5) . " файлов");
                    }
                }
                
                $this->newLine();
                $this->warn('💡 Выполняется автоматическое сохранение изменений...');
                
                // Сохраняем изменения в stash
                $stashProcess = new SymfonyProcess(['git', 'stash', 'push', '-u', '-m', 'Auto-stash before deploy: ' . date('Y-m-d H:i:s')]);
                $stashProcess->run();
                
                if ($stashProcess->isSuccessful()) {
                    $stashOutput = trim($stashProcess->getOutput());
                    if (!empty($stashOutput) && strpos($stashOutput, 'No local changes') === false) {
                        $this->info('✅ Локальные изменения сохранены в stash');
                    } else {
                        $this->info('✅ Нет изменений для сохранения');
                    }
                } else {
                    $this->warn('⚠️  Не удалось сохранить изменения в stash, продолжаем...');
                }
            }
            
            try {
                // Сначала делаем fetch
                $fetchProcess = new SymfonyProcess(['git', 'fetch', 'origin']);
                $fetchProcess->run();
                
                if (!$fetchProcess->isSuccessful()) {
                    $this->error('❌ Ошибка при получении обновлений из Git (fetch)');
                    $this->error($fetchProcess->getErrorOutput());
                    return Command::FAILURE;
                }
                
                // Определяем текущую ветку
                $branchProcess = new SymfonyProcess(['git', 'branch', '--show-current']);
                $branchProcess->run();
                $currentBranch = trim($branchProcess->getOutput()) ?: 'master';
                
                // Пробуем pull с rebase для более чистой истории
                $pullProcess = new SymfonyProcess(['git', 'pull', '--rebase', 'origin', $currentBranch]);
                $pullProcess->run();
                
                if (!$pullProcess->isSuccessful()) {
                    $errorOutput = $pullProcess->getErrorOutput();
                    
                    // Если rebase не удался, пробуем обычный pull
                    if (strpos($errorOutput, 'conflict') !== false || strpos($errorOutput, 'CONFLICT') !== false) {
                        $this->warn('⚠️  Обнаружены конфликты при rebase, пробуем обычный pull...');
                        
                        // Отменяем rebase
                        $abortProcess = new SymfonyProcess(['git', 'rebase', '--abort']);
                        $abortProcess->run();
                        
                        // Пробуем обычный pull
                        $pullProcess = new SymfonyProcess(['git', 'pull', 'origin', $currentBranch]);
                        $pullProcess->run();
                        
                        if (!$pullProcess->isSuccessful()) {
                            $this->error('❌ Ошибка при получении обновлений из Git');
                            $this->error($pullProcess->getErrorOutput());
                            $this->warn('');
                            $this->warn('Необходимо разрешить конфликты вручную:');
                            $this->line("   git pull origin {$currentBranch}");
                            return Command::FAILURE;
                        }
                    } else {
                        $this->error('❌ Ошибка при получении обновлений из Git');
                        $this->error($errorOutput);
                        return Command::FAILURE;
                    }
                }
            } catch (\Exception $e) {
                $this->error('❌ Ошибка при получении обновлений из Git: ' . $e->getMessage());
                return Command::FAILURE;
            }
            
            $this->info('✅ Обновления получены');
            $bar->advance();

            // 2. Composer install
            if (file_exists(base_path('composer.json'))) {
                $this->newLine();
                $this->info('📦 Обновление Composer зависимостей...');
                
                // Определяем версию PHP и путь к composer
                $phpVersion = $this->findPhp82();
                $composerPath = $this->detectComposerPath();
                
                // Если не нашли php8.2, проверяем текущую версию PHP
                if (!$phpVersion) {
                    $currentPhp = PHP_VERSION;
                    if (version_compare($currentPhp, '8.2', '>=')) {
                        $phpVersion = null;
                    } else {
                        $this->warn('⚠️  Текущая версия PHP: ' . $currentPhp);
                        $this->warn('⚠️  Требуется PHP >= 8.2 для Composer');
                    }
                }
                
                // Формируем команду
                if ($phpVersion && $composerPath) {
                    $composerCommand = "{$phpVersion} {$composerPath}";
                } elseif ($phpVersion) {
                    $composerCommand = "{$phpVersion} " . ($composerPath ?: 'composer');
                } elseif ($composerPath) {
                    $composerCommand = $composerPath;
                } else {
                    $composerCommand = 'composer';
                }
                
                // Всегда используем SymfonyProcess
                $command = [];
                if ($phpVersion) {
                    $command[] = $phpVersion;
                }
                if ($composerPath) {
                    $command[] = $composerPath;
                } else {
                    $command[] = 'composer';
                }
                $command = array_merge($command, ['install', '--no-dev', '--optimize-autoloader']);
                
                try {
                    $process = new SymfonyProcess($command);
                    $process->setTimeout(600);
                    $process->setWorkingDirectory(base_path());
                    $process->run();
                    
                    $output = $process->getOutput();
                    $errorOutput = $process->getErrorOutput();
                    $exitCode = $process->getExitCode();
                    
                    $fullOutput = $output . "\n" . $errorOutput;
                    
                    // Проверяем на реальные ошибки
                    $hasRealError = false;
                    
                    if (strpos($fullOutput, 'php version') !== false || 
                        strpos($fullOutput, 'php ^8.2') !== false) {
                        if (preg_match('/Problem \d+.*?requires php.*?your php version.*?does not satisfy/i', $fullOutput) ||
                            (strpos($fullOutput, 'does not satisfy that requirement') !== false && 
                             preg_match('/Problem \d+/', $fullOutput))) {
                            $hasRealError = true;
                        }
                    }
                    
                    if ($hasRealError) {
                        $this->error('❌ Ошибка при обновлении Composer зависимостей');
                        $this->error($errorOutput ?: $output);
                        $this->warn('');
                        $this->warn('⚠️  Composer использует неправильную версию PHP!');
                        $this->warn('');
                        $this->warn('Попробуйте выполнить вручную:');
                        $this->line("   {$composerCommand} install --no-dev --optimize-autoloader");
                        return Command::FAILURE;
                    }
                    
                    $isSuccessful = strpos($fullOutput, 'Package operations') !== false || 
                                   strpos($fullOutput, 'Nothing to install') !== false ||
                                   strpos($fullOutput, 'updating') !== false ||
                                   strpos($fullOutput, 'installing') !== false ||
                                   strpos($fullOutput, 'removals') !== false ||
                                   $exitCode === 0;
                    
                    if (!$isSuccessful && $exitCode !== 0) {
                        $this->error('❌ Ошибка при обновлении Composer зависимостей');
                        if (!empty($errorOutput)) {
                            $this->error($errorOutput);
                        }
                        if (!empty($output)) {
                            $this->line($output);
                        }
                        $this->warn('');
                        $this->warn('Попробуйте выполнить вручную:');
                        $this->line("   {$composerCommand} install --no-dev --optimize-autoloader");
                        return Command::FAILURE;
                    }
                    
                    if (strpos($fullOutput, 'Warning:') !== false && !$hasRealError) {
                        $this->warn('⚠️  Composer выполнен с предупреждениями (но без ошибок)');
                    }
                } catch (\Exception $e) {
                    $this->error('❌ Ошибка при обновлении Composer зависимостей: ' . $e->getMessage());
                    $this->warn('');
                    $this->warn('Попробуйте выполнить вручную:');
                    $this->line("   {$composerCommand} install --no-dev --optimize-autoloader");
                    return Command::FAILURE;
                }
                
                $this->info('✅ Composer зависимости обновлены');
            }
            $bar->advance();

            // 3. NPM install
            if (file_exists(base_path('package.json'))) {
                $this->newLine();
                $this->info('📦 Обновление NPM зависимостей...');
                
                $nvmCommand = $this->getNvmCommand();
                
                try {
                    if ($nvmCommand) {
                        $result = Process::run("{$nvmCommand} && npm install");
                    } else {
                        $result = Process::run('npm install');
                    }
                    
                    if (!$result->successful()) {
                        $this->error('❌ Ошибка при обновлении NPM зависимостей');
                        $this->error($result->errorOutput());
                        return Command::FAILURE;
                    }
                } catch (\Exception $e) {
                    $command = $nvmCommand 
                        ? ['bash', '-c', "{$nvmCommand} && npm install"]
                        : ['npm', 'install'];
                    
                    $process = new SymfonyProcess($command);
                    $process->run();
                    
                    if (!$process->isSuccessful()) {
                        $this->error('❌ Ошибка при обновлении NPM зависимостей');
                        $this->error($process->getErrorOutput());
                        return Command::FAILURE;
                    }
                }
                
                $this->info('✅ NPM зависимости обновлены');
            }
            $bar->advance();

            // 4. Build frontend
            if (!$this->option('skip-build') && file_exists(base_path('package.json'))) {
                $this->newLine();
                $this->info('🔨 Сборка фронтенда...');
                
                // Устанавливаем права на выполнение для node_modules/.bin (исправление проблемы с vite)
                $nodeModulesBin = base_path('node_modules/.bin');
                if (is_dir($nodeModulesBin)) {
                    try {
                        // Пробуем установить права через chmod
                        $chmodProcess = new SymfonyProcess(['chmod', '-R', '+x', $nodeModulesBin]);
                        $chmodProcess->run();
                        
                        // Также пробуем установить права конкретно на vite и npx
                        $vitePath = $nodeModulesBin . '/vite';
                        $npxPath = $nodeModulesBin . '/npx';
                        
                        if (file_exists($vitePath)) {
                            $viteChmod = new SymfonyProcess(['chmod', '+x', $vitePath]);
                            $viteChmod->run();
                        }
                        
                        if (file_exists($npxPath)) {
                            $npxChmod = new SymfonyProcess(['chmod', '+x', $npxPath]);
                            $npxChmod->run();
                        }
                        
                        if ($chmodProcess->isSuccessful()) {
                            $this->line('   ✓ Права на выполнение установлены для node_modules/.bin');
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки chmod, продолжаем сборку
                        $this->warn('   ⚠️  Не удалось установить права автоматически, пробуем npx напрямую...');
                    }
                }
                
                $nvmCommand = $this->getNvmCommand();
                
                $buildOutput = '';
                $buildError = '';
                $buildSuccess = false;
                
                try {
                    // Читаем package.json для проверки доступных скриптов
                    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
                    $hasBuild = isset($packageJson['scripts']['build']);
                    $hasProd = isset($packageJson['scripts']['prod']);
                    
                    if (!$hasBuild && !$hasProd) {
                        $this->warn('⚠️  Скрипты build и prod не найдены в package.json');
                        $this->warn('   Пропуск сборки фронтенда');
                    } else {
                        // Пробуем сначала build, потом prod (если есть и build не удался)
                        if ($hasBuild) {
                            // Сначала пробуем npm run build
                            if ($nvmCommand) {
                                $result = Process::run("{$nvmCommand} && npm run build");
                            } else {
                                $result = Process::run('npm run build');
                            }
                            
                            $buildOutput = $result->output();
                            $buildError = $result->errorOutput();
                            
                            if ($result->successful()) {
                                $buildSuccess = true;
                            } else {
                                $this->warn('⚠️  npm run build не удался');
                                $errorPreview = substr($buildError ?: $buildOutput, 0, 200);
                                $this->line('   Вывод: ' . $errorPreview);
                                
                                // Если ошибка связана с правами на vite, пробуем npx напрямую
                                $isPermissionError = strpos($buildError, 'Permission denied') !== false || 
                                                    strpos($buildError, 'vite') !== false ||
                                                    strpos($buildOutput, 'Permission denied') !== false;
                                
                                if ($isPermissionError) {
                                    $this->warn('   Обнаружена ошибка прав доступа, пробуем npx vite build напрямую...');
                                    
                                    if ($nvmCommand) {
                                        $result = Process::run("{$nvmCommand} && npx vite build");
                                    } else {
                                        $result = Process::run('npx vite build');
                                    }
                                    
                                    $buildOutput = $result->output();
                                    $buildError = $result->errorOutput();
                                    
                                    if ($result->successful()) {
                                        $buildSuccess = true;
                                        $this->info('   ✅ Сборка выполнена через npx vite build');
                                    } else {
                                        $this->error('   ❌ npx vite build также не удался');
                                        $this->line('   Ошибка: ' . substr($buildError ?: $buildOutput, 0, 300));
                                        
                                        if ($hasProd) {
                                            $this->warn('   Пробуем npm run prod...');
                                        } else {
                                            // Если prod нет, показываем полную ошибку
                                            $this->error('   Ошибка сборки:');
                                            $this->line($buildError ?: $buildOutput);
                                        }
                                    }
                                } elseif ($hasProd) {
                                    $this->warn('   Пробуем npm run prod...');
                                } else {
                                    // Если prod нет, показываем полную ошибку
                                    $this->error('   Ошибка сборки:');
                                    $this->line($buildError ?: $buildOutput);
                                }
                            }
                        }
                        
                        // Если build не удался и есть prod, пробуем prod
                        if (!$buildSuccess && $hasProd) {
                            if ($nvmCommand) {
                                $result = Process::run("{$nvmCommand} && npm run prod");
                            } else {
                                $result = Process::run('npm run prod');
                            }
                            
                            $buildOutput = $result->output();
                            $buildError = $result->errorOutput();
                            
                            if ($result->successful()) {
                                $buildSuccess = true;
                            } else {
                                $this->error('❌ Ошибка при сборке фронтенда');
                                $this->error($result->errorOutput() ?: $result->output());
                                $this->warn('');
                                $this->warn('Попробуйте выполнить вручную:');
                                if ($hasBuild) {
                                    $this->line('   npm run build');
                                }
                                if ($hasProd) {
                                    $this->line('   npm run prod');
                                }
                                return Command::FAILURE;
                            }
                        } elseif (!$buildSuccess) {
                            // Если build не удался и prod нет
                            $this->error('❌ Ошибка при сборке фронтенда');
                            $this->error($buildError ?: $buildOutput);
                            $this->warn('');
                            $this->warn('Попробуйте выполнить вручную:');
                            $this->line('   npm run build');
                            return Command::FAILURE;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback на SymfonyProcess
                    try {
                        // Проверяем package.json перед выполнением
                        $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
                        $hasBuild = isset($packageJson['scripts']['build']);
                        $hasProd = isset($packageJson['scripts']['prod']);
                        
                        if (!$hasBuild && !$hasProd) {
                            $this->warn('⚠️  Скрипты build и prod не найдены в package.json');
                            $this->warn('   Пропуск сборки фронтенда');
                            $buildSuccess = false;
                        } elseif ($hasBuild) {
                            $command = $nvmCommand 
                                ? ['bash', '-c', "{$nvmCommand} && npm run build"]
                                : ['npm', 'run', 'build'];
                            
                            $process = new SymfonyProcess($command);
                            $process->setTimeout(600);
                            $process->run();
                            
                            $buildOutput = $process->getOutput();
                            $buildError = $process->getErrorOutput();
                            
                            if ($process->isSuccessful()) {
                                $buildSuccess = true;
                            } elseif ($hasProd) {
                                // Пробуем prod только если он есть
                                $this->warn('⚠️  npm run build не удался, пробуем npm run prod...');
                                $command = $nvmCommand 
                                    ? ['bash', '-c', "{$nvmCommand} && npm run prod"]
                                    : ['npm', 'run', 'prod'];
                                
                                $process = new SymfonyProcess($command);
                                $process->setTimeout(600);
                                $process->run();
                                
                                if ($process->isSuccessful()) {
                                    $buildSuccess = true;
                                } else {
                                    $this->error('❌ Ошибка при сборке фронтенда');
                                    $this->error($process->getErrorOutput() ?: $process->getOutput());
                                    $this->warn('');
                                    $this->warn('Попробуйте выполнить вручную:');
                                    $this->line('   npm run build');
                                    return Command::FAILURE;
                                }
                            } else {
                                $this->error('❌ Ошибка при сборке фронтенда');
                                $this->error($buildError ?: $buildOutput);
                                $this->warn('');
                                $this->warn('Попробуйте выполнить вручную:');
                                $this->line('   npm run build');
                                return Command::FAILURE;
                            }
                        }
                    } catch (\Exception $e2) {
                        $this->error('❌ Ошибка при сборке фронтенда: ' . $e2->getMessage());
                        $this->warn('');
                        $this->warn('Попробуйте выполнить вручную:');
                        $this->line('   npm run build');
                        return Command::FAILURE;
                    }
                }
                
                // Проверяем результат только если сборка была выполнена
                if ($buildSuccess || (!$hasBuild && !$hasProd)) {
                    // Проверяем, что файлы сборки действительно созданы
                    $buildDir = base_path('public/build');
                    $manifestFile = $buildDir . '/.vite/manifest.json';
                    
                    if (file_exists($manifestFile) || is_dir($buildDir)) {
                        $this->info('✅ Фронтенд собран успешно');
                        $this->line('   Файлы сборки находятся в: public/build');
                    } elseif ($hasBuild || $hasProd) {
                        $this->warn('⚠️  Сборка выполнена, но файлы не найдены в public/build');
                        $this->warn('   Проверьте вывод сборки выше');
                    }
                }
                
                // Проверяем, что файлы сборки действительно созданы
                $buildDir = base_path('public/build');
                $manifestFile = $buildDir . '/.vite/manifest.json';
                
                if (file_exists($manifestFile) || is_dir($buildDir)) {
                    $this->info('✅ Фронтенд собран успешно');
                    $this->line('   Файлы сборки находятся в: public/build');
                } else {
                    $this->warn('⚠️  Сборка выполнена, но файлы не найдены в public/build');
                    $this->warn('   Проверьте вывод сборки выше');
                }
            } elseif ($this->option('skip-build')) {
                $this->warn('⚠️  Сборка фронтенда пропущена (--skip-build)');
                $this->warn('   Убедитесь, что фронтенд собран вручную!');
            }
            $bar->advance();

            // 5. Migrations
            if (!$this->option('skip-migrations')) {
                $this->newLine();
                $this->info('🗄️  Выполнение миграций...');
                Artisan::call('migrate', ['--force' => true]);
                $this->info('✅ Миграции выполнены');
            }
            $bar->advance();

            // 6. Clear cache
            $this->newLine();
            $this->info('🧹 Очистка кэша...');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            $this->info('✅ Кэш очищен');
            $this->warn('💡 Если изменения не видны, очистите кеш браузера (Ctrl+F5 или Cmd+Shift+R)');
            $bar->advance();

            // 7. Optimize
            if (!$this->option('skip-optimize')) {
                $this->newLine();
                $this->info('⚡ Оптимизация приложения...');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
                Artisan::call('optimize');
                $this->info('✅ Приложение оптимизировано');
            }
            $bar->advance();

            $bar->finish();
            $this->newLine(2);
            $this->info('🎉 Обновление проекта завершено успешно!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('❌ Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Определить путь к composer
     */
    protected function detectComposerPath(): ?string
    {
        // Стандартные пути к composer
        $composerPaths = [
            base_path('composer.phar'), // В корне проекта
            getenv('HOME') . '/composer.phar',
            '/home/d/dsc23ytp/composer.phar',
            '/home/d/dsc23ytp/.local/bin/composer',
            '~/.local/bin/composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ];
        
        foreach ($composerPaths as $path) {
            // Заменяем ~ на домашнюю директорию
            if (strpos($path, '~') === 0) {
                $path = str_replace('~', getenv('HOME') ?: getenv('USERPROFILE') ?: '/home/' . get_current_user(), $path);
            }
            
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        // Попробуем найти через which
        try {
            $process = new SymfonyProcess(['which', 'composer']);
            $process->run();
            
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (!empty($path) && file_exists($path)) {
                    return $path;
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибку
        }
        
        return null;
    }

    /**
     * Получить команду для загрузки nvm
     */
    protected function getNvmCommand(): ?string
    {
        $nvmDir = getenv('NVM_DIR') ?: (getenv('HOME') . '/.nvm');
        
        if (file_exists($nvmDir . '/nvm.sh')) {
            return "export NVM_DIR=\"{$nvmDir}\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && \. \"\$NVM_DIR/nvm.sh\" && nvm use default";
        }
        
        return null;
    }

    /**
     * Найти php8.2
     */
    protected function findPhp82(): ?string
    {
        $phpVersions = ['php8.2', 'php82', '/usr/bin/php8.2', '/usr/local/bin/php8.2'];
        
        foreach ($phpVersions as $phpVersion) {
            try {
                if (strpos($phpVersion, '/') === 0) {
                    if (file_exists($phpVersion) && is_executable($phpVersion)) {
                        return $phpVersion;
                    }
                } else {
                    $process = new SymfonyProcess(['which', $phpVersion]);
                    $process->run();
                    
                    if ($process->isSuccessful()) {
                        $path = trim($process->getOutput());
                        if (!empty($path)) {
                            return $phpVersion;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }
}

