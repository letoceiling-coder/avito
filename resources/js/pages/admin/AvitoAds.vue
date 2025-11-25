<template>
    <div class="avito-ads-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Объявления Авито</h1>
            <p class="text-muted-foreground mt-1">Просмотр текущих объявлений на вашем аккаунте</p>
        </div>

        <!-- Фильтры -->
        <div class="bg-card rounded-lg border border-border p-4 mb-6">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Статус</label>
                    <select
                        v-model="filters.status"
                        @change="loadAds"
                        class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                    >
                        <option value="">Все</option>
                        <option value="active">Активные</option>
                        <option value="inactive">Неактивные</option>
                        <option value="removed">Удаленные</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Категория</label>
                    <input
                        v-model="filters.category_id"
                        type="number"
                        placeholder="ID категории"
                        class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                    />
                </div>
                <button
                    @click="loadAds"
                    :disabled="loading"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                >
                    {{ loading ? 'Загрузка...' : 'Обновить' }}
                </button>
            </div>
        </div>

        <!-- Список объявлений -->
        <div v-if="loading && !ads.length" class="text-center py-12">
            <p class="text-muted-foreground">Загрузка объявлений...</p>
        </div>

        <div v-else-if="ads.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Объявления не найдены</p>
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="ad in ads"
                :key="ad.id"
                class="bg-card rounded-lg border border-border p-6 hover:shadow-md transition-shadow"
            >
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold mb-2">{{ ad.title }}</h3>
                        <p class="text-sm text-muted-foreground mb-3 line-clamp-2">{{ ad.description }}</p>
                        
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div>
                                <span class="text-muted-foreground">ID:</span>
                                <span class="ml-1 font-medium">{{ ad.id }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Статус:</span>
                                <span
                                    :class="[
                                        'ml-1 px-2 py-1 rounded-full text-xs font-medium',
                                        getStatusClass(ad.status)
                                    ]"
                                >
                                    {{ getStatusText(ad.status) }}
                                </span>
                            </div>
                            <div v-if="ad.price">
                                <span class="text-muted-foreground">Цена:</span>
                                <span class="ml-1 font-medium">{{ formatPrice(ad.price) }} ₽</span>
                            </div>
                            <div v-if="ad.location">
                                <span class="text-muted-foreground">Город:</span>
                                <span class="ml-1 font-medium">{{ ad.location.city || ad.location.city_id }}</span>
                            </div>
                        </div>

                        <div v-if="ad.images && ad.images.length" class="mt-4 flex gap-2">
                            <img
                                v-for="(image, index) in ad.images.slice(0, 3)"
                                :key="index"
                                :src="image"
                                :alt="ad.title"
                                class="w-20 h-20 object-cover rounded border border-border"
                            />
                            <div
                                v-if="ad.images.length > 3"
                                class="w-20 h-20 flex items-center justify-center bg-muted rounded border border-border text-sm text-muted-foreground"
                            >
                                +{{ ad.images.length - 3 }}
                            </div>
                        </div>
                    </div>

                    <div class="ml-4">
                        <a
                            :href="`https://www.avito.ru/${ad.url || ''}`"
                            target="_blank"
                            class="px-3 py-1 text-sm bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/90"
                        >
                            Открыть на Авито
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Пагинация -->
        <div v-if="pagination && pagination.total > pagination.per_page" class="mt-6 flex justify-center gap-2">
            <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="!pagination.prev_page_url || loading"
                class="px-4 py-2 border border-border rounded-md hover:bg-muted disabled:opacity-50"
            >
                Назад
            </button>
            <span class="px-4 py-2 text-sm text-muted-foreground">
                Страница {{ pagination.current_page }} из {{ pagination.last_page }}
            </span>
            <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="!pagination.next_page_url || loading"
                class="px-4 py-2 border border-border rounded-md hover:bg-muted disabled:opacity-50"
            >
                Вперед
            </button>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'AvitoAds',
    data() {
        return {
            ads: [],
            loading: false,
            filters: {
                status: '',
                category_id: '',
            },
            pagination: null,
        };
    },
    mounted() {
        this.loadAds();
    },
    methods: {
        async loadAds(page = 1) {
            this.loading = true;
            try {
                const params = {
                    page: page,
                    per_page: 20,
                };

                if (this.filters.status) {
                    params.status = this.filters.status;
                }

                if (this.filters.category_id) {
                    params.category_id = this.filters.category_id;
                }

                const response = await axios.get('/api/avito/ads', { params });

                if (response.data.success) {
                    this.ads = response.data.data.items || [];
                    this.pagination = response.data.data.pagination || null;
                } else {
                    this.ads = [];
                    console.error('Ошибка загрузки объявлений:', response.data.error);
                }
            } catch (error) {
                console.error('Ошибка загрузки объявлений:', error);
                this.ads = [];
            } finally {
                this.loading = false;
            }
        },
        changePage(page) {
            if (page >= 1 && (!this.pagination || page <= this.pagination.last_page)) {
                this.loadAds(page);
            }
        },
        getStatusText(status) {
            const statusMap = {
                active: 'Активно',
                inactive: 'Неактивно',
                removed: 'Удалено',
                rejected: 'Отклонено',
                blocked: 'Заблокировано',
            };
            return statusMap[status] || status;
        },
        getStatusClass(status) {
            const classMap = {
                active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                removed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                rejected: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                blocked: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            };
            return classMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        },
        formatPrice(price) {
            // Цена приходит в копейках
            return (price / 100).toLocaleString('ru-RU');
        },
    },
};
</script>

