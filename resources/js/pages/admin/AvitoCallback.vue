<template>
    <div class="avito-callback-page">
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <div v-if="processing" class="space-y-4">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
                    <p class="text-muted-foreground">Обработка авторизации...</p>
                    <p class="text-xs text-muted-foreground italic">Пожалуйста, не закрывайте это окно</p>
                </div>
                <div v-else-if="error" class="space-y-4">
                    <p class="text-red-600 dark:text-red-400">Ошибка авторизации</p>
                    <p class="text-sm text-muted-foreground">{{ error }}</p>
                    <button
                        @click="closeWindow"
                        class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
                    >
                        Закрыть
                    </button>
                </div>
                <div v-else class="space-y-4">
                    <p class="text-green-600 dark:text-green-400">Авторизация успешна!</p>
                    <p class="text-sm text-muted-foreground">Это окно можно закрыть</p>
                    <button
                        @click="closeWindow"
                        class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AvitoCallback',
    data() {
        return {
            processing: true,
            error: null,
        };
    },
    mounted() {
        console.log('🚀 AvitoCallback component mounted');
        console.log('📍 Current URL:', window.location.href);
        console.log('🔗 Has opener:', !!window.opener);
        console.log('🌐 Window origin:', window.location.origin);
        console.log('📋 Query params:', window.location.search);
        console.log('⏰ Timestamp:', new Date().toISOString());
        
        // Проверяем, что компонент действительно загрузился
        if (!this.$el) {
            console.error('❌ Component element not found!');
        } else {
            console.log('✅ Component element found:', this.$el);
        }
        
        this.processCallback();
    },
    methods: {
        async processCallback() {
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            const error = urlParams.get('error');
            const errorDescription = urlParams.get('error_description');
            const state = urlParams.get('state');

            console.log('🔔 Callback page loaded:', { 
                code: code ? 'present (' + code.substring(0, 10) + '...)' : 'missing', 
                error, 
                errorDescription,
                state,
                fullUrl: window.location.href,
                search: window.location.search,
                hasOpener: !!window.opener,
                openerClosed: window.opener ? window.opener.closed : 'N/A'
            });

            if (error) {
                this.error = errorDescription || error;
                this.processing = false;
                
                // Отправляем ошибку в родительское окно, если оно есть
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'avito-auth-error',
                        error: error,
                        errorDescription: errorDescription,
                    }, window.location.origin);
                }
                return;
            }

            if (code) {
                console.log('✅ Authorization code received!');
                console.log('📋 Code details:', {
                    codeLength: code.length,
                    codePreview: code.substring(0, 20) + '...',
                    origin: window.location.origin,
                    href: window.location.href,
                    hasOpener: !!window.opener,
                    openerClosed: window.opener ? window.opener.closed : 'N/A',
                    openerType: window.opener ? typeof window.opener : 'N/A'
                });
                
                // Отправляем код в родительское окно
                if (window.opener) {
                    if (window.opener.closed) {
                        console.warn('⚠️ Opener window is closed! Using localStorage fallback');
                        localStorage.setItem('avito_auth_code', code);
                        localStorage.setItem('avito_auth_redirect', window.location.href);
                        window.location.href = '/admin/avito/integration';
                        return;
                    }
                    
                    try {
                        // Отправляем сообщение с нашим origin, чтобы родительское окно могло его принять
                        const targetOrigin = window.location.origin;
                        const message = {
                            type: 'avito-auth-success',
                            code: code,
                        };
                        
                        console.log('📤 Sending message to opener:', {
                            targetOrigin: targetOrigin,
                            messageType: message.type,
                            hasCode: !!message.code,
                            codeLength: message.code ? message.code.length : 0,
                            openerClosed: window.opener.closed
                        });
                        
                        // Отправляем сообщение немедленно
                        window.opener.postMessage(message, targetOrigin);
                        console.log('✅ Message sent to opener (attempt 1)');
                        
                        // Отправляем сообщение несколько раз с разными интервалами
                        // Это увеличивает шанс, что сообщение будет получено
                        const sendMessage = () => {
                            if (window.opener && !window.opener.closed) {
                                window.opener.postMessage(message, targetOrigin);
                                return true;
                            }
                            return false;
                        };
                        
                        // Отправляем сообщения с интервалами: 100ms, 300ms, 500ms, 1s, 2s, 3s
                        const intervals = [100, 300, 500, 1000, 2000, 3000];
                        let sentCount = 1;
                        
                        intervals.forEach((delay, index) => {
                            setTimeout(() => {
                                if (sendMessage()) {
                                    sentCount++;
                                    console.log(`📤 Message sent (retry ${index + 1}, total: ${sentCount})`);
                                } else {
                                    console.warn(`⚠️ Opener closed during retry ${index + 1}`);
                                }
                            }, delay);
                        });
                        
                        // Показываем успех перед закрытием
                        this.processing = false;
                        
                        // Увеличиваем время перед закрытием, чтобы дать больше времени на отправку
                        setTimeout(() => {
                            if (window.opener && !window.opener.closed) {
                                // Последняя попытка перед закрытием
                                sendMessage();
                                console.log('📤 Final message sent before closing');
                            }
                            this.closeWindow();
                        }, 4000); // Увеличено с 1500ms до 4000ms
                    } catch (e) {
                        console.error('Error sending message:', e);
                        this.error = 'Ошибка отправки сообщения: ' + e.message;
                        this.processing = false;
                    }
                } else {
                    console.log('No opener found, using localStorage fallback');
                    // Если нет opener, возможно это прямой редирект
                    // Сохраняем код в localStorage
                    localStorage.setItem('avito_auth_code', code);
                    localStorage.setItem('avito_auth_redirect', window.location.href);
                    
                    // НЕ редиректим сразу - показываем сообщение пользователю
                    // Редирект произойдет автоматически при следующем визите на страницу интеграции
                    this.processing = false;
                    this.error = null;
                    
                    // Показываем сообщение о том, что код сохранен
                    setTimeout(() => {
                        alert('Код авторизации получен и сохранен. Теперь вы можете закрыть это окно и вернуться на страницу интеграции.');
                    }, 500);
                }
            } else {
                this.error = 'Код авторизации не получен';
                this.processing = false;
            }
        },
        closeWindow() {
            window.close();
        },
    },
};
</script>

