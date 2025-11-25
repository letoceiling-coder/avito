<template>
    <div class="avito-mass-posting-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Массовый постинг объявлений</h1>
            <p class="text-muted-foreground mt-1">Размещение объявлений по разным городам</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Выбор объявления и городов -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Выбор объявления и городов</h2>

                <form @submit.prevent="postMassAds" class="space-y-4">
                    <!-- Выбор объявления -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Выберите объявление *</label>
                        <div v-if="loadingAds" class="text-sm text-muted-foreground mb-2">
                            Загрузка объявлений...
                        </div>
                        <select
                            v-model="selectedAdId"
                            @change="onAdSelected"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            required
                            :disabled="loadingAds"
                        >
                            <option value="">-- Выберите объявление --</option>
                            <option v-for="ad in generatedAds" :key="ad.id" :value="ad.id">
                                {{ ad.title }} ({{ ad.category_name || 'Категория' }})
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground mt-1">
                            Выберите объявление из списка сгенерированных объявлений
                        </p>
                    </div>

                    <!-- Предпросмотр выбранного объявления -->
                    <div v-if="selectedAd" class="border border-border rounded-md p-4 bg-muted/30">
                        <h3 class="font-semibold mb-2">Предпросмотр объявления:</h3>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-muted-foreground">Название:</span>
                                <span class="ml-2 font-medium">{{ selectedAd.title }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Категория:</span>
                                <span class="ml-2">{{ selectedAd.category_name || 'Не указана' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Цена:</span>
                                <span class="ml-2">{{ selectedAd.price ? formatPrice(selectedAd.price) + ' ₽' : 'Не указана' }}</span>
                            </div>
                            <div v-if="selectedAd.images && selectedAd.images.length" class="mt-2">
                                <span class="text-muted-foreground">Изображений:</span>
                                <span class="ml-2">{{ selectedAd.images.length }}</span>
                                <div class="flex gap-2 mt-2">
                                    <img
                                        v-for="(img, idx) in selectedAd.images.slice(0, 3)"
                                        :key="idx"
                                        :src="getImageUrl(img)"
                                        :alt="selectedAd.title"
                                        class="w-16 h-16 object-cover rounded border border-border"
                                        @error="handleImageError"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Выбор городов -->
                    <div class="border-t border-border pt-4">
                        <h3 class="text-lg font-semibold mb-3">Города для размещения *</h3>
                        
                        <div class="mb-3">
                            <input
                                v-model="citySearch"
                                @input="searchCities"
                                type="text"
                                class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                                placeholder="Поиск города..."
                            />
                        </div>

                        <div v-if="searchingCities" class="text-sm text-muted-foreground mb-2">
                            Поиск городов...
                        </div>

                        <div v-if="foundCities.length" class="max-h-40 overflow-y-auto border border-border rounded-md mb-3">
                            <button
                                v-for="city in foundCities"
                                :key="city.id"
                                @click="addCity(city)"
                                type="button"
                                class="w-full text-left px-3 py-2 hover:bg-muted border-b border-border last:border-b-0"
                            >
                                {{ city.name }} (ID: {{ city.id }})
                            </button>
                        </div>

                        <div v-if="selectedCities.length" class="space-y-2">
                            <div
                                v-for="city in selectedCities"
                                :key="city.id"
                                class="flex items-center justify-between px-3 py-2 bg-muted rounded-md"
                            >
                                <span>{{ city.name }}</span>
                                <button
                                    @click="removeCity(city.id)"
                                    type="button"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="loading || selectedCities.length === 0 || !selectedAdId"
                        class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ loading ? 'Публикация объявлений...' : `Опубликовать в ${selectedCities.length} ${selectedCities.length === 1 ? 'городе' : 'городах'}` }}
                    </button>
                </form>
            </div>

            <!-- Результаты -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Результаты</h2>

                <div v-if="!results.length && !loading" class="text-center py-12">
                    <p class="text-muted-foreground">Результаты появятся здесь после публикации объявлений</p>
                </div>

                <div v-else-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
                    <p class="text-muted-foreground">Публикация объявлений...</p>
                </div>

                <div v-else class="space-y-4">
                    <div class="flex justify-between items-center p-4 bg-muted rounded-md">
                        <div>
                            <p class="font-medium">Всего: {{ results.length }}</p>
                            <p class="text-sm text-muted-foreground">
                                Успешно: <span class="text-green-600">{{ successCount }}</span> |
                                Ошибок: <span class="text-red-600">{{ failCount }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div
                            v-for="result in results"
                            :key="result.city_id"
                            :class="[
                                'p-3 rounded-md border',
                                result.success
                                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                                    : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                            ]"
                        >
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-medium">
                                        {{ getCityName(result.city_id) }}
                                    </p>
                                    <p
                                        :class="[
                                            'text-sm mt-1',
                                            result.success
                                                ? 'text-green-700 dark:text-green-300'
                                                : 'text-red-700 dark:text-red-300'
                                        ]"
                                    >
                                        {{ result.success ? 'Успешно опубликовано' : result.error }}
                                    </p>
                                    <p v-if="result.data && result.data.id" class="text-xs text-muted-foreground mt-1">
                                        ID объявления на Авито: {{ result.data.id }}
                                    </p>
                                </div>
                                <a
                                    v-if="result.success && result.data && result.data.url"
                                    :href="`https://www.avito.ru${result.data.url}`"
                                    target="_blank"
                                    class="ml-2 px-2 py-1 text-xs bg-secondary text-secondary-foreground rounded hover:bg-secondary/90"
                                >
                                    Открыть
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'AvitoMassPosting',
    data() {
        return {
            generatedAds: [],
            selectedAdId: '',
            selectedAd: null,
            loadingAds: false,
            selectedCities: [],
            citySearch: '',
            foundCities: [],
            searchingCities: false,
            loading: false,
            results: [],
        };
    },
    computed: {
        successCount() {
            return this.results.filter(r => r.success).length;
        },
        failCount() {
            return this.results.filter(r => !r.success).length;
        },
    },
    mounted() {
        this.loadGeneratedAds();
        this.loadLocations();
    },
    methods: {
        async loadGeneratedAds() {
            this.loadingAds = true;
            try {
                const response = await axios.get('/api/avito/generator/ads');
                if (response.data.success) {
                    const data = response.data.data || {};
                    this.generatedAds = data.items || data.data || data || [];
                }
            } catch (error) {
                console.error('Ошибка загрузки объявлений:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: 'Не удалось загрузить список объявлений',
                });
            } finally {
                this.loadingAds = false;
            }
        },
        onAdSelected() {
            if (this.selectedAdId) {
                this.selectedAd = this.generatedAds.find(ad => ad.id == this.selectedAdId);
            } else {
                this.selectedAd = null;
            }
        },
        async loadLocations() {
            try {
                const response = await axios.get('/api/avito/locations');
                if (response.data.success) {
                    const data = response.data.data || {};
                    this.allLocations = data.locations || data.data || [];
                }
            } catch (error) {
                console.error('Ошибка загрузки городов:', error);
            }
        },
        async searchCities() {
            if (!this.citySearch || this.citySearch.length < 2) {
                this.foundCities = [];
                return;
            }

            this.searchingCities = true;
            try {
                const response = await axios.get('/api/avito/locations', {
                    params: { q: this.citySearch },
                });

                if (response.data.success) {
                    const data = response.data.data || {};
                    this.foundCities = data.locations || data.data || [];
                } else {
                    this.foundCities = [];
                }
            } catch (error) {
                console.error('Ошибка поиска городов:', error);
                this.foundCities = [];
            } finally {
                this.searchingCities = false;
            }
        },
        addCity(city) {
            if (!this.selectedCities.find(c => c.id === city.id)) {
                this.selectedCities.push(city);
                this.citySearch = '';
                this.foundCities = [];
            }
        },
        removeCity(cityId) {
            this.selectedCities = this.selectedCities.filter(c => c.id !== cityId);
        },
        getCityName(cityId) {
            const city = this.selectedCities.find(c => c.id === cityId);
            return city ? city.name : `Город ID: ${cityId}`;
        },
        formatPrice(price) {
            return new Intl.NumberFormat('ru-RU').format(price);
        },
        getImageUrl(img) {
            if (img && img.startsWith('http')) {
                return img;
            }
            if (img && img.startsWith('/storage')) {
                return window.location.origin + img;
            }
            if (img && !img.startsWith('/')) {
                return window.location.origin + '/storage/' + img;
            }
            return img || '';
        },
        handleImageError(event) {
            event.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="14" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3EИзображение%3C/text%3E%3C/svg%3E';
        },
        async postMassAds() {
            if (this.selectedCities.length === 0) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Внимание',
                    text: 'Выберите хотя бы один город',
                });
                return;
            }

            if (!this.selectedAdId) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Внимание',
                    text: 'Выберите объявление для публикации',
                });
                return;
            }

            const confirm = await Swal.fire({
                title: 'Подтверждение',
                text: `Вы уверены, что хотите опубликовать объявление в ${this.selectedCities.length} ${this.selectedCities.length === 1 ? 'городе' : 'городах'}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Да, опубликовать',
                cancelButtonText: 'Отмена',
            });

            if (!confirm.isConfirmed) {
                return;
            }

            this.loading = true;
            this.results = [];

            const data = {
                generated_ad_id: this.selectedAdId,
                cities: this.selectedCities.map(c => c.id),
            };

            try {
                const response = await axios.post('/api/avito/ads/mass-create', data);

                if (response.data.success) {
                    this.results = response.data.results;
                    
                    await Swal.fire({
                        icon: 'success',
                        title: 'Готово',
                        text: `Опубликовано ${response.data.success_count} из ${response.data.total} объявлений`,
                    });
                } else {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Ошибка',
                        text: response.data.error || 'Не удалось опубликовать объявления',
                    });
                }
            } catch (error) {
                console.error('Ошибка публикации:', error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.message || 'Ошибка при публикации объявлений',
                });
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
