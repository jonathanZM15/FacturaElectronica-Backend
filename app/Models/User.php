<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\UserRole;
use App\Models\Company;

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
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        'last_user_agent',
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
        'puntos_emision_ids' => 'json',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected $appends = ['establecimientos', 'puntos_emision'];

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
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    /**
     * Usuarios bajo este emisor
     */
    public function usuariosBajoEmisor()
    {
        return $this->hasMany(User::class, 'emisor_id');
    }

    /**
     * Obtiene los establecimientos del usuario como objetos completos
     */
    public function getEstablecimientosAttribute()
    {
        if (!$this->establecimientos_ids) {
            return [];
        }
        $ids = is_array($this->establecimientos_ids) ? $this->establecimientos_ids : json_decode($this->establecimientos_ids, true) ?? [];
        $establecimientos = Establecimiento::whereIn('id', $ids)->get();
        
        return $establecimientos->map(function ($est) {
            return [
                'id' => $est->id,
                'codigo' => $est->codigo,
                'nombre' => $est->nombre,
                'estado' => $est->estado,
            ];
        });
    }

    /**
     * Obtiene los puntos de emisión del usuario como objetos completos
     * Incluye información del establecimiento al que pertenece cada punto
     */
    public function getPuntosEmisionAttribute()
    {
        if (!$this->puntos_emision_ids) {
            return [];
        }
        $ids = is_array($this->puntos_emision_ids) ? $this->puntos_emision_ids : json_decode($this->puntos_emision_ids, true) ?? [];
        $puntos = PuntoEmision::whereIn('id', $ids)->with('establecimiento')->get();
        
        return $puntos->map(function ($punto) {
            return [
                'id' => $punto->id,
                'codigo' => $punto->codigo,
                'nombre' => $punto->nombre,
                'establecimiento_id' => $punto->establecimiento_id,
                'establecimiento_codigo' => $punto->establecimiento?->codigo,
                'establecimiento_nombre' => $punto->establecimiento?->nombre,
                'estado' => $punto->estado,
            ];
        });
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

    /**
     * Obtiene las transiciones de estado permitidas
     */
    public static function getTransicionesPermitidas(): array
    {
        return [
            'nuevo' => ['activo', 'pendiente_verificacion'],
            'activo' => ['suspendido', 'pendiente_verificacion', 'retirado'],
            'pendiente_verificacion' => ['activo', 'suspendido'],
            'suspendido' => ['activo', 'retirado'],
            'retirado' => ['pendiente_verificacion'],
        ];
    }

    /**
     * Verifica si una transición de estado es válida
     * 
     * @param string $estadoDestino El estado al que se quiere cambiar
     * @return bool
     */
    public function puedeTransicionarA(string $estadoDestino): bool
    {
        // admin@factura.local siempre debe ser activo
        if ($this->email === 'admin@factura.local' && $estadoDestino !== 'activo') {
            return false;
        }

        // Si es el mismo estado, no hay transición
        if ($this->estado === $estadoDestino) {
            return true;
        }

        $transiciones = self::getTransicionesPermitidas();
        
        // Verificar si existe la transición
        if (!isset($transiciones[$this->estado])) {
            return false;
        }

        return in_array($estadoDestino, $transiciones[$this->estado]);
    }

    /**
     * Obtiene el mensaje de error para transición inválida
     * 
     * @param string $estadoDestino
     * @return string
     */
    public function getMensajeTransicionInvalida(string $estadoDestino): string
    {
        $mensajes = [
            'nuevo' => [
                'default' => 'Un usuario nuevo solo puede pasar a estado Activo o Pendiente de Verificación',
            ],
            'activo' => [
                'nuevo' => 'Un usuario activo no puede volver al estado Nuevo',
                'default' => 'Un usuario activo solo puede pasar a: Suspendido, Pendiente de verificación o Retirado',
            ],
            'pendiente_verificacion' => [
                'nuevo' => 'No se puede cambiar a estado Nuevo desde Pendiente de verificación',
                'retirado' => 'No se puede retirar un usuario que está pendiente de verificación. Primero suspéndelo',
                'default' => 'Un usuario pendiente de verificación solo puede pasar a: Activo o Suspendido',
            ],
            'suspendido' => [
                'nuevo' => 'No se puede cambiar a estado Nuevo desde Suspendido',
                'pendiente_verificacion' => 'No se puede cambiar a Pendiente de verificación desde Suspendido. Primero reactívalo',
                'default' => 'Un usuario suspendido solo puede pasar a: Activo o Retirado',
            ],
            'retirado' => [
                'nuevo' => 'No se puede cambiar a estado Nuevo desde Retirado',
                'activo' => 'No se puede activar directamente un usuario retirado. Primero debe pasar a Pendiente de verificación',
                'suspendido' => 'No se puede suspender un usuario retirado',
                'default' => 'Un usuario retirado solo puede pasar a: Pendiente de verificación (para reactivación)',
            ],
        ];

        if (isset($mensajes[$this->estado][$estadoDestino])) {
            return $mensajes[$this->estado][$estadoDestino];
        }

        return $mensajes[$this->estado]['default'] ?? 'Transición de estado no permitida';
    }
}


