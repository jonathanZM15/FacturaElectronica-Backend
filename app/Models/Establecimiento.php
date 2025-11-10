<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    protected $fillable = [
        'company_id', 'codigo', 'estado', 'nombre', 'nombre_comercial', 'direccion', 'correo', 'telefono', 'logo_path', 'actividades_economicas', 'fecha_inicio_actividades', 'fecha_reinicio_actividades', 'fecha_cierre_establecimiento', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'actividades_economicas' => 'string'
    ];

    protected $appends = ['logo_url', 'created_by_name', 'updated_by_name'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Relación con el usuario que creó el registro
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con el usuario que actualizó el registro
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Accessor para obtener el nombre del usuario que creó el registro
     */
    public function getCreatedByNameAttribute(): ?string
    {
        return $this->creator ? $this->creator->name : null;
    }

    /**
     * Accessor para obtener el nombre del usuario que actualizó el registro
     */
    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updater ? $this->updater->name : null;
    }

    /**
     * Accessor para obtener la URL del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        
        // Generar URL completa con el dominio
        $url = Storage::url($this->logo_path);
        
        // Si la URL es relativa, agregarle el APP_URL
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('app.url'), '/') . $url;
        }
        
        return $url;
    }
}
