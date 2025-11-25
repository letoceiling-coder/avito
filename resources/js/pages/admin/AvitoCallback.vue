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
        this.processCallback();
    },
    methods: {
        async processCallback() {
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            const error = urlParams.get('error');
            const errorDescription = urlParams.get('error_description');

            console.log('Callback received:', { code, error, errorDescription });

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
                // Отправляем код в родительское окно
                if (window.opener && !window.opener.closed) {
                    try {
                        window.opener.postMessage({
                            type: 'avito-auth-success',
                            code: code,
                        }, window.location.origin);
                        
                        console.log('Message sent to opener');
                        
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

