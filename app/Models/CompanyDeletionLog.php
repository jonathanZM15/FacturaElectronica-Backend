<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDeletionLog extends Model
{
    protected $table = 'company_deletion_logs';

    protected $fillable = [
        'company_id',
        'action_type',
        'user_id',
        'description',
        'backup_file_path',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la empresa
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con el usuario que ejecutó la acción
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
