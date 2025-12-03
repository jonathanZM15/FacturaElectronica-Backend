<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'identifier',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'success',
        'failure_reason',
        'attempted_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para intentos fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope para intentos exitosos
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope para intentos recientes
     */
    public function scopeRecent($query, $minutes = 10)
    {
        return $query->where('attempted_at', '>=', now()->subMinutes($minutes));
    }
}
