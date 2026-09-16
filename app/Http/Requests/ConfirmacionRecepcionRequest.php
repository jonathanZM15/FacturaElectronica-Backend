<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Producto;

class ConfirmacionRecepcionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'bodega_incidencia_id' => ['nullable', 'integer', 'exists:bodegas,id'],
            'observacion' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            
            'detalles.*.cantidad_buena' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad_faltante' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad_danada' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad_sobrante' => ['nullable', 'numeric', 'min:0'],

            'detalles.*.lotes' => ['nullable', 'array'],
            'detalles.*.lotes.*.numero_lote' => ['required_with:detalles.*.lotes', 'string', 'max:100'],
            'detalles.*.lotes.*.cantidad_buena' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.lotes.*.cantidad_faltante' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.lotes.*.cantidad_danada' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.lotes.*.cantidad_sobrante' => ['nullable', 'numeric', 'min:0'],

            'detalles.*.series' => ['nullable', 'array'],
            'detalles.*.series.*.numero_serie' => ['required_with:detalles.*.series', 'string', 'max:100'],
            'detalles.*.series.*.estado_recepcion' => ['required_with:detalles.*.series', 'string', 'in:BUENA,FALTANTE,DANADA'],
        ];
    }
}
