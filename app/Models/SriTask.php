<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SriTask extends Model
{
    protected $table = 'sri_tareas';
    protected $guarded = [];
    protected $casts = [
        'revision' => 'integer', 'attempts' => 'integer',
        'lease_until' => 'datetime', 'enqueued_at' => 'datetime', 'last_started_at' => 'datetime',
    ];
}
