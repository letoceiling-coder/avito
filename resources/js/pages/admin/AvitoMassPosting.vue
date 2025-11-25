<template>
    <div class="avito-mass-posting-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Массовый постинг объявлений</h1>
            <p class="text-muted-foreground mt-1">Размещение объявлений по разным городам</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Форма создания объявления -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Шаблон объявления</h2>

                <form @submit.prevent="createMassAds" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Название *</label>
                        <input
                            v-model="template.title"
                            type="text"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="Название услуги"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Описание *</label>
                        <textarea
                            v-model="template.description"
                            rows="4"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="Подробное описание услуги"
                            required
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Категория (ID) *</label>
                        <input
                            v-model.number="template.category_id"
                            type="number"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="ID категории из Авито"
                            required
                        />
                        <p class="text-xs text-muted-foreground mt-1">
                            Для сферы услуг обычно: 9 (Услуги)
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Цена (руб.) *</label>
                        <input
                            v-model.number="template.price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="0.00"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Телефон *</label>
                        <input
                            v-model="template.contact_phone"
                            type="tel"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="+7 (999) 123-45-67"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Изображения (URL, по одному на строку)</label>
                        <textarea
                            v-model="imagesText"
                            rows="3"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Тип услуги</label>
                        <select
                            v-model="template.service_type"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        >
                            <option value="service">Услуга</option>
                            <option value="master">Мастер</option>
                        </select>
                    </div>

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
                        :disabled="loading || selectedCities.length === 0"
                        class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ loading ? 'Создание объявлений...' : `Создать объявления (${selectedCities.length})` }}
                    </button>
                </form>
            </div>

            <!-- Результаты -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Результаты</h2>

                <div v-if="!results.length && !loading" class="text-center py-12">
                    <p class="text-muted-foreground">Результаты появятся здесь после создания объявлений</p>
                </div>

                <div v-else-if="loading" class="text-center py-12">
                    <p class="text-muted-foreground">Создание объявлений...</p>
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
                                        {{ result.success ? 'Успешно создано' : result.error }}
                                    </p>
                                    <p v-if="result.data && result.data.id" class="text-xs text-muted-foreground mt-1">
                                        ID объявления: {{ result.data.id }}
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
            template: {
                title: '',
                description: '',
                category_id: 9, // Услуги по умолчанию
                price: 0,
                contact_phone: '',
                images: [],
                service_type: 'service',
            },
            imagesText: '',
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
    methods: {
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
                    this.foundCities = response.data.data.locations || [];
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
        async createMassAds() {
            if (this.selectedCities.length === 0) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Внимание',
                    text: 'Выберите хотя бы один город',
                });
                return;
            }

            this.loading = true;
            this.results = [];

            // Парсим изображения
            const images = this.imagesText
                .split('\n')
                .map(url => url.trim())
                .filter(url => url && url.startsWith('http'));

            const data = {
                template: {
                    ...this.template,
                    images: images,
                },
                cities: this.selectedCities.map(c => c.id),
            };

            try {
                const response = await axios.post('/api/avito/ads/mass-create', data);

                if (response.data.success) {
                    this.results = response.data.results;
                    
                    await Swal.fire({
                        icon: 'success',
                        title: 'Готово',
                        text: `Создано ${response.data.success_count} из ${response.data.total} объявлений`,
                    });
                } else {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Ошибка',
                        text: response.data.error || 'Не удалось создать объявления',
                    });
                }
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.message || 'Ошибка при создании объявлений',
                });
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

