<?php

namespace App\Services;

use App\Enums\{TipoControlInventario, TipoProducto, UserRole};
use App\Models\{Cliente, Company, Comprobante, ComprobanteDetalle, ComprobanteImpuesto, PosTurno, Producto, User};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class InvoiceSaleService
{
    public function __construct(private FacturaCalculatorService $calculator, private MovimientoInventarioService $inventory) {}

    public function validate(array $payload): array
    {
        if (!isset($payload['cliente']) || !is_array($payload['cliente'])) {
            throw ValidationException::withMessages(['cliente' => ['Cliente debe ser un objeto.']]);
        }
        $payload['cliente']['tipo_identificacion'] = ClientIdentification::normalize((string) ($payload['cliente']['tipo_identificacion'] ?? ''));
        $validator = Validator::make($payload, [
            'operacion_id' => ['required', 'uuid'],
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'pos_turno_id' => ['required', 'integer', 'exists:pos_turnos,id'],
            'bodega_id' => ['nullable', 'integer', 'exists:bodegas,id'],
            'cliente.tipo_identificacion' => ['required', Rule::in(array_values(ClientIdentification::CODES))],
            'cliente.identificacion' => ['required', 'string', 'max:13'],
            'cliente.razon_social' => ['required', 'string', 'max:255'],
            'cliente.direccion' => ['required', 'string', 'max:500'],
            'cliente.email' => ['required', 'email', 'max:255'],
            'cliente.telefono' => ['nullable', 'string', 'max:50'],
            'detalles' => ['required', 'array', 'min:1', 'max:500'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.000001', 'max:999999999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.impuesto' => ['nullable', 'array'],
            'detalles.*.impuesto.tipo_impuesto_id' => ['nullable', 'integer'],
            'detalles.*.impuesto.tarifa' => ['nullable', 'numeric'],
            'detalles.*.lotes' => ['nullable', 'array'],
            'detalles.*.lotes.*.numero_lote' => ['required', 'string'],
            'detalles.*.lotes.*.cantidad' => ['required', 'numeric', 'min:0.000001'],
            'detalles.*.series' => ['nullable', 'array'],
            'detalles.*.series.*.numero_serie' => ['required', 'string'],
            'pagos' => ['required', 'array', 'min:1', 'max:10'],
            'pagos.*.forma_pago' => ['required', Rule::in(['01', '15', '16', '17', '18', '19', '20', '21'])],
            'pagos.*.total' => ['required', 'numeric', 'min:0'],
            'pagos.*.efectivo_recibido' => ['nullable', 'numeric', 'min:0'],
            'informacion_adicional' => ['nullable', 'array', 'max:14'],
            'informacion_adicional.*.nombre' => ['required', 'string', 'max:300'],
            'informacion_adicional.*.valor' => ['required', 'string', 'max:300'],
        ]);
        $validator->after(function ($v) use ($payload) {
            $client = $payload['cliente'] ?? [];
            if (!ClientIdentification::valid((string) ($client['tipo_identificacion'] ?? ''), (string) ($client['identificacion'] ?? ''))) {
                $v->errors()->add('cliente.identificacion', 'Identificación inválida para el tipo indicado.');
            }
        });
        return $validator->validate();
    }

    /** Invoice, payment data, MOV03 and stock commit together; no external I/O here. */
    public function confirm(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data) {
            $company = Company::whereKey($data['emisor_id'])->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->estaActivo() && $actor->role === UserRole::CAJERO, 403, 'Solo Cajero activo puede confirmar ventas.');
            BusinessAccess::company($actor, (int) $company->id);
            if (!$company->ruc || !$company->razon_social || !$company->direccion_matriz) $this->invalid('emisor_id', 'Completa los datos fiscales del emisor antes de vender.');
            $branch = BusinessAccess::establishment($actor, (int) $data['establecimiento_id'], $company->id);
            $point = BusinessAccess::point($actor, (int) $data['punto_emision_id'], $company->id);
            abort_unless((int) $point->establecimiento_id === (int) $branch->id && $branch->estado === 'ABIERTO' && $point->estado === 'ACTIVO' && $company->estado === 'ACTIVO', 422, 'Empresa, sucursal y punto deben estar activos y relacionados.');
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = Comprobante::where('emisor_id', $company->id)->where('operacion_id', $data['operacion_id'])->first();
            if ($existing) {
                BusinessAccess::invoice($actor, $existing);
                if (!hash_equals((string) $existing->request_hash, $hash)) $this->invalid('operacion_id', 'La operación ya existe con otros datos.');
                return ['comprobante' => $existing, 'created' => false];
            }
            $turn = PosTurno::whereKey($data['pos_turno_id'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $turn->usuario_id === (int) $actor->id && (int) $turn->punto_emision_id === (int) $point->id && $turn->estado === 'Abierto', 403, 'Debes operar tu propio turno abierto y punto asignado.');
            $details = []; $physical = [];
            foreach ($data['detalles'] as $index => $detail) {
                $product = Producto::where('emisor_id', $company->id)->find($detail['producto_id']);
                if (!$product || !$product->permite_venta || $product->uso_interno) $this->invalid("detalles.$index.producto_id", 'Producto no disponible para venta en esta empresa.');
                $price = $product->{'precio_'.($product->precio_por_defecto ?: 1)} ?? $product->precio_1;
                if (abs((float) $detail['precio_unitario'] - (float) $price) > .000001) $this->invalid("detalles.$index.precio_unitario", 'El precio debe coincidir con el catálogo vigente.');
                $tax = ProductTaxService::resolve($product);
                if (!$tax) $this->invalid("detalles.$index.impuesto", 'Configura un impuesto activo y no ambiguo para el producto.');
                if ($product->impuesto_ice || (float) $product->irbpnr > 0) $this->invalid("detalles.$index.impuesto", 'La venta con ICE o IRBPNR requiere el flujo tributario ampliado, fuera de esta versión.');
                $requested = $detail['impuesto'] ?? [];
                if ((isset($requested['tipo_impuesto_id']) && (int) $requested['tipo_impuesto_id'] !== (int) $tax->id) || (isset($requested['tarifa']) && abs((float) $requested['tarifa'] - (float) $tax->valor_tarifa) > .000001)) $this->invalid("detalles.$index.impuesto", 'El impuesto no coincide con el catálogo del producto.');
                $detail['precio_unitario'] = (float) $price;
                $detail['descripcion'] = $product->nombre;
                $detail['codigo_principal'] = $product->codigo;
                $detail['codigo_auxiliar'] = $product->codigo_auxiliar;
                $detail['impuesto'] = ['tipo_impuesto_id' => $tax->id, 'tipo' => 'IVA', 'codigo' => $tax->codigo_porcentaje, 'codigo_impuesto' => $tax->codigo_impuesto, 'codigo_porcentaje' => $tax->codigo_porcentaje, 'tarifa' => (float) $tax->valor_tarifa];
                $details[] = $detail;
                if ($product->tipo === TipoProducto::FISICO && $product->tipo_control_inventario !== TipoControlInventario::SIN_CONTROL) $physical[] = $detail;
            }
            $calculation = $this->calculator->calcularComprobante($details);
            $total = $calculation['totales']['importe_total'];
            if (abs(round(array_sum(array_column($data['pagos'], 'total')), 2) - $total) > .001) $this->invalid('pagos', 'Los pagos deben sumar el total confirmado de la venta.');
            foreach ($data['pagos'] as $payment) {
                if ($payment['forma_pago'] === '01' && isset($payment['efectivo_recibido']) && (float) $payment['efectivo_recibido'] < (float) $payment['total']) $this->invalid('pagos', 'El efectivo recibido no cubre el pago.');
            }
            $warehouse = null;
            if ($physical) {
                if (empty($data['bodega_id'])) $this->invalid('bodega_id', 'Selecciona la bodega de salida de la venta.');
                $warehouse = BusinessAccess::warehouse($actor, (int) $data['bodega_id'], $company->id);
                if ((int) $warehouse->establecimiento_id !== (int) $branch->id || !$warehouse->permite_venta) $this->invalid('bodega_id', 'La bodega debe permitir ventas y pertenecer a la sucursal del turno.');
                app(BodegaValidationService::class)->validateSale($warehouse);
            }
            $clientData = $data['cliente'];
            $client = Cliente::firstOrNew(['emisor_id' => $company->id, 'tipo_identificacion' => $clientData['tipo_identificacion'], 'identificacion' => $clientData['identificacion']]);
            $client->fill($clientData);
            if (!$client->exists) $client->created_by = $actor->id;
            $client->updated_by = $actor->id;
            $client->save();
            $sequence = $point->nextSecuencialFactura();
            $subtotals = ['subtotal_iva_0' => 0, 'subtotal_iva' => 0, 'subtotal_no_objeto' => 0, 'subtotal_exento' => 0];
            foreach ($calculation['detalles'] as $detail) {
                $code = $detail['impuesto']['codigo_porcentaje'];
                $field = $code === 6 ? 'subtotal_no_objeto' : ($code === 7 ? 'subtotal_exento' : ($detail['impuesto']['tarifa'] > 0 ? 'subtotal_iva' : 'subtotal_iva_0'));
                $subtotals[$field] += $detail['precio_total_sin_impuesto'];
            }
            $invoice = Comprobante::create(array_merge($subtotals, [
                'emisor_id' => $company->id, 'establecimiento_id' => $branch->id, 'punto_emision_id' => $point->id,
                'pos_turno_id' => $turn->id, 'cliente_id' => $client->id, 'bodega_id' => $warehouse?->id,
                'operacion_id' => $data['operacion_id'], 'request_hash' => $hash, 'created_by_id' => $actor->id,
                'tipo_comprobante' => 'FACTURA', 'secuencial' => $sequence['secuencial'], 'secuencial_formateado' => $sequence['secuencial_formateado'],
                'codigo_establecimiento' => $branch->codigo, 'punto_emision_codigo' => $point->codigo, 'fecha_emision' => now()->toDateString(),
                'subtotal_sin_impuestos' => $calculation['totales']['subtotal_sin_impuestos'], 'total_descuento' => $calculation['totales']['total_descuento'],
                'total_iva' => $calculation['totales']['total_iva'], 'total_impuestos' => $calculation['totales']['total_iva'], 'total' => $total,
                'estado_sri' => 'BORRADOR', 'ambiente' => $company->ambiente, 'tipo_emision' => $company->tipo_emision,
                'cliente_snapshot' => $client->getAttributes(), 'emisor_snapshot' => $company->getAttributes(), 'establecimiento_snapshot' => $branch->getAttributes(),
                'pagos' => $data['pagos'], 'informacion_adicional' => $data['informacion_adicional'] ?? [],
            ]));
            foreach ($calculation['detalles'] as $detail) {
                $line = ComprobanteDetalle::create(['comprobante_id' => $invoice->id, 'producto_id' => $detail['producto_id'], 'codigo_principal' => $detail['codigo_principal'], 'codigo_auxiliar' => $detail['codigo_auxiliar'], 'descripcion' => $detail['descripcion'], 'cantidad' => $detail['cantidad'], 'precio_unitario' => $detail['precio_unitario'], 'descuento' => $detail['descuento'] ?? 0, 'subtotal' => $detail['precio_total_sin_impuesto']]);
                $this->tax($invoice, $line->id, $detail['impuesto'], $detail['precio_total_sin_impuesto'], round($detail['precio_total_sin_impuesto'] * $detail['impuesto']['tarifa'] / 100, 2));
            }
            foreach ($calculation['impuestos'] as $tax) $this->tax($invoice, null, $tax, $tax['base_imponible'], $tax['valor']);
            if ($physical) {
                $movement = $this->inventory->ventaInmediata($warehouse, $physical, 'Venta '.$invoice->codigo_establecimiento.'-'.$invoice->punto_emision_codigo.'-'.$invoice->secuencial_formateado, $actor->id);
                $invoice->update(['registro_operativo_id' => $movement->id]);
            }
            return ['comprobante' => $invoice, 'created' => true];
        }, 3);
    }

    private function tax(Comprobante $invoice, ?int $line, array $tax, float $base, float $value): void
    {
        ComprobanteImpuesto::create(['comprobante_id' => $invoice->id, 'comprobante_detalle_id' => $line, 'tipo_impuesto_id' => $tax['tipo_impuesto_id'], 'codigo_impuesto' => $tax['codigo_impuesto'] ?? 2, 'codigo_porcentaje' => $tax['codigo_porcentaje'] ?? $tax['codigo'], 'tarifa' => $tax['tarifa'], 'base_imponible' => $base, 'valor' => $value]);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
