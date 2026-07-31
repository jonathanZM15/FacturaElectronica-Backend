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
            'bodega_id' => 'required|exists:bodegas,id',
            'tipo' => 'required|string|in:AJUSTE_POSITIVO,AJUSTE_NEGATIVO',
            'observacion' => 'required|string|max:1000', // Justificación obligatoria para ajustes
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.000001',
        ];
    }
}
