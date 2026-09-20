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
            'motivo_id' => 'required|integer|exists:motivos_movimiento,id',
            'tipo' => 'required|string|in:AJUSTE_POSITIVO,AJUSTE_NEGATIVO',
            'observacion' => 'nullable|string|max:1000',
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

    public function messages(): array
    {
        return [
            'motivo_id.required' => 'El motivo del movimiento es obligatorio.',
            'motivo_id.exists' => 'El motivo seleccionado no es válido o no existe.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $motivoId = $this->input('motivo_id');
            $tipo = $this->input('tipo');
            if ($motivoId) {
                $motivo = \App\Models\MotivoMovimiento::find($motivoId);
                if ($motivo) {
                    $motivoTipo = $motivo->tipo_movimiento instanceof \BackedEnum ? $motivo->tipo_movimiento->value : (string)$motivo->tipo_movimiento;
                    
                    if (str_contains((string)$tipo, 'POSITIVO')) {
                        if ($motivoTipo !== \App\Enums\TipoMovimientoInventario::MOV_09_AJUSTE_POSITIVO->value) {
                            $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Ajuste Positivo (MOV-09).');
                        }
                    } else {
                        if ($motivoTipo !== \App\Enums\TipoMovimientoInventario::MOV_10_AJUSTE_NEGATIVO->value) {
                            $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Ajuste Negativo (MOV-10).');
                        }
                    }

                    if (in_array($motivo->codigo, ['AJP-99', 'AJN-99']) && empty(trim((string)$this->input('observacion')))) {
                        $validator->errors()->add('observacion', 'La observación general es obligatoria cuando el motivo seleccionado es Otro.');
                    }
                }
            }
        });
    }
}
