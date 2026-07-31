<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoEntrega;
use App\Enums\TipoBodegaSalida;

class StoreProductoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'codigo' => 'required|string|max:255',
            'codigo_auxiliar' => 'nullable|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'foto' => 'nullable|string',
            
            'precio_1' => 'required|numeric|min:0',
            'precio_2' => 'nullable|numeric|min:0',
            'precio_3' => 'nullable|numeric|min:0',
            'precio_por_defecto' => 'nullable|integer|in:1,2,3',
            
            'tipo_iva' => 'required|string',
            'impuesto_ice' => 'nullable|boolean',
            'porcentaje_ice' => 'nullable|numeric|min:0',
            'irbpnr' => 'nullable|numeric|min:0',
            
            'tipo' => ['required', new Enum(TipoProducto::class)],
            'tipo_control_inventario' => [
                'required',
                new Enum(TipoControlInventario::class),
                function ($attribute, $value, $fail) {
                    if ($this->input('tipo') === TipoProducto::SERVICIO->value && $value !== TipoControlInventario::SIN_CONTROL->value) {
                        $fail('Si el producto es SERVICIO, el control de inventario debe ser obligatoriamente SIN_CONTROL.');
                    }
                },
            ],
            
            'permite_venta' => 'required|boolean',
            'permite_compra' => 'required|boolean',
            'uso_interno' => 'required|boolean',
            'permite_exhibicion' => 'required|boolean',
            'categoria_id' => 'nullable|exists:categorias,id',
            'unidad_medida' => 'nullable|string|max:255',
            'subsidio_unitario' => 'nullable|numeric|min:0',
            'ubicacion_referencial' => 'nullable|string|max:255',
            
            'seleccionable_venta_suspendida' => 'nullable|boolean',
            'tipo_entrega' => ['nullable', new Enum(TipoEntrega::class)],
            'tipo_bodega_salida' => ['nullable', new Enum(TipoBodegaSalida::class)],
            'requiere_preparacion' => 'nullable|boolean',
            'seleccion_avanzada_bodega_salida' => 'nullable|boolean',
            'permite_devolucion' => 'nullable|boolean',
            'tiempo_garantia_devolucion' => 'nullable|integer|min:0',

            // Stock inicial validation can also be partially handled here
            'stock_inicial' => 'nullable|array',
        ];
    }
}
