<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoEntrega;
use App\Enums\TipoBodegaSalida;

class UpdateProductoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'codigo' => 'sometimes|required|string|max:255',
            'codigo_auxiliar' => 'nullable|string|max:255',
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'foto' => 'nullable|string',
            
            'precio_1' => 'sometimes|required|numeric|min:0',
            'precio_2' => 'nullable|numeric|min:0',
            'precio_3' => 'nullable|numeric|min:0',
            'precio_por_defecto' => 'nullable|integer|in:1,2,3',
            
            'tipo_iva' => 'sometimes|required|string',
            'impuesto_ice' => 'nullable|boolean',
            'porcentaje_ice' => 'nullable|numeric|min:0',
            'irbpnr' => 'nullable|numeric|min:0',
            
            'tipo' => ['sometimes', 'required', new Enum(TipoProducto::class)],
            'tipo_control_inventario' => [
                'sometimes',
                'required',
                new Enum(TipoControlInventario::class),
                function ($attribute, $value, $fail) {
                    $tipo = $this->input('tipo') ?? $this->producto->tipo->value ?? null;
                    if ($tipo === TipoProducto::SERVICIO->value && $value !== TipoControlInventario::SIN_CONTROL->value) {
                        $fail('Si el producto es SERVICIO, el control de inventario debe ser obligatoriamente SIN_CONTROL.');
                    }
                },
            ],
            
            'permite_venta' => 'sometimes|required|boolean',
            'permite_compra' => 'sometimes|required|boolean',
            'uso_interno' => 'sometimes|required|boolean',
            'permite_exhibicion' => 'sometimes|required|boolean',
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
        ];
    }
}
