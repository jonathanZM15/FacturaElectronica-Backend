<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transportista extends Model
{
    use HasFactory;

    protected $table = 'transportistas';

    protected $fillable = [
        'emisor_id',
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'email',
        'telefono',
        'placa_vehiculo',
        'created_by',
        'updated_by',
    ];

    public function emisor()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }
}
