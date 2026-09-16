<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Producto;

class TransferenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'bodega_origen_id' => ['required', 'integer', 'exists:bodegas,id'],
            'bodega_destino_id' => ['required', 'integer', 'exists:bodegas,id', 'different:bodega_origen_id'],
            'observacion' => ['required', 'string', 'max:255'],
            
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            
            'detalles.*.lotes' => ['nullable', 'array'],
            'detalles.*.lotes.*.numero_lote' => ['required_with:detalles.*.lotes', 'string', 'max:100'],
            'detalles.*.lotes.*.cantidad' => ['required_with:detalles.*.lotes', 'numeric', 'gt:0'],

            'detalles.*.series' => ['nullable', 'array'],
            'detalles.*.series.*.numero_serie' => ['required_with:detalles.*.series', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bodega_origen_id' => 'bodega de origen',
            'bodega_destino_id' => 'bodega de destino',
            'detalles.*.producto_id' => 'producto',
            'detalles.*.cantidad' => 'cantidad del producto',
            'detalles.*.lotes.*.numero_lote' => 'número de lote',
            'detalles.*.lotes.*.cantidad' => 'cantidad del lote',
            'detalles.*.series.*.numero_serie' => 'número de serie'
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $detalles = $this->input('detalles', []);
            
            if (!is_array($detalles)) {
                return;
            }

            foreach ($detalles as $index => $detalle) {
                if (!isset($detalle['producto_id']) || !isset($detalle['cantidad'])) continue;

                $producto = Producto::find($detalle['producto_id']);
                if (!$producto) continue;

                $tipoControl = $producto->tipo_control_inventario->value ?? $producto->tipo_control_inventario;

                // Validación LOTE
                if ($tipoControl === 'LOTE') {
                    if (empty($detalle['lotes'])) {
                        $validator->errors()->add("detalles.{$index}.lotes", "El producto {$producto->codigo} requiere especificar los lotes.");
                    } else {
                        $sumaLotes = array_sum(array_column($detalle['lotes'], 'cantidad'));
                        if (abs($sumaLotes - $detalle['cantidad']) > 0.000001) {
                            $validator->errors()->add("detalles.{$index}.cantidad", "La suma de lotes ({$sumaLotes}) no coincide con la cantidad total enviada ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                        }
                    }
                }

                // Validación SERIE
                if ($tipoControl === 'SERIE') {
                    if (empty($detalle['series'])) {
                        $validator->errors()->add("detalles.{$index}.series", "El producto {$producto->codigo} requiere especificar las series.");
                    } else {
                        $countSeries = count($detalle['series']);
                        if (abs($countSeries - $detalle['cantidad']) > 0.000001) {
                            $validator->errors()->add("detalles.{$index}.cantidad", "El número de series indicadas ({$countSeries}) no coincide con la cantidad total enviada ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                        }
                    }
                }
            }
        });
    }
}
