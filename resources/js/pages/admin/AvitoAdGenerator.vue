<template>
    <div class="avito-ad-generator-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Генератор объявлений Авито</h1>
            <p class="text-muted-foreground mt-1">Создание уникальных объявлений с помощью AI</p>
        </div>

        <!-- Форма генерации -->
        <div class="bg-card rounded-lg border border-border p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Настройки генерации</h2>
            
            <form @submit.prevent="generateAds" class="space-y-4">
                <!-- Тема объявления -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        Тема объявления <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="form.topic"
                        rows="3"
                        placeholder="Например: Ремонт квартир под ключ в Москве, Услуги сантехника, Дизайн интерьера..."
                        class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        required
                    ></textarea>
                    <p class="text-xs text-muted-foreground mt-1">
                        Опишите тему объявления подробно для лучшего результата
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Количество объявлений -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Количество объявлений <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model.number="form.count"
                            type="number"
                            min="1"
                            max="10"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            required
                        />
                    </div>

                    <!-- Количество фото -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Количество фото
                        </label>
                        <input
                            v-model.number="form.num_images"
                            type="number"
                            min="1"
                            max="10"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        />
                    </div>

                    <!-- Категория -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Категория <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model.number="form.category_id"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            required
                            @change="loadCategories"
                        >
                            <option value="">Загрузка...</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Локация -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Город/Регион <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model.number="form.location_id"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                            required
                            @change="loadLocations"
                        >
                            <option value="">Загрузка...</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                {{ loc.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Цена -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Цена (руб.)</label>
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        />
                    </div>

                    <!-- Телефон -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Контактный телефон</label>
                        <input
                            v-model="form.contact_phone"
                            type="tel"
                            placeholder="+7 (999) 123-45-67"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        />
                    </div>

                    <!-- Адрес -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">Адрес</label>
                        <input
                            v-model="form.address"
                            type="text"
                            placeholder="Например: Москва, ул. Ленина, д. 10"
                            class="w-full px-3 py-2 border border-border rounded-md bg-background text-foreground"
                        />
                    </div>
                </div>

                <!-- Дополнительные настройки -->
                <div class="border-t border-border pt-4">
                    <h3 class="text-sm font-semibold mb-3">Дополнительные настройки</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input
                                v-model="form.is_vip"
                                type="checkbox"
                                class="rounded border-border"
                            />
                            <span class="text-sm">VIP объявление</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input
                                v-model="form.is_premium"
                                type="checkbox"
                                class="rounded border-border"
                            />
                            <span class="text-sm">Премиум</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input
                                v-model="form.is_auto_renew"
                                type="checkbox"
                                class="rounded border-border"
                            />
                            <span class="text-sm">Автопродление</span>
                        </label>
                    </div>
                </div>

                <!-- Кнопка генерации -->
                <div class="flex justify-end pt-4">
                    <button
                        type="submit"
                        :disabled="generating || !formValid"
                        class="px-6 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ generating ? 'Генерация...' : 'Сгенерировать объявления' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Список сгенерированных объявлений -->
        <div v-if="generatedAds.length > 0" class="mb-6">
            <h2 class="text-xl font-semibold mb-4">Сгенерированные объявления</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="ad in generatedAds"
                    :key="ad.id"
                    class="bg-card rounded-lg border border-border p-4 hover:shadow-md transition-shadow cursor-pointer"
                    @click="viewAd(ad)"
                >
                    <h3 class="font-semibold mb-2 line-clamp-2">{{ ad.title }}</h3>
                    <p class="text-sm text-muted-foreground mb-3 line-clamp-3">{{ ad.description }}</p>
                    
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ ad.category_name || 'Категория' }}</span>
                        <span v-if="ad.price">{{ formatPrice(ad.price) }} ₽</span>
                    </div>
                    
                    <div v-if="ad.images && ad.images.length" class="mt-3 flex gap-2">
                        <img
                            v-for="(img, idx) in ad.images.slice(0, 3)"
                            :key="idx"
                            :src="img"
                            :alt="ad.title"
                            class="w-16 h-16 object-cover rounded border border-border"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно просмотра объявления -->
        <div
            v-if="selectedAd"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="selectedAd = null"
        >
            <div class="bg-card rounded-lg border border-border max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-2xl font-bold">{{ selectedAd.title }}</h2>
                        <button
                            @click="selectedAd = null"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Изображения -->
                    <div v-if="selectedAd.images && selectedAd.images.length" class="mb-6">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <img
                                v-for="(img, idx) in selectedAd.images"
                                :key="idx"
                                :src="img"
                                :alt="selectedAd.title"
                                class="w-full h-48 object-cover rounded-lg border border-border"
                            />
                        </div>
                    </div>

                    <!-- Описание -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">Описание</h3>
                        <div class="text-muted-foreground whitespace-pre-wrap">{{ selectedAd.description }}</div>
                    </div>

                    <!-- Детали -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <div class="text-sm text-muted-foreground">Категория</div>
                            <div class="font-medium">{{ selectedAd.category_name || 'Не указана' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">Город</div>
                            <div class="font-medium">{{ selectedAd.location_name || 'Не указан' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">Цена</div>
                            <div class="font-medium">{{ selectedAd.price ? formatPrice(selectedAd.price) + ' ₽' : 'Не указана' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">Статус</div>
                            <div class="font-medium">{{ getStatusText(selectedAd.status) }}</div>
                        </div>
                    </div>

                    <!-- Действия -->
                    <div class="flex gap-2 justify-end">
                        <button
                            @click="selectedAd = null"
                            class="px-4 py-2 border border-border rounded-md hover:bg-muted"
                        >
                            Закрыть
                        </button>
                        <button
                            @click="deleteAd(selectedAd.id)"
                            class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600"
                        >
                            Удалить
                        </button>
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
    name: 'AvitoAdGenerator',
    data() {
        return {
            form: {
                topic: '',
                count: 1,
                num_images: 3,
                category_id: '',
                location_id: '',
                price: null,
                contact_phone: '',
                address: '',
                is_vip: false,
                is_premium: false,
                is_auto_renew: false,
            },
            categories: [],
            locations: [],
            generating: false,
            generatedAds: [],
            selectedAd: null,
        };
    },
    computed: {
        formValid() {
            return this.form.topic && 
                   this.form.category_id && 
                   this.form.location_id && 
                   this.form.count > 0;
        },
    },
    mounted() {
        this.loadCategories();
        this.loadLocations();
        this.loadGeneratedAds();
    },
    methods: {
        async loadCategories() {
            try {
                const response = await axios.get('/api/avito/categories');
                console.log('Categories response:', response.data);
                if (response.data.success) {
                    // Структура ответа может быть разной, проверяем разные варианты
                    const data = response.data.data || {};
                    this.categories = data.categories || data.data || data.result || [];
                    
                    // Если категории в плоском виде, преобразуем
                    if (Array.isArray(data) && !this.categories.length) {
                        this.categories = data;
                    }
                    
                    console.log('Loaded categories:', this.categories);
                } else {
                    console.error('Failed to load categories:', response.data.error);
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                if (error.response) {
                    console.error('Response data:', error.response.data);
                }
            }
        },
        async loadLocations() {
            try {
                const response = await axios.get('/api/avito/locations');
                console.log('Locations response:', response.data);
                if (response.data.success) {
                    // Структура ответа может быть разной, проверяем разные варианты
                    const data = response.data.data || {};
                    this.locations = data.locations || data.data || data.result || [];
                    
                    // Если локации в плоском виде, преобразуем
                    if (Array.isArray(data) && !this.locations.length) {
                        this.locations = data;
                    }
                    
                    console.log('Loaded locations:', this.locations);
                } else {
                    console.error('Failed to load locations:', response.data.error);
                }
            } catch (error) {
                console.error('Error loading locations:', error);
                if (error.response) {
                    console.error('Response data:', error.response.data);
                }
            }
        },
        async loadGeneratedAds() {
            try {
                const response = await axios.get('/api/avito/generator/ads');
                if (response.data.success) {
                    this.generatedAds = response.data.data.data || [];
                }
            } catch (error) {
                console.error('Error loading generated ads:', error);
            }
        },
        async generateAds() {
            if (!this.formValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: 'Заполните все обязательные поля',
                });
                return;
            }

            this.generating = true;

            try {
                const response = await axios.post('/api/avito/generator/generate', this.form);

                if (response.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Успешно!',
                        text: `Сгенерировано ${response.data.count} объявлений`,
                    });

                    // Добавляем новые объявления в список
                    if (response.data.ads) {
                        this.generatedAds = [...response.data.ads, ...this.generatedAds];
                    }

                    // Очищаем форму
                    this.form.topic = '';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ошибка',
                        text: response.data.error || 'Не удалось сгенерировать объявления',
                    });
                }
            } catch (error) {
                console.error('Error generating ads:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: error.response?.data?.error || 'Произошла ошибка при генерации',
                });
            } finally {
                this.generating = false;
            }
        },
        viewAd(ad) {
            this.selectedAd = ad;
        },
        async deleteAd(id) {
            const result = await Swal.fire({
                title: 'Удалить объявление?',
                text: 'Это действие нельзя отменить',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Удалить',
                cancelButtonText: 'Отмена',
            });

            if (result.isConfirmed) {
                try {
                    await axios.delete(`/api/avito/generator/ads/${id}`);
                    this.generatedAds = this.generatedAds.filter(ad => ad.id !== id);
                    if (this.selectedAd && this.selectedAd.id === id) {
                        this.selectedAd = null;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Удалено',
                        text: 'Объявление успешно удалено',
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ошибка',
                        text: 'Не удалось удалить объявление',
                    });
                }
            }
        },
        formatPrice(price) {
            return new Intl.NumberFormat('ru-RU').format(price);
        },
        getStatusText(status) {
            const statusMap = {
                draft: 'Черновик',
                ready: 'Готово',
                published: 'Опубликовано',
                failed: 'Ошибка',
            };
            return statusMap[status] || status;
        },
    },
};
</script>

