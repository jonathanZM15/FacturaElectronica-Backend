<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    protected $fillable = [
        'company_id', 'codigo', 'estado', 'nombre', 'nombre_comercial', 'direccion', 'correo', 'telefono', 'logo_path', 'actividades_economicas', 'fecha_inicio_actividades', 'fecha_reinicio_actividades', 'fecha_cierre_establecimiento', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'actividades_economicas' => 'string'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
