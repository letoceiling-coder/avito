<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avito_generated_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Основные поля объявления
            $table->string('title');
            $table->text('description');
            $table->integer('category_id');
            $table->string('category_name')->nullable();
            $table->integer('location_id');
            $table->string('location_name')->nullable();
            $table->string('address')->nullable();
            
            // Цена и контакты
            $table->decimal('price', 10, 2)->nullable();
            $table->string('contact_phone')->nullable();
            
            // Изображения (JSON массив URL)
            $table->json('images')->nullable();
            
            // Дополнительные поля согласно документации Авито
            $table->string('condition')->nullable(); // Состояние товара
            $table->json('params')->nullable(); // Дополнительные параметры (JSON)
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_auto_renew')->default(false);
            
            // Статус объявления
            $table->enum('status', ['draft', 'ready', 'published', 'failed'])->default('draft');
            $table->string('avito_item_id')->nullable(); // ID объявления на Авито после публикации
            
            // Метаданные генерации
            $table->string('generation_topic')->nullable(); // Тема для генерации
            $table->text('generation_prompt')->nullable(); // Промпт, использованный для генерации
            $table->json('generation_settings')->nullable(); // Настройки генерации (количество фото и т.д.)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avito_generated_ads');
    }
};
