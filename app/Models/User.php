<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\UserRole;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cedula',
        'nombres',
        'apellidos',
        'username',
        'email',
        'password',
        'role',
        'estado',
        'created_by_id',
        'distribuidor_id',
        'emisor_id',
        'establecimientos_ids',
        'puntos_emision_ids',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'establecimientos_ids' => 'json',
    ];

    // ==================== Relaciones ====================

    /**
     * Usuario que creó este usuario
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Usuarios creados por este usuario
     */
    public function usuariosCreados()
    {
        return $this->hasMany(User::class, 'created_by_id');
    }

    /**
     * Distribuidor del que depende este usuario
     */
    public function distribuidor()
    {
        return $this->belongsTo(User::class, 'distribuidor_id');
    }

    /**
     * Usuarios bajo este distribuidor
     */
    public function usuariosBajoDistribuidor()
    {
        return $this->hasMany(User::class, 'distribuidor_id');
    }

    /**
     * Emisor del que depende este usuario
     */
    public function emisor()
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    /**
     * Usuarios bajo este emisor
     */
    public function usuariosBajoEmisor()
    {
        return $this->hasMany(User::class, 'emisor_id');
    }

    // ==================== Métodos ====================

    /**
     * Verifica si el usuario es admin
     */
    public function esAdministrador(): bool
    {
        return $this->role === UserRole::ADMINISTRADOR;
    }

    /**
     * Verifica si es admin o distribuidor
     */
    public function esAdministrativo(): bool
    {
        return $this->role->esAdministrativo();
    }

    /**
     * Verifica si es panel cliente
     */
    public function esClientPanel(): bool
    {
        return $this->role->esClientPanel();
    }

    /**
     * Obtiene los roles que puede crear este usuario
     */
    public function rolesPuedoCrear(): array
    {
        return $this->role->puedeCrearRoles();
    }

    /**
     * Verifica si está activo
     */
    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }
}

