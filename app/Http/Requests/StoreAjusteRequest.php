<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAjusteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'bodega_id' => 'required|integer|exists:bodegas,id',
            'tipo' => 'required|string|in:AJUSTE_POSITIVO,AJUSTE_NEGATIVO',
            'observacion' => 'required|string|max:1000',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.000001',
            
            // Lotes
            'detalles.*.lotes' => 'nullable|array',
            'detalles.*.lotes.*.numero_lote' => 'required_with:detalles.*.lotes|string|max:100',
            'detalles.*.lotes.*.cantidad' => 'required_with:detalles.*.lotes|numeric|min:0.000001',
            'detalles.*.lotes.*.fecha_fabricacion' => 'nullable|date',
            'detalles.*.lotes.*.fecha_vencimiento' => 'nullable|date',
            
            // Series
            'detalles.*.series' => 'nullable|array',
            'detalles.*.series.*.numero_serie' => 'required_with:detalles.*.series|string|max:100',
        ];
    }
}
