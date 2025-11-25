<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoGeneratedAd extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category_id',
        'category_name',
        'location_id',
        'location_name',
        'address',
        'price',
        'contact_phone',
        'images',
        'condition',
        'params',
        'is_vip',
        'is_premium',
        'is_auto_renew',
        'status',
        'avito_item_id',
        'generation_topic',
        'generation_prompt',
        'generation_settings',
    ];

    protected $casts = [
        'images' => 'array',
        'params' => 'array',
        'generation_settings' => 'array',
        'price' => 'decimal:2',
        'is_vip' => 'boolean',
        'is_premium' => 'boolean',
        'is_auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
