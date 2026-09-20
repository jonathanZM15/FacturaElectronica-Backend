<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Producto;

class InventarioInicialRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'bodega_id' => ['required', 'integer', 'exists:bodegas,id'],
            'motivo_id' => ['required', 'integer', 'exists:motivos_movimiento,id'],
            'observacion' => ['nullable', 'string', 'max:255'],
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

    public function messages(): array
    {
        return [
            'motivo_id.required' => 'El motivo del movimiento es obligatorio.',
            'motivo_id.exists' => 'El motivo seleccionado no es válido o no existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validación de Motivo MOV-01 (v6)
            $motivoId = $this->input('motivo_id');
            if ($motivoId) {
                $motivo = \App\Models\MotivoMovimiento::find($motivoId);
                if ($motivo) {
                    $tipoEnum = $motivo->tipo_movimiento instanceof \BackedEnum ? $motivo->tipo_movimiento->value : (string)$motivo->tipo_movimiento;
                    if ($tipoEnum !== \App\Enums\TipoMovimientoInventario::MOV_01_INVENTARIO_INICIAL->value) {
                        $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Inventario Inicial (MOV-01).');
                    }
                    if ($motivo->codigo === 'INI-99' && empty(trim((string)$this->input('observacion')))) {
                        $validator->errors()->add('observacion', 'La observación general es obligatoria cuando el motivo seleccionado es Otro (INI-99).');
                    }
                }
            }

            $detalles = $this->input('detalles', []);
            if (!is_array($detalles)) return;

            foreach ($detalles as $index => $detalle) {
                if (!isset($detalle['producto_id']) || !isset($detalle['cantidad'])) continue;
                $producto = Producto::find($detalle['producto_id']);
                if (!$producto) continue;
                
                $tipoControl = $producto->tipo_control_inventario->value ?? $producto->tipo_control_inventario;

                if ($tipoControl === 'LOTE') {
                    if (empty($detalle['lotes'])) {
                        $validator->errors()->add("detalles.{$index}.lotes", "Requiere especificar lotes.");
                    } else {
                        $suma = array_sum(array_column($detalle['lotes'], 'cantidad'));
                        if (abs($suma - $detalle['cantidad']) > 0.000001) {
                            $validator->errors()->add("detalles.{$index}.cantidad", "Suma de lotes no coincide.");
                        }
                    }
                }
                if ($tipoControl === 'SERIE') {
                    if (empty($detalle['series'])) {
                        $validator->errors()->add("detalles.{$index}.series", "Requiere especificar series.");
                    } else {
                        if (count($detalle['series']) != $detalle['cantidad']) {
                            $validator->errors()->add("detalles.{$index}.cantidad", "Suma de series no coincide.");
                        }
                    }
                }
            }
        });
    }
}
