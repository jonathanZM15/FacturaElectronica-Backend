<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'emisor_id',
        'nombre',
        'descripcion',
        'estado',
        'color',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
