<?php

namespace App\Models;

use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoEntrega;
use App\Enums\TipoBodegaSalida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'emisor_id',
        'codigo',
        'codigo_auxiliar',
        'nombre',
        'descripcion',
        'foto',
        'precio_1',
        'precio_2',
        'precio_3',
        'precio_por_defecto',
        'tipo_iva',
        'impuesto_ice',
        'porcentaje_ice',
        'irbpnr',
        'tipo',
        'tipo_control_inventario',
        'permite_venta',
        'permite_compra',
        'uso_interno',
        'permite_exhibicion',
        'categoria_id',
        'unidad_medida',
        'subsidio_unitario',
        'ubicacion_referencial',
        'seleccionable_venta_suspendida',
        'tipo_entrega',
        'tipo_bodega_salida',
        'requiere_preparacion',
        'seleccion_avanzada_bodega_salida',
        'permite_devolucion',
        'tiempo_garantia_devolucion',
    ];

    protected $casts = [
        'precio_1' => 'decimal:6',
        'precio_2' => 'decimal:6',
        'precio_3' => 'decimal:6',
        'precio_por_defecto' => 'integer',
        'impuesto_ice' => 'boolean',
        'porcentaje_ice' => 'decimal:6',
        'irbpnr' => 'decimal:6',
        'tipo' => TipoProducto::class,
        'tipo_control_inventario' => TipoControlInventario::class,
        'permite_venta' => 'boolean',
        'permite_compra' => 'boolean',
        'uso_interno' => 'boolean',
        'permite_exhibicion' => 'boolean',
        'subsidio_unitario' => 'decimal:6',
        'seleccionable_venta_suspendida' => 'boolean',
        'tipo_entrega' => TipoEntrega::class,
        'tipo_bodega_salida' => TipoBodegaSalida::class,
        'requiere_preparacion' => 'boolean',
        'seleccion_avanzada_bodega_salida' => 'boolean',
        'permite_devolucion' => 'boolean',
        'tiempo_garantia_devolucion' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function bodegas()
    {
        return $this->belongsToMany(Bodega::class, 'producto_bodega_stock')
            ->withPivot([
                'stock_minimo',
                'stock_maximo',
                'base_comparacion',
                'activo',
                'observacion',
                'stock_fisico',
                'stock_disponible',
                'stock_reservado',
            ])
            ->withTimestamps();
    }
}
