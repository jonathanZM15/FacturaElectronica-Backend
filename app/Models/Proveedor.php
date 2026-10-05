<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'emisor_id',
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'email',
        'telefono',
        'created_by',
        'updated_by',
    ];

    public function emisor()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }
}
