<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

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

    public function company()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class, 'cliente_id');
    }
}
