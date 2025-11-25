<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'expires_at',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверка, действителен ли токен
     */
    public function isTokenValid(): bool
    {
        if (!$this->access_token || !$this->expires_at) {
            return false;
        }

        return $this->expires_at->isFuture();
    }
}
