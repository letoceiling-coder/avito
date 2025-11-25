<template>
    <div class="avito-callback-page">
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <div v-if="processing" class="space-y-4">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
                    <p class="text-muted-foreground">Обработка авторизации...</p>
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
                        
                        // Отправляем сообщение несколько раз на случай, если первое не дошло
                        setTimeout(() => {
                            if (window.opener && !window.opener.closed) {
                                console.log('📤 Resending message (retry 1)');
                                window.opener.postMessage(message, targetOrigin);
                            } else {
                                console.warn('⚠️ Opener closed during retry 1');
                            }
                        }, 300);
                        
                        setTimeout(() => {
                            if (window.opener && !window.opener.closed) {
                                console.log('📤 Resending message (retry 2)');
                                window.opener.postMessage(message, targetOrigin);
                            } else {
                                console.warn('⚠️ Opener closed during retry 2');
                            }
                        }, 600);
                        
                        setTimeout(() => {
                            if (window.opener && !window.opener.closed) {
                                console.log('📤 Resending message (retry 3)');
                                window.opener.postMessage(message, targetOrigin);
                            } else {
                                console.warn('⚠️ Opener closed during retry 3');
                            }
                        }, 1000);
                        
                        // Показываем успех перед закрытием
                        this.processing = false;
                        
                        setTimeout(() => {
                            this.closeWindow();
                        }, 1500);
                    } catch (e) {
                        console.error('Error sending message:', e);
                        this.error = 'Ошибка отправки сообщения: ' + e.message;
                        this.processing = false;
                    }
                } else {
                    console.log('No opener found, using localStorage fallback');
                    // Если нет opener, возможно это прямой редирект
                    // Сохраняем код в localStorage и редиректим обратно
                    localStorage.setItem('avito_auth_code', code);
                    localStorage.setItem('avito_auth_redirect', window.location.href);
                    
                    // Редиректим на страницу интеграции
                    window.location.href = '/admin/avito/integration';
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

