<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferenciaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'bodega_origen_id' => 'required|exists:bodegas,id',
            'bodega_destino_id' => 'required|exists:bodegas,id|different:bodega_origen_id',
            'observacion' => 'nullable|string|max:1000',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.000001',
        ];
    }
}
