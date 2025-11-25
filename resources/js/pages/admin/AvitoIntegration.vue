<template>
    <div class="avito-integration-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Интеграция с Авито</h1>
            <p class="text-muted-foreground mt-1">Настройка подключения к API Авито</p>
        </div>

        <!-- Настройки интеграции -->
        <div class="bg-card rounded-lg border border-border p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Настройки API</h2>
            
            <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                <p class="text-sm text-blue-800 dark:text-blue-200 mb-2">
                    <strong>Важно! Инструкция по настройке:</strong>
                </p>
                <ol class="text-sm text-blue-700 dark:text-blue-300 list-decimal list-inside space-y-2">
                    <li>Получите Client ID и Client Secret в <a href="https://developers.avito.ru/applications" target="_blank" class="underline font-semibold">личном кабинете разработчика Авито</a></li>
                    <li>
                        <strong>КРИТИЧЕСКИ ВАЖНО:</strong> В настройках приложения Авито укажите Redirect URI точно так:
                        <div class="mt-1 p-2 bg-blue-100 dark:bg-blue-800 rounded font-mono text-xs break-all">
                            {{ windowLocation }}/admin/avito/callback
                        </div>
                        <p class="mt-1 text-xs italic">Redirect URI должен совпадать <strong>ТОЧНО</strong>, включая протокол (http/https) и путь!</p>
                    </li>
                    <li>Сохраните настройки ниже и нажмите "Авторизоваться через OAuth"</li>
                    <li>Если видите ошибку "Что-то пошло не так", проверьте, что Redirect URI в настройках Авито совпадает с указанным выше</li>
                </ol>
            </div>
            
            <form @submit.prevent="saveSettings" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Client ID</label>
                    <input
                        v-model="form.client_id"
                        type="text"
                        class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        placeholder="Введите Client ID"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Client Secret</label>
                    <input
                        v-model="form.client_secret"
                        type="password"
                        class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        placeholder="Введите Client Secret"
                        required
                    />
                </div>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ loading ? 'Сохранение...' : 'Сохранить настройки' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Статус подключения -->
        <div v-if="integration" class="bg-card rounded-lg border border-border p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Статус подключения</h2>
            
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Статус:</span>
                    <span
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-medium',
                            integration.is_active && integration.is_token_valid
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                        ]"
                    >
                        {{ integration.is_active && integration.is_token_valid ? 'Активно' : 'Не активно' }}
                    </span>
                </div>

                <div v-if="integration.expires_at" class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Токен действителен до:</span>
                    <span class="text-sm">{{ new Date(integration.expires_at).toLocaleString('ru-RU') }}</span>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                <button
                    v-if="!integration.is_active || !integration.is_token_valid"
                    @click="authorize"
                    :disabled="loading || !integration.client_id"
                    class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                >
                    Авторизоваться через OAuth
                </button>

                <button
                    @click="testConnection"
                    :disabled="loading || !integration.is_active"
                    class="w-full px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/90 disabled:opacity-50"
                >
                    {{ loading ? 'Проверка...' : 'Тест подключения' }}
                </button>
            </div>
        </div>

        <!-- Результат теста подключения -->
        <div v-if="testResult" class="bg-card rounded-lg border border-border p-6">
            <h2 class="text-xl font-semibold mb-4">Результат теста</h2>
            
            <div
                :class="[
                    'p-4 rounded-md',
                    testResult.success
                        ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
                        : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
                ]"
            >
                <div v-if="testResult.success" class="space-y-2">
                    <p class="font-medium text-green-800 dark:text-green-200">Подключение успешно!</p>
                    <div v-if="testResult.data" class="text-sm text-green-700 dark:text-green-300">
                        <p><strong>ID аккаунта:</strong> {{ testResult.data.id }}</p>
                        <p v-if="testResult.data.name"><strong>Имя:</strong> {{ testResult.data.name }}</p>
                        <p v-if="testResult.data.email"><strong>Email:</strong> {{ testResult.data.email }}</p>
                    </div>
                </div>
                <div v-else>
                    <p class="font-medium text-red-800 dark:text-red-200">Ошибка подключения</p>
                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ testResult.error }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'AvitoIntegration',
    data() {
        return {
            integration: null,
            form: {
                client_id: '',
                client_secret: '',
            },
            loading: false,
            testResult: null,
            windowLocation: window.location.origin,
        };
    },
    mounted() {
        this.loadIntegration();
        this.checkAuthCallback();
    },
    methods: {
        async loadIntegration() {
            try {
                const response = await axios.get('/api/avito/integration');
                this.integration = response.data.integration;
                
                if (this.integration) {
                    this.form.client_id = this.integration.client_id || '';
                }
            } catch (error) {
                console.error('Ошибка загрузки интеграции:', error);
            }
        },
        async saveSettings() {
            this.loading = true;
            try {
                const response = await axios.post('/api/avito/integration', this.form);
                this.integration = response.data.integration;
                
                await Swal.fire({
                    icon: 'success',
                    title: 'Успешно',
                    text: 'Настройки сохранены',
                    timer: 2000,
                    showConfirmButton: false,
                });
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.message || 'Не удалось сохранить настройки',
                });
            } finally {
                this.loading = false;
            }
        },
        async authorize() {
            try {
                // Формируем redirect_uri точно так же, как он должен быть в настройках приложения Авито
                // Используем текущий протокол и домен
                const redirectUri = window.location.origin + '/admin/avito/callback';
                
                // Логируем для отладки
                console.log('Current location:', {
                    origin: window.location.origin,
                    protocol: window.location.protocol,
                    hostname: window.location.hostname,
                    redirectUri: redirectUri
                });
                
                console.log('Requesting auth URL with redirect_uri:', redirectUri);
                
                const response = await axios.get('/api/avito/integration/auth-url', {
                    params: {
                        redirect_uri: redirectUri,
                    },
                });

                if (!response.data.auth_url) {
                    throw new Error('URL авторизации не получен');
                }

                console.log('Auth URL received:', response.data.auth_url);
                console.log('Redirect URI:', response.data.redirect_uri);
                console.log('Client ID:', response.data.client_id);
                
                // Показываем информацию о redirect_uri для проверки
                const redirectUriInfo = `
                    <div style="text-align: left; margin: 10px 0;">
                        <p><strong>Проверьте настройки приложения Авито:</strong></p>
                        <p style="margin: 5px 0;">Redirect URI должен быть точно таким:</p>
                        <code style="background: #f0f0f0; padding: 8px; border-radius: 4px; display: block; word-break: break-all; margin: 5px 0;">${response.data.redirect_uri}</code>
                        <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
                            Если видите ошибку "Что-то пошло не так", скорее всего Redirect URI в настройках Авито не совпадает с указанным выше.
                        </p>
                    </div>
                `;
                
                const confirm = await Swal.fire({
                    icon: 'info',
                    title: 'Перед авторизацией',
                    html: redirectUriInfo,
                    confirmButtonText: 'Продолжить',
                    showCancelButton: true,
                    cancelButtonText: 'Отмена',
                });
                
                if (!confirm.isConfirmed) {
                    return;
                }
                
                // Открываем окно авторизации
                const authWindow = window.open(
                    response.data.auth_url,
                    'Avito Auth',
                    'width=600,height=700,scrollbars=yes,resizable=yes,left=' + (screen.width/2 - 300) + ',top=' + (screen.height/2 - 350)
                );

                if (!authWindow) {
                    // Если всплывающее окно заблокировано, используем редирект
                    const useRedirect = await Swal.fire({
                        icon: 'warning',
                        title: 'Всплывающее окно заблокировано',
                        text: 'Использовать редирект для авторизации?',
                        showCancelButton: true,
                        confirmButtonText: 'Да, использовать редирект',
                        cancelButtonText: 'Отмена',
                    });

                    if (useRedirect.isConfirmed) {
                        window.location.href = response.data.auth_url;
                        return;
                    }
                    return;
                }

                // Слушаем сообщения от окна авторизации
                const messageListener = async (event) => {
                    console.log('📨 Message received:', {
                        origin: event.origin,
                        data: event.data,
                        dataType: event.data?.type,
                        hasCode: !!event.data?.code,
                        fullData: event.data
                    });
                    
                    const currentOrigin = window.location.origin;
                    const currentHostname = window.location.hostname;
                    
                    // Принимаем сообщения от нашего домена
                    const isOurDomain = event.origin === currentOrigin || 
                                       event.origin.includes(currentHostname);
                    
                    // ВАЖНО: Принимаем сообщения от Авито (www.avito.ru, avito.ru)
                    // Авито может отправлять сообщения напрямую
                    const isAvitoDomain = event.origin.includes('avito.ru') || 
                                        event.origin === 'https://www.avito.ru' ||
                                        event.origin === 'https://avito.ru';
                    
                    // ПРИНИМАЕМ сообщения от Авито ИЛИ от нашего домена
                    const shouldAccept = isOurDomain || isAvitoDomain;
                    
                    console.log('🔍 Origin check:', {
                        eventOrigin: event.origin,
                        currentOrigin: currentOrigin,
                        currentHostname: currentHostname,
                        isOurDomain: isOurDomain,
                        isAvitoDomain: isAvitoDomain,
                        shouldAccept: shouldAccept
                    });
                    
                    // ВАЖНО: Если сообщение от Авито, принимаем его независимо от других проверок
                    if (isAvitoDomain) {
                        console.log('✅ AVITO DOMAIN DETECTED - Accepting message from:', event.origin);
                    } else if (!shouldAccept) {
                        console.warn('❌ Origin rejected:', event.origin, 'not our domain and not Avito');
                        return;
                    } else {
                        console.log('✅ Origin ACCEPTED (our domain):', event.origin);
                    }
                    
                    // Проверяем данные сообщения
                    if (!event.data) {
                        console.log('⚠️ Message has no data, ignoring');
                        return;
                    }
                    
                    console.log('📋 Processing message data:', {
                        type: event.data.type,
                        hasCode: !!event.data.code,
                        hasError: !!event.data.error,
                        keys: Object.keys(event.data)
                    });
                    
                    // Игнорируем служебные сообщения от Авито (COUNTER_SCRIPT_READY и т.д.)
                    // Эти сообщения не содержат код авторизации
                    if (event.data.type && 
                        event.data.type !== 'avito-auth-success' && 
                        event.data.type !== 'avito-auth-error' &&
                        !event.data.code) {
                        console.log('⚠️ Ignoring service message from Avito:', event.data.type);
                        return;
                    }
                    
                    // Важно: код авторизации должен приходить от callback страницы (нашего домена)
                    // Если сообщение от Авито, но нет кода - это служебное сообщение
                    if (isAvitoDomain && !event.data.code && event.data.type !== 'avito-auth-error') {
                        console.log('⚠️ Service message from Avito (no code), ignoring');
                        return;
                    }
                    
                    // Обрабатываем успешную авторизацию
                    if (event.data.type === 'avito-auth-success' && event.data.code) {
                        console.log('✅ Auth success, code received:', event.data.code.substring(0, 10) + '...');
                        window.removeEventListener('message', messageListener);
                        clearInterval(checkClosed);
                        
                        if (authWindow && !authWindow.closed) {
                            authWindow.close();
                        }
                        
                        // Сохраняем токены
                        await this.saveTokens(event.data.code);
                    } else if (event.data.type === 'avito-auth-error') {
                        console.error('Auth error:', event.data.error);
                        window.removeEventListener('message', messageListener);
                        clearInterval(checkClosed);
                        
                        if (authWindow && !authWindow.closed) {
                            authWindow.close();
                        }
                        
                        await Swal.fire({
                            icon: 'error',
                            title: 'Ошибка авторизации',
                            text: event.data.errorDescription || event.data.error || 'Неизвестная ошибка',
                        });
                    }
                };

                window.addEventListener('message', messageListener);

                // Проверяем, не закрыл ли пользователь окно
                let checkCount = 0;
                const checkClosed = setInterval(() => {
                    checkCount++;
                    if (authWindow.closed) {
                        console.log('⚠️ Auth window closed by user (check #' + checkCount + ')');
                        clearInterval(checkClosed);
                        window.removeEventListener('message', messageListener);
                        
                        // Проверяем, есть ли код в localStorage (fallback)
                        const savedCode = localStorage.getItem('avito_auth_code');
                        if (savedCode) {
                            console.log('✅ Found code in localStorage, using fallback');
                            localStorage.removeItem('avito_auth_code');
                            localStorage.removeItem('avito_auth_redirect');
                            // Сохраняем токены асинхронно
                            this.saveTokens(savedCode).catch(err => {
                                console.error('Error saving tokens from localStorage:', err);
                            });
                        } else {
                            console.warn('⚠️ No code found in localStorage. Callback page may not have loaded.');
                        }
                    } else {
                        // Проверяем URL окна авторизации (если доступно)
                        try {
                            const windowUrl = authWindow.location.href;
                            if (checkCount % 5 === 0) { // Логируем каждые 5 секунд
                                console.log('🔍 Auth window URL (check #' + checkCount + '):', windowUrl);
                            }
                            
                            // Если видим callback URL, значит редирект произошел
                            if (windowUrl.includes('/admin/avito/callback')) {
                                console.log('✅ Callback URL detected in auth window!');
                                console.log('⏳ Waiting for callback page to send message...');
                            }
                        } catch (e) {
                            // Не можем получить URL из-за CORS - это нормально
                            if (checkCount % 10 === 0) {
                                console.log('🔍 Cannot access auth window URL (CORS) - check #' + checkCount);
                            }
                        }
                    }
                }, 1000);

                // Таймаут на случай, если окно не вернет ответ
                setTimeout(() => {
                    if (!authWindow.closed) {
                        console.warn('⚠️ Auth timeout - window still open after 5 minutes');
                        console.warn('💡 Возможно, callback страница не загрузилась или не отправила сообщение');
                        console.warn('💡 Проверьте, что callback URL правильный и страница доступна');
                    }
                }, 300000); // 5 минут
            } catch (error) {
                console.error('Authorization error:', error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.error || error.message || 'Не удалось получить URL авторизации',
                });
            }
        },
        async saveTokens(code) {
            this.loading = true;
            try {
                const response = await axios.post('/api/avito/integration/tokens', {
                    code: code,
                    redirect_uri: window.location.origin + '/admin/avito/callback',
                });
                
                await this.loadIntegration();
                
                await Swal.fire({
                    icon: 'success',
                    title: 'Успешно',
                    text: 'Авторизация выполнена',
                    timer: 2000,
                    showConfirmButton: false,
                });
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.error || 'Не удалось сохранить токены',
                });
            } finally {
                this.loading = false;
            }
        },
        async testConnection() {
            this.loading = true;
            this.testResult = null;
            
            try {
                const response = await axios.post('/api/avito/integration/test');
                this.testResult = response.data;
            } catch (error) {
                this.testResult = {
                    success: false,
                    error: error.response?.data?.error || 'Ошибка при тестировании подключения',
                };
            } finally {
                this.loading = false;
            }
        },
        async checkAuthCallback() {
            // Проверяем, есть ли код авторизации в localStorage (для случая прямого редиректа)
            const code = localStorage.getItem('avito_auth_code');
            if (code) {
                console.log('✅ Found saved auth code in localStorage');
                localStorage.removeItem('avito_auth_code');
                localStorage.removeItem('avito_auth_redirect');
                
                await Swal.fire({
                    icon: 'info',
                    title: 'Код авторизации получен',
                    text: 'Сохраняем токены...',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                });
                
                await this.saveTokens(code);
            }
        },
    },
};
</script>

