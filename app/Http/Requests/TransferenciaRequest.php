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
            'motivo_id' => ['nullable', 'integer', 'exists:motivos_movimiento,id'],
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

    public function attributes(): array
    {
        return [
            'bodega_origen_id' => 'bodega de origen',
            'bodega_destino_id' => 'bodega de destino',
            'motivo_id' => 'motivo del movimiento',
            'detalles.*.producto_id' => 'producto',
            'detalles.*.cantidad' => 'cantidad del producto',
            'detalles.*.lotes.*.numero_lote' => 'número de lote',
            'detalles.*.lotes.*.cantidad' => 'cantidad del lote',
            'detalles.*.series.*.numero_serie' => 'número de serie'
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
            $isReacondicionar = str_contains($this->path(), 'reacondicionar');
            $motivoId = $this->input('motivo_id');
            $bodegaOrigenId = $this->input('bodega_origen_id');
            $bodegaDestinoId = $this->input('bodega_destino_id');

            $origen = $bodegaOrigenId ? \App\Models\Bodega::find($bodegaOrigenId) : null;
            $destino = $bodegaDestinoId ? \App\Models\Bodega::find($bodegaDestinoId) : null;

            $origenTipo = $origen ? ($origen->tipo instanceof \BackedEnum ? $origen->tipo->value : (string)$origen->tipo) : null;
            $destinoTipo = $destino ? ($destino->tipo instanceof \BackedEnum ? $destino->tipo->value : (string)$destino->tipo) : null;

            if ($isReacondicionar) {
                if ($origenTipo && $origenTipo !== 'MERMAS') {
                    $validator->errors()->add('bodega_origen_id', 'Para un reacondicionamiento (MOV-12), la bodega origen debe ser de tipo MERMAS.');
                }
                if ($destinoTipo && $destinoTipo === 'MERMAS') {
                    $validator->errors()->add('bodega_destino_id', 'Para un reacondicionamiento (MOV-12), la bodega destino no puede ser de tipo MERMAS.');
                }
                if (!$motivoId) {
                    $validator->errors()->add('motivo_id', 'El motivo del movimiento es obligatorio.');
                } else {
                    $motivo = \App\Models\MotivoMovimiento::find($motivoId);
                    $tipoEnum = $motivo?->tipo_movimiento instanceof \BackedEnum ? $motivo->tipo_movimiento->value : (string)$motivo?->tipo_movimiento;
                    if ($tipoEnum !== \App\Enums\TipoMovimientoInventario::MOV_12_REACONDICIONAMIENTO_MERMAS->value) {
                        $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Reacondicionamiento (MOV-12).');
                    }
                    if ($motivo?->codigo === 'REC-99' && empty(trim((string)$this->input('observacion')))) {
                        $validator->errors()->add('observacion', 'La observación general es obligatoria cuando el motivo seleccionado es Otro (REC-99).');
                    }
                }
            } elseif ($destinoTipo === 'MERMAS') {
                if ($origenTipo === 'MERMAS') {
                    $validator->errors()->add('bodega_origen_id', 'La bodega origen no puede ser de tipo MERMAS para un envío a mermas (MOV-11).');
                }
                if ($origenTipo === 'TRANSITO') {
                    $validator->errors()->add('bodega_origen_id', 'La bodega origen no puede ser de tipo TRÁNSITO.');
                }
                if (!$motivoId) {
                    $validator->errors()->add('motivo_id', 'El motivo del movimiento es obligatorio.');
                } else {
                    $motivo = \App\Models\MotivoMovimiento::find($motivoId);
                    $tipoEnum = $motivo?->tipo_movimiento instanceof \BackedEnum ? $motivo->tipo_movimiento->value : (string)$motivo?->tipo_movimiento;
                    if ($tipoEnum !== \App\Enums\TipoMovimientoInventario::MOV_11_ENVIO_MERMAS->value) {
                        $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Envío a mermas (MOV-11).');
                    }
                    if ($motivo?->codigo === 'MER-99' && empty(trim((string)$this->input('observacion')))) {
                        $validator->errors()->add('observacion', 'La observación general es obligatoria cuando el motivo seleccionado es Otro (MER-99).');
                    }
                }
            } else {
                if ($origenTipo === 'MERMAS') {
                    $validator->errors()->add('bodega_origen_id', 'Para retirar productos de mermas debe usarse Reacondicionamiento (MOV-12).');
                }
                if ($motivoId) {
                    $motivo = \App\Models\MotivoMovimiento::find($motivoId);
                    $tipoEnum = $motivo?->tipo_movimiento instanceof \BackedEnum ? $motivo->tipo_movimiento->value : (string)$motivo?->tipo_movimiento;
                    if ($tipoEnum !== \App\Enums\TipoMovimientoInventario::MOV_06_TRANSFERENCIA_INTERNA->value) {
                        $validator->errors()->add('motivo_id', 'El motivo seleccionado no corresponde a Transferencia Interna (MOV-06).');
                    }
                    if ($motivo?->codigo === 'TRA-99' && empty(trim((string)$this->input('observacion')))) {
                        $validator->errors()->add('observacion', 'La observación general es obligatoria cuando el motivo seleccionado es Otro (TRA-99).');
                    }
                }
            }

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
