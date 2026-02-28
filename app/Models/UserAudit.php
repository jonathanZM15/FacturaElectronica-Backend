<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAudit extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'user_audit';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'target_user_id',
        'action',
        'description',
        'changes',
        'actor_user_id',
        'actor_role',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function registrar(
        int $targetUserId,
        string $action,
        string $description,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?array $changes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'target_user_id' => $targetUserId,
            'action' => $action,
            'description' => $description,
            'changes' => $changes,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
